<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
    ) {}

    public function edit(): Response
    {
        $settings = Setting::some([
            'reminder_enabled',
            'reminder_days_before',
            'reminder_overdue_intervals',
            'reminder_message_template',
            'reminder_channels',
        ]);

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
            'reminder_overdue_intervals' => ['required', 'string', 'regex:/^\d+(?:\s*,\s*\d+)*$/'],
            'reminder_message_template' => ['nullable', 'string', 'max:1000'],
            'reminder_channels' => ['required', 'array', 'min:1'],
            'reminder_channels.*' => ['string', 'in:log,whatsapp,mail'],
        ]);

        $validated['reminder_overdue_intervals'] = array_map(
            fn ($v) => (int) trim($v),
            explode(',', $validated['reminder_overdue_intervals']),
        );

        $this->updateSettings->execute($validated, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder settings updated.')]);

        return back();
    }
}
