<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        DB::table('orders')
            ->whereIn('user_id', DB::table('users')->where('role', 'customer')->select('id'))
            ->update(['customer_id' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
    }
};
