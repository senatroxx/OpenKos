<?php

namespace App\Installation;

enum InstallationState: string
{
    case Welcome = 'welcome';
    case Requirements = 'requirements';
    case Database = 'database';
    case Installing = 'installing';
    case Admin = 'admin';
    case Organization = 'organization';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::Requirements => 'System Requirements',
            self::Database => 'Database Configuration',
            self::Installing => 'Installation',
            self::Admin => 'Administrator Setup',
            self::Organization => 'Organization Setup',
            self::Completed => 'Complete',
        };
    }

    public static function all(): array
    {
        return self::cases();
    }
}
