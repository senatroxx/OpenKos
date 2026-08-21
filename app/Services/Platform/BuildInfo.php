<?php

namespace App\Services\Platform;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class BuildInfo
{
    /** @var array{version: string, channel: string, commitSha: string|null}|null */
    private ?array $resolved = null;

    /**
     * @return array{version: string, channel: string, commitSha: string|null}
     */
    public function toArray(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $rootPackage = InstalledVersions::getRootPackage();
        $cacheKey = 'openkos.build-info.'.md5(serialize([
            config('app.build.version'),
            config('app.build.channel'),
            config('app.build.commit_sha'),
            $rootPackage['reference'] ?? null,
        ]));

        return $this->resolved = Cache::rememberForever(
            $cacheKey,
            fn (): array => $this->resolve($rootPackage),
        );
    }

    /**
     * @param  array<string, mixed>  $rootPackage
     * @return array{version: string, channel: string, commitSha: string|null}
     */
    private function resolve(array $rootPackage): array
    {
        $version = $this->configuredString('app.build.version');
        $channel = $this->configuredString('app.build.channel');
        $commitSha = $this->configuredString('app.build.commit_sha');

        if ($version === null) {
            $version = $this->stringValue($rootPackage['pretty_version'] ?? null);
            $commitSha ??= $this->stringValue($rootPackage['reference'] ?? null);

            if ($version === null || $version === 'dev-main') {
                $version = $this->git(['describe', '--tags', '--always', '--dirty']) ?? $version;
            }
        }

        $commitSha ??= $this->git(['rev-parse', '--short', 'HEAD']);
        $version = $this->normalizeVersion($version ?? 'development');

        return [
            'version' => $version,
            'channel' => $channel ?? $this->inferChannel($version),
            'commitSha' => $commitSha,
        ];
    }

    private function configuredString(string $key): ?string
    {
        return $this->stringValue(config($key));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeVersion(string $version): string
    {
        return preg_replace('/^v(?=\d)/', '', $version) ?: $version;
    }

    private function inferChannel(string $version): string
    {
        if (str_starts_with($version, 'nightly')) {
            return 'nightly';
        }

        if ($version === 'development' || str_starts_with($version, 'dev-')) {
            return 'development';
        }

        return str_contains($version, '-') ? 'prerelease' : 'stable';
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(array $arguments): ?string
    {
        try {
            $process = new Process(['git', ...$arguments], base_path());
            $process->setTimeout(2);
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        return $this->stringValue($process->getOutput());
    }
}
