<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_reset_an_admin_password_and_revoke_sessions(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => 'OldPassword123',
            'remember_token' => 'old-token',
        ]);
        DB::table('sessions')->insert([
            'id' => 'admin-session',
            'user_id' => $admin->id,
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($superAdmin)->get('/staff/admins')
            ->assertOk()
            ->assertSee($admin->name);

        $this->put('/staff/admins/'.$admin->id.'/password', [
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertRedirect();

        $admin->refresh();
        $this->assertTrue(Hash::check('NewPassword456!', $admin->password));
        $this->assertNotSame('old-token', $admin->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $admin->id]);

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'NewPassword456!'])
            ->assertRedirect('/dashboard');
    }

    public function test_only_super_admin_can_manage_admin_passwords(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        foreach ([$admin, $cashier] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)->get('/staff/admins')->assertForbidden();
            $this->put('/staff/admins/'.$admin->id.'/password', [
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])->assertForbidden();
        }
    }

    public function test_super_admin_cannot_use_admin_reset_for_another_role(): void
    {
        $superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'OriginalPassword123',
        ]);
        $cashier = User::factory()->create([
            'role' => User::ROLE_CASHIER,
            'password' => 'OriginalPassword123',
        ]);

        foreach ([$superAdmin, $cashier] as $protectedUser) {
            $this->actingAs($superAdmin)->put('/staff/admins/'.$protectedUser->id.'/password', [
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])->assertNotFound();
            $this->assertTrue(Hash::check('OriginalPassword123', $protectedUser->fresh()->password));
        }
    }
}
