<?php

namespace App\Console\Commands;

use App\Services\Platform\RuntimePluginArtifactValidator;
use App\Services\Platform\RuntimePluginStore;
use Illuminate\Console\Command;
use Throwable;

final class ListRuntimePluginsCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List installed runtime plugins and their state.';

    public function handle(RuntimePluginStore $store, RuntimePluginArtifactValidator $validator): int
    {
        try {
            $rows = $store->withLock(function (RuntimePluginStore $store) use ($validator): array {
                $state = $store->readState();
                $rows = [];

                foreach ($store->installedPackages() as $id => $path) {
                    $enabled = $state[$id]['enabled'] ?? false;

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
            });
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
