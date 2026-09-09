<?php

namespace App\Contracts\DataTransfer;

use Generator;
use Illuminate\Http\UploadedFile;

interface TabularReader
{
    /**
     * @return Generator<int, array{line: int, values: array<int, string|null>}>
     */
    public function rows(UploadedFile $file): Generator;
}
