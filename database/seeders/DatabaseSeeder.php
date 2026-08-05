<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MenuCatalogSeeder::class);

        // User::factory(10)->create();

        foreach ([
            ['name' => 'Super Admin', 'email' => 'superadmin@gmail.com', 'role' => 'super_admin'],
            ['name' => 'Administrator', 'email' => 'admin@gmail.com', 'role' => 'admin'],
            ['name' => 'Cashier User', 'email' => 'cashier@gmail.com', 'role' => 'cashier'],
        ] as $account) {
            User::query()->updateOrCreate(['email' => $account['email']], [...$account, 'password' => 'password']);
        }

        $this->call(MenuImageSeeder::class);
    }
}
