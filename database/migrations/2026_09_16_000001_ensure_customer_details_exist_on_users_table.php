<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'birthday')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->date('birthday')->nullable()->after('phone');
            });
        }

        if (! Schema::hasColumn('users', 'sex')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('sex', 30)->nullable()->after('birthday');
            });
        }

        if (! Schema::hasColumn('users', 'address')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('address')->nullable()->after('sex');
            });
        }
    }

    public function down(): void
    {
        // This repair migration must not remove columns that may predate it.
    }
};
