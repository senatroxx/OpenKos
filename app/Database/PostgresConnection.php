<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Laravel's Connection::prepareBindings() converts PHP booleans to integers
 * (1/0). Postgres is strictly typed and rejects an integer against a boolean
 * column ("operator does not exist: boolean = integer" / "column is of type
 * boolean but expression is of type integer") — MySQL and SQLite silently
 * coerce, which is why the test suite (sqlite) never sees it.
 *
 * Binding the literals 'true'/'false' instead lets Postgres coerce them to
 * booleans, so `where('is_active', true)` and `['is_active' => true]` work
 * everywhere without DB::raw. Converting before parent runs means parent's
 * is_bool() branch never fires while its DateTime/object handling is kept.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
