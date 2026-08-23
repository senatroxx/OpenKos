<?php

namespace App\Services;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ReferenceAllocationRetry
{
    private const MAX_ATTEMPTS = 3;

    /**
     * @var array<string, list<string>>
     */
    private const REFERENCE_CONSTRAINTS = [
        'leases' => [
            'leases_reference_unique',
            'leases.reference',
        ],
        'maintenance_tickets' => [
            'maintenance_tickets_reference_unique',
            'maintenance_tickets.reference',
        ],
    ];

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback, string $table): mixed
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction($callback);
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_ATTEMPTS || ! $this->isRetryable($exception, $table)) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Reference allocation retry loop exited unexpectedly.');
    }

    private function isRetryable(QueryException $exception, string $table): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $isUniqueViolation = match (DB::getDriverName()) {
            'pgsql' => (string) ($errorInfo[0] ?? $exception->getCode()) === '23505',
            'mysql' => (int) ($errorInfo[1] ?? 0) === 1062,
            'sqlite' => (int) ($errorInfo[1] ?? 0) === 19,
            default => false,
        };

        if (! $isUniqueViolation) {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        foreach (self::REFERENCE_CONSTRAINTS[$table] ?? [] as $constraint) {
            if (str_contains($message, $constraint)) {
                return true;
            }
        }

        return false;
    }
}
