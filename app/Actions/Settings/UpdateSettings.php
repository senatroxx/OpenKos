<?php

namespace App\Actions\Settings;

use App\Events\Settings\SettingsUpdated;
use App\Models\AuditLog;
use App\Models\Setting;
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

        AuditLog::record(
            auditable: Setting::get(),
            operation: 'update',
            before: $result->original,
            after: $result->values,
            actor: $actor,
        );

        $this->events->dispatch(new SettingsUpdated(
            group: $result->group,
            keys: array_keys($data),
            actorId: $actor?->getKey(),
        ));
    }
}
