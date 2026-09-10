<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const LEGACY_SUPER_ADMIN_EMAIL = 'superadmin@gmail.com';

    private const SUPER_ADMIN_EMAIL = 'kermitsbantayan1@gmail.com';

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
        return $this->sendResetLink($request);
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
        $acceptedEmails = $requiredRole === User::ROLE_SUPER_ADMIN && $email === self::SUPER_ADMIN_EMAIL
            ? [self::SUPER_ADMIN_EMAIL, self::LEGACY_SUPER_ADMIN_EMAIL]
            : [$email];
        $user = User::query()
            ->whereIn(DB::raw('LOWER(email)'), $acceptedEmails)
            ->when($requiredRole, fn ($query, string $role) => $query->where('role', $role))
            ->first();

        if ($user) {
            if ($requiredRole === User::ROLE_SUPER_ADMIN
                && strtolower($user->email) === self::LEGACY_SUPER_ADMIN_EMAIL
                && ! User::query()->whereKeyNot($user->id)->whereRaw('LOWER(email) = ?', [self::SUPER_ADMIN_EMAIL])->exists()) {
                $user->forceFill(['email' => self::SUPER_ADMIN_EMAIL])->save();
            }
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with(
            'status',
            'If an active account uses that email address, a password reset link has been sent.'
        );
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
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
            ->withErrors(['email' => __($status)]);
    }
}
