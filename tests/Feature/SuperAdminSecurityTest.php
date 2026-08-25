<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
            ->assertSee('Change my password');

        $this->actingAs($admin)->get(route('superadmin.security.edit'))->assertForbidden();
        $this->put(route('superadmin.security.password.update'), [])->assertForbidden();
    }

    public function test_current_password_is_required_to_change_super_admin_password(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'CurrentPassword123!',
        ]);

        $this->actingAs($superAdmin)->put(route('superadmin.security.password.update'), [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertSessionHasErrors('current_password');

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

        $this->actingAs($superAdmin)->put(route('superadmin.security.password.update'), [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePassword456!', $superAdmin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $superAdmin->id]);
    }
}
