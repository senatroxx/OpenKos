<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class InstallPluginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['zip'])->max((int) ceil((int) config('platform.runtime.max_upload_bytes', 64 * 1024 * 1024) / 1024)),
            ],
        ];
    }
}
