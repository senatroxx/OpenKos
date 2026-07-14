<?php

namespace App\Enums\Installation;

enum InstallationState: string
{
    case Welcome = 'welcome';
    case Requirements = 'requirements';
    case Database = 'database';
    case Application = 'application';
    case Admin = 'admin';
    case Organization = 'organization';
    case Notifications = 'notifications';
    case Installing = 'installing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::Requirements => 'System Requirements',
            self::Database => 'Database Configuration',
            self::Application => 'Application Settings',
            self::Admin => 'Administrator',
            self::Organization => 'Organization',
            self::Notifications => 'Notifications',
            self::Installing => 'Installation',
            self::Completed => 'Complete',
        };
    }

    public static function all(): array
    {
        return self::cases();
    }
}
