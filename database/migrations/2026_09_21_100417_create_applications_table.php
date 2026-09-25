<?php

use App\Enums\ApplicationTargetType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('target_type', array_map(
                static fn (ApplicationTargetType $target): string => $target->value,
                ApplicationTargetType::cases(),
            ));
            $table->string('status', 32)->default('new');
            $table->string('applicant_name');
            $table->string('applicant_email');
            $table->string('applicant_phone')->nullable();
            $table->date('intended_move_in_date')->nullable();
            $table->string('intended_move_in_timeframe')->nullable();
            $table->text('applicant_message')->nullable();
            $table->text('operator_notes')->nullable();
            $table->text('applicant_feedback')->nullable();
            $table->string('open_application_key')->nullable()->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->index(['user_id', 'status']);
            $table->index(['property_id', 'unit_type_id', 'status']);
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'mysql'], true)) {
            DB::statement("alter table applications add constraint applications_target_columns_check check ((target_type = '".ApplicationTargetType::WholeProperty->value."' and unit_type_id is null) or (target_type = '".ApplicationTargetType::UnitType->value."' and unit_type_id is not null))");
        }

        if ($driver === 'sqlite') {
            DB::statement("create trigger applications_target_columns_insert before insert on applications begin select raise(abort, 'Invalid application target columns') where (new.target_type = '".ApplicationTargetType::WholeProperty->value."' and new.unit_type_id is not null) or (new.target_type = '".ApplicationTargetType::UnitType->value."' and new.unit_type_id is null); end");
            DB::statement("create trigger applications_target_columns_update before update of target_type, unit_type_id on applications begin select raise(abort, 'Invalid application target columns') where (new.target_type = '".ApplicationTargetType::WholeProperty->value."' and new.unit_type_id is not null) or (new.target_type = '".ApplicationTargetType::UnitType->value."' and new.unit_type_id is null); end");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('drop trigger if exists applications_target_columns_insert');
            DB::statement('drop trigger if exists applications_target_columns_update');
        }

        Schema::dropIfExists('applications');
    }
};
