<?php

namespace App\Services\DataTransfer;

use App\Actions\Units\CreateUnit;
use App\Enums\DataTransferDataset;
use App\Enums\UnitStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class UnitsDefinition extends DatasetDefinition
{
    public function __construct(private CreateUnit $createUnit) {}

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::Units;
    }

    public function importHeaders(): array
    {
        return [
            'property_slug',
            'name',
            'slug',
            'floor',
            'description',
            'size_sqm',
            'capacity',
            'status',
            'notes',
        ];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $this->importHeaders();
    }

    protected function requiredImportHeaders(): array
    {
        return ['property_slug', 'name', 'capacity'];
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        $values = $this->normalizeValues($values);
        $valid = $this->validateFields($values, [
            'property_slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'capacity' => ['required', 'integer', 'min:0', 'max:255'],
            'status' => ['nullable', Rule::in(UnitStatus::values())],
            'notes' => ['nullable', 'string', 'max:65535'],
        ], $line, $context);

        if (! $valid) {
            return null;
        }

        $rowErrorCount = count($context->errors);
        $contextPropertySlug = $context->constraints['property_slug'] ?? null;
        $contextPropertyId = $context->constraints['property_id'] ?? null;

        if ($contextPropertySlug !== null && $values['property_slug'] !== $contextPropertySlug) {
            $context->error($line, 'property_slug', __('This import must target the selected property.'));

            return null;
        }

        $property = $contextPropertyId !== null
            ? $this->accessiblePropertyById($actor, (int) $contextPropertyId)
            : $this->accessibleProperty($actor, (string) $values['property_slug']);

        if ($property === null) {
            $context->error($line, 'property_slug', __('The referenced property could not be resolved or is not accessible.'));

            return null;
        }

        $nameKey = 'unit|'.$property->id.'|'.mb_strtolower((string) $values['name']);
        if (isset($context->state[$nameKey]) || Unit::withTrashed()
            ->where('property_id', $property->id)
            ->where('name', $values['name'])
            ->exists()
        ) {
            $context->error($line, 'name', __('A unit with this name already exists in the property.'));
        }
        $context->state[$nameKey] = $line;

        $slug = $values['slug'] ?? null;
        if ($slug !== null) {
            $slugKey = 'unit-slug|'.$property->id.'|'.mb_strtolower($slug);

            if (isset($context->state[$slugKey]) || Unit::withTrashed()
                ->where('property_id', $property->id)
                ->where('slug', $slug)
                ->exists()
            ) {
                $context->error($line, 'slug', __('A unit with this slug already exists in the property.'));
            }

            $context->state[$slugKey] = $line;
        }

        $status = $values['status'] ?? UnitStatus::Available->value;

        if ($context->errorsSince($rowErrorCount)) {
            return null;
        }

        return [
            'property_id' => $property->id,
            'name' => $values['name'],
            'slug' => $slug,
            'floor' => $values['floor'] ?? null,
            'description' => $values['description'] ?? null,
            'size_sqm' => $values['size_sqm'] ?? null,
            'capacity' => (int) $values['capacity'],
            'status' => $status,
            'notes' => $values['notes'] ?? null,
        ];
    }

    public function persist(array $row, User $actor): Model
    {
        $property = $this->accessiblePropertyById($actor, (int) $row['property_id']);

        if ($property === null) {
            throw new ImportCommitException(
                __('The referenced property was deleted, deactivated, or is no longer accessible. No records were imported. Preview the file again and retry.'),
            );
        }

        unset($row['property_id']);

        return $this->createUnit->execute($property, $row);
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        $status = $this->filterValues($filters, 'status');
        $propertySlugs = $this->filterValues($filters, 'property_slug');
        $includeArchived = (bool) ($filters['include_archived'] ?? false)
            || in_array('archived', $status, true);

        return Unit::query()
            ->when($includeArchived, fn (Builder $query) => $query->withTrashed())
            ->when(! $includeArchived, fn (Builder $query) => $query->whereNull('units.deleted_at'))
            ->whereHas('property', function (Builder $property) use ($actor, $propertySlugs): void {
                $property
                    ->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                        'users',
                        fn (Builder $users) => $users->whereKey($actor->id),
                    ))
                    ->when($propertySlugs !== [], fn (Builder $query) => $query->whereIn('slug', $propertySlugs));
            })
            ->when($status !== [], function (Builder $query) use ($status): void {
                if (array_diff($status, [...UnitStatus::values(), 'archived']) !== []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where(function (Builder $query) use ($status): void {
                    foreach ($status as $value) {
                        $query->orWhere(fn (Builder $query) => $query->statusFilter($value));
                    }
                });
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->listSearch($search);
            })
            ->with('property')
            ->orderBy('units.property_id')
            ->orderBy('units.name');
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var Unit $unit */
        $unit = $model;

        return [
            $unit->property?->slug,
            $unit->name,
            $unit->slug,
            $unit->floor,
            $unit->description,
            $unit->size_sqm,
            $unit->capacity,
            $unit->status?->value ?? $unit->status,
            $unit->notes,
        ];
    }
}
