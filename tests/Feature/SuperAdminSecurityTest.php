<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SuperAdminPasswordVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SuperAdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_security_page(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($superAdmin)->get(route('superadmin.security.edit'))
            ->assertOk()
            ->assertSee('Change my password')
            ->assertSee('Send verification code')
            ->assertDontSee('Current password')
            ->assertDontSee('AFTER YOU SAVE')
            ->assertDontSee('Your account stays protected');

        $this->actingAs($admin)->get(route('superadmin.security.edit'))->assertForbidden();
        $this->post(route('superadmin.security.email-code'))->assertForbidden();
        $this->put(route('superadmin.security.password.update'), [])->assertForbidden();
    }

    public function test_super_admin_can_request_an_email_verification_code(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->create([
            'email' => 'kermitsbantayan1@gmail.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->post(route('superadmin.security.email-code'))
            ->assertRedirect()
            ->assertSessionHas('verification_sent', 'A 6-digit verification code was sent to kermitsbantayan1@gmail.com.')
            ->assertSessionHas('super_admin_password_verification', fn (array $verification): bool => $verification['user_id'] === $superAdmin->id
                && $verification['email'] === $superAdmin->email
                && $verification['expires_at'] > now()->timestamp
                && is_string($verification['code_hash'])
                && $verification['code_hash'] !== ''
            );

        Notification::assertSentTo(
            $superAdmin,
            SuperAdminPasswordVerification::class,
            fn (SuperAdminPasswordVerification $notification): bool => preg_match('/^\d{6}$/', $notification->code) === 1
                && $notification->toMail($superAdmin)->subject === 'Super Admin verification code'
                && ! str_contains(implode(' ', $notification->toMail($superAdmin)->introLines), "Kermit's"),
        );

        $this->withSession(['verification_sent' => 'A 6-digit verification code was sent to kermitsbantayan1@gmail.com.'])
            ->get(route('superadmin.security.edit'))
            ->assertOk()
            ->assertSee('id="security-toast"', false)
            ->assertSee('A 6-digit verification code was sent to kermitsbantayan1@gmail.com.');
    }

    public function test_email_verification_is_required_to_change_the_password(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'CurrentPassword123!',
        ]);

        $this->actingAs($superAdmin)->put(route('superadmin.security.password.update'), [
            'verification_code' => '123456',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertSessionHasErrors('verification_code');

        $this->assertTrue(Hash::check('CurrentPassword123!', $superAdmin->fresh()->password));
    }

    public function test_expired_email_verification_code_cannot_change_the_password(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'CurrentPassword123!',
        ]);

        $this->actingAs($superAdmin)->withSession($this->verificationSession($superAdmin, now()->subSecond()->timestamp))
            ->put(route('superadmin.security.password.update'), [
                'verification_code' => '123456',
                'password' => 'NewSecurePassword456!',
                'password_confirmation' => 'NewSecurePassword456!',
            ])->assertSessionHasErrors('verification_code');

        $this->assertTrue(Hash::check('CurrentPassword123!', $superAdmin->fresh()->password));
    }

    public function test_super_admin_can_change_own_password_and_revoke_other_sessions(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'CurrentPassword123!',
        ]);
        DB::table('sessions')->insert([
            'id' => 'another-session',
            'user_id' => $superAdmin->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $superAdmin->id,
            'name' => 'Android phone',
            'token_hash' => hash('sha256', 'mobile-token'),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)->withSession($this->verificationSession($superAdmin))->put(route('superadmin.security.password.update'), [
            'verification_code' => '123456',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePassword456!', $superAdmin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $superAdmin->id]);
        $this->assertNull(session('super_admin_password_verification'));
    }

    private function verificationSession(User $user, int $expiresAt = 0): array
    {
        return ['super_admin_password_verification' => [
            'user_id' => $user->id,
            'email' => strtolower($user->email),
            'code_hash' => Hash::make('123456'),
            'expires_at' => $expiresAt ?: now()->addMinutes(10)->timestamp,
        ]];
    }
}
