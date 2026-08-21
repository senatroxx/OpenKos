<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->decimal('amount', 20, 3)->change();
            $table->char('currency', 3)->nullable()->after('amount');
        });

        Schema::table('leases', function (Blueprint $table): void {
            $table->decimal('rent_amount', 20, 3)->nullable()->change();
            $table->decimal('deposit_amount', 20, 3)->default(0)->change();
            $table->decimal('deposit_refund_amount', 20, 3)->nullable()->change();
            $table->char('currency', 3)->nullable()->after('rent_amount');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('total', 20, 3)->change();
            $table->decimal('amount_paid', 20, 3)->default(0)->change();
            $table->char('currency', 3)->nullable()->after('amount_paid');
        });

        Schema::table('invoice_line_items', function (Blueprint $table): void {
            $table->decimal('amount', 20, 3)->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('amount', 20, 3)->change();
            $table->char('currency', 3)->nullable()->after('amount');
        });

        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->decimal('amount', 20, 3)->change();
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->decimal('amount', 20, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('payment_allocations', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('currency');
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('invoice_line_items', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('currency');
            $table->decimal('total', 12, 2)->change();
            $table->decimal('amount_paid', 12, 2)->default(0)->change();
        });

        Schema::table('leases', function (Blueprint $table): void {
            $table->dropColumn('currency');
            $table->decimal('rent_amount', 12, 2)->change();
            $table->decimal('deposit_amount', 12, 2)->change();
            $table->decimal('deposit_refund_amount', 12, 2)->change();
        });

        Schema::table('unit_rates', function (Blueprint $table): void {
            $table->dropColumn('currency');
            $table->decimal('amount', 12, 2)->change();
        });
    }
};
