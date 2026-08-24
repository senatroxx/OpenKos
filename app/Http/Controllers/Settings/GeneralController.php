<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateBranding;
use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBrandingRequest;
use App\Models\Setting;
use App\Models\UnitRate;
use App\Services\Payments\MoneyConverter;
use App\Services\Settings\InstallationCurrencySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
        private UpdateBranding $updateBranding,
        private InstallationCurrencySettings $currencies,
    ) {}

    public function edit(): Response
    {
        $settings = Setting::some([
            'site_name',
            'country_code',
            'locale',
            'currency',
            'timezone',
            'lease_id_prefix',
            'invoice_id_prefix',
            'invoice_pdf_enabled',
        ]);
        $settings['supported_currencies'] = $this->currencies->supported();

        return Inertia::render('settings/general', [
            'settings' => $settings,
            'timezone_list' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->normalizeCurrencyInput($request);

        $previousSupported = $this->currencies->supported();
        $hasStoredSupportedCurrencies = $this->currencies->hasStoredSupportedCurrencies();

        $validated = $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:255'],
            'country_code' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Z]+$/'],
            'locale' => ['sometimes', 'required', 'string', 'max:10'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::in(array_keys(app(MoneyConverter::class)->scales()))],
            'supported_currencies' => ['sometimes', 'required', 'array', 'list', 'min:1'],
            'supported_currencies.*' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'distinct:strict', Rule::in(array_keys(app(MoneyConverter::class)->scales()))],
            'timezone' => ['sometimes', 'required', 'string', Rule::in(timezone_identifiers_list())],
            'lease_id_prefix' => ['sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Z]+$/'],
            'invoice_id_prefix' => ['sometimes', 'required', 'string', 'max:10', 'regex:/^[A-Z]+$/'],
            'invoice_pdf_enabled' => ['sometimes', 'boolean'],
        ]);

        $nextDefault = $validated['currency'] ?? $this->currencies->default();
        if (array_key_exists('supported_currencies', $validated)) {
            try {
                $validated['supported_currencies'] = $this->currencies->normalize(
                    $validated['supported_currencies'],
                );
                $this->currencies->normalize($validated['supported_currencies'], $nextDefault);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'supported_currencies' => __($exception->getMessage()),
                ]);
            }
        } elseif ($hasStoredSupportedCurrencies && ! in_array($nextDefault, $previousSupported, true)) {
            throw ValidationException::withMessages([
                'currency' => __('The default currency must be included in supported currencies.'),
            ]);
        }

        $nextSupported = $validated['supported_currencies']
            ?? ($hasStoredSupportedCurrencies ? $previousSupported : [$nextDefault]);
        $removedCurrencies = array_values(array_diff($previousSupported, $nextSupported));

        $this->updateSettings->execute($validated, $request->user());

        $activeRemovedCurrencies = empty($removedCurrencies)
            ? []
            : UnitRate::query()
                ->whereIn('currency', $removedCurrencies)
                ->where('is_active', true)
                ->distinct()
                ->orderBy('currency')
                ->pluck('currency')
                ->all();

        if ($activeRemovedCurrencies !== []) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('General settings updated. Active rates still use: :currencies.', [
                    'currencies' => implode(', ', $activeRemovedCurrencies),
                ]),
            ]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('General settings updated.')]);
        }

        return back();
    }

    private function normalizeCurrencyInput(Request $request): void
    {
        if ($request->exists('currency') && is_string($request->input('currency'))) {
            $request->merge(['currency' => strtoupper(trim($request->input('currency')))]);
        }

        if ($request->exists('supported_currencies') && is_array($request->input('supported_currencies'))) {
            $request->merge([
                'supported_currencies' => array_map(
                    static fn (mixed $currency): mixed => is_string($currency)
                        ? strtoupper(trim($currency))
                        : $currency,
                    array_values($request->input('supported_currencies')),
                ),
            ]);
        }
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
