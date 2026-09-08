<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The following numbered-table migration replaces this global constraint.
        // Existing simultaneous bookings must survive upgrading older installations.
        if (DB::table('reservations')->select('reservation_at')->groupBy('reservation_at')->havingRaw('COUNT(*) > 1')->exists()) {
            return;
        }
        Schema::table('reservations', function (Blueprint $table) {
            $table->unique('reservation_at', 'reservations_reservation_at_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('reservations', 'reservations_reservation_at_unique')) {
            return;
        }
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_reservation_at_unique');
        });
    }
};
