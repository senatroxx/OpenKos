<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.$this->maxSize(),
                'mimes:'.$this->extensions(),
                'mimetypes:'.$this->mimeTypes(),
            ],
        ];
    }

    private function maxSize(): int
    {
        return $this->asset() === 'favicon' ? 512 : 2048;
    }

    private function extensions(): string
    {
        return $this->asset() === 'favicon'
            ? 'png,ico'
            : 'jpg,jpeg,png,webp';
    }

    private function mimeTypes(): string
    {
        return $this->asset() === 'favicon'
            ? 'image/png,image/x-icon,image/vnd.microsoft.icon'
            : 'image/jpeg,image/png,image/webp';
    }

    private function asset(): string
    {
        return (string) $this->route('asset');
    }
}
