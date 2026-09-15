<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_EMAIL = 'cashier@gmail.com';

    private const NEW_EMAIL = 'kermitscashier@gmail.com';

    public function up(): void
    {
        if (! DB::table('users')->where('email', self::NEW_EMAIL)->exists()) {
            DB::table('users')
                ->where('role', 'cashier')
                ->where('email', self::OLD_EMAIL)
                ->update([
                    'email' => self::NEW_EMAIL,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! DB::table('users')->where('email', self::OLD_EMAIL)->exists()) {
            DB::table('users')
                ->where('role', 'cashier')
                ->where('email', self::NEW_EMAIL)
                ->update([
                    'email' => self::OLD_EMAIL,
                    'updated_at' => now(),
                ]);
        }
    }
};
