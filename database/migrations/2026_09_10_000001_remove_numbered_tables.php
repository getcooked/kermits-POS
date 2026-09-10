<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reservations', 'dining_table_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('dining_table_id');
            });
        }

        Schema::dropIfExists('dining_tables');
    }

    public function down(): void
    {
        if (! Schema::hasTable('dining_tables')) {
            Schema::create('dining_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('number')->unique();
                $table->unsignedInteger('capacity');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            foreach ([1 => 2, 2 => 2, 3 => 2, 4 => 4, 5 => 4, 6 => 4, 7 => 4, 8 => 12] as $number => $capacity) {
                DB::table('dining_tables')->insert(compact('number', 'capacity') + [
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
};
