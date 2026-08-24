<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_policy_requires_length_mixed_case_number_and_symbol(): void
    {
        foreach ([
            'Short1!',
            'lowercase123!',
            'UPPERCASE123!',
            'NoNumberHere!',
            'NoSymbol12345',
        ] as $weakPassword) {
            $this->assertTrue(Validator::make(
                ['password' => $weakPassword],
                ['password' => [Password::defaults()]],
            )->fails(), "Expected {$weakPassword} to be rejected.");
        }

        $this->assertFalse(Validator::make(
            ['password' => 'StrongPassword123!'],
            ['password' => [Password::defaults()]],
        )->fails());
    }

    public function test_user_password_is_automatically_hashed_before_storage(): void
    {
        $plainPassword = 'StrongPassword123!';
        $user = User::factory()->create(['password' => $plainPassword]);
        $storedPassword = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame($plainPassword, $storedPassword);
        $this->assertTrue(Hash::check($plainPassword, $storedPassword));
    }
}
