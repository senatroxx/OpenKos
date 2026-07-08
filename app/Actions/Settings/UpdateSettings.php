<?php

namespace App\Actions\Settings;

use App\Events\Settings\SettingsUpdated;
use App\Models\AuditLog;
use App\Repositories\SettingRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;

class UpdateSettings
{
    public function __construct(
        private SettingRepository $repository,
        private Dispatcher $events,
    ) {}

    public function execute(array $data, ?Authenticatable $actor = null): void
    {
        $result = $this->repository->update($data);

        $before = $this->maskSensitive($result->original);
        $after = $this->maskSensitive($result->values);

        AuditLog::record(
            auditable: null,
            operation: 'settings.update',
            before: $before,
            after: $after,
            actor: $actor,
        );

        $this->events->dispatch(new SettingsUpdated(
            group: $result->group,
            keys: array_keys($data),
            actorId: $actor?->getKey(),
        ));
    }

    private function maskSensitive(array $data): array
    {
        $sensitive = ['mail_password'];

        foreach ($data as $key => $value) {
            if (in_array($key, $sensitive, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (in_array($key, ['mail_config', 'whatsapp_config'], true) && is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    if (in_array($subKey, ['password', 'mail_password'], true)) {
                        $data[$key][$subKey] = '[REDACTED]';
                    }
                }
            }
        }

        return $data;
    }
}
