<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('processed_by')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('payment_status')->index();
            $table->index(['processed_by', 'paid_at']);
        });

        DB::table('orders')
            ->where('payment_status', 'paid')
            ->orderBy('id')
            ->chunkById(200, function ($orders): void {
                $staffIds = DB::table('users')
                    ->whereIn('id', $orders->pluck('user_id')->filter()->unique())
                    ->whereIn('role', ['cashier', 'super_admin'])
                    ->pluck('id')
                    ->flip();

                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'processed_by' => $staffIds->has($order->user_id) ? $order->user_id : null,
                        'paid_at' => $order->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['processed_by', 'paid_at']);
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn('paid_at');
        });
    }
};
