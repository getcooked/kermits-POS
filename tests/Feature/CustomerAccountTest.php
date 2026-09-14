<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_popover_opens_only_the_selected_account_section(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('Personal information')
            ->assertDontSee('Change password')
            ->assertDontSee('<span>1</span>', false)
            ->assertDontSee('<span>2</span>', false)
            ->assertSee(route('customer.profile.update'), false)
            ->assertDontSee(route('customer.settings.password.update'), false);

        $this->actingAs($customer)->get(route('customer.profile.edit', ['section' => 'password']))
            ->assertOk()
            ->assertSee('Change password')
            ->assertDontSee('Personal information')
            ->assertSee(route('customer.settings.password.update'), false)
            ->assertDontSee(route('customer.profile.update'), false);

        $this->actingAs($customer)->get(route('customer.settings.edit'))
            ->assertRedirect(route('customer.profile.edit', ['section' => 'password']));

        $this->actingAs($customer)->get(route('shop'))
            ->assertOk()
            ->assertSee('shop-profile-button', false)
            ->assertSee('data-profile-popover', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('href="'.route('customer.profile.edit', ['section' => 'personal']).'"', false)
            ->assertSee('href="'.route('customer.profile.edit', ['section' => 'password']).'"', false)
            ->assertDontSee('>Profile</a>', false)
            ->assertDontSee('>Settings</a>', false);

        $this->actingAs($customer)->get(route('customer.history'))
            ->assertOk()
            ->assertDontSee('>Profile</a>', false)
            ->assertDontSee('>Settings</a>', false);

        $this->actingAs($customer)->get(route('reservations.create'))
            ->assertOk()
            ->assertDontSee('>Profile</a>', false)
            ->assertDontSee('>Settings</a>', false);
    }

    public function test_customer_can_update_their_own_profile_without_changing_verified_email_or_role(): void
    {
        $customer = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old.name',
            'email' => 'verified@gmail.com',
            'phone' => '09171234567',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'New Name',
            'username' => 'new.name',
            'phone' => '09181234567',
            'email' => 'changed@gmail.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertRedirect()->assertSessionHas('status');

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new.name', $customer->username);
        $this->assertSame('09181234567', $customer->phone);
        $this->assertSame('verified@gmail.com', $customer->email);
        $this->assertSame(User::ROLE_CUSTOMER, $customer->role);
    }

    public function test_profile_update_rejects_an_existing_username_and_invalid_phone(): void
    {
        User::factory()->create(['username' => 'already.used']);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'Customer',
            'username' => 'already.used',
            'phone' => '12345',
        ])->assertSessionHasErrors(['username', 'phone']);
    }

    public function test_customer_can_change_password_with_current_password_and_mobile_sessions_are_revoked(): void
    {
        $customer = User::factory()->create([
            'password' => 'CurrentPassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);
        MobileApiToken::query()->create([
            'user_id' => $customer->id,
            'name' => 'Test phone',
            'token_hash' => hash('sha256', 'mobile-token'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($customer)->put(route('customer.settings.password.update'), [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePassword456!', $customer->fresh()->password));
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $customer->id]);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $customer = User::factory()->create([
            'password' => 'CurrentPassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->actingAs($customer)->put(route('customer.settings.password.update'), [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword123!', $customer->fresh()->password));
    }

    public function test_guests_and_staff_cannot_access_customer_account_pages(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->get(route('customer.profile.edit'))->assertRedirect(route('login'));
        $this->get(route('customer.settings.edit'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('customer.profile.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('customer.settings.edit'))->assertForbidden();
    }
}
