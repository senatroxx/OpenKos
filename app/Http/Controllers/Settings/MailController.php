<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/mail', [
            'settings' => Setting::get()->only(
                'mail_driver',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_driver' => ['nullable', 'string', 'in:smtp,log,sendmail'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = Setting::get();

        if (blank($validated['mail_password'] ?? null)) {
            unset($validated['mail_password']);
        }

        $setting->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mail settings updated.')]);

        return to_route('settings.mail.edit');
    }

    public function test(): RedirectResponse
    {
        $setting = Setting::get();

        if (! $setting->mail_host) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Configure SMTP host before testing.')]);

            return to_route('settings.mail.edit');
        }

        try {
            Mail::raw(__('Test email from OpenKOS.'), function ($message) use ($setting): void {
                $message->to($setting->mail_from_address)
                    ->subject(__('Test Email'));
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Failed to send: :error', ['error' => $e->getMessage()])]);
        }

        return to_route('settings.mail.edit');
    }
}
