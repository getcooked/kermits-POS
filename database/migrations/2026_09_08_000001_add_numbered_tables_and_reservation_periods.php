<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->unsignedInteger('capacity');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        foreach ([1 => 2, 2 => 2, 3 => 2, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 12] as $number => $capacity) {
            DB::table('dining_tables')->insert(compact('number', 'capacity') + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        // A stable row serializes bookings even when there are no reservations yet.
        Schema::create('reservation_locks', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('reservation_locks')->insert(['id' => 1]);
        $hasOldIndex = Schema::hasIndex('reservations', 'reservations_reservation_at_unique');
        Schema::table('reservations', function (Blueprint $table) use ($hasOldIndex) {
            $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            $table->dateTime('reservation_end_at')->nullable();
            $table->dateTime('hold_expires_at')->nullable();
            $table->index(['status', 'reservation_at', 'reservation_end_at'], 'reservations_period_index');
            if ($hasOldIndex) {
                $table->dropUnique('reservations_reservation_at_unique');
            }
        });
        // Keep old bookings and protect unassigned tables until staff reviews them.
        DB::table('reservations')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $start = CarbonImmutable::parse($row->reservation_at);
                $end = $start->addHours(2);
                $closing = $start->setTime(23, 0);
                if ($start->lt($closing) && $end->gt($closing)) {
                    $end = $closing;
                }
                DB::table('reservations')->where('id', $row->id)->update(['reservation_end_at' => $end->format('Y-m-d H:i:s')]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('reservations')->select('reservation_at')->groupBy('reservation_at')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old schedule constraint while simultaneous reservations exist.');
        }
        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('reservation_at', 'reservations_reservation_at_unique');
            $table->dropIndex('reservations_period_index');
            $table->dropConstrainedForeignId('dining_table_id');
            $table->dropColumn(['reservation_end_at', 'hold_expires_at']);
        });
        Schema::dropIfExists('reservation_locks');
        Schema::dropIfExists('dining_tables');
    }
};
