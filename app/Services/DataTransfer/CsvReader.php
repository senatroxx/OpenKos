<?php

namespace App\Services\DataTransfer;

use App\Contracts\DataTransfer\TabularReader;
use Generator;
use Illuminate\Http\UploadedFile;
use RuntimeException;

final class CsvReader implements TabularReader
{
    /**
     * @return Generator<int, array{line: int, values: array<int, string|null>, error?: string}>
     */
    public function rows(UploadedFile $file): Generator
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        try {
            $recordLine = 1;
            $currentLine = 1;
            $fields = [];
            $field = '';
            $fieldStart = true;
            $recordStarted = false;
            $inQuotes = false;
            $afterQuote = false;
            $skipLf = false;

            while (($character = fgetc($handle)) !== false) {
                if ($skipLf) {
                    $skipLf = false;

                    if ($character === "\n") {
                        continue;
                    }
                }

                if ($inQuotes) {
                    if ($character === '"') {
                        $inQuotes = false;
                        $afterQuote = true;

                        continue;
                    }

                    $field .= $character;
                    $currentLine += $character === "\n" ? 1 : 0;

                    continue;
                }

                if ($afterQuote) {
                    if ($character === '"') {
                        $field .= '"';
                        $inQuotes = true;
                        $afterQuote = false;

                        continue;
                    }

                    if ($character === ',') {
                        $fields[] = $field;
                        $field = '';
                        $fieldStart = true;
                        $afterQuote = false;

                        continue;
                    }

                    if ($character === "\r" || $character === "\n") {
                        $fields[] = $field;
                        yield [
                            'line' => $recordLine,
                            'values' => $fields,
                        ];
                        $fields = [];
                        $field = '';
                        $fieldStart = true;
                        $recordStarted = false;
                        $afterQuote = false;
                        $currentLine++;
                        $recordLine = $currentLine;
                        $skipLf = $character === "\r";

                        continue;
                    }

                    yield [
                        'line' => $recordLine,
                        'values' => [],
                        'error' => 'malformed',
                    ];

                    return;
                }

                if ($character === '"') {
                    if (! $fieldStart) {
                        yield [
                            'line' => $recordLine,
                            'values' => [],
                            'error' => 'malformed',
                        ];

                        return;
                    }

                    $inQuotes = true;
                    $fieldStart = false;
                    $recordStarted = true;

                    continue;
                }

                if ($character === ',') {
                    $fields[] = $field;
                    $field = '';
                    $fieldStart = true;
                    $recordStarted = true;

                    continue;
                }

                if ($character === "\r" || $character === "\n") {
                    if ($recordStarted || $fields !== [] || $field !== '') {
                        $fields[] = $field;
                    }

                    yield [
                        'line' => $recordLine,
                        'values' => $fields,
                    ];
                    $fields = [];
                    $field = '';
                    $fieldStart = true;
                    $recordStarted = false;
                    $currentLine++;
                    $recordLine = $currentLine;
                    $skipLf = $character === "\r";

                    continue;
                }

                $field .= $character;
                $fieldStart = false;
                $recordStarted = true;
            }

            if ($inQuotes) {
                yield [
                    'line' => $recordLine,
                    'values' => [],
                    'error' => 'malformed',
                ];

                return;
            }

            if ($afterQuote || $recordStarted || $fields !== [] || $field !== '') {
                $fields[] = $field;

                yield [
                    'line' => $recordLine,
                    'values' => $fields,
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}
