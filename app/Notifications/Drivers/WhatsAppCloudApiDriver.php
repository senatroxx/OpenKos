<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiDriver implements WhatsAppDriver
{
    private const GRAPH_API_VERSION = 'v22.0';

    private const GRAPH_API_BASE = 'https://graph.facebook.com/'.self::GRAPH_API_VERSION;

    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        $phoneNumberId = $this->config['phone_number_id'] ?? throw new \RuntimeException('WhatsApp Cloud API phone_number_id is not configured.');
        $token = $this->config['access_token'] ?? throw new \RuntimeException('WhatsApp Cloud API access_token is not configured.');

        $response = Http::withToken($token)->post(self::GRAPH_API_BASE.'/'.$phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($message->phone),
            'type' => 'text',
            'text' => ['body' => $message->message],
        ]);

        $body = $response->json();

        if (isset($body['error'])) {
            throw new \RuntimeException($body['error']['message'] ?? 'WhatsApp Cloud API send failed');
        }
    }

    public function health(): DriverHealthResult
    {
        $phoneNumberId = $this->config['phone_number_id'] ?? null;
        $token = $this->config['access_token'] ?? null;

        if (! $phoneNumberId || ! $token) {
            return new DriverHealthResult(false, 'WhatsApp Cloud API credentials not configured.');
        }

        try {
            $response = Http::withToken($token)->get(self::GRAPH_API_BASE.'/'.$phoneNumberId);
        } catch (\Throwable $e) {
            return new DriverHealthResult(false, $e->getMessage());
        }

        $body = $response->json();

        if (isset($body['error'])) {
            return new DriverHealthResult(false, $body['error']['message']);
        }

        return new DriverHealthResult(
            healthy: true,
            message: 'Connected as '.($body['display_phone_number'] ?? ''),
            phone: $body['display_phone_number'] ?? null,
        );
    }

    public function supportsPairing(): bool
    {
        return false;
    }

    public function configurationSchema(): array
    {
        return [
            'phone_number_id' => ['label' => 'Phone Number ID', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 123456789012345'],
            'access_token' => ['label' => 'Access Token', 'type' => 'password', 'required' => true],
        ];
    }

    public function getPairingQrCode(): ?string
    {
        return null;
    }

    public function pair(): void
    {
        throw new \RuntimeException('Pairing not supported.');
    }

    public function disconnect(): void
    {
        throw new \RuntimeException('Disconnect not supported.');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        return '+'.$phone;
    }
}
