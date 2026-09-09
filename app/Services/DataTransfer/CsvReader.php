<?php

namespace App\Services\DataTransfer;

use App\Contracts\DataTransfer\TabularReader;
use Generator;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class CsvReader implements TabularReader
{
    /**
     * @return Generator<int, array{line: int, values: array<int, string|null>}>
     */
    public function rows(UploadedFile $file): Generator
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        try {
            $line = 0;

            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $line++;

                yield [
                    'line' => $line,
                    'values' => $values,
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}
