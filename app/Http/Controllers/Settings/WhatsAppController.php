<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateSettings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsAppManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OpenKOS\Platform\Notification\NotificationDriverRegistration;
use OpenKOS\Platform\Notification\NotificationRegistry;

class WhatsAppController extends Controller
{
    public function __construct(
        private WhatsAppManager $whatsapp,
        private NotificationRegistry $registry,
        private UpdateSettings $updateSettings,
    ) {}

    public function pair(): JsonResponse
    {
        $health = $this->whatsapp->health();

        if ($health->healthy) {
            return response()->json(['message' => 'Device is already connected.']);
        }

        try {
            $this->whatsapp->pair();
        } catch (\RuntimeException) {
        }

        $qrCode = $this->whatsapp->getPairingQrCode();

        return response()->json([
            'qr_code' => $qrCode,
            'state' => $qrCode ? 'connecting' : 'connecting',
        ]);
    }

    public function qr(): JsonResponse
    {
        $health = $this->whatsapp->health();
        $qrCode = $this->whatsapp->getPairingQrCode();

        return response()->json([
            'qr_code' => $qrCode,
            'state' => match (true) {
                $health->healthy => 'connected',
                $health->message && str_contains($health->message, 'connecting') => 'connecting',
                default => 'disconnected',
            },
            'phone' => $health->phone,
            'lastConnected' => $health->lastConnected,
        ]);
    }

    public function edit(): Response
    {
        $drivers = collect($this->registry->forChannel('whatsapp'))
            ->map(function (NotificationDriverRegistration $registration) {
                $class = $registration->driverClass;
                $instance = new $class([]);

                return [
                    'name' => $registration->name,
                    'label' => $registration->label,
                    'configuration_schema' => $instance->configurationSchema(),
                    'supports_pairing' => $instance->supportsPairing(),
                ];
            })
            ->values();

        $settings = Setting::some(['whatsapp_driver', 'whatsapp_config']);

        $connection = null;
        if (($settings['whatsapp_driver'] ?? null) === 'baileys') {
            try {
                $result = $this->whatsapp->health();
                $connection = [
                    'state' => $result->healthy ? 'connected' : 'disconnected',
                    'phone' => $result->phone,
                    'lastConnected' => $result->lastConnected,
                ];
            } catch (\Throwable) {
                $connection = ['state' => 'disconnected', 'phone' => null, 'lastConnected' => null];
            }
        }

        return Inertia::render('settings/whatsapp', [
            'drivers' => $drivers,
            'settings' => $settings,
            'connection' => $connection,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $driverNames = array_map(
            fn (NotificationDriverRegistration $r) => $r->name,
            $this->registry->forChannel('whatsapp'),
        );

        $validated = $request->validate([
            'whatsapp_driver' => ['nullable', 'string', 'in:'.implode(',', $driverNames)],
            'whatsapp_config' => ['nullable', 'array'],
        ]);

        $data = $validated;
        if (isset($validated['whatsapp_config'])) {
            $existing = Setting::get('whatsapp_config') ?? [];
            $data['whatsapp_config'] = array_merge($existing, $validated['whatsapp_config']);
        }

        $this->updateSettings->execute($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp settings updated.')]);

        return to_route('settings.whatsapp.edit');
    }

    public function test(): RedirectResponse
    {
        $result = $this->whatsapp->health();

        if ($result->healthy) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp connection is healthy.')]);
        } else {
            Inertia::flash('toast', ['type' => 'error', 'message' => $result->message ?? __('WhatsApp connection failed.')]);
        }

        return to_route('settings.whatsapp.edit');
    }

    public function status(): JsonResponse
    {
        $result = $this->whatsapp->health();

        return response()->json([
            'healthy' => $result->healthy,
            'message' => $result->message,
            'phone' => $result->phone,
            'lastConnected' => $result->lastConnected,
        ]);
    }

    public function disconnect(): RedirectResponse
    {
        $this->whatsapp->disconnect();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device disconnected.')]);

        return to_route('settings.whatsapp.edit');
    }
}
