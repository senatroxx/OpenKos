<?php

namespace App\Services\Marketplace;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class MarketplaceClient
{
    private const API_PREFIX = 'api/v1';

    private ?string $baseUrl;

    public function __construct()
    {
        $configuredUrl = trim((string) config('services.marketplace.url'));
        $parts = parse_url($configuredUrl);

        $this->baseUrl = $configuredUrl !== ''
            && is_array($parts)
            && is_string($parts['scheme'] ?? null)
            && is_string($parts['host'] ?? null)
            && ! isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            && (! app()->environment('production') || strtolower($parts['scheme']) === 'https')
            ? rtrim($configuredUrl, '/')
            : null;
    }

    /** @return array<string, mixed> */
    public function listPlugins(?string $search, int $page, int $limit): array
    {
        $query = [
            'page' => $page,
            'limit' => $limit,
        ];

        if ($search !== null && $search !== '') {
            $query['q'] = $search;
        }

        try {
            return $this->getJson('plugins', $query);
        } catch (MarketplaceException $exception) {
            if ($exception->status === 404) {
                throw new MarketplaceException('Marketplace catalog is unavailable.', previous: $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function resolveVersion(
        string $pluginId,
        string $openkosVersion,
        string $platformVersion,
        string $phpVersion,
    ): ?array {
        try {
            return $this->getJson(
                'plugins/'.rawurlencode($this->pluginId($pluginId)).'/versions/resolve',
                [
                    'openkos_version' => $openkosVersion,
                    'platform_version' => $platformVersion,
                    'php_version' => $phpVersion,
                ],
            );
        } catch (MarketplaceException $exception) {
            if ($exception->status === 404) {
                return null;
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function getVersion(string $pluginId, string $version): array
    {
        $version = $this->version($version);
        try {
            $data = $this->getJson(
                'plugins/'.rawurlencode($this->pluginId($pluginId)).'/versions/'.rawurlencode($version),
            );
        } catch (MarketplaceException $exception) {
            if ($exception->status === 404) {
                throw new MarketplaceException('Marketplace plugin version is no longer available.', 404, $exception);
            }

            throw $exception;
        }

        if (($data['version'] ?? null) !== $version) {
            throw new MarketplaceException('Marketplace returned a different plugin version.');
        }

        return $data;
    }

    public function downloadArtifact(
        string $pluginId,
        string $version,
        int $expectedSize,
        string $expectedSha256,
    ): string {
        $version = $this->version($version);
        $maxBytes = (int) config('services.marketplace.max_artifact_bytes');
        $expectedSha256 = strtolower(trim($expectedSha256));

        if (
            $expectedSize < 1
            || $expectedSize > $maxBytes
            || ! preg_match('/\A[a-f0-9]{64}\z/i', $expectedSha256)
        ) {
            throw new MarketplaceException('Marketplace artifact metadata is invalid.');
        }

        $disk = Storage::disk('local');
        $directory = 'plugin-downloads';
        $disk->makeDirectory($directory);
        $path = $disk->path($directory.'/'.Str::uuid()->toString().'.zip');
        $destination = null;
        $source = null;

        try {
            $response = $this->request(
                'plugins/'.rawurlencode($this->pluginId($pluginId)).'/versions/'.rawurlencode($version).'/artifact',
                'application/zip',
            );
            $this->assertSuccessful($response);

            $contentLength = $this->contentLength($response);

            if ($contentLength !== null && $contentLength !== $expectedSize) {
                throw new MarketplaceException('Marketplace artifact size does not match its metadata.');
            }

            $destination = fopen($path, 'x+b');

            if ($destination === false) {
                throw new MarketplaceException('Marketplace artifact could not be stored temporarily.');
            }

            $source = $response->resource();
            $hash = hash_init('sha256');
            $bytes = 0;

            while (! feof($source)) {
                $chunk = fread($source, 8192);

                if ($chunk === false) {
                    throw new MarketplaceException('Marketplace artifact download could not be read.');
                }

                if ($chunk === '') {
                    if (! feof($source)) {
                        throw new MarketplaceException('Marketplace artifact download stalled.');
                    }

                    break;
                }

                $bytes += strlen($chunk);

                if ($bytes > $maxBytes || $bytes > $expectedSize) {
                    throw new MarketplaceException('Marketplace artifact exceeds the configured size limit.');
                }

                hash_update($hash, $chunk);

                $written = 0;
                $chunkLength = strlen($chunk);

                while ($written < $chunkLength) {
                    $count = fwrite($destination, substr($chunk, $written));

                    if ($count === false || $count === 0) {
                        throw new MarketplaceException('Marketplace artifact could not be stored temporarily.');
                    }

                    $written += $count;
                }
            }

            $actualSha256 = hash_final($hash);

            if ($bytes !== $expectedSize || ! hash_equals($expectedSha256, $actualSha256)) {
                throw new MarketplaceException('Marketplace artifact integrity verification failed.');
            }

            return $path;
        } catch (MarketplaceException $exception) {
            @unlink($path);

            throw $exception;
        } catch (Throwable $exception) {
            @unlink($path);

            throw new MarketplaceException('Marketplace artifact download failed.', previous: $exception);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }

            if (is_resource($destination)) {
                fclose($destination);
            }
        }
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    private function getJson(string $path, array $query = []): array
    {
        $response = $this->request($path, 'application/json', $query);
        $this->assertSuccessful($response);
        $body = $this->readBody($response, (int) config('services.marketplace.max_response_bytes'));

        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new MarketplaceException('Marketplace returned malformed JSON.', previous: $exception);
        }

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            throw new MarketplaceException('Marketplace returned a malformed response.');
        }

        return $payload['data'];
    }

    /** @param array<string, mixed> $query */
    private function request(string $path, string $accept, array $query = []): Response
    {
        try {
            return Http::accept($accept)
                ->connectTimeout((int) config('services.marketplace.connect_timeout'))
                ->timeout((int) config('services.marketplace.timeout'))
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                ])
                ->get($this->url($path), $query);
        } catch (Throwable $exception) {
            throw new MarketplaceException('Marketplace is unavailable.', previous: $exception);
        }
    }

    private function assertSuccessful(Response $response): void
    {
        if (! $response->successful()) {
            throw new MarketplaceException(
                'Marketplace request failed.',
                $response->status(),
            );
        }
    }

    private function readBody(Response $response, int $maxBytes): string
    {
        $contentLength = $this->contentLength($response);

        if ($contentLength !== null && $contentLength > $maxBytes) {
            throw new MarketplaceException('Marketplace response exceeds the configured size limit.');
        }

        $source = null;
        $body = '';

        try {
            $source = $response->resource();

            while (! feof($source)) {
                $chunk = fread($source, 8192);

                if ($chunk === false) {
                    throw new MarketplaceException('Marketplace response could not be read.');
                }

                if ($chunk === '') {
                    if (! feof($source)) {
                        throw new MarketplaceException('Marketplace response stalled.');
                    }

                    break;
                }

                $body .= $chunk;

                if (strlen($body) > $maxBytes) {
                    throw new MarketplaceException('Marketplace response exceeds the configured size limit.');
                }
            }
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        return $body;
    }

    private function contentLength(Response $response): ?int
    {
        $value = trim($response->header('Content-Length'));

        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }

    private function url(string $path): string
    {
        if ($this->baseUrl === null) {
            throw new MarketplaceException('Marketplace URL configuration is invalid.');
        }

        return $this->baseUrl.'/'.self::API_PREFIX.'/'.ltrim($path, '/');
    }

    private function pluginId(string $id): string
    {
        if (! preg_match('/\A[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*\z/', $id)) {
            throw new MarketplaceException('Marketplace plugin identity is invalid.');
        }

        return $id;
    }

    private function version(string $version): string
    {
        if (! preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?\z/', $version)) {
            throw new MarketplaceException('Marketplace plugin version is invalid.');
        }

        return $version;
    }
}
