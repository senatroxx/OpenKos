<?php

namespace App\Console\Commands;

use App\Services\Platform\PluginInstaller;
use Illuminate\Console\Command;
use Throwable;

final class RemoveRuntimePluginCommand extends Command
{
    protected $signature = 'plugin:remove {id : Runtime plugin ID} {--force : Do not ask for confirmation}';

    protected $description = 'Remove an installed runtime plugin without dropping its data.';

    public function handle(PluginInstaller $installer): int
    {
        if (! $this->option('force') && ! $this->confirm('Remove the runtime plugin package?')) {
            return self::SUCCESS;
        }

        try {
            $installer->remove((string) $this->argument('id'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Runtime plugin [{$this->argument('id')}] removed. Plugin data was not dropped.");
        $this->warn('Restart FrankenPHP, queue workers, and the scheduler to apply the change.');

        return self::SUCCESS;
    }
}
