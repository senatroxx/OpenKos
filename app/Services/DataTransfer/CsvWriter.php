<?php

namespace App\Services\DataTransfer;

use App\Contracts\DataTransfer\TabularWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvWriter implements TabularWriter
{
    /**
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $headers
     */
    public function streamDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fputcsv($output, $headers, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, array_map(
                    static fn (mixed $value): string => $value === null ? '' : (string) $value,
                    $row,
                ), ',', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
