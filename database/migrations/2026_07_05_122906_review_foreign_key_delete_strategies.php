<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // leases.unit_id — CASCADE → RESTRICT
        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });

        // lease_tenant.lease_id — CASCADE → RESTRICT
        Schema::table('lease_tenant', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->restrictOnDelete();
        });

        // lease_tenant.tenant_id — CASCADE → RESTRICT
        Schema::table('lease_tenant', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        // lease_unit_histories.* — CASCADE → RESTRICT (3 FKs)
        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->restrictOnDelete();
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['from_unit_id']);
            $table->foreign('from_unit_id')->references('id')->on('units')->restrictOnDelete();
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['to_unit_id']);
            $table->foreign('to_unit_id')->references('id')->on('units')->restrictOnDelete();
        });

        // reminder_logs.lease_id — CASCADE → RESTRICT
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->restrictOnDelete();
        });

        // tenant_documents.tenant_id — CASCADE → RESTRICT
        Schema::table('tenant_documents', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        // unit_rates.unit_id — CASCADE → RESTRICT
        Schema::table('unit_rates', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });

        // maintenance_tickets.property_id — CASCADE → RESTRICT
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });

        // maintenance_tickets.unit_id — CASCADE → SET NULL
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });

        // units.property_id — CASCADE → RESTRICT
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });

        // cities.region_id — CASCADE → RESTRICT
        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Restore original cascadeOnDelete for all 13 FKs

        Schema::table('leases', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        Schema::table('lease_tenant', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();
        });

        Schema::table('lease_tenant', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['from_unit_id']);
            $table->foreign('from_unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        Schema::table('lease_unit_histories', function (Blueprint $table) {
            $table->dropForeign(['to_unit_id']);
            $table->foreign('to_unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropForeign(['lease_id']);
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();
        });

        Schema::table('tenant_documents', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('unit_rates', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
        });
    }
};
