<?php

namespace App\Services\DataTransfer;

use App\Actions\Properties\CreateProperty;
use App\Enums\DataTransferDataset;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PropertiesDefinition extends DatasetDefinition
{
    public function __construct(private CreateProperty $createProperty) {}

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::Properties;
    }

    public function importHeaders(): array
    {
        return [
            'slug',
            'name',
            'type',
            'region_country_code',
            'region_name',
            'city_name',
            'address',
            'postal_code',
            'phone',
            'description',
            'is_active',
        ];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $this->importHeaders();
    }

    protected function requiredImportHeaders(): array
    {
        return ['slug', 'name'];
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        $values = $this->normalizeValues($values);
        $valid = $this->validateFields($values, [
            'slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'region_country_code' => ['nullable', 'string', 'size:2'],
            'region_name' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:65535'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{6,14}$/'],
            'description' => ['nullable', 'string', 'max:65535'],
        ], $line, $context);

        if (! $valid) {
            return null;
        }

        $rowErrorCount = count($context->errors);

        $slugConflict = $this->hasImportConflict(
            'slug',
            $values['slug'],
            $line,
            $context,
            __('Another row uses the same property slug.'),
        );

        if (! $slugConflict && Property::withTrashed()->where('slug', $values['slug'])->exists()) {
            $context->error($line, 'slug', __('A property with this slug already exists. Imports create new records only.'));
        }

        $type = null;
        if (($values['type'] ?? null) !== null) {
            $type = PropertyType::query()
                ->where('slug', $values['type'])
                ->where('is_active', true)
                ->first();

            if ($type === null) {
                $context->error($line, 'type', __('The referenced property type could not be resolved.'));
            }
        }

        $region = null;
        $hasRegionValue = ($values['region_country_code'] ?? null) !== null || ($values['region_name'] ?? null) !== null;

        if ($hasRegionValue && (($values['region_country_code'] ?? null) === null || ($values['region_name'] ?? null) === null)) {
            $context->error($line, 'region_name', __('Both region_country_code and region_name are required together.'));
        } elseif ($hasRegionValue) {
            $region = Region::query()
                ->where('country_code', strtoupper((string) $values['region_country_code']))
                ->whereRaw('lower(name) = ?', [mb_strtolower((string) $values['region_name'])])
                ->get();

            if ($region->count() !== 1) {
                $context->error(
                    $line,
                    'region_name',
                    $region->isEmpty()
                        ? __('The referenced region could not be resolved.')
                        : __('The referenced region is ambiguous.'),
                );
                $region = null;
            } else {
                $region = $region->first();
            }
        }

        $city = null;
        if (($values['city_name'] ?? null) !== null) {
            if ($region === null) {
                $context->error($line, 'city_name', __('A city requires a resolvable region.'));
            } else {
                $cities = City::query()
                    ->where('region_id', $region->id)
                    ->whereRaw('lower(name) = ?', [mb_strtolower($values['city_name'])])
                    ->get();

                if ($cities->count() !== 1) {
                    $context->error(
                        $line,
                        'city_name',
                        $cities->isEmpty()
                            ? __('The referenced city could not be resolved.')
                            : __('The referenced city is ambiguous.'),
                    );
                } else {
                    $city = $cities->first();
                }
            }
        }

        $isActive = $this->boolean($values['is_active'] ?? null, $line, 'is_active', $context);

        if ($context->errorsSince($rowErrorCount)) {
            return null;
        }

        $attributes = [
            'slug' => $values['slug'],
            'name' => $values['name'],
            'address' => $values['address'] ?? null,
            'region_id' => $region?->id,
            'city_id' => $city?->id,
            'postal_code' => $values['postal_code'] ?? null,
            'phone' => $values['phone'] ?? null,
            'description' => $values['description'] ?? null,
            'is_active' => $isActive,
        ];

        if ($type !== null) {
            $attributes['type'] = $type->slug;
        }

        return $attributes;
    }

    public function persist(array $row, User $actor): Model
    {
        return $this->createProperty->execute($actor, $row);
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        $includeArchived = (bool) ($filters['include_archived'] ?? false);

        return Property::query()
            ->when($includeArchived, fn (Builder $query) => $query->withTrashed())
            ->when(! $includeArchived, fn (Builder $query) => $query
                ->whereNull('properties.deleted_at')
                ->where('properties.is_active', true))
            ->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $users) => $users->whereKey($actor->id),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->when(
                in_array($status, ['active', 'archived'], true),
                fn (Builder $query) => $query->where('properties.is_active', $status === 'active'),
            ))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('properties.type', $type))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = mb_strtolower($search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('lower(properties.name) like ?', ["%{$search}%"])
                        ->orWhereRaw('lower(properties.slug) like ?', ["%{$search}%"])
                        ->orWhereHas('region', fn (Builder $region) => $region->whereRaw('lower(name) like ?', ["%{$search}%"]))
                        ->orWhereHas('city', fn (Builder $city) => $city->whereRaw('lower(name) like ?', ["%{$search}%"]));
                });
            })
            ->with(['propertyType', 'region', 'city'])
            ->orderBy('properties.name');
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var Property $property */
        $property = $model;

        return [
            $property->slug,
            $property->name,
            $property->type,
            $property->region?->country_code,
            $property->region?->name,
            $property->city?->name,
            $property->address,
            $property->postal_code,
            $property->phone,
            $property->description,
            $property->is_active ? '1' : '0',
        ];
    }
}
