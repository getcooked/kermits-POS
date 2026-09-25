<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dining_tables')) {
            Schema::create('dining_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('number')->unique();
                $table->unsignedInteger('seats');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            // Start from the capacity pool the venue already books against.
            $capacities = array_map('intval', (array) config('reservations.table_capacities', []));
            sort($capacities);
            foreach (array_values($capacities) as $index => $seats) {
                DB::table('dining_tables')->insert([
                    'number' => $index + 1,
                    'seats' => $seats,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasColumn('reservations', 'dining_table_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->foreignId('dining_table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'dining_table_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('dining_table_id');
            });
        }

        Schema::dropIfExists('dining_tables');
    }
};
