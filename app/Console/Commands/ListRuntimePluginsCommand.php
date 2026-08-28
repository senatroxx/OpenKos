<?php

namespace App\Console\Commands;

use App\Services\Platform\PluginManagementService;
use Illuminate\Console\Command;

final class ListRuntimePluginsCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List installed runtime plugins and their state.';

    public function handle(PluginManagementService $management): int
    {
        $catalog = $management->catalog();

        if ($catalog['error'] !== null) {
            $this->error($catalog['error']);

            return self::FAILURE;
        }

        $rows = [];

        foreach ($catalog['plugins'] as $plugin) {
            if (($plugin['source'] ?? null) !== 'runtime') {
                continue;
            }

            $rows[] = [
                $plugin['managed_id'] ?? $plugin['id'],
                $plugin['version'] ?? (($plugin['status'] ?? null) === 'missing_package' ? 'missing' : 'unknown'),
                $this->stateLabel($plugin),
            ];
        }

        if ($rows === []) {
            $this->info('No runtime plugins are installed.');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Version', 'State'], $rows);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $plugin */
    private function stateLabel(array $plugin): string
    {
        return match ($plugin['status'] ?? null) {
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
            'conflict' => 'Conflict',
            'incompatible' => 'Incompatible',
            'broken' => ($plugin['enabled'] ?? false) ? 'Invalid (enabled)' : 'Invalid (disabled)',
            'missing_package' => ($plugin['enabled'] ?? false) ? 'Missing (enabled)' : 'Missing (disabled)',
            'orphaned_state' => 'Orphaned state',
            'orphaned_runtime_artifact' => 'Orphaned artifact',
            'pending_recovery' => 'Pending recovery',
            'unrecoverable_recovery' => 'Unrecoverable recovery',
            default => ucfirst(str_replace('_', ' ', (string) ($plugin['status'] ?? 'Unknown'))),
        };
    }
}
