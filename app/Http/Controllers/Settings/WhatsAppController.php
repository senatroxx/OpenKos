<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsAppManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppController extends Controller
{
    public function __construct(private WhatsAppManager $whatsapp) {}

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
        $drivers = config('services.whatsapp.drivers', []);

        $drivers = collect($drivers)->map(function ($config, $name) {
            $class = $config['class'];
            $instance = new $class([]);

            return [
                'name' => $name,
                'label' => $this->driverLabel($name),
                'configuration_schema' => $instance->configurationSchema(),
                'supports_pairing' => $instance->supportsPairing(),
            ];
        })->values();

        $settings = Setting::get()->only('whatsapp_driver', 'whatsapp_config');

        return Inertia::render('settings/whatsapp', [
            'drivers' => $drivers,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $driverNames = array_keys(config('services.whatsapp.drivers', []));

        $validated = $request->validate([
            'whatsapp_driver' => ['nullable', 'string', 'in:'.implode(',', $driverNames)],
            'whatsapp_config' => ['nullable', 'array'],
        ]);

        $setting = Setting::get();

        $data = $validated;
        if (isset($validated['whatsapp_config'])) {
            $existing = $setting->whatsapp_config ?? [];
            $data['whatsapp_config'] = array_merge($existing, $validated['whatsapp_config']);
        }

        $setting->update($data);

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

    private function driverLabel(string $name): string
    {
        return match ($name) {
            'log' => 'Log',
            'baileys' => 'Baileys',
            'fonnte' => 'Fonnte',
            'api_co_id' => 'ApiCo.id',
            default => $name,
        };
    }
}
