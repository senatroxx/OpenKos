<?php

namespace App\Services\Listings;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PublicSlugAllocator
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback): mixed
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction($callback, attempts: 5);
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception) || $attempt === 5) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Public slug transaction did not complete.');
    }

    public function allocate(Builder $query, string $name, string $fallback): string
    {
        $base = Str::slug($name) ?: $fallback;
        $counter = 0;

        while (true) {
            $slug = $counter === 0 ? $base : $base.'-'.$counter;

            if (! (clone $query)->where('public_slug', $slug)->exists()) {
                return $slug;
            }

            $counter++;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && (str_contains($message, 'unique')
                || str_contains($message, 'duplicate'));
    }
}
