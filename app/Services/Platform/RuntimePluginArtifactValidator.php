<?php

namespace App\Services\Platform;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use InvalidArgumentException;
use OpenKOS\Platform\Plugin\Plugin;
use Symfony\Component\Process\Process;
use Throwable;

final class RuntimePluginArtifactValidator
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     entry_class: class-string<Plugin>,
     *     core_version: string,
     *     php: string,
     *     dependencies: array<int, string>
     * }
     */
    public function validate(string $directory, ?string $expectedId = null): array
    {
        if (! is_dir($directory) || is_link($directory)) {
            throw new InvalidArgumentException('Runtime plugin artifact directory is missing or unsafe.');
        }

        $this->validateTree($directory);

        $manifest = $this->readJsonFile($directory.'/manifest.json', 'manifest.json');
        $composer = $this->readJsonFile($directory.'/composer.json', 'composer.json');
        $lock = $this->readJsonFile($directory.'/composer.lock', 'composer.lock');

        $metadata = $this->validateManifest($manifest);

        if ($expectedId !== null && $metadata['id'] !== $expectedId) {
            throw new InvalidArgumentException(
                "Runtime plugin manifest ID [{$metadata['id']}] does not match installed package [{$expectedId}].",
            );
        }

        $this->validateComposerMetadata($metadata, $composer, $lock, $directory);

        $autoload = $directory.'/vendor/autoload.php';
        if (! is_file($autoload)) {
            throw new InvalidArgumentException('Runtime plugin artifact must include vendor/autoload.php.');
        }

        require_once $autoload;

        $entryClass = $metadata['entry_class'];

        if (! class_exists($entryClass)) {
            throw new InvalidArgumentException("Runtime plugin entry class [{$entryClass}] does not exist.");
        }

        if (! is_a($entryClass, Plugin::class, true)) {
            throw new InvalidArgumentException(
                "Runtime plugin entry class [{$entryClass}] must extend ".Plugin::class.'.',
            );
        }

        $reflection = new \ReflectionClass($entryClass);
        $entryFile = $reflection->getFileName();
        $realDirectory = realpath($directory);

        $resolvedEntryFile = is_string($entryFile) ? realpath($entryFile) : false;
        $sourceMarker = is_string($entryFile) ? DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR : '';
        $sourcePosition = is_string($entryFile) ? strrpos($entryFile, $sourceMarker) : false;
        $relativeSource = is_int($sourcePosition)
            ? substr($entryFile, $sourcePosition + strlen($sourceMarker))
            : '';
        $entryFileRelocatedFromStaging = is_string($entryFile)
            && $resolvedEntryFile === false
            && str_contains($entryFile, DIRECTORY_SEPARATOR.'.staging'.DIRECTORY_SEPARATOR)
            && $relativeSource !== ''
            && is_file($directory.'/src/'.$relativeSource);

        if (
            ! is_string($entryFile) ||
            ! is_string($realDirectory) ||
            (! str_starts_with($resolvedEntryFile ?: '', $realDirectory.DIRECTORY_SEPARATOR) && ! $entryFileRelocatedFromStaging)
        ) {
            throw new InvalidArgumentException(
                "Runtime plugin entry class [{$entryClass}] is not loaded from the artifact.",
            );
        }

        /** @var Plugin $plugin */
        $plugin = app()->make($entryClass);
        $pluginManifest = $plugin->manifest();

        if (
            $pluginManifest->id !== $metadata['id'] ||
            $pluginManifest->name !== $metadata['name'] ||
            $pluginManifest->version !== $metadata['version'] ||
            $pluginManifest->description !== $metadata['description'] ||
            $pluginManifest->coreVersion !== $metadata['core_version'] ||
            $pluginManifest->dependencies !== $metadata['dependencies']
        ) {
            throw new InvalidArgumentException(
                "Runtime plugin manifest for [{$metadata['id']}] does not match its entry class.",
            );
        }

        return $metadata;
    }

    /**
     * Read manifest metadata without loading the runtime package.
     *
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     entry_class: class-string<Plugin>,
     *     core_version: string,
     *     php: string,
     *     dependencies: array<int, string>
     * }
     */
    public function inspectStaticMetadata(string $directory, ?string $expectedId = null): array
    {
        if (! is_dir($directory) || is_link($directory)) {
            throw new InvalidArgumentException('Runtime plugin artifact directory is missing or unsafe.');
        }

        $this->validateTree($directory);
        $metadata = $this->validateManifest($this->readJsonFile($directory.'/manifest.json', 'manifest.json'));

        if ($expectedId !== null && $metadata['id'] !== $expectedId) {
            throw new InvalidArgumentException(
                "Runtime plugin manifest ID [{$metadata['id']}] does not match installed package [{$expectedId}].",
            );
        }

        $composer = $this->readJsonFile($directory.'/composer.json', 'composer.json');
        $lock = $this->readJsonFile($directory.'/composer.lock', 'composer.lock');

        if (! is_array($lock['packages'] ?? null)) {
            throw new InvalidArgumentException('Runtime plugin composer.lock must contain packages.');
        }

        if (($composer['name'] ?? null) !== $metadata['id']) {
            throw new InvalidArgumentException('Runtime plugin composer name must match manifest ID.');
        }

        if (data_get($composer, 'extra.openkos.plugin') !== $metadata['entry_class']) {
            throw new InvalidArgumentException('Runtime plugin Composer metadata must match manifest entry class.');
        }

        if (! is_file($directory.'/vendor/autoload.php') || is_link($directory.'/vendor/autoload.php')) {
            throw new InvalidArgumentException('Runtime plugin artifact must include vendor/autoload.php.');
        }

        return $metadata;
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     entry_class: class-string<Plugin>,
     *     core_version: string,
     *     php: string,
     *     dependencies: array<int, string>
     * }
     */
    public function validateInFreshProcess(string $directory, ?string $expectedId = null): array
    {
        try {
            $runtimeConfig = json_encode(config('platform.runtime'), JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Runtime plugin configuration cannot be serialized.', previous: $exception);
        }

        $process = new Process([
            PHP_BINARY,
            base_path('bin/validate-runtime-plugin.php'),
            $directory,
            $expectedId ?? '',
            $runtimeConfig,
            (string) config('platform.version', '0.2.0'),
        ], base_path());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new InvalidArgumentException($message !== '' ? $message : 'Runtime plugin validation failed.');
        }

        try {
            $metadata = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Runtime plugin validation returned malformed metadata.', previous: $exception);
        }

        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Runtime plugin validation returned malformed metadata.');
        }

        /** @var array{
         *     id: string,
         *     name: string,
         *     version: string,
         *     description: string,
         *     entry_class: class-string<Plugin>,
         *     core_version: string,
         *     php: string,
         *     dependencies: array<int, string>
         * } $metadata
         */
        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{
     *     id: string,
     *     name: string,
     *     version: string,
     *     description: string,
     *     entry_class: class-string<Plugin>,
     *     core_version: string,
     *     php: string,
     *     dependencies: array<int, string>
     * }
     */
    private function validateManifest(array $manifest): array
    {
        $required = ['id', 'name', 'version', 'entry_class', 'core_version', 'php', 'dependencies'];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new InvalidArgumentException("Runtime plugin manifest is missing [{$key}].");
            }
        }

        foreach (['id', 'name', 'version', 'entry_class', 'core_version', 'php'] as $key) {
            if (! is_string($manifest[$key]) || trim($manifest[$key]) === '') {
                throw new InvalidArgumentException("Runtime plugin manifest field [{$key}] must be a non-empty string.");
            }
        }

        if (preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $manifest['entry_class']) !== 1) {
            throw new InvalidArgumentException('Runtime plugin manifest entry_class must be a valid PHP class name.');
        }

        if (! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $manifest['id'])) {
            throw new InvalidArgumentException("Runtime plugin ID [{$manifest['id']}] is unsafe.");
        }

        if (! is_array($manifest['dependencies']) || array_is_list($manifest['dependencies']) === false) {
            throw new InvalidArgumentException('Runtime plugin dependencies must be a list.');
        }

        foreach ($manifest['dependencies'] as $dependency) {
            if (! is_string($dependency) || ! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $dependency)) {
                throw new InvalidArgumentException('Runtime plugin dependencies must contain safe plugin IDs.');
            }
        }

        /** @var array{
         *     id: string,
         *     name: string,
         *     version: string,
         *     description?: string,
         *     entry_class: class-string<Plugin>,
         *     core_version: string,
         *     php: string,
         *     dependencies: array<int, string>
         * } $manifest
         */
        return [
            'id' => $manifest['id'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'description' => is_string($manifest['description'] ?? '') ? $manifest['description'] : '',
            'entry_class' => $manifest['entry_class'],
            'core_version' => $manifest['core_version'],
            'php' => $manifest['php'],
            'dependencies' => array_values($manifest['dependencies']),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $composer
     * @param  array<string, mixed>  $lock
     */
    private function validateComposerMetadata(array $metadata, array $composer, array $lock, string $directory): void
    {
        $this->assertSatisfies(PHP_VERSION, $metadata['php'], 'Runtime plugin PHP');

        if (($composer['name'] ?? null) !== $metadata['id']) {
            throw new InvalidArgumentException('Runtime plugin composer name must match manifest ID.');
        }

        $declaredPlugin = data_get($composer, 'extra.openkos.plugin');
        if ($declaredPlugin !== $metadata['entry_class']) {
            throw new InvalidArgumentException('Runtime plugin Composer metadata must match manifest entry class.');
        }

        if (! is_array($lock['packages'] ?? null)) {
            throw new InvalidArgumentException('Runtime plugin composer.lock must contain packages.');
        }

        $requires = is_array($composer['require'] ?? null) ? $composer['require'] : [];
        $lockedPackages = [];
        foreach ([...$lock['packages'], ...(is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : [])] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null)) {
                $lockedPackages[$package['name']] = true;
            }
        }

        $bundledPackages = $this->bundledPackages($directory.'/vendor/composer/installed.php');

        foreach (array_keys($bundledPackages) as $package) {
            if ($this->isSharedPackage($package)) {
                throw new InvalidArgumentException("Runtime plugin must not bundle host package [{$package}].");
            }
        }

        foreach ($requires as $package => $constraint) {
            if (! is_string($package) || ! is_string($constraint)) {
                throw new InvalidArgumentException('Runtime plugin Composer requirements must be valid strings.');
            }

            if ($package === 'php') {
                $this->assertSatisfies(PHP_VERSION, $constraint, 'PHP');

                continue;
            }

            if (str_starts_with($package, 'ext-')) {
                if (! extension_loaded(substr($package, 4))) {
                    throw new InvalidArgumentException("Required PHP extension [{$package}] is not installed.");
                }

                continue;
            }

            if ($this->isSharedPackage($package)) {
                if (! InstalledVersions::isInstalled($package)) {
                    throw new InvalidArgumentException("Host package [{$package}] is not installed.");
                }

                $this->assertSatisfies(
                    $this->installedPackageVersion($package),
                    $constraint,
                    "Host package [{$package}]",
                );

                continue;
            }

            if (! isset($lockedPackages[$package]) && ! InstalledVersions::isInstalled($package)) {
                throw new InvalidArgumentException(
                    "Runtime plugin dependency [{$package}] is absent from composer.lock and the host.",
                );
            }

            if (isset($bundledPackages[$package])) {
                $this->assertSatisfies(
                    $bundledPackages[$package],
                    $constraint,
                    "Bundled package [{$package}]",
                );
            }

            if (InstalledVersions::isInstalled($package)) {
                $this->assertSatisfies(
                    $this->installedPackageVersion($package),
                    $constraint,
                    "Host package [{$package}]",
                );
            } elseif (! isset($bundledPackages[$package])) {
                throw new InvalidArgumentException(
                    "Runtime plugin dependency [{$package}] is not bundled in vendor/.",
                );
            }
        }

        $this->assertNoComposerInstallScripts($composer);
    }

    /**
     * @return array<string, string>
     */
    private function bundledPackages(string $installedPath): array
    {
        if (! is_file($installedPath)) {
            throw new InvalidArgumentException('Runtime plugin vendor metadata is missing.');
        }

        $installed = require $installedPath;
        $versions = is_array($installed['versions'] ?? null) ? $installed['versions'] : $installed;

        if (! is_array($versions)) {
            throw new InvalidArgumentException('Runtime plugin vendor metadata is malformed.');
        }

        $packages = [];
        foreach ($versions as $package => $metadata) {
            if (! is_string($package) || ! is_array($metadata)) {
                throw new InvalidArgumentException('Runtime plugin vendor package metadata is malformed.');
            }

            if (! is_string($metadata['pretty_version'] ?? null)) {
                if (isset($metadata['provided']) || isset($metadata['replaced'])) {
                    continue;
                }

                throw new InvalidArgumentException('Runtime plugin vendor package metadata is malformed.');
            }

            $packages[$package] = $metadata['pretty_version'];
        }

        return $packages;
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    private function assertNoComposerInstallScripts(array $composer): void
    {
        $scripts = $composer['scripts'] ?? [];

        if (! is_array($scripts)) {
            throw new InvalidArgumentException('Runtime plugin Composer scripts must be an object.');
        }

        foreach (array_keys($scripts) as $script) {
            if (is_string($script) && preg_match('/^(pre|post)-(install|update)-cmd$/', $script)) {
                throw new InvalidArgumentException("Runtime plugin Composer script [{$script}] is not allowed.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path, string $label): array
    {
        if (! is_file($path) || is_link($path)) {
            throw new InvalidArgumentException("Runtime plugin artifact is missing [{$label}].");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Runtime plugin artifact [{$label}] cannot be read.");
        }

        try {
            $value = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("Runtime plugin artifact [{$label}] is malformed.", previous: $exception);
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("Runtime plugin artifact [{$label}] must contain an object.");
        }

        return $value;
    }

    private function validateTree(string $directory): void
    {
        $realDirectory = realpath($directory);
        if (! is_string($realDirectory)) {
            throw new InvalidArgumentException('Runtime plugin artifact directory cannot be resolved.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $entryCount = 0;
        $size = 0;

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = ltrim(str_replace($realDirectory, '', $path), DIRECTORY_SEPARATOR);
            $topLevel = explode(DIRECTORY_SEPARATOR, $relative, 2)[0] ?? '';

            if ($file->isLink() || is_link($path)) {
                throw new InvalidArgumentException("Runtime plugin artifact contains symlink [{$relative}].");
            }

            if (! $file->isDir() && ! $file->isFile()) {
                throw new InvalidArgumentException("Runtime plugin artifact contains unsafe filesystem node [{$relative}].");
            }

            $entryCount++;

            if ($topLevel === '' || ! in_array($topLevel, [
                'manifest.json',
                'composer.json',
                'composer.lock',
                'src',
                'vendor',
                'config',
                'routes',
                'database',
                'resources',
            ], true)) {
                throw new InvalidArgumentException("Runtime plugin artifact contains unexpected path [{$relative}].");
            }

            if ($file->isFile()) {
                $size += $file->getSize();

                if (($file->getPerms() & 0111) !== 0) {
                    throw new InvalidArgumentException("Runtime plugin artifact contains executable file [{$relative}].");
                }

                if (preg_match('/(^|\/)(install|post-install|pre-install)\.(php|sh|bash|bat|cmd|exe)$/i', $relative)) {
                    throw new InvalidArgumentException("Runtime plugin artifact contains an install script [{$relative}].");
                }
            }
        }

        if ($entryCount > (int) config('platform.runtime.max_files', 5000)) {
            throw new InvalidArgumentException('Runtime plugin artifact exceeds the maximum file count.');
        }

        if ($size > (int) config('platform.runtime.max_uncompressed_bytes', 268_435_456)) {
            throw new InvalidArgumentException('Runtime plugin artifact exceeds the maximum extracted size.');
        }
    }

    private function isSharedPackage(string $package): bool
    {
        return in_array($package, config('platform.runtime.shared_packages', []), true)
            || collect(config('platform.runtime.shared_package_prefixes', []))
                ->contains(fn (string $prefix): bool => str_starts_with($package, $prefix));
    }

    private function installedPackageVersion(string $package): string
    {
        $version = InstalledVersions::getPrettyVersion($package);

        if (is_string($version) && trim($version) !== '') {
            return $version;
        }

        if (str_starts_with($package, 'illuminate/')) {
            $frameworkVersion = InstalledVersions::getPrettyVersion('laravel/framework');

            if (is_string($frameworkVersion) && trim($frameworkVersion) !== '') {
                return $frameworkVersion;
            }
        }

        throw new InvalidArgumentException("Host package [{$package}] does not expose a concrete version.");
    }

    private function assertSatisfies(string $version, string $constraint, string $subject): void
    {
        try {
            $satisfies = Semver::satisfies($version, $constraint);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("{$subject} constraint [{$constraint}] is invalid.", previous: $exception);
        }

        if (! $satisfies) {
            throw new InvalidArgumentException("{$subject} version [{$version}] does not satisfy [{$constraint}].");
        }
    }
}
