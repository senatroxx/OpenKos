<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->date('statement_date')->nullable();
            $table->string('status')->default('uploaded');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_statement_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->integer('row_number');
            $table->date('transaction_date')->nullable();
            $table->text('description')->nullable();
            $table->string('unit_number')->nullable();
            $table->string('normalized_unit_number')->nullable();
            $table->string('payer_name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->foreignId('matched_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('matched_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('match_status')->default('unmatched');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->date('month');
            $table->decimal('expected_rent', 15, 2)->default(0);
            $table->decimal('water_bill', 15, 2)->default(0);
            $table->decimal('waste_bill', 15, 2)->default(0);
            $table->decimal('total_expected', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_matched', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->foreignId('source_import_id')->nullable()->constrained('bank_statement_imports')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('bank_statement_rows');
        Schema::dropIfExists('bank_statement_imports');
    }
};
