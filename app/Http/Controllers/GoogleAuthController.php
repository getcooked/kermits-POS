<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        $this->ensureEnabled();

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        if ($request->filled('error')) {
            return $this->failed('Google sign-in was cancelled.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->failed('Your Google sign-in session expired. Please try again.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('We could not sign you in with Google. Please try again.');
        }

        $googleId = (string) $googleUser->getId();
        $email = Str::lower(trim((string) $googleUser->getEmail()));

        if ($googleId === '' || $email === '' || ($googleUser->user['email_verified'] ?? false) !== true) {
            return $this->failed('Your Google account email must be verified before you can sign in.');
        }

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            if ($this->matchesDisabledAccount($googleId, $email)) {
                return $this->failed('This account has been disabled. Please contact Kermit’s for help.');
            }

            if (! str_ends_with($email, '@gmail.com')) {
                return $this->failed('Please continue with a Gmail account.');
            }

            $user = $this->createCustomer($googleUser, $googleId, $email);
        }

        if (! $user->hasRole(User::ROLE_CUSTOMER)) {
            return $this->failed('Staff accounts must log in with their username and password.');
        }

        if ($user->google_id === null) {
            // Linking an unverified address would let whoever registered it keep a
            // password into the real owner's account.
            if ($user->email_verified_at === null) {
                return $this->failed('Log in with your password first to verify this email address.');
            }

            $user->forceFill(['google_id' => $googleId])->save();
        } elseif ($user->google_id !== $googleId) {
            return $this->failed('This email is already linked to a different Google account.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($user->homeRoute());
    }

    private function createCustomer(GoogleUser $googleUser, string $googleId, string $email): User
    {
        $name = Str::limit(trim((string) $googleUser->getName()), 100, '') ?: Str::before($email, '@');

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'role' => User::ROLE_CUSTOMER,
            'password' => Str::password(40),
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['google_id' => $googleId])->save();

        return $user;
    }

    private function matchesDisabledAccount(string $googleId, string $email): bool
    {
        return User::onlyTrashed()
            ->where(fn ($query) => $query
                ->where('google_id', $googleId)
                ->orWhereRaw('LOWER(email) = ?', [$email]))
            ->exists();
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }

    private function ensureEnabled(): void
    {
        abort_unless(
            config('services.google.enabled')
                && filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret')),
            404,
        );
    }
}
