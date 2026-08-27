<?php

namespace App\Console\Commands;

use App\Services\Platform\ComposerPluginDiscovery;
use App\Services\Platform\RuntimePluginArtifactValidator;
use App\Services\Platform\RuntimePluginDiscovery;
use App\Services\Platform\RuntimePluginStore;
use Illuminate\Console\Command;
use Throwable;

final class ListRuntimePluginsCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List installed runtime plugins and their state.';

    public function handle(
        RuntimePluginStore $store,
        RuntimePluginArtifactValidator $validator,
        RuntimePluginDiscovery $discovery,
        ComposerPluginDiscovery $composer,
    ): int {
        try {
            $rows = $store->withLock(function (RuntimePluginStore $store) use ($validator, $discovery, $composer): array {
                $state = $store->readState();
                $packages = $store->installedPackages();
                $existingClasses = array_values(array_filter(config('platform.plugins', []), 'is_string'));

                try {
                    $existingClasses = [...$existingClasses, ...$composer->discoverComposerOnly()];
                } catch (Throwable $exception) {
                    report($exception);
                }

                $conflictingIds = $discovery->conflictingIds($packages, $state, $existingClasses);
                $rows = [];

                foreach ($packages as $id => $path) {
                    $enabled = $state[$id]['enabled'] ?? false;

                    if (in_array($id, $conflictingIds, true)) {
                        try {
                            $version = $validator->inspectStaticMetadata($path, $id)['version'];
                        } catch (Throwable) {
                            $version = 'unknown';
                        }

                        $rows[] = [$id, $version, 'Conflict'];

                        continue;
                    }

                    try {
                        $metadata = $enabled
                            ? $validator->validate($path, $id)
                            : $validator->inspectStaticMetadata($path, $id);
                        $rows[] = [$id, $metadata['version'], $enabled ? 'Enabled' : 'Disabled'];
                    } catch (Throwable $exception) {
                        $rows[] = [$id, 'unknown', $enabled ? 'Invalid (enabled)' : 'Invalid (disabled)'];
                        report($exception);
                    }
                }

                foreach (array_diff_key($state, $store->installedPackages()) as $id => $entry) {
                    $rows[] = [$id, 'missing', $entry['enabled'] ? 'Missing (enabled)' : 'Missing (disabled)'];
                }

                return $rows;
            }, false);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->info('No runtime plugins are installed.');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Version', 'State'], $rows);

        return self::SUCCESS;
    }
}
