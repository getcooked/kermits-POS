<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_shows_admin_counts_create_form_and_admin_list(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Main Admin']);
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Second Admin']);
        User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'name' => 'Old Admin', 'disabled_at' => now()]);
        User::factory()->create(['role' => User::ROLE_CASHIER, 'name' => 'A Cashier']);

        $response = $this->actingAs($me)->get(route('superadmin.security.edit'))->assertOk();

        $response
            ->assertSeeInOrder(['Admin Accounts', '3', 'Active Admin Accounts', '2', 'Disabled Admin Accounts', '1'])
            ->assertSee('Create Admin Account')
            ->assertSee('Send code')
            ->assertSee('Edit account')
            ->assertSee('Main Admin')
            ->assertSee('Old Admin')
            ->assertDontSee('A Cashier')
            ->assertDontSee('Change my password');
    }

    public function test_only_super_admin_can_manage_admin_accounts(): void
    {
        $target = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        foreach ([User::ROLE_ADMIN, User::ROLE_CASHIER, User::ROLE_CUSTOMER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('superadmin.security.edit'))->assertForbidden();
            $this->post(route('superadmin.admins.email-code'), ['email' => 'x@gmail.com'])->assertForbidden();
            $this->post(route('superadmin.admins.store'), [])->assertForbidden();
            $this->put(route('superadmin.admins.update', $target), [])->assertForbidden();
            $this->patch(route('superadmin.admins.disable', $target))->assertForbidden();
        }

        $this->assertNull($target->fresh()->disabled_at);
    }

    public function test_send_code_emails_the_new_admin_address(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($me)->from(route('superadmin.security.edit'))
            ->post(route('superadmin.admins.email-code'), ['email' => ' New.Admin@Gmail.com ', 'name' => 'New Admin'])
            ->assertRedirect(route('superadmin.security.edit'))
            ->assertSessionHas('status', 'We sent a 6-digit verification code to new.admin@gmail.com.')
            ->assertSessionHasInput('name', 'New Admin');

        $this->assertSame('new.admin@gmail.com', session('admin_email_verification.email'));
        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('new.admin@gmail.com', $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());

        $this->post(route('superadmin.admins.email-code'), ['email' => $me->email])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_account_is_created_only_with_the_emailed_code(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $form = $this->newAdminForm();

        $this->actingAs($me)->post(route('superadmin.admins.store'), $form)
            ->assertSessionHasErrors('verification_code');

        $this->withSession($this->verificationSession('someone.else@gmail.com'))
            ->post(route('superadmin.admins.store'), $form)
            ->assertSessionHasErrors('verification_code');

        $this->withSession($this->verificationSession('new.admin@gmail.com', expiresAt: now()->subMinute()->timestamp))
            ->post(route('superadmin.admins.store'), $form)
            ->assertSessionHasErrors('verification_code');

        $this->withSession($this->verificationSession('new.admin@gmail.com'))
            ->post(route('superadmin.admins.store'), [...$form, 'verification_code' => '000000'])
            ->assertSessionHasErrors('verification_code');
        $this->assertSame(1, session('admin_email_verification.attempts'));
        $this->assertDatabaseMissing('users', ['email' => 'new.admin@gmail.com']);

        $this->post(route('superadmin.admins.store'), $form)
            ->assertRedirect(route('superadmin.security.edit'))
            ->assertSessionHas('status', 'Admin account created successfully.');

        $admin = User::query()->where('email', 'new.admin@gmail.com')->firstOrFail();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertSame('newadmin', $admin->username);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('NewAdmin123!', $admin->password));
        $this->assertNull(session('admin_email_verification'));
    }

    public function test_new_admin_can_log_in_and_open_the_dashboard(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($me)->withSession($this->verificationSession('new.admin@gmail.com'))
            ->post(route('superadmin.admins.store'), $this->newAdminForm())
            ->assertSessionHasNoErrors();
        auth()->logout();

        $this->post(route('login.store'), ['email' => 'newadmin', 'password' => 'NewAdmin123!'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_edit_account_updates_details_and_password_and_signs_that_admin_out(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => 'other@gmail.com']);
        $this->addSession($other, 'other-session');

        $this->actingAs($me)->put(route('superadmin.admins.update', $other), [
            'name' => 'Renamed Admin', 'username' => 'renamed', 'email' => 'Renamed@Gmail.com', 'phone' => '09171234567',
            'password' => 'Changed123!', 'password_confirmation' => 'Changed123!',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Admin account updated successfully.');

        $other->refresh();
        $this->assertSame(['Renamed Admin', 'renamed', 'renamed@gmail.com', '09171234567'], [$other->name, $other->username, $other->email, $other->phone]);
        $this->assertTrue(Hash::check('Changed123!', $other->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_admin_can_change_their_own_password_from_edit_account(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'username' => null, 'phone' => null]);
        $this->addSession($me, 'my-other-browser');

        $this->actingAs($me)->put(route('superadmin.admins.update', $me), [
            'name' => $me->name, 'email' => $me->email,
            'password' => 'MyNewPass123!', 'password_confirmation' => 'MyNewPass123!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('MyNewPass123!', $me->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'my-other-browser']);
        $this->assertAuthenticatedAs($me);
    }

    public function test_disabled_admin_is_signed_out_and_cannot_log_in_until_enabled(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'username' => 'otheradmin', 'password' => 'Other123!']);
        $this->addSession($other, 'other-session');

        $this->actingAs($me)->patch(route('superadmin.admins.disable', $other))
            ->assertSessionHas('status');
        $this->assertNotNull($other->fresh()->disabled_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);

        $this->actingAs($other->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'otheradmin', 'password' => 'Other123!'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'This account has been disabled. Please contact a Super Admin.']);
        $this->assertGuest();

        $this->actingAs($me)->patch(route('superadmin.admins.enable', $other))->assertSessionHas('status');
        auth()->logout();
        $this->post(route('login.store'), ['email' => 'otheradmin', 'password' => 'Other123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_admin_cannot_disable_their_own_account_or_non_admins(): void
    {
        $me = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($me)->patch(route('superadmin.admins.disable', $me))
            ->assertSessionHasErrors(['admin' => 'You cannot disable your own account.']);
        $this->patch(route('superadmin.admins.disable', $cashier))->assertNotFound();
        $this->put(route('superadmin.admins.update', $cashier), ['name' => 'X', 'email' => 'x@gmail.com'])->assertNotFound();

        $this->assertNull($me->fresh()->disabled_at);
        $this->assertNull($cashier->fresh()->disabled_at);
    }

    private function newAdminForm(): array
    {
        return [
            'name' => 'New Admin', 'username' => 'newadmin', 'email' => 'New.Admin@gmail.com',
            'verification_code' => '123456', 'phone' => '09170000009',
            'password' => 'NewAdmin123!', 'password_confirmation' => 'NewAdmin123!',
        ];
    }

    private function verificationSession(string $email, ?int $expiresAt = null): array
    {
        return ['admin_email_verification' => [
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => $expiresAt ?? now()->addMinutes(10)->timestamp,
        ]];
    }

    private function addSession(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => 'test', 'last_activity' => now()->timestamp]);
    }
}
