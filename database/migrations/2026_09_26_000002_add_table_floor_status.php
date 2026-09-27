<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dining_tables', 'occupied_at')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                // Staff mark tables occupied and free by hand; bookings only estimate how long a party stays.
                $table->dateTime('occupied_at')->nullable();
                $table->dateTime('expected_free_at')->nullable();
                $table->foreignId('occupied_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
                $table->dateTime('freed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reservations', 'seated_at')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dateTime('seated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'seated_at')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('seated_at');
            });
        }

        if (Schema::hasColumn('dining_tables', 'occupied_at')) {
            Schema::table('dining_tables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('occupied_reservation_id');
                $table->dropColumn(['occupied_at', 'expected_free_at', 'freed_at']);
            });
        }
    }
};
