<?php

namespace App\Http\Requests;

use App\Enums\TenantDocumentType;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'type' => ['required', 'string', 'in:'.implode(',', TenantDocumentType::values())],
        ];
    }
}
