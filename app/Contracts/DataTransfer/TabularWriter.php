<?php

namespace App\Contracts\DataTransfer;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface TabularWriter
{
    /**
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $headers
     */
    public function streamDownload(string $filename, array $headers, iterable $rows): StreamedResponse;
}
