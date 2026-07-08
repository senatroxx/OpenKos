<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailController extends Controller
{
    public function __construct(
        private UpdateSettings $updateSettings,
    ) {}

    public function edit(): Response
    {
        $settings = Setting::some(['mail_config']);
        if (isset($settings['mail_config'])) {
            unset($settings['mail_config']['password']);
        }

        return Inertia::render('settings/mail', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_config.driver' => ['nullable', 'string', 'in:smtp,log,sendmail'],
            'mail_config.host' => ['nullable', 'string', 'max:255'],
            'mail_config.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_config.username' => ['nullable', 'string', 'max:255'],
            'mail_config.password' => ['nullable', 'string', 'max:255'],
            'mail_config.encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_config.from_address' => ['nullable', 'email', 'max:255'],
            'mail_config.from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $data = $validated['mail_config'] ?? [];

        $existing = Setting::get('mail_config') ?? [];
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data = array_merge($existing, $data);

        $this->updateSettings->execute(['mail_config' => $data], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mail settings updated.')]);

        return to_route('settings.mail.edit');
    }

    public function test(): RedirectResponse
    {
        $config = Setting::get('mail_config') ?? [];

        if (! ($config['host'] ?? null)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Configure SMTP host before testing.')]);

            return to_route('settings.mail.edit');
        }

        try {
            Mail::raw(__('Test email from OpenKOS.'), function ($message) use ($config): void {
                $message->to($config['from_address'] ?? '')
                    ->subject(__('Test Email'));
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Failed to send: :error', ['error' => $e->getMessage()])]);
        }

        return to_route('settings.mail.edit');
    }
}
