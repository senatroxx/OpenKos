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
        return Inertia::render('settings/reminders', [
            'settings' => Setting::get()->only(
                'reminder_enabled',
                'reminder_days_before',
                'reminder_overdue_intervals',
                'reminder_message_template',
            ),
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
        ]);

        $setting = Setting::get();
        $setting->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reminder settings updated.')]);

        return to_route('settings.reminders.edit');
    }
}
