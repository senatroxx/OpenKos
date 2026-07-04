<?php

namespace OpenKOS\Platform\Console;

use Illuminate\Console\Command;
use OpenKOS\Platform\Permission\PermissionRegistry;
use Spatie\Permission\Models\Permission;

/**
 * Persists permissions declared by enabled plugins into the Spatie
 * permissions table. Run after enabling a plugin, alongside `migrate`.
 */
class SyncPluginPermissionsCommand extends Command
{
    protected $signature = 'platform:permissions:sync';

    protected $description = 'Create permissions declared by enabled plugins.';

    public function handle(PermissionRegistry $registry): int
    {
        $guard = config('auth.defaults.guard', 'web');

        foreach (array_keys($registry->all()) as $name) {
            Permission::findOrCreate($name, $guard);
        }

        $this->info(count($registry->all()).' plugin permission(s) synced.');

        return self::SUCCESS;
    }
}
