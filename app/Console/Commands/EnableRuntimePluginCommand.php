<?php

namespace App\Console\Commands;

use App\Services\Platform\PluginInstaller;
use Illuminate\Console\Command;
use Throwable;

final class EnableRuntimePluginCommand extends Command
{
    protected $signature = 'plugin:enable {id : Runtime plugin ID}';

    protected $description = 'Validate and enable an installed runtime plugin.';

    public function handle(PluginInstaller $installer): int
    {
        try {
            $metadata = $installer->enable((string) $this->argument('id'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Runtime plugin [{$metadata['id']}] enabled.");
        $this->warn('Restart FrankenPHP, queue workers, and the scheduler to apply the change.');

        return self::SUCCESS;
    }
}
