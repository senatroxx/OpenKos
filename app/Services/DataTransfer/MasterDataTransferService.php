<?php

namespace App\Services\DataTransfer;

use App\Contracts\DataTransfer\TabularReader;
use App\Contracts\DataTransfer\TabularWriter;
use App\Data\DataTransfer\ImportValidationResult;
use App\Enums\DataTransferDataset;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MasterDataTransferService
{
    public const MAX_ROWS = 10_000;

    public const MAX_FILE_BYTES = 10_485_760;

    /**
     * @var array<string, DatasetDefinition>
     */
    private array $definitions;

    public function __construct(
        private TabularReader $reader,
        private TabularWriter $writer,
        PropertiesDefinition $properties,
        UnitsDefinition $units,
        TenantsDefinition $tenants,
        UnitRatesDefinition $unitRates,
        PropertyTypesDefinition $propertyTypes,
        ExpensesDefinition $expenses,
    ) {
        $this->definitions = [
            $properties->dataset()->value => $properties,
            $units->dataset()->value => $units,
            $tenants->dataset()->value => $tenants,
            $unitRates->dataset()->value => $unitRates,
            $propertyTypes->dataset()->value => $propertyTypes,
            $expenses->dataset()->value => $expenses,
        ];
    }

    public function definition(DataTransferDataset $dataset): DatasetDefinition
    {
        return $this->definitions[$dataset->value];
    }

    public function validate(
        DataTransferDataset $dataset,
        UploadedFile $file,
        User $actor,
        array $constraints = [],
    ): ImportValidationResult {
        $definition = $this->definition($dataset);
        $context = new ImportValidationContext($constraints);
        $rows = [];
        $rowCount = 0;
        $header = null;
        $headersValid = false;
        $limitErrorAdded = false;
        $syntaxError = false;

        foreach ($this->reader->rows($file) as $record) {
            if (isset($record['error'])) {
                $context->error(
                    $record['line'],
                    'file',
                    __('The CSV file contains malformed syntax, such as an unterminated quoted field.'),
                );
                $syntaxError = true;

                continue;
            }

            $values = $record['values'];

            if ($header === null) {
                $header = array_map(static function (mixed $value, int $index): string {
                    $value = trim((string) $value);

                    return $index === 0 ? (string) preg_replace('/^\xEF\xBB\xBF/', '', $value) : $value;
                }, $values, array_keys($values));
                $headersValid = $definition->validateHeaders($header, $context->errors);

                continue;
            }

            if ($this->isBlankRow($values)) {
                continue;
            }

            $rowCount++;

            if ($rowCount > self::MAX_ROWS) {
                if (! $limitErrorAdded) {
                    $context->error(null, 'file', __('The CSV file exceeds the maximum of :count rows.', ['count' => self::MAX_ROWS]));
                    $limitErrorAdded = true;
                }

                continue;
            }

            if (! $headersValid) {
                continue;
            }

            if (count($values) !== count($header)) {
                $context->error($record['line'], 'row', __('The row must contain the same number of columns as the header.'));

                continue;
            }

            /** @var array<string, string|null> $mappedValues */
            $mappedValues = array_combine($header, $values);
            $before = count($context->errors);
            $normalized = $definition->validateRow($mappedValues, $record['line'], $actor, $context);

            if ($normalized !== null && ! $context->errorsSince($before)) {
                $rows[] = $normalized;
            }
        }

        if ($header === null && ! $syntaxError) {
            $context->error(null, 'file', __('The CSV file must contain a header row.'));
        }

        if ($rowCount === 0 && $header !== null && $headersValid) {
            $context->error(null, 'file', __('The CSV file must contain at least one data row.'));
        }

        return new ImportValidationResult($rowCount, $rows, $context->errors);
    }

    public function commit(
        DataTransferDataset $dataset,
        ImportValidationResult $result,
        User $actor,
    ): int {
        if (! $result->isValid()) {
            return 0;
        }

        $definition = $this->definition($dataset);

        try {
            DB::transaction(function () use ($definition, $result, $actor): void {
                $definition->prepareCommit($result->rows, $actor);

                foreach ($result->rows as $row) {
                    $definition->persist($row, $actor);
                }
            });
        } catch (ImportCommitException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new ImportCommitException(
                __('The import conflicted with data changed by another request. No records were imported. Preview the file again and retry.'),
                previous: $exception,
            );
        } catch (ModelNotFoundException|InvalidArgumentException $exception) {
            throw new ImportCommitException(
                __('Referenced data or import configuration changed after validation. No records were imported. Preview the file again and retry.'),
                previous: $exception,
            );
        }

        return count($result->rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(DataTransferDataset $dataset, User $actor, array $filters = []): StreamedResponse
    {
        $definition = $this->definition($dataset);
        $rows = (function () use ($definition, $actor, $filters): \Generator {
            foreach ($definition->exportQuery($actor, $filters)->lazy(500) as $model) {
                yield $definition->exportRow($model, (bool) ($filters['sensitive'] ?? false));
            }
        })();

        return $this->writer->streamDownload(
            $dataset->value.'-v1.csv',
            $definition->exportHeaders((bool) ($filters['sensitive'] ?? false)),
            $rows,
        );
    }

    public function template(DataTransferDataset $dataset): StreamedResponse
    {
        $definition = $this->definition($dataset);

        return $this->writer->streamDownload(
            $dataset->value.'-v1-template.csv',
            $definition->importHeaders(),
            [],
        );
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
