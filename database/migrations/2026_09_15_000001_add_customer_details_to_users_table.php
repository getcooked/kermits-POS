<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('birthday')->nullable()->after('phone');
            $table->string('sex', 30)->nullable()->after('birthday');
            $table->text('address')->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['birthday', 'sex', 'address']);
        });
    }
};
