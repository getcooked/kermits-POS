<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'paymongo_payment_intent_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('paymongo_payment_intent_id')->nullable();
            });
        }

        if (! Schema::hasIndex('orders', ['paymongo_payment_intent_id'], 'unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('paymongo_payment_intent_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('orders', ['paymongo_payment_intent_id'], 'unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique(['paymongo_payment_intent_id']);
            });
        }

        if (Schema::hasColumn('orders', 'paymongo_payment_intent_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('paymongo_payment_intent_id');
            });
        }
    }
};
