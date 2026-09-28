<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Exclusive Venue bookings pay part of the total online before staff can confirm them.
            $table->decimal('downpayment_amount', 10, 2)->nullable()->after('total_amount');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('downpayment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['downpayment_amount', 'amount_paid']);
        });
    }
};
