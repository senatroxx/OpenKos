<?php

namespace App\Services\Platform;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenKOS\Platform\Plugin\Plugin;
use Throwable;

class PluginManagementService
{
    public function __construct(
        private RuntimePluginStore $store,
        private RuntimePluginArtifactValidator $validator,
        private ComposerPluginDiscovery $composer,
        private PluginInstaller $installer,
        private RuntimePluginGraphValidator $graph,
    ) {}

    /**
     * @return array{plugins: array<int, array<string, mixed>>, error: string|null}
     */
    public function catalog(): array
    {
        $legacy = $this->legacyPlugins();
        $runtime = [];
        $error = null;

        try {
            $runtime = $this->runtimePlugins();
        } catch (Throwable $exception) {
            report($exception);
            $error = __('Runtime plugin state could not be read. Check the application logs for details.');
        }

        $this->markConflicts($runtime, $legacy);

        return [
            'plugins' => [...$legacy, ...$runtime],
            'error' => $error,
        ];
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
    public function install(UploadedFile $file): array
    {
        $disk = Storage::disk('local');
        $path = $file->storeAs('plugin-uploads', Str::uuid()->toString().'.zip', 'local');

        if (! is_string($path)) {
            throw new \RuntimeException('Plugin upload could not be stored.');
        }

        try {
            return $this->installer->install($disk->path($path));
        } finally {
            $disk->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(string $id): array
    {
        return $this->installer->enable($id);
    }

    public function disable(string $id): void
    {
        $this->installer->disable($id);
    }

    public function remove(string $id): void
    {
        $this->installer->remove($id);
    }

    public function userMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if ($exception instanceof RuntimePluginDependencyException) {
            return __($exception->getMessage());
        }

        if ($exception instanceof RuntimePluginConflictException || str_contains($message, 'conflict')) {
            return __('This plugin conflicts with an explicit or Composer-installed plugin. Disable or remove the runtime copy.');
        }

        if (
            str_contains($message, 'constraint')
            || str_contains($message, 'dependency')
            || str_contains($message, 'php version')
            || str_contains($message, 'not installed')
            || str_contains($message, 'absent')
            || str_contains($message, 'not bundled')
        ) {
            return __('This plugin is not compatible with the current OpenKOS or PHP installation.');
        }

        return __('The plugin artifact was rejected. Check that it is a valid prepared runtime plugin ZIP.');
    }

    /** @return array<int, array<string, mixed>> */
    private function runtimePlugins(): array
    {
        $state = $this->store->readState();
        $packages = $this->store->installedPackages();
        $runtime = [];
        $rows = [];

        foreach ($packages as $id => $path) {
            $enabled = $state[$id]['enabled'] ?? false;

            try {
                $runtime[$id] = [
                    'metadata' => $this->validator->validateInFreshProcess($path, $id),
                    'enabled' => $enabled,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $runtime[$id] = [
                    'metadata' => null,
                    'enabled' => $enabled,
                    'status' => $this->validationStatus($exception),
                    'error' => $this->validationError($exception),
                ];
            }
        }

        $health = $this->graph->validate($runtime, $this->hostPluginClasses());

        foreach ($packages as $id => $path) {
            $entry = $runtime[$id];
            $enabled = $entry['enabled'];

            if (is_array($entry['metadata'])) {
                $metadata = $entry['metadata'];
                $issue = $health['issues'][$id] ?? null;

                if ($issue !== null) {
                    $rows[] = [
                        ...$this->runtimeRow($metadata, $enabled),
                        'status' => $issue['status'],
                        'error' => __($issue['error']),
                        'can_enable' => false,
                        'can_disable' => $enabled,
                        'can_remove' => true,
                    ];

                    continue;
                }

                $rows[] = $this->runtimeRow($metadata, $enabled);

                continue;
            }

            $status = $entry['status'];
            $rows[] = [
                ...$this->manifestPreview($path, $id),
                'source' => 'runtime',
                'status' => $status,
                'enabled' => $enabled,
                'error' => $entry['error'],
                'can_enable' => false,
                'can_disable' => $enabled,
                'can_remove' => true,
            ];
        }

        foreach (array_diff_key($state, $packages) as $id => $entry) {
            $rows[] = [
                'id' => $id,
                'managed_id' => $id,
                'declared_id' => null,
                'name' => $id,
                'version' => null,
                'description' => '',
                'entry_class' => null,
                'core_version' => null,
                'php' => null,
                'dependencies' => [],
                'source' => 'runtime',
                'status' => 'missing',
                'enabled' => $entry['enabled'],
                'error' => __('Plugin state exists without an installed package. Remove the stale state entry.'),
                'can_enable' => false,
                'can_disable' => $entry['enabled'],
                'can_remove' => true,
            ];
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyPlugins(): array
    {
        $configured = array_values(array_filter(config('platform.plugins', []), 'is_string'));
        $discovered = [];

        try {
            $discovered = $this->composer->discoverComposerOnly();
        } catch (Throwable $exception) {
            report($exception);
        }

        $rows = [];

        foreach (array_values(array_unique([...$configured, ...$discovered])) as $class) {
            try {
                if (! class_exists($class) || ! is_a($class, Plugin::class, true)) {
                    throw new \InvalidArgumentException('Plugin class is invalid.');
                }

                $manifest = app()->make($class)->manifest();
                $rows[] = [
                    'id' => $manifest->id,
                    'managed_id' => $manifest->id,
                    'declared_id' => $manifest->id,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'description' => $manifest->description,
                    'entry_class' => $class,
                    'core_version' => $manifest->coreVersion,
                    'php' => null,
                    'dependencies' => $manifest->dependencies,
                    'source' => in_array($class, $configured, true) ? 'explicit' : 'composer',
                    'status' => 'legacy',
                    'enabled' => true,
                    'error' => null,
                    'can_enable' => false,
                    'can_disable' => false,
                    'can_remove' => false,
                ];
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $metadata */
    private function runtimeRow(array $metadata, bool $enabled): array
    {
        return [
            ...$metadata,
            'managed_id' => $metadata['id'],
            'declared_id' => $metadata['id'],
            'source' => 'runtime',
            'status' => $enabled ? 'enabled' : 'disabled',
            'enabled' => $enabled,
            'error' => null,
            'can_enable' => ! $enabled,
            'can_disable' => $enabled,
            'can_remove' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function manifestPreview(string $path, string $id): array
    {
        $preview = [
            'id' => $id,
            'managed_id' => $id,
            'declared_id' => null,
            'name' => $id,
            'version' => null,
            'description' => '',
            'entry_class' => null,
            'core_version' => null,
            'php' => null,
            'dependencies' => [],
        ];
        $manifestPath = $path.'/manifest.json';

        if (! is_file($manifestPath) || is_link($manifestPath)) {
            return $preview;
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            return $preview;
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $preview;
        }

        if (! is_array($manifest)) {
            return $preview;
        }

        foreach (['id', 'name', 'version', 'description', 'entry_class', 'core_version', 'php'] as $key) {
            if (is_string($manifest[$key] ?? null) && $manifest[$key] !== '') {
                if ($key === 'id') {
                    $preview['declared_id'] = $manifest[$key];
                } else {
                    $preview[$key] = $manifest[$key];
                }
            }
        }

        if (is_array($manifest['dependencies'] ?? null)) {
            $preview['dependencies'] = array_values(array_filter($manifest['dependencies'], 'is_string'));
        }

        return $preview;
    }

    private function validationStatus(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'constraint')
            || str_contains($message, 'dependency')
            || str_contains($message, 'php version')
            || str_contains($message, 'not installed')
            || str_contains($message, 'absent')
            || str_contains($message, 'not bundled')
            ? 'incompatible'
            : 'broken';
    }

    private function validationError(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'does not match installed package')) {
            return __('Manifest plugin ID does not match installed package identity.');
        }

        return $this->validationStatus($exception) === 'incompatible'
            ? __('This plugin is not compatible with the current OpenKOS or PHP installation.')
            : __('This plugin is incomplete or failed validation. Disable or remove it, then install a valid artifact.');
    }

    /** @return array<int, string> */
    private function hostPluginClasses(): array
    {
        $classes = array_values(array_filter(config('platform.plugins', []), 'is_string'));

        try {
            $classes = [...$classes, ...$this->composer->discoverComposerOnly()];
        } catch (Throwable $exception) {
            report($exception);
        }

        return array_values(array_unique(array_filter($classes, 'is_string')));
    }

    /** @param array<int, array<string, mixed>> $runtime @param array<int, array<string, mixed>> $legacy */
    private function markConflicts(array &$runtime, array &$legacy): void
    {
        $legacyIds = array_count_values(array_column($legacy, 'id'));
        $legacyClasses = array_column($legacy, 'entry_class');

        foreach ($legacy as &$plugin) {
            if (($legacyIds[$plugin['id']] ?? 0) > 1) {
                $this->markConflict($plugin, __('Multiple Composer or explicit plugins declare the same identity.'));
            }
        }
        unset($plugin);

        foreach ($runtime as &$plugin) {
            if (in_array($plugin['status'], ['broken', 'incompatible', 'missing'], true)) {
                continue;
            }

            if (in_array($plugin['id'], array_column($legacy, 'id'), true) || in_array($plugin['entry_class'], $legacyClasses, true)) {
                $this->markConflict($plugin, __('A Composer or explicit plugin has the same identity; this runtime copy is not loaded.'));

                foreach ($legacy as &$legacyPlugin) {
                    if ($legacyPlugin['id'] === $plugin['id'] || $legacyPlugin['entry_class'] === $plugin['entry_class']) {
                        $this->markConflict($legacyPlugin, __('A runtime plugin has the same identity; the Composer or explicit copy takes precedence.'));
                    }
                }
                unset($legacyPlugin);
            }
        }
        unset($plugin);
    }

    /** @param array<string, mixed> $plugin */
    private function markConflict(array &$plugin, string $message): void
    {
        $plugin['status'] = 'conflict';
        $plugin['error'] = $message;
        $plugin['can_enable'] = false;
        $plugin['can_disable'] = $plugin['source'] === 'runtime' && $plugin['enabled'];
        $plugin['can_remove'] = $plugin['source'] === 'runtime';
    }
}
