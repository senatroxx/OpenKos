<?php

namespace App\Services\DataTransfer;

use App\Enums\DataTransferDataset;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

abstract class DatasetDefinition
{
    abstract public function dataset(): DataTransferDataset;

    /**
     * @return array<int, string>
     */
    abstract public function importHeaders(): array;

    /**
     * @return array<int, string>
     */
    abstract public function exportHeaders(bool $sensitive = false): array;

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>|null
     */
    abstract public function validateRow(
        array $values,
        int $line,
        User $actor,
        ImportValidationContext $context,
    ): ?array;

    /**
     * @param  array<string, mixed>  $row
     */
    abstract public function persist(array $row, User $actor): Model;

    /**
     * @param  array<string, mixed>  $filters
     */
    abstract public function exportQuery(User $actor, array $filters): Builder;

    /**
     * @return array<int, mixed>
     */
    abstract public function exportRow(Model $model, bool $sensitive = false): array;

    /**
     * @param  array<string, array<int, mixed>|string>  $rules
     * @param  array<string, mixed>  $values
     */
    protected function validateFields(
        array $values,
        array $rules,
        int $line,
        ImportValidationContext $context,
    ): bool {
        $validator = Validator::make($values, $rules);
        $failed = $validator->fails();

        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $context->error($line, $field, $message);
            }
        }

        return ! $failed;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string|null>
     */
    protected function normalizeValues(array $values): array
    {
        return array_map(static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }, $values);
    }

    protected function boolean(
        ?string $value,
        int $line,
        string $field,
        ImportValidationContext $context,
        bool $default = true,
    ): bool {
        if ($value === null) {
            return $default;
        }

        return match (strtolower($value)) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => $this->invalidBoolean($line, $field, $context, $default),
        };
    }

    protected function invalidBoolean(
        int $line,
        string $field,
        ImportValidationContext $context,
        bool $default,
    ): bool {
        $context->error($line, $field, __('The value must be true, false, 1, or 0.'));

        return $default;
    }

    protected function accessibleProperty(User $actor, string $slug): ?Property
    {
        return Property::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->when(! $actor->isOwner(), fn (Builder $query) => $query->whereHas(
                'users',
                fn (Builder $users) => $users->whereKey($actor->id),
            ))
            ->first();
    }

    protected function hasImportConflict(
        string $field,
        mixed $value,
        int $line,
        ImportValidationContext $context,
        string $message,
    ): bool {
        if ($value === null || $value === '') {
            return false;
        }

        $key = $field.'|'.mb_strtolower((string) $value);

        if (isset($context->state[$key])) {
            $context->error($line, $field, $message);

            return true;
        }

        $context->state[$key] = $line;

        return false;
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<int, string>  $allowed
     * @param  array<int, string>  $headers
     * @param  array<int, array{line: int|null, field: string, message: string}>  $errors
     */
    public function validateHeaders(array $headers, array &$errors): bool
    {
        $required = $this->requiredImportHeaders();
        $allowed = $this->importHeaders();
        $normalized = array_map(static fn (string $header): string => trim($header), $headers);
        $valid = true;

        foreach (array_count_values($normalized) as $header => $count) {
            if ($count > 1) {
                $errors[] = [
                    'line' => 1,
                    'field' => $header,
                    'message' => __('The header appears more than once.'),
                ];
                $valid = false;
            }
        }

        foreach ($normalized as $header) {
            if (! in_array($header, $allowed, true)) {
                $errors[] = [
                    'line' => 1,
                    'field' => $header,
                    'message' => __('This header is not supported for this dataset.'),
                ];
                $valid = false;
            }
        }

        foreach ($required as $header) {
            if (! in_array($header, $normalized, true)) {
                $errors[] = [
                    'line' => 1,
                    'field' => $header,
                    'message' => __('This required header is missing.'),
                ];
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * @return array<int, string>
     */
    protected function requiredImportHeaders(): array
    {
        return $this->importHeaders();
    }
}
