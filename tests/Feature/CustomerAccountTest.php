<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\User;
use App\Notifications\CustomerPasswordVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_popover_opens_only_the_selected_account_section(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'birthday' => '2000-09-15',
            'sex' => 'female',
            'address' => 'Bantayan, Cebu',
        ]);

        $this->actingAs($customer)->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('Personal Information')
            ->assertDontSee('Change password')
            ->assertDontSee('<span>1</span>', false)
            ->assertDontSee('<span>2</span>', false)
            ->assertSee('id="email" type="email" value="'.$customer->email.'" readonly', false)
            ->assertSee('cannot be changed')
            ->assertSee('name="birthday"', false)
            ->assertSee('name="sex"', false)
            ->assertSee('name="address"', false)
            ->assertSee('Bantayan, Cebu')
            ->assertSee('Use my current location')
            ->assertSee(route('customer.profile.update'), false)
            ->assertDontSee(route('customer.settings.password.update'), false);

        $this->actingAs($customer)->get(route('customer.profile.edit', ['section' => 'password']))
            ->assertOk()
            ->assertSee('Change password')
            ->assertDontSee('Personal Information')
            ->assertSee('Send verification code')
            ->assertSee(route('customer.settings.password.email-code'), false)
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
            'birthday' => '2000-09-15',
            'sex' => 'female',
            'address' => 'Old address',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->actingAs($customer)->put(route('customer.profile.update'), [
            'name' => 'New Name',
            'username' => 'new.name',
            'phone' => '09181234567',
            'birthday' => '1999-05-20',
            'sex' => 'male',
            'address' => 'New present address',
            'email' => 'changed@gmail.com',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertRedirect()->assertSessionHas('status');

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('new.name', $customer->username);
        $this->assertSame('09181234567', $customer->phone);
        $this->assertSame('1999-05-20', $customer->birthday->format('Y-m-d'));
        $this->assertSame('male', $customer->sex);
        $this->assertSame('New present address', $customer->address);
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
            'birthday' => '2000-09-15',
            'sex' => 'female',
            'address' => 'Bantayan, Cebu',
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

        $this->actingAs($customer)
            ->withSession($this->passwordVerificationSession($customer))
            ->put(route('customer.settings.password.update'), [
                'verification_code' => '123456',
                'current_password' => 'CurrentPassword123!',
                'password' => 'NewSecurePassword456!',
                'password_confirmation' => 'NewSecurePassword456!',
            ])->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionMissing('customer_password_verification');

        $this->assertTrue(Hash::check('NewSecurePassword456!', $customer->fresh()->password));
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $customer->id]);
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $customer = User::factory()->create([
            'password' => 'CurrentPassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->actingAs($customer)
            ->withSession($this->passwordVerificationSession($customer))
            ->put(route('customer.settings.password.update'), [
                'verification_code' => '123456',
                'current_password' => 'WrongPassword123!',
                'password' => 'NewSecurePassword456!',
                'password_confirmation' => 'NewSecurePassword456!',
            ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword123!', $customer->fresh()->password));
    }

    public function test_customer_can_request_a_password_verification_code_by_email(): void
    {
        Notification::fake();
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);

        $this->actingAs($customer)
            ->post(route('customer.settings.password.email-code'))
            ->assertRedirect()
            ->assertSessionHas('verification_sent')
            ->assertSessionHas('customer_password_verification', function (array $verification) use ($customer): bool {
                return $verification['user_id'] === $customer->id
                    && $verification['email'] === $customer->email
                    && $verification['expires_at'] > now()->timestamp
                    && is_string($verification['code_hash']);
            });

        Notification::assertSentTo(
            $customer,
            CustomerPasswordVerification::class,
            fn (CustomerPasswordVerification $notification): bool => preg_match('/^\d{6}$/', $notification->code) === 1,
        );
    }

    public function test_password_change_requires_a_valid_unexpired_email_code(): void
    {
        $customer = User::factory()->create([
            'password' => 'CurrentPassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $passwordData = [
            'verification_code' => '654321',
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ];

        $this->actingAs($customer)
            ->put(route('customer.settings.password.update'), $passwordData)
            ->assertSessionHasErrors('verification_code');

        $this->actingAs($customer)
            ->withSession($this->passwordVerificationSession($customer, now()->subMinute()->timestamp))
            ->put(route('customer.settings.password.update'), $passwordData)
            ->assertSessionHasErrors('verification_code');

        $this->assertTrue(Hash::check('CurrentPassword123!', $customer->fresh()->password));
    }

    public function test_guests_and_staff_cannot_access_customer_account_pages(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->get(route('customer.profile.edit'))->assertRedirect(route('login'));
        $this->get(route('customer.settings.edit'))->assertRedirect(route('login'));
        $this->post(route('customer.settings.password.email-code'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('customer.profile.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('customer.settings.edit'))->assertForbidden();
        $this->actingAs($staff)->post(route('customer.settings.password.email-code'))->assertForbidden();
    }

    private function passwordVerificationSession(User $customer, ?int $expiresAt = null): array
    {
        return [
            'customer_password_verification' => [
                'user_id' => $customer->id,
                'email' => strtolower($customer->email),
                'code_hash' => Hash::make('123456'),
                'expires_at' => $expiresAt ?? now()->addMinutes(10)->timestamp,
            ],
        ];
    }
}
