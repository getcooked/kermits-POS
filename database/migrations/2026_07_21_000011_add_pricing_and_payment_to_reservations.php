<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('reservation_fee', 10, 2)->default(0)->after('guests');
            $table->decimal('food_total', 10, 2)->default(0)->after('reservation_fee');
            $table->decimal('total_amount', 10, 2)->default(0)->after('food_total');
            $table->string('payment_method')->default('cash')->after('total_amount');
            $table->string('payment_status')->default('pending')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['reservation_fee', 'food_total', 'total_amount', 'payment_method', 'payment_status']);
        });
    }
};
