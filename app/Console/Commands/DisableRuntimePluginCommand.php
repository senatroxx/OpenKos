<?php

namespace App\Console\Commands;

use App\Services\Platform\PluginInstaller;
use Illuminate\Console\Command;
use Throwable;

final class DisableRuntimePluginCommand extends Command
{
    protected $signature = 'plugin:disable {id : Runtime plugin ID}';

    protected $description = 'Disable an installed runtime plugin without deleting its files.';

    public function handle(PluginInstaller $installer): int
    {
        try {
            $installer->disable((string) $this->argument('id'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Runtime plugin [{$this->argument('id')}] disabled.");
        $this->warn('Restart FrankenPHP, queue workers, and the scheduler to apply the change.');

        return self::SUCCESS;
    }
}
