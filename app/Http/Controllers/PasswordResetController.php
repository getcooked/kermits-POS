<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    private const RESET_LINK_STATUS = 'If an eligible account exists, a password reset link has been sent.';

    public function request(): View
    {
        return view('auth.forgot-password', ['superAdminRecovery' => false]);
    }

    public function requestSuperAdmin(): View
    {
        return view('auth.forgot-password', ['superAdminRecovery' => true]);
    }

    public function email(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, User::ROLE_CUSTOMER);
    }

    public function emailSuperAdmin(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, User::ROLE_SUPER_ADMIN);
    }

    private function sendResetLink(Request $request, ?string $requiredRole = null): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
        ]);

        $email = Str::lower($validated['email']);
        $superAdminEmail = $this->superAdminEmail();
        $legacyEmail = $this->legacySuperAdminEmail();
        $acceptedEmails = $requiredRole === User::ROLE_SUPER_ADMIN && $superAdminEmail !== '' && $email === $superAdminEmail
            ? array_values(array_filter([$superAdminEmail, $legacyEmail]))
            : [$email];
        $user = User::query()
            ->whereIn(DB::raw('LOWER(email)'), $acceptedEmails)
            ->when($requiredRole, fn ($query, string $role) => $query->where('role', $role))
            ->first();

        if (! $user) {
            return back()->with('status', self::RESET_LINK_STATUS);
        }

        if ($requiredRole === User::ROLE_SUPER_ADMIN
            && $superAdminEmail !== '' && $legacyEmail !== ''
            && strtolower($user->email) === $legacyEmail
            && ! User::query()->whereKeyNot($user->id)->whereRaw('LOWER(email) = ?', [$superAdminEmail])->exists()) {
            $user->forceFill(['email' => $superAdminEmail])->save();
        }
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
        } catch (TransportExceptionInterface) {
            Password::deleteToken($user);
            Log::warning('Web password reset email delivery failed.');

            return back()->with('status', self::RESET_LINK_STATUS);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            Log::notice('Password reset link was not sent.', ['status' => $status, 'role' => $requiredRole]);
        }

        return back()->with('status', self::RESET_LINK_STATUS);
    }

    public function reset(Request $request, string $token): View|RedirectResponse
    {
        $email = Str::lower($request->string('email')->trim()->toString());
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereIn('role', [User::ROLE_CUSTOMER, User::ROLE_SUPER_ADMIN])
            ->first();

        if (! $user || ! Password::tokenExists($user, $token)) {
            return redirect()
                ->route('password.request')
                ->withInput(['email' => $email])
                ->withErrors([
                    'email' => 'This password reset link is invalid, expired, or does not belong to a registered account.',
                ]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:160'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::defaults(),
            ],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($validated['email'])])
            ->whereIn('role', [User::ROLE_CUSTOMER, User::ROLE_SUPER_ADMIN])
            ->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or expired.']);
        }

        $validated['email'] = $user->email;

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    // Receiving and using the reset link proves ownership of this email.
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();

                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with(
                'status',
                'Your password has been reset. You can now log in with your new password.'
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'This password reset link is invalid or expired.']);
    }

    private function superAdminEmail(): string
    {
        return Str::lower(trim((string) config('auth.staff.super_admin_email')));
    }

    private function legacySuperAdminEmail(): string
    {
        return Str::lower(trim((string) config('auth.staff.legacy_super_admin_email')));
    }
}
