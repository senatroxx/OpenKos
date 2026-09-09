<?php

namespace App\Services\DataTransfer;

use App\Actions\Tenants\CreateTenant;
use App\Enums\DataTransferDataset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class TenantsDefinition extends DatasetDefinition
{
    public function __construct(private CreateTenant $createTenant) {}

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::Tenants;
    }

    public function importHeaders(): array
    {
        return [
            'name',
            'phone',
            'id_card_number',
            'emergency_contact_name',
            'emergency_contact_phone',
            'notes',
            'is_active',
        ];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $sensitive
            ? $this->importHeaders()
            : [
                'name',
                'phone',
                'emergency_contact_name',
                'emergency_contact_phone',
                'notes',
                'is_active',
            ];
    }

    protected function requiredImportHeaders(): array
    {
        return ['name'];
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        $values = $this->normalizeValues($values);
        $valid = $this->validateFields($values, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'id_card_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ], $line, $context);

        if (! $valid) {
            return null;
        }

        $rowErrorCount = count($context->errors);

        foreach ([
            'id_card_number' => __('Another row uses the same tenant identifier.'),
            'phone' => __('Another row uses the same tenant phone number.'),
        ] as $field => $message) {
            $value = $values[$field] ?? null;

            if ($value === null) {
                continue;
            }

            $key = 'tenant|'.$field.'|'.mb_strtolower($value);
            if (isset($context->state[$key])) {
                $context->error($line, $field, $message);
            } else {
                $context->state[$key] = $line;
            }

            $matches = Tenant::withTrashed()->where($field, $value)->count();
            if ($matches > 0) {
                $context->error(
                    $line,
                    $field,
                    $matches > 1
                        ? __('This explicit field matches multiple existing tenants and is ambiguous.')
                        : __('This explicit field matches an existing tenant. Imports create new records only.'),
                );
            }
        }

        if (($values['id_card_number'] ?? null) === null && ($values['phone'] ?? null) === null) {
            $normalizedName = mb_strtolower((string) $values['name']);
            $nameKey = 'tenant|name|'.$normalizedName;

            if (isset($context->state[$nameKey])) {
                $context->error($line, 'name', __('Another row uses the same name without an explicit tenant identifier.'));
            } else {
                $context->state[$nameKey] = $line;
            }

            $matches = Tenant::withTrashed()
                ->whereRaw('lower(name) = ?', [$normalizedName])
                ->count();

            if ($matches > 0) {
                $context->error(
                    $line,
                    'name',
                    $matches > 1
                        ? __('Multiple existing tenants match this name. Manual resolution is required.')
                        : __('An existing tenant matches this name without an explicit identifier. Manual resolution is required.'),
                );
            }
        }

        $isActive = $this->boolean($values['is_active'] ?? null, $line, 'is_active', $context);

        if ($context->errorsSince($rowErrorCount)) {
            return null;
        }

        return [
            'name' => $values['name'],
            'phone' => $values['phone'] ?? null,
            'id_card_number' => $values['id_card_number'] ?? null,
            'emergency_contact_name' => $values['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $values['emergency_contact_phone'] ?? null,
            'notes' => $values['notes'] ?? null,
            'is_active' => $isActive,
        ];
    }

    public function persist(array $row, User $actor): Model
    {
        return $this->createTenant->execute($row);
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        $status = $this->filterValues($filters, 'status');
        $appAccess = $this->filterValues($filters, 'app_access');
        $includeArchived = (bool) ($filters['include_archived'] ?? false)
            || in_array('archived', $status, true);
        $assignedPropertyIds = ! $actor->isOwner()
            ? $actor->properties()->pluck('properties.id')
            : null;

        return Tenant::query()
            ->when($includeArchived, fn (Builder $query) => $query->withTrashed())
            ->when(! $includeArchived && $status === [], fn (Builder $query) => $query
                ->whereNull('tenants.deleted_at')
                ->where('tenants.is_active', true))
            ->when($assignedPropertyIds !== null, fn (Builder $query) => $query->whereHas(
                'leases',
                fn (Builder $leases) => $leases->whereHas(
                    'unit',
                    fn (Builder $units) => $units->whereIn('property_id', $assignedPropertyIds),
                ),
            ))
            ->when($status !== [], function (Builder $query) use ($status): void {
                if (array_diff($status, ['active', 'inactive', 'archived']) !== []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function (Builder $query) use ($status): void {
                    foreach ($status as $value) {
                        $query->orWhere(fn (Builder $query) => $query->statusFilter($value));
                    }
                });
            })
            ->when($appAccess !== [], function (Builder $query) use ($appAccess): void {
                if (array_diff($appAccess, ['active', 'invited', 'email_only', 'disabled', 'none']) !== []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function (Builder $query) use ($appAccess): void {
                    foreach ($appAccess as $value) {
                        $query->orWhere(fn (Builder $query) => $query->appAccessFilter($value));
                    }
                });
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($filters): void {
                $query->listSearch($search, (bool) ($filters['sensitive'] ?? false));
            })
            ->with('user:id,email')
            ->orderBy('tenants.name');
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var Tenant $tenant */
        $tenant = $model;
        $row = [
            $tenant->name,
            $tenant->phone,
            $tenant->emergency_contact_name,
            $tenant->emergency_contact_phone,
            $tenant->notes,
            $tenant->is_active ? '1' : '0',
        ];

        if ($sensitive) {
            array_splice($row, 2, 0, [$tenant->id_card_number]);
        }

        return $row;
    }
}
