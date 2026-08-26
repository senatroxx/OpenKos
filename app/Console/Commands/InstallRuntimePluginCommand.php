<?php

namespace App\Console\Commands;

use App\Services\Platform\PluginInstaller;
use Illuminate\Console\Command;
use Throwable;

final class InstallRuntimePluginCommand extends Command
{
    protected $signature = 'plugin:install {zip : Path to a prepared plugin ZIP}';

    protected $description = 'Install or update a prepared runtime plugin ZIP.';

    public function handle(PluginInstaller $installer): int
    {
        try {
            $metadata = $installer->install((string) $this->argument('zip'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Runtime plugin [{$metadata['id']}] version {$metadata['version']} installed.");
        $this->warn('Restart FrankenPHP, queue workers, and the scheduler to apply the change.');

        return self::SUCCESS;
    }
}
