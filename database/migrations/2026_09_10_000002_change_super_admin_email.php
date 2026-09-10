<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_EMAIL = 'superadmin@gmail.com';

    private const NEW_EMAIL = 'kermitsbantayan1@gmail.com';

    public function up(): void
    {
        $target = DB::table('users')->whereRaw('LOWER(email) = ?', [self::NEW_EMAIL])->first();
        if ($target && $target->role !== 'super_admin') {
            throw new RuntimeException('The new Super Admin email is already assigned to another account.');
        }
        if ($target) {
            return;
        }

        DB::table('users')
            ->where('role', 'super_admin')
            ->whereRaw('LOWER(email) = ?', [self::OLD_EMAIL])
            ->update(['email' => self::NEW_EMAIL, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('users')->whereRaw('LOWER(email) = ?', [self::OLD_EMAIL])->exists()) {
            return;
        }

        DB::table('users')
            ->where('role', 'super_admin')
            ->whereRaw('LOWER(email) = ?', [self::NEW_EMAIL])
            ->update(['email' => self::OLD_EMAIL, 'updated_at' => now()]);
    }
};
