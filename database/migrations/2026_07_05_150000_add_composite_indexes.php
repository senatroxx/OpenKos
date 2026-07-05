<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // payments: covers dashboard rent stats (whereNotIn status),
        // overview finance (where status = confirmed), lease overdue checks
        // period_start is excluded — whereMonth/whereYear wraps it in EXTRACT()
        // which a B-tree index can't use. The queries now use range conditions.
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paymentable_type', 'paymentable_id', 'status'], 'idx_payments_type_id_status');

            // The morphs() call created (paymentable_type, paymentable_id) —
            // the 3-column index above is a superset, so drop the morphs one.
            $table->dropIndex(['paymentable_type', 'paymentable_id']);
        });

        // leases: covers dashboard base query where(status = 'active', start_date <= now)
        // and the overdue lease check in LeaseController::globalIndex
        Schema::table('leases', function (Blueprint $table) {
            $table->index(['status', 'start_date'], 'idx_leases_status_start_date');
        });

        // maintenance_tickets: covers property-scoped ticket filtering
        // and unit-scoped maintenance history lookups
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->index(['property_id', 'status'], 'idx_maintenance_tickets_property_status');
            $table->index(['unit_id', 'status'], 'idx_maintenance_tickets_unit_status');
        });

        // units: covers unit availability listings (property-scoped status filter
        // used in scopeAvailableForAssignment and property unit lists)
        Schema::table('units', function (Blueprint $table) {
            $table->index(['property_id', 'status'], 'idx_units_property_status');
        });

        // lease_unit_histories: covers maintenance transfer lookups
        // filter by from_unit_id + reason, ordered by effective_date
        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->index(['from_unit_id', 'reason', 'effective_date'], 'idx_luh_from_reason_date');
        });

        // unit_rates: covers activeRates() relation filter
        Schema::table('unit_rates', function (Blueprint $table) {
            $table->index(['unit_id', 'is_active', 'billing_interval'], 'idx_unit_rates_unit_active_interval');
        });

        // properties: covers admin property dropdowns filtered by is_active
        Schema::table('properties', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'idx_properties_active_name');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_type_id_status');

            // Re-create the morphs index that was dropped
            $table->index(['paymentable_type', 'paymentable_id']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex('idx_leases_status_start_date');
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropIndex('idx_maintenance_tickets_property_status');
            $table->dropIndex('idx_maintenance_tickets_unit_status');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex('idx_units_property_status');
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropIndex('idx_luh_from_reason_date');
        });

        Schema::table('unit_rates', function (Blueprint $table) {
            $table->dropIndex('idx_unit_rates_unit_active_interval');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('idx_properties_active_name');
        });
    }
};
