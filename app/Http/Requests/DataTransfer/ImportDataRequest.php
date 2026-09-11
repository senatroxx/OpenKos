<?php

namespace App\Http\Requests\DataTransfer;

use App\Enums\DataTransferDataset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

class ImportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dataset' => ['required', new Enum(DataTransferDataset::class)],
            'file' => [
                'required',
                File::types(['csv'])->max('10mb'),
            ],
        ];
    }

    public function dataset(): DataTransferDataset
    {
        return DataTransferDataset::from($this->validated('dataset'));
    }
}
