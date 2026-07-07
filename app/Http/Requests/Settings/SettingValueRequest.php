<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use OpenKOS\Platform\Facades\OpenKOS;

class SettingValueRequest extends FormRequest
{
    public function rules(): array
    {
        $key = $this->input('key');
        $definitions = OpenKOS::settings()->definitions();

        if (isset($definitions[$key])) {
            return [
                'key' => ['required', 'string'],
                'value' => $definitions[$key]->rules,
            ];
        }

        return [
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
