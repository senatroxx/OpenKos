<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/general', [
            'settings' => Setting::get()->only('lease_id_prefix'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lease_id_prefix' => ['required', 'string', 'max:10', Rule::regex('/^[A-Z]+$/')],
        ]);

        $setting = Setting::get();
        $setting->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('General settings updated.')]);

        return to_route('settings.general.edit');
    }
}
