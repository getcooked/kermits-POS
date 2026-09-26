<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Models\User;
use App\Services\CustomerDetailsSchema;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function register(): View
    {
        return view('auth.register', [
            'pendingVerification' => session('registration_email_verification.email'),
            'verifiedEmail' => session('registration_email_verification.verified') === true
                ? session('registration_email_verification.email')
                : null,
        ]);
    }

    public function sendRegistrationCode(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160', 'regex:/^[^@\s]+@gmail\.com$/i', 'unique:users,email'],
        ], [
            'email.regex' => 'Please use a Gmail address.',
        ]);

        $code = (string) random_int(100000, 999999);

        session([
            'registration_email_verification' => [
                'email' => strtolower($validated['email']),
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10)->timestamp,
                'verified' => false,
            ],
        ]);

        try {
            Mail::raw("Your Kermit's verification code is {$code}. This code expires in 10 minutes.", function ($message) use ($validated): void {
                $message->to($validated['email'])
                    ->subject("Kermit's account verification code");
            });
        } catch (TransportExceptionInterface $exception) {
            session()->forget('registration_email_verification');
            Log::warning('Web registration email delivery failed.', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'The verification email could not be sent. Please try again later.']);
        }

        return back()->with('status', 'We sent a 6-digit verification code to your Gmail.');
    }

    public function verifyRegistrationCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $verification = session('registration_email_verification');

        if (! $verification || ($verification['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('registration_email_verification');

            return back()->withErrors(['code' => 'The verification code expired. Please request a new code.']);
        }

        if ((int) ($verification['attempts'] ?? 0) >= 5) {
            session()->forget('registration_email_verification');

            return back()->withErrors(['code' => 'Too many incorrect attempts. Please request a new code.']);
        }

        if (! Hash::check($validated['code'], $verification['code_hash'] ?? '')) {
            $verification['attempts'] = (int) ($verification['attempts'] ?? 0) + 1;
            if ($verification['attempts'] >= 5) {
                session()->forget('registration_email_verification');

                return back()->withErrors(['code' => 'Too many incorrect attempts. Please request a new code.']);
            }
            session(['registration_email_verification' => $verification]);

            return back()->withErrors(['code' => 'The verification code is incorrect.']);
        }

        $verification['verified'] = true;
        session(['registration_email_verification' => $verification]);

        return back()->with('status', 'Gmail verified. You can now create your account.');
    }

    public function storeRegistration(
        RegisterCustomerRequest $request,
        CustomerDetailsSchema $customerDetailsSchema,
    ): RedirectResponse {
        $verification = session('registration_email_verification');
        $email = strtolower($request->validated('email'));

        if (($verification['verified'] ?? false) !== true
            || ($verification['email'] ?? null) !== $email
            || (int) ($verification['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('registration_email_verification');

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Please verify this Gmail address before creating your account.']);
        }

        $customerDetailsSchema->ensure();

        $user = User::query()->create([
            ...$request->validated(),
            'role' => User::ROLE_CUSTOMER,
            'email_verified_at' => now(),
        ]);

        $request->session()->forget('registration_email_verification');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('shop');
    }

    public function store(LoginRequest $request, LoginAttemptLimiter $loginAttempts): RedirectResponse
    {
        $login = $request->string('email')->trim()->toString();
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $login = Str::lower($login);
        }

        if ($loginAttempts->isLocked($request, $login)) {
            return $this->lockoutResponse($request, $loginAttempts, $login);
        }

        $credentialLogin = $this->resolveSuperAdminEmail($login);
        $field = filter_var($credentialLogin, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $credentialLogin, 'password' => $request->validated('password')];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($loginAttempts->recordFailure($request, $login)) {
                return $this->lockoutResponse($request, $loginAttempts, $login);
            }

            return back()->withErrors([
                'email' => 'The username/email or password is incorrect.',
            ])->onlyInput('email');
        }

        if ($request->user()->isDisabled()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account has been disabled. Please contact a Super Admin.'])
                ->onlyInput('email');
        }

        $this->updateLegacySuperAdminEmail($request->user());
        $loginAttempts->clear($request, $login);
        $request->session()->regenerate();

        return redirect()->intended($request->user()->homeRoute());
    }

    private function resolveSuperAdminEmail(string $login): string
    {
        $superAdminEmail = $this->superAdminEmail();
        $legacyEmail = $this->legacySuperAdminEmail();
        if ($superAdminEmail === '' || $legacyEmail === '' || strtolower($login) !== $superAdminEmail
            || User::query()->whereRaw('LOWER(email) = ?', [$superAdminEmail])->exists()) {
            return $login;
        }

        return User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->whereRaw('LOWER(email) = ?', [$legacyEmail])
            ->exists() ? $legacyEmail : $login;
    }

    private function updateLegacySuperAdminEmail(User $user): void
    {
        $superAdminEmail = $this->superAdminEmail();
        $legacyEmail = $this->legacySuperAdminEmail();
        if ($superAdminEmail === '' || $legacyEmail === '' || $user->role !== User::ROLE_SUPER_ADMIN
            || strtolower($user->email) !== $legacyEmail
            || User::query()->whereKeyNot($user->id)->whereRaw('LOWER(email) = ?', [$superAdminEmail])->exists()) {
            return;
        }

        $user->forceFill(['email' => $superAdminEmail])->save();
    }

    private function superAdminEmail(): string
    {
        return Str::lower(trim((string) config('auth.staff.super_admin_email')));
    }

    private function legacySuperAdminEmail(): string
    {
        return Str::lower(trim((string) config('auth.staff.legacy_super_admin_email')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }

    private function lockoutResponse(
        LoginRequest $request,
        LoginAttemptLimiter $loginAttempts,
        string $login,
    ): RedirectResponse {
        $retryAfter = $loginAttempts->secondsRemaining($request, $login);
        $unit = $retryAfter === 1 ? 'second' : 'seconds';

        return redirect()->route('login')
            ->withErrors(['email' => "Too many login attempts. Try again in {$retryAfter} {$unit}."])
            ->withInput([
                'email' => $login,
                'remember' => $request->boolean('remember'),
            ])
            ->with('login_retry_after', $retryAfter);
    }
}
