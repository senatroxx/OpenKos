<?php

namespace App\Http\Controllers;

use App\Enums\DataTransferDataset;
use App\Enums\Permission;
use App\Http\Requests\DataTransfer\ImportDataRequest;
use App\Models\User;
use App\Services\DataTransfer\MasterDataTransferService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTransferController extends Controller
{
    public function __construct(private MasterDataTransferService $transfer) {}

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
        $status = $request->string('status')->trim()->toString() ?: null;

        return $this->transfer->export($dataset, $request->user(), [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'status' => $status,
            'type' => $request->string('type')->trim()->toString() ?: null,
            'app_access' => $request->string('app_access')->trim()->toString() ?: null,
            'property_slug' => $request->string('property_slug')->trim()->toString() ?: null,
            'unit_name' => $request->string('unit_name')->trim()->toString() ?: null,
            'currency' => $request->string('currency')->trim()->toString() ?: null,
            'include_archived' => $request->boolean('include_archived')
                || in_array($status, ['archived', 'inactive'], true),
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
