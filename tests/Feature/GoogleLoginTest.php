<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google' => [
            'enabled' => true,
            'client_id' => 'test-google-client',
            'client_secret' => 'test-google-secret',
            'redirect' => '/auth/google/callback',
        ]]);
    }

    public function test_google_login_is_hidden_and_unreachable_when_disabled(): void
    {
        config(['services.google.enabled' => false]);

        $this->get(route('login'))->assertOk()->assertDontSee('Continue with Google');
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_login_page_shows_google_button_that_redirects_to_google(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee(route('auth.google'), false)
            ->assertDontSee('test-google-secret');

        $location = $this->get(route('auth.google'))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('test-google-client', $query['client_id']);
        $this->assertSame(url('/auth/google/callback'), $query['redirect_uri']);
        $this->assertNotEmpty($query['state']);
        $this->assertStringNotContainsString('test-google-secret', $location);
    }

    public function test_new_gmail_user_is_registered_as_verified_customer_and_logged_in(): void
    {
        $this->fakeGoogleUser('google-123', 'New.Customer@Gmail.com', 'New Customer');

        $this->get(route('auth.google.callback'))->assertRedirect(route('shop'));

        $user = User::query()->where('email', 'new.customer@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertSame('New Customer', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_existing_verified_customer_is_linked_by_email_and_logged_in(): void
    {
        $customer = User::factory()->create(['email' => 'customer@gmail.com', 'role' => User::ROLE_CUSTOMER]);
        $this->fakeGoogleUser('google-456', 'customer@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('shop'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame('google-456', $customer->fresh()->google_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_linked_customer_can_log_in_after_changing_their_google_email(): void
    {
        $customer = User::factory()->create(['email' => 'old@gmail.com', 'role' => User::ROLE_CUSTOMER]);
        $customer->forceFill(['google_id' => 'google-789'])->save();
        $this->fakeGoogleUser('google-789', 'renamed@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('shop'));

        $this->assertAuthenticatedAs($customer);
    }

    public function test_staff_accounts_cannot_log_in_with_google(): void
    {
        User::factory()->create(['email' => 'boss@gmail.com', 'role' => User::ROLE_SUPER_ADMIN]);
        $this->fakeGoogleUser('google-staff', 'boss@gmail.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => 'Staff accounts must log in with their username and password.']);

        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogleUser('google-unverified', 'someone@gmail.com', emailVerified: false);

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone@gmail.com']);
    }

    public function test_unverified_local_account_is_not_linked_to_google(): void
    {
        $customer = User::factory()->unverified()->create(['email' => 'claimed@gmail.com', 'role' => User::ROLE_CUSTOMER]);
        $this->fakeGoogleUser('google-owner', 'claimed@gmail.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($customer->fresh()->google_id);
    }

    public function test_account_linked_to_another_google_account_is_rejected(): void
    {
        $customer = User::factory()->create(['email' => 'linked@gmail.com', 'role' => User::ROLE_CUSTOMER]);
        $customer->forceFill(['google_id' => 'google-original'])->save();
        $this->fakeGoogleUser('google-other', 'linked@gmail.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('google-original', $customer->fresh()->google_id);
    }

    public function test_new_accounts_require_a_gmail_address(): void
    {
        $this->fakeGoogleUser('google-work', 'person@company.com');

        $this->get(route('auth.google.callback'))
            ->assertSessionHasErrors(['google' => 'Please continue with a Gmail account.']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'person@company.com']);
    }

    public function test_disabled_accounts_cannot_log_in_or_be_recreated(): void
    {
        $customer = User::factory()->create(['email' => 'removed@gmail.com', 'role' => User::ROLE_CUSTOMER]);
        $customer->delete();
        $this->fakeGoogleUser('google-removed', 'removed@gmail.com');

        $this->get(route('auth.google.callback'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame(1, User::withTrashed()->count());
    }

    public function test_expired_state_and_cancelled_sign_in_return_to_login_with_error(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google' => 'Google sign-in was cancelled.']);

        $this->assertGuest();
    }

    public function test_logged_in_users_are_sent_away_from_google_login(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($customer)->get(route('auth.google'))->assertRedirect(route('shop'));
    }

    private function fakeGoogleUser(string $id, string $email, string $name = 'Google User', bool $emailVerified = true): void
    {
        $googleUser = (new SocialiteUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $emailVerified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
