<?php

namespace App\Http\Controllers;

use App\Enums\DataTransferDataset;
use App\Enums\Permission;
use App\Enums\UnitStatus;
use App\Http\Requests\DataTransfer\ImportDataRequest;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Unit;
use App\Models\User;
use App\Services\DataTransfer\MasterDataTransferService;
use App\Services\Settings\InstallationCurrencySettings;
use App\Support\DelimitedValues;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTransferController extends Controller
{
    public function __construct(
        private MasterDataTransferService $transfer,
        private InstallationCurrencySettings $currencies,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $datasets = collect(DataTransferDataset::cases())
            ->filter(fn (DataTransferDataset $dataset): bool => $this->canExport($actor, $dataset)
                || ($dataset->isImportable() && $this->canImport($actor, $dataset)))
            ->map(fn (DataTransferDataset $dataset): array => [
                'value' => $dataset->value,
                'label' => $dataset->label(),
                'importable' => $dataset->isImportable() && $this->canImport($actor, $dataset),
                'exportable' => $this->canExport($actor, $dataset),
            ])
            ->values()
            ->all();

        abort_if($datasets === [], 403);

        return Inertia::render('data-transfer/index', [
            'datasets' => $datasets,
            'maxRows' => MasterDataTransferService::MAX_ROWS,
            'maxFileSizeMb' => MasterDataTransferService::MAX_FILE_BYTES / 1_048_576,
        ]);
    }

    public function importPage(
        Request $request,
        ?Property $property = null,
        ?Unit $unit = null,
    ): Response {
        $dataset = $this->pageDataset($request);
        $this->authorizeDataset($request->user(), $dataset, 'import');
        $this->authorizeContext($dataset, $property, $unit);

        return Inertia::render('data-transfer/import', [
            'dataset' => $dataset->value,
            'datasetLabel' => $dataset->label(),
            'maxRows' => MasterDataTransferService::MAX_ROWS,
            'maxFileSizeMb' => MasterDataTransferService::MAX_FILE_BYTES / 1_048_576,
            'backUrl' => $this->backUrl($dataset, $property, $unit),
        ]);
    }

    public function exportPage(
        Request $request,
        ?Property $property = null,
        ?Unit $unit = null,
    ): Response {
        $dataset = $this->pageDataset($request);
        $this->authorizeDataset($request->user(), $dataset, 'export');
        $this->authorizeContext($dataset, $property, $unit);

        $status = DelimitedValues::normalize($request->query('status'));

        return Inertia::render('data-transfer/export', [
            'dataset' => $dataset->value,
            'datasetLabel' => $dataset->label(),
            'pageUrl' => $request->url(),
            'backUrl' => $this->backUrl($dataset, $property, $unit),
            'context' => $this->transferContext($dataset, $property, $unit),
            'filters' => $this->exportFilters($dataset, $unit),
            'initialQuery' => [
                'search' => $request->string('search')->trim()->toString(),
                'status' => implode(',', $status),
                'type' => implode(',', DelimitedValues::normalize($request->query('type'))),
                'app_access' => implode(',', DelimitedValues::normalize($request->query('app_access'))),
                'currency' => implode(',', DelimitedValues::normalize($request->query('currency'))),
            ],
            'includeArchivedDefault' => $request->boolean('include_archived')
                || in_array('archived', $status, true),
            'canExportSensitive' => $dataset === DataTransferDataset::Tenants
                && ($request->user()->isOwner()
                    || $request->user()->can(Permission::TenantsExportSensitive->value)),
            'searchSupported' => in_array($dataset, [
                DataTransferDataset::Properties,
                DataTransferDataset::Units,
                DataTransferDataset::Tenants,
            ], true),
        ]);
    }

    public function preview(ImportDataRequest $request): JsonResponse
    {
        $dataset = $request->dataset();
        $this->authorizeDataset($request->user(), $dataset, 'import');

        $result = $this->transfer->validate($dataset, $request->file('file'), $request->user());

        return response()->json($result->toArray(), $result->isValid() ? 200 : 422);
    }

    public function commit(ImportDataRequest $request): JsonResponse
    {
        $dataset = $request->dataset();
        $this->authorizeDataset($request->user(), $dataset, 'import');
        $result = $this->transfer->validate($dataset, $request->file('file'), $request->user());

        if (! $result->isValid()) {
            return response()->json($result->toArray(), 422);
        }

        try {
            $count = $this->transfer->commit($dataset, $result, $request->user());
        } catch (QueryException) {
            return response()->json([
                'valid' => false,
                'row_count' => $result->rowCount,
                'error_count' => 1,
                'errors' => [[
                    'line' => null,
                    'field' => 'file',
                    'message' => __('The import conflicted with data created by another request. No records were imported.'),
                ]],
            ], 422);
        }

        return response()->json([
            'committed' => true,
            'count' => $count,
        ]);
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        $dataset = $this->resolveDataset($dataset);
        $sensitive = $request->boolean('include_sensitive');
        $this->authorizeDataset($request->user(), $dataset, 'export', $sensitive);
        $status = DelimitedValues::normalize($request->query('status'));

        return $this->transfer->export($dataset, $request->user(), [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'status' => $status,
            'type' => DelimitedValues::normalize($request->query('type')),
            'app_access' => DelimitedValues::normalize($request->query('app_access')),
            'property_slug' => DelimitedValues::normalize($request->query('property_slug')),
            'unit_name' => DelimitedValues::normalize($request->query('unit_name')),
            'currency' => DelimitedValues::normalize($request->query('currency')),
            'include_archived' => $request->boolean('include_archived')
                || in_array('archived', $status, true),
            'sensitive' => $sensitive,
        ]);
    }

    public function template(Request $request, string $dataset): StreamedResponse
    {
        $dataset = $this->resolveDataset($dataset);
        abort_unless($dataset->isImportable(), 404);
        $this->authorizeDataset($request->user(), $dataset, 'import');

        return $this->transfer->template($dataset);
    }

    private function pageDataset(Request $request): DataTransferDataset
    {
        return $this->resolveDataset((string) $request->route('dataset'));
    }

    private function authorizeContext(
        DataTransferDataset $dataset,
        ?Property $property,
        ?Unit $unit,
    ): void {
        if ($dataset === DataTransferDataset::Units && $property !== null) {
            $this->authorize('viewAny', [Unit::class, $property]);
        }

        if ($dataset === DataTransferDataset::UnitRates && $unit !== null) {
            $this->authorize('view', $unit);
        }
    }

    /**
     * @return array<string, string>
     */
    private function transferContext(
        DataTransferDataset $dataset,
        ?Property $property,
        ?Unit $unit,
    ): array {
        return match ($dataset) {
            DataTransferDataset::Units => $property === null
                ? []
                : ['property_slug' => $property->slug],
            DataTransferDataset::UnitRates => $property === null || $unit === null
                ? []
                : [
                    'property_slug' => $property->slug,
                    'unit_name' => $unit->name,
                ],
            default => [],
        };
    }

    private function backUrl(
        DataTransferDataset $dataset,
        ?Property $property,
        ?Unit $unit,
    ): string {
        return match ($dataset) {
            DataTransferDataset::Properties => route('properties.index'),
            DataTransferDataset::Units => $property === null
                ? route('properties.index')
                : route('properties.units.index', ['property' => $property]),
            DataTransferDataset::Tenants => route('tenants.index'),
            DataTransferDataset::UnitRates => $property === null || $unit === null
                ? route('properties.index')
                : route('properties.units.rates', [
                    'property' => $property,
                    'unit' => $unit,
                ]),
            DataTransferDataset::PropertyTypes => route('settings.property-types.index'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, options: array<int, mixed>}>
     */
    private function exportFilters(DataTransferDataset $dataset, ?Unit $unit): array
    {
        return match ($dataset) {
            DataTransferDataset::Properties => [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['active', 'archived'],
                ],
                [
                    'key' => 'type',
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => PropertyType::query()
                        ->ordered()
                        ->get(['slug', 'label'])
                        ->map(fn (PropertyType $type): array => [
                            'value' => $type->slug,
                            'label' => $type->label,
                        ])
                        ->all(),
                ],
            ],
            DataTransferDataset::Units => [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => array_map(
                        fn (string $value): array => [
                            'value' => $value,
                            'label' => $value === 'archived'
                                ? 'Archived'
                                : UnitStatus::from($value)->label(),
                        ],
                        [...UnitStatus::values(), 'archived'],
                    ),
                ],
            ],
            DataTransferDataset::Tenants => [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['active', 'inactive', 'archived'],
                ],
                [
                    'key' => 'app_access',
                    'label' => 'App Access',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'active', 'label' => 'Has access'],
                        ['value' => 'invited', 'label' => 'Invite pending'],
                        ['value' => 'email_only', 'label' => 'Email only'],
                        ['value' => 'disabled', 'label' => 'Access disabled'],
                        ['value' => 'none', 'label' => 'No access'],
                    ],
                ],
            ],
            DataTransferDataset::UnitRates => [
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['active', 'inactive'],
                ],
                [
                    'key' => 'currency',
                    'label' => 'Currency',
                    'type' => 'select',
                    'options' => $this->rateCurrencies($unit),
                ],
            ],
            DataTransferDataset::PropertyTypes => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function rateCurrencies(?Unit $unit): array
    {
        $currencies = $this->currencies->supported();

        if ($unit !== null) {
            $currencies = [...$currencies, ...$unit->rates()->pluck('currency')->all()];
        }

        return array_values(array_unique(array_map('strtoupper', $currencies)));
    }

    private function resolveDataset(string $value): DataTransferDataset
    {
        $dataset = DataTransferDataset::tryFrom($value);

        abort_if($dataset === null, 404);

        return $dataset;
    }

    private function authorizeDataset(
        User $actor,
        DataTransferDataset $dataset,
        string $action,
        bool $sensitive = false,
    ): void {
        if ($action === 'import') {
            abort_unless($dataset->isImportable(), 404);
            abort_unless($this->canImport($actor, $dataset), 403);

            return;
        }

        abort_unless($this->canExport($actor, $dataset), 403);

        if ($sensitive) {
            abort_unless($actor->isOwner() || $actor->can(Permission::TenantsExportSensitive->value), 403);
        }
    }

    private function canImport(User $actor, DataTransferDataset $dataset): bool
    {
        if (! $dataset->isImportable()) {
            return false;
        }

        if ($dataset === DataTransferDataset::Tenants && ! $actor->isOwner()) {
            return false;
        }

        return $actor->isOwner() || $actor->can($this->permission($dataset, 'import'));
    }

    private function canExport(User $actor, DataTransferDataset $dataset): bool
    {
        return $actor->isOwner() || $actor->can($this->permission($dataset, 'export'));
    }

    private function permission(DataTransferDataset $dataset, string $action): string
    {
        return match ($dataset) {
            DataTransferDataset::Properties, DataTransferDataset::PropertyTypes => $action === 'import'
                ? Permission::PropertiesImport->value
                : Permission::PropertiesExport->value,
            DataTransferDataset::Units => $action === 'import'
                ? Permission::UnitsImport->value
                : Permission::UnitsExport->value,
            DataTransferDataset::Tenants => $action === 'import'
                ? Permission::TenantsImport->value
                : Permission::TenantsExport->value,
            DataTransferDataset::UnitRates => $action === 'import'
                ? Permission::UnitRatesImport->value
                : Permission::UnitRatesExport->value,
        };
    }
}
