<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBranding;
use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Models\Setting;
use App\Services\Payments\MoneyConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
        private UpdateBranding $updateBranding,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('settings/general', [
            'settings' => Setting::some([
                'site_name',
                'country_code',
                'locale',
                'currency',
                'timezone',
                'lease_id_prefix',
                'invoice_id_prefix',
                'invoice_pdf_enabled',
            ]),
            'timezone_list' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:255'],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Z]+$/'],
            'locale' => ['sometimes', 'required', 'string', 'max:10'],
            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        app(MoneyConverter::class)->normalizeCurrency((string) $value);
                    } catch (\Throwable) {
                        $fail(__('This currency is not supported.'));
                    }
                },
            ],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(timezone_identifiers_list())],
            'lease_id_prefix' => ['sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Z]+$/'],
            'invoice_id_prefix' => ['sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Z]+$/'],
            'invoice_pdf_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->updateSettings->execute($validated, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('General settings updated.')]);

        return back();
    }

    public function updateBranding(UpdateBrandingRequest $request, string $asset): RedirectResponse
    {
        $this->updateBranding->execute($asset, $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':asset updated.', ['asset' => ucfirst($asset)])]);

        return back();
    }

    public function removeBranding(Request $request, string $asset): RedirectResponse
    {
        abort_unless($request->user()?->isOwner(), 403);

        $this->updateBranding->remove($asset, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default :asset restored.', ['asset' => ucfirst($asset)])]);

        return back();
    }
}
