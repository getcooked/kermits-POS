<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerDetailsSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_repairs_missing_customer_columns_before_inserting(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['birthday', 'sex', 'address']);
        });

        $this->assertFalse(Schema::hasColumn('users', 'birthday'));
        $this->assertFalse(Schema::hasColumn('users', 'sex'));
        $this->assertFalse(Schema::hasColumn('users', 'address'));

        $this->withSession([
            'registration_email_verification' => [
                'email' => 'schema.repair@gmail.com',
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10)->timestamp,
                'verified' => true,
            ],
        ])->post('/register', [
            'name' => 'Schema Repair',
            'username' => 'schema.repair',
            'email' => 'schema.repair@gmail.com',
            'phone' => '09171234567',
            'birthday' => '2000-09-15',
            'sex' => 'female',
            'address' => 'Binaobao, Bantayan, Cebu, Philippines',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/shop');

        $this->assertTrue(Schema::hasColumn('users', 'birthday'));
        $this->assertTrue(Schema::hasColumn('users', 'sex'));
        $this->assertTrue(Schema::hasColumn('users', 'address'));
        $this->assertDatabaseHas('users', [
            'email' => 'schema.repair@gmail.com',
            'birthday' => '2000-09-15 00:00:00',
            'sex' => 'female',
            'address' => 'Binaobao, Bantayan, Cebu, Philippines',
        ]);
    }
}
