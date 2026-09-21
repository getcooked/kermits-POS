<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'paymongo_checkout_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('paymongo_checkout_id')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'paymongo_checkout_url')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->text('paymongo_checkout_url')->nullable();
            });
        }

        if (! Schema::hasIndex('orders', ['paymongo_checkout_id'], 'unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('paymongo_checkout_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('orders', ['paymongo_checkout_id'], 'unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique(['paymongo_checkout_id']);
            });
        }

        $columns = array_values(array_filter(
            ['paymongo_checkout_id', 'paymongo_checkout_url'],
            fn (string $column): bool => Schema::hasColumn('orders', $column),
        ));

        if ($columns !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
