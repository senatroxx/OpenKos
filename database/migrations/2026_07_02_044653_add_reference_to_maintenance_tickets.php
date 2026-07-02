<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('id');
        });

        // Backfill existing tickets with auto-generated references
        $tickets = DB::table('maintenance_tickets')->whereNull('reference')->orderBy('id')->get();

        foreach ($tickets as $ticket) {
            $year = substr($ticket->created_at, 0, 4);
            $pattern = 'TKT'.$year.'%';

            $max = DB::table('maintenance_tickets')
                ->where('reference', 'like', $pattern)
                ->orderBy('reference', 'desc')
                ->value('reference');

            $seq = $max ? (int) substr($max, -4) + 1 : 1;

            DB::table('maintenance_tickets')
                ->where('id', $ticket->id)
                ->update(['reference' => 'TKT'.$year.str_pad((string) $seq, 4, '0', STR_PAD_LEFT)]);
        }
    }

    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
