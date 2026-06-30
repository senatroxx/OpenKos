<?php

namespace App\Notifications\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

class BaileysDriver implements WhatsAppDriver
{
    public function __construct(private array $config = []) {}

    public function send(WhatsAppMessage $message): void
    {
        $this->signedRequest('POST', '/api/send', [
            'to' => $message->phone,
            'text' => $message->message,
        ]);
    }

    public function health(): DriverHealthResult
    {
        try {
            $result = $this->signedRequest('GET', '/api/sessions');
        } catch (\RuntimeException $e) {
            return new DriverHealthResult(false, $e->getMessage());
        }

        $session = $result['data'] ?? [];
        $state = $session['state'] ?? 'unknown';

        return new DriverHealthResult(
            healthy: $state === 'connected',
            message: "State: {$state}",
            phone: $session['phone'] ?? null,
            lastConnected: $session['lastConnected'] ?? null,
        );
    }

    public function supportsPairing(): bool
    {
        return true;
    }

    public function configurationSchema(): array
    {
        return [
            'url' => ['label' => 'Service URL', 'type' => 'url', 'required' => true, 'placeholder' => 'http://localhost:3000'],
            'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function getPairingQrCode(): ?string
    {
        try {
            $result = $this->signedRequest('GET', '/api/sessions/qr');
        } catch (\RuntimeException) {
            return null;
        }

        $qr = $result['data']['qr'] ?? null;

        if ($qr === null) {
            return null;
        }

        return str_replace('data:image/png;base64,', '', $qr);
    }

    public function pair(): void
    {
        $this->signedRequest('POST', '/api/sessions');
    }

    public function disconnect(): void
    {
        $this->signedRequest('DELETE', '/api/sessions');
    }

    private function signedRequest(string $method, string $path, array $data = []): array
    {
        $baseUrl = $this->config['url'] ?? throw new \RuntimeException('Baileys URL is not configured.');
        $apiKey = $this->config['api_key'] ?? '';

        $url = $baseUrl.$path;
        $timestamp = (string) time();
        $body = $data ? json_encode($data) : '';
        $payload = $timestamp."\n".$method."\n".$path."\n".$body;
        $signature = hash_hmac('sha256', $payload, $apiKey);

        $request = Http::withHeaders([
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ]);

        $response = match ($method) {
            'GET' => $request->get($url),
            'POST' => $request->withBody($body, 'application/json')->post($url),
            'DELETE' => $request->delete($url),
        };

        $json = $response->json();

        \Log::debug('Baileys request', [
            'method' => $method,
            'url' => $url,
            'request_body' => $body,
            'response_status' => $response->status(),
            'response_body' => $json,
        ]);

        if (! ($json['meta']['success'] ?? false)) {
            throw new \RuntimeException($json['meta']['message'] ?? 'Baileys request failed');
        }

        return $json;
    }
}
