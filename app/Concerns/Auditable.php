<?php

namespace App\Concerns;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected bool $auditRestoring = false;

    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->recordAudit('create'));
        static::updated(fn (self $model) => $model->recordAudit('update'));
        static::deleted(fn (self $model) => $model->recordAudit('delete'));
        static::restoring(function (self $model) {
            $model->auditRestoring = true;
        });
        static::restored(function (self $model) {
            $model->recordAudit('restore');
        });
    }

    public function recordAudit(string $operation): void
    {
        if ($operation === 'update' && $this->auditRestoring) {
            $this->auditRestoring = false;

            return;
        }

        if ($operation === 'update' && ! $this->isDirty()) {
            return;
        }

        $before = match ($operation) {
            'create' => null,
            'update' => $this->auditableSnapshot($this->getDirty(), $this->getOriginal()),
            'delete' => $this->auditableSnapshot($this->getAttributes()),
            'restore' => null,
        };

        $after = match ($operation) {
            'create' => $this->auditableSnapshot($this->getAttributes()),
            'update' => $this->auditableSnapshot($this->getDirty(), $this->getAttributes()),
            'delete' => null,
            'restore' => $this->auditableSnapshot($this->getAttributes()),
        };

        AuditLog::record(
            auditable: $this,
            operation: $operation,
            before: $before,
            after: $after,
            actor: $this->resolveAuditActor(),
            source: $this->resolveAuditSource(),
        );
    }

    protected function auditableSnapshot(array $fields, ?array $source = null): ?array
    {
        $allowed = $this->getAuditableFields();
        $except = $this->getAuditableExcept();
        $mask = $this->getAuditableMask();

        $keys = $source ? array_intersect(array_keys($fields), $allowed) : $allowed;
        $values = $source
            ? array_intersect_key($source, array_flip($keys))
            : array_intersect_key($fields, array_flip($keys));

        if ($except) {
            $values = array_diff_key($values, array_flip($except));
        }

        if (empty($values)) {
            return null;
        }

        foreach ($mask as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '***MASKED***';
            }
        }

        return $values;
    }

    protected function getAuditableFields(): array
    {
        if (property_exists($this, 'auditable')) {
            return $this->auditable;
        }

        return $this->getFillable();
    }

    protected function getAuditableExcept(): array
    {
        return property_exists($this, 'auditableExcept') ? $this->auditableExcept : [];
    }

    protected function getAuditableMask(): array
    {
        return property_exists($this, 'auditableMask') ? $this->auditableMask : [];
    }

    protected function resolveAuditActor(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    protected function resolveAuditSource(): string
    {
        if (app()->runningInConsole()) {
            return 'CLI';
        }

        return 'UI';
    }
}
