<?php

use Illuminate\Support\Facades\DB;

/**
 * Expected composite indexes for each table.
 *
 * Map of [table => [index_name => [columns...]]].
 * If a migration changes an index without updating this test, it will fail.
 */
$expected = [
    'payments' => [
        'idx_payments_type_id_status' => ['paymentable_type', 'paymentable_id', 'status'],
    ],
    'leases' => [
        'idx_leases_status_start_date' => ['status', 'start_date'],
    ],
    'maintenance_tickets' => [
        'idx_maintenance_tickets_property_status' => ['property_id', 'status'],
        'idx_maintenance_tickets_unit_status' => ['unit_id', 'status'],
    ],
    'units' => [
        'idx_units_property_status' => ['property_id', 'status'],
    ],
    'lease_unit_histories' => [
        'idx_luh_from_reason_date' => ['from_unit_id', 'reason', 'effective_date'],
    ],
    'unit_rates' => [
        'idx_unit_rates_unit_active_interval' => ['unit_id', 'is_active', 'billing_interval'],
    ],
    'properties' => [
        'idx_properties_active_name' => ['is_active', 'name'],
    ],
];

$appTables = array_keys($expected);

it('every expected composite index exists with correct columns', function () use ($expected, $appTables) {
    $driver = DB::getDriverName();
    $actual = $driver === 'sqlite' ? loadIndexesSqlite($appTables) : loadIndexesPgsql($appTables);

    foreach ($expected as $table => $indexes) {
        foreach ($indexes as $indexName => $expectedColumns) {
            expect(isset($actual[$table][$indexName]))
                ->toBeTrue("Missing index {$indexName} on {$table}");

            $actualColumns = $actual[$table][$indexName];
            expect($actualColumns)
                ->toBe($expectedColumns, "{$table}.{$indexName}: expected columns [".implode(', ', $expectedColumns).'], got ['.implode(', ', $actualColumns).']');
        }
    }

    foreach ($actual as $table => $indexes) {
        expect(isset($expected[$table]))->toBeTrue("Unexpected index entry for table: {$table}");
    }
});

function loadIndexesSqlite(array $tables): array
{
    $indexes = [];

    foreach ($tables as $table) {
        $rows = DB::select("PRAGMA index_list({$table})");
        foreach ($rows as $row) {
            $name = $row->name;

            // Skip auto-generated indexes (unique, primary)
            if (str_starts_with($name, 'sqlite_autoindex')) {
                continue;
            }

            $info = DB::select("PRAGMA index_info({$name})");
            $columns = array_column($info, 'name');
            $indexes[$table][$name] = $columns;
        }
    }

    return $indexes;
}

function loadIndexesPgsql(array $tables): array
{
    $placeholders = implode(', ', array_fill(0, count($tables), '?'));
    $rows = DB::select("
        SELECT
            i.relname AS index_name,
            t.relname AS table_name,
            a.attname AS column_name,
            i.indisunique,
            i.indisprimary
        FROM pg_index idx
        JOIN pg_class i ON i.oid = idx.indexrelid
        JOIN pg_class t ON t.oid = idx.indrelid
        JOIN pg_attribute a ON a.attrelid = idx.indrelid
            AND a.attnum = ANY(idx.indkey)
        WHERE t.relname IN ({$placeholders})
            AND NOT i.indisunique
            AND NOT i.indisprimary
        ORDER BY i.relname, a.attnum
    ", $tables);

    $indexes = [];
    foreach ($rows as $row) {
        $indexes[$row->table_name][$row->index_name][] = $row->column_name;
    }

    return $indexes;
}
