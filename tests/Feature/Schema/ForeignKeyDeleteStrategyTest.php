<?php

use Illuminate\Support\Facades\DB;

/**
 * Expected delete strategy for each foreign key.
 *
 * Map of [table][column] => strategy.
 * All app foreign keys are enumerated here.
 * If a migration changes an FK without updating this test, it will fail.
 */
$expected = [
    // RESTRICT — historical business records, block parent force-delete
    'leases' => ['unit_id' => 'RESTRICT'],
    'lease_tenant' => ['lease_id' => 'RESTRICT', 'tenant_id' => 'RESTRICT'],
    'lease_unit_histories' => ['lease_id' => 'RESTRICT', 'from_unit_id' => 'RESTRICT', 'to_unit_id' => 'RESTRICT'],
    'reminder_logs' => ['lease_id' => 'RESTRICT'],
    'tenant_documents' => ['tenant_id' => 'RESTRICT'],
    'unit_rates' => ['unit_id' => 'RESTRICT'],
    'maintenance_tickets' => ['property_id' => 'RESTRICT'],
    'units' => ['property_id' => 'RESTRICT'],
    'cities' => ['region_id' => 'RESTRICT'],

    // SET NULL — nullable audit/user references, record survives
    'lease_unit_histories' => ['transferred_by' => 'SET NULL'],
    'leases' => ['primary_tenant_id' => 'SET NULL', 'unit_rate_id' => 'SET NULL', 'previous_lease_id' => 'SET NULL'],
    'maintenance_tickets' => ['unit_id' => 'SET NULL', 'assigned_to' => 'SET NULL', 'created_by' => 'SET NULL'],
    'payments' => ['confirmed_by' => 'SET NULL', 'recorded_by' => 'SET NULL', 'verified_by' => 'SET NULL'],
    'properties' => ['region_id' => 'SET NULL', 'city_id' => 'SET NULL'],
    'tenants' => ['user_id' => 'SET NULL'],

    // CASCADE — auth, framework, pivot, dependent content
    'passkeys' => ['user_id' => 'CASCADE'],
    'model_has_permissions' => ['permission_id' => 'CASCADE'],
    'model_has_roles' => ['role_id' => 'CASCADE'],
    'role_has_permissions' => ['permission_id' => 'CASCADE', 'role_id' => 'CASCADE'],
    'property_user' => ['user_id' => 'CASCADE', 'property_id' => 'CASCADE'],
    'payment_proofs' => ['payment_id' => 'CASCADE'],
];

$appTables = array_keys($expected);

it('every foreign key matches its declared delete strategy', function () use ($expected, $appTables) {
    $driver = DB::getDriverName();
    $actual = $driver === 'sqlite' ? loadFkSqlite($appTables) : loadFkPgsql($appTables);

    foreach ($expected as $table => $columns) {
        foreach ($columns as $column => $expectedStrategy) {
            $actualStrategy = $actual[$table][$column] ?? null;
            expect($actualStrategy)
                ->toBe($expectedStrategy, "{$table}.{$column}: expected {$expectedStrategy}, got {$actualStrategy}");
        }
    }
});

/**
 * Load FK rules from SQLite via PRAGMA.
 */
function loadFkSqlite(array $tables): array
{
    $fk = [];
    foreach ($tables as $table) {
        $rows = DB::select("PRAGMA foreign_key_list({$table})");
        foreach ($rows as $row) {
            $fk[$table][$row->from] = strtoupper($row->on_delete);
        }
    }

    return $fk;
}

/**
 * Load FK rules from PostgreSQL via information_schema.
 */
function loadFkPgsql(array $tables): array
{
    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $rows = DB::select("
        SELECT
            tc.table_name,
            kcu.column_name,
            rc.delete_rule
        FROM information_schema.table_constraints tc
        JOIN information_schema.key_column_usage kcu
            ON tc.constraint_name = kcu.constraint_name
            AND tc.table_schema = kcu.table_schema
        JOIN information_schema.referential_constraints rc
            ON tc.constraint_name = rc.constraint_name
            AND tc.table_schema = rc.constraint_schema
        WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public'
            AND tc.table_name IN ({$placeholders})
        ORDER BY tc.table_name, kcu.column_name
    ", $tables);

    $fk = [];
    foreach ($rows as $row) {
        $fk[$row->table_name][$row->column_name] = $row->delete_rule;
    }

    return $fk;
}
