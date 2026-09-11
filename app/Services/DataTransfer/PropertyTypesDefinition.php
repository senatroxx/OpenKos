<?php

namespace App\Services\DataTransfer;

use App\Enums\DataTransferDataset;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class PropertyTypesDefinition extends DatasetDefinition
{
    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::PropertyTypes;
    }

    public function importHeaders(): array
    {
        return ['slug', 'label', 'is_active', 'sort_order'];
    }

    public function exportHeaders(bool $sensitive = false): array
    {
        return $this->importHeaders();
    }

    public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array {
        throw new LogicException('Property types are export-only.');
    }

    public function persist(array $row, User $actor): Model
    {
        throw new LogicException('Property types are export-only.');
    }

    public function exportQuery(User $actor, array $filters): Builder
    {
        return PropertyType::query()
            ->when(! ($filters['include_archived'] ?? false), fn (Builder $query) => $query->where('is_active', true))
            ->ordered();
    }

    public function exportRow(Model $model, bool $sensitive = false): array
    {
        /** @var PropertyType $type */
        $type = $model;

        return [$type->slug, $type->label, $type->is_active ? '1' : '0', $type->sort_order];
    }
}
