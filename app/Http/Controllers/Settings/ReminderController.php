<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function edit(): Response
    {
        $settings = Setting::get()->only(
            'reminder_enabled',
            'reminder_days_before',
            'reminder_overdue_intervals',
            'reminder_message_template',
            'reminder_channels',
        );

        $settings['reminder_channels'] ??= ['log'];

        return Inertia::render('settings/reminders', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reminder_enabled' => ['boolean'],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'reminder_overdue_intervals' => ['required', 'array'],
            'reminder_overdue_intervals.*' => ['integer', 'min:1', 'max:365'],
            'reminder_message_template' => ['nullable', 'string', 'max:1000'],
            'reminder_channels' => ['nullable', 'array'],
            'reminder_channels.*' => ['string', 'in:log,whatsapp,mail'],
        ]);

        $setting = Setting::get();
        $validated['reminder_channels'] ??= [];
        $setting->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder settings updated.')]);

        return to_route('settings.reminders.edit');
    }
}
