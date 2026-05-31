<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('billing_interval');
            $table->string('billing_unit');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'billing_interval', 'billing_unit']);
        });

        $timestamp = Carbon::now();
        $rates = DB::table('rooms')
            ->whereNotNull('base_price')
            ->get()
            ->map(fn ($room) => [
                'room_id' => $room->id,
                'billing_interval' => 1,
                'billing_unit' => 'month',
                'amount' => $room->base_price,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->toArray();

        if (! empty($rates)) {
            DB::table('room_rates')->insert($rates);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
