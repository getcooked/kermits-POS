<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('paymongo_checkout_id')->nullable()->unique();
            $table->text('paymongo_checkout_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_checkout_id']);
            $table->dropColumn(['paymongo_checkout_id', 'paymongo_checkout_url']);
        });
    }
};
