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
            return $this->validateCatalog($this->getJson('plugins', $query));
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
            $data = $this->getJson(
                'plugins/'.rawurlencode($this->pluginId($pluginId)).'/versions/resolve',
                [
                    'openkos_version' => $openkosVersion,
                    'platform_version' => $platformVersion,
                    'php_version' => $phpVersion,
                ],
            );

            return $this->validateVersion($data, $pluginId, false);
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

        return $this->validateVersion($data, $pluginId, true);
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateCatalog(array $data): array
    {
        if (
            ! is_int($data['current_page'] ?? null)
            || ! is_int($data['total_page'] ?? null)
            || ! is_int($data['total_records'] ?? null)
            || $data['current_page'] < 1
            || $data['total_page'] < 0
            || $data['total_records'] < 0
            || ! is_array($data['records'] ?? null)
            || ! array_is_list($data['records'])
        ) {
            throw new MarketplaceException('Marketplace returned a malformed plugin listing.');
        }

        $records = [];

        foreach ($data['records'] as $record) {
            if (! is_array($record)) {
                throw new MarketplaceException('Marketplace returned a malformed plugin listing.');
            }

            $records[] = $this->validatePluginRecord($record);
        }

        return [
            'current_page' => $data['current_page'],
            'total_page' => $data['total_page'],
            'total_records' => $data['total_records'],
            'records' => $records,
        ];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function validatePluginRecord(array $record): array
    {
        foreach (['id', 'name', 'summary', 'description', 'publisher', 'repository_url', 'homepage_url', 'latest_version'] as $key) {
            if (! array_key_exists($key, $record)) {
                throw new MarketplaceException('Marketplace returned a malformed plugin listing.');
            }
        }

        $id = $record['id'];

        if (! is_string($id) || preg_match('/\A[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*\z/', $id) !== 1) {
            throw new MarketplaceException('Marketplace returned a malformed plugin identity.');
        }

        if (! is_string($record['name']) || trim($record['name']) === '') {
            throw new MarketplaceException('Marketplace returned a malformed plugin listing.');
        }

        foreach (['summary', 'description'] as $key) {
            if ($record[$key] !== null && ! is_string($record[$key])) {
                throw new MarketplaceException('Marketplace returned a malformed plugin listing.');
            }
        }

        $publisher = $record['publisher'];

        if ($publisher !== null && (! is_array($publisher) || ! is_string($publisher['name'] ?? null) || trim($publisher['name']) === '')) {
            throw new MarketplaceException('Marketplace returned a malformed publisher.');
        }

        if (is_array($publisher) && (! array_key_exists('url', $publisher) || $publisher['url'] !== null && ! is_string($publisher['url']))) {
            throw new MarketplaceException('Marketplace returned a malformed publisher.');
        }

        foreach (['repository_url', 'homepage_url'] as $key) {
            if ($record[$key] !== null && (! is_string($record[$key]) || $this->isValidUrl($record[$key]) === false)) {
                throw new MarketplaceException('Marketplace returned a malformed plugin URL.');
            }
        }

        if (is_array($publisher) && $publisher['url'] !== null && $this->isValidUrl($publisher['url']) === false) {
            throw new MarketplaceException('Marketplace returned a malformed publisher URL.');
        }

        return [
            'id' => $id,
            'name' => $record['name'],
            'summary' => $record['summary'],
            'description' => $record['description'],
            'publisher' => $publisher === null ? null : [
                'name' => $publisher['name'],
                'url' => $publisher['url'],
            ],
            'repository_url' => $record['repository_url'],
            'homepage_url' => $record['homepage_url'],
            'latest_version' => $record['latest_version'] === null
                ? null
                : $this->validateVersion($record['latest_version'], $id, false),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validateVersion(array $data, string $pluginId, bool $includeManifest): array
    {
        if (
            ! is_string($data['version'] ?? null)
            || preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?\z/', $data['version']) !== 1
            || ! is_string($data['entry_class'] ?? null)
            || preg_match('/\A(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*\z/', $data['entry_class']) !== 1
            || ! is_array($data['compatibility'] ?? null)
            || ! is_string($data['published_at'] ?? null)
            || trim($data['published_at']) === ''
            || ! is_array($data['artifact'] ?? null)
            || ! is_int($data['artifact']['size'] ?? null)
            || $data['artifact']['size'] < 1
            || $data['artifact']['size'] > (int) config('services.marketplace.max_artifact_bytes')
            || ! is_string($data['artifact']['sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/i', $data['artifact']['sha256']) !== 1
        ) {
            throw new MarketplaceException('Marketplace returned malformed plugin version metadata.');
        }

        foreach (['openkos', 'platform', 'php'] as $key) {
            if (! is_string($data['compatibility'][$key] ?? null) || trim($data['compatibility'][$key]) === '') {
                throw new MarketplaceException('Marketplace returned malformed compatibility metadata.');
            }
        }

        $normalized = [
            'version' => $data['version'],
            'entry_class' => $data['entry_class'],
            'compatibility' => [
                'openkos' => $data['compatibility']['openkos'],
                'platform' => $data['compatibility']['platform'],
                'php' => $data['compatibility']['php'],
            ],
            'published_at' => $data['published_at'],
            'artifact' => [
                'size' => $data['artifact']['size'],
                'sha256' => strtolower($data['artifact']['sha256']),
            ],
        ];

        if (! $includeManifest) {
            return $normalized;
        }

        if (
            ! array_key_exists('dependencies', $data)
            || ! is_array($data['dependencies'])
            || ! array_is_list($data['dependencies'])
            || ! array_key_exists('release_notes', $data)
            || ($data['release_notes'] !== null && ! is_string($data['release_notes']))
            || ! is_array($data['manifest'] ?? null)
        ) {
            throw new MarketplaceException('Marketplace returned incomplete plugin version metadata.');
        }

        $dependencies = $this->validateDependencies($data['dependencies']);
        $manifest = $this->validateManifest($data['manifest'], $pluginId, $data['version'], $data['entry_class'], $data['compatibility']);

        if ($dependencies !== $manifest['dependencies']) {
            throw new MarketplaceException('Marketplace version dependencies do not match its manifest.');
        }

        return [
            ...$normalized,
            'dependencies' => $dependencies,
            'release_notes' => $data['release_notes'],
            'manifest' => $manifest,
        ];
    }

    /** @param array<string, mixed> $manifest @param array<string, mixed> $compatibility @return array<string, mixed> */
    private function validateManifest(array $manifest, string $pluginId, string $version, string $entryClass, array $compatibility): array
    {
        foreach (['id', 'name', 'version', 'description', 'entry_class', 'core_version', 'php', 'dependencies'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new MarketplaceException('Marketplace returned an incomplete plugin manifest.');
            }
        }

        foreach (['id', 'name', 'version', 'description', 'entry_class', 'core_version', 'php'] as $key) {
            if (! is_string($manifest[$key]) || ($key !== 'description' && trim($manifest[$key]) === '')) {
                throw new MarketplaceException('Marketplace returned an invalid plugin manifest.');
            }
        }

        if (
            $manifest['id'] !== $pluginId
            || $manifest['version'] !== $version
            || $manifest['entry_class'] !== $entryClass
            || $manifest['core_version'] !== $compatibility['openkos']
            || $manifest['php'] !== $compatibility['php']
        ) {
            throw new MarketplaceException('Marketplace version metadata does not match its manifest.');
        }

        return [
            'id' => $manifest['id'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'description' => $manifest['description'],
            'entry_class' => $manifest['entry_class'],
            'core_version' => $manifest['core_version'],
            'php' => $manifest['php'],
            'dependencies' => $this->validateDependencies($manifest['dependencies']),
        ];
    }

    /** @param mixed $dependencies @return array<int, string> */
    private function validateDependencies(mixed $dependencies): array
    {
        if (! is_array($dependencies) || ! array_is_list($dependencies)) {
            throw new MarketplaceException('Marketplace returned invalid plugin dependencies.');
        }

        foreach ($dependencies as $dependency) {
            if (! is_string($dependency) || preg_match('/\A[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*\z/', $dependency) !== 1) {
                throw new MarketplaceException('Marketplace returned invalid plugin dependencies.');
            }
        }

        return array_values($dependencies);
    }

    private function isValidUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'], $parts['pass']);
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
