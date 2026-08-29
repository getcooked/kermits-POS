<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Models\User;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

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
                'expires_at' => now()->addMinutes(10)->timestamp,
                'verified' => false,
            ],
        ]);

        Mail::raw("Your Kermit's verification code is {$code}. This code expires in 10 minutes.", function ($message) use ($validated): void {
            $message->to($validated['email'])
                ->subject("Kermit's account verification code");
        });

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

        if (! Hash::check($validated['code'], $verification['code_hash'] ?? '')) {
            return back()->withErrors(['code' => 'The verification code is incorrect.']);
        }

        $verification['verified'] = true;
        session(['registration_email_verification' => $verification]);

        return back()->with('status', 'Gmail verified. You can now create your account.');
    }

    public function storeRegistration(RegisterCustomerRequest $request): RedirectResponse
    {
        $verification = session('registration_email_verification');
        $email = strtolower($request->validated('email'));

        if (($verification['verified'] ?? false) !== true || ($verification['email'] ?? null) !== $email) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Please verify this Gmail address before creating your account.']);
        }

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

        if ($loginAttempts->isLocked($request, $login)) {
            return $this->lockoutResponse($request, $loginAttempts, $login);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $login, 'password' => $request->validated('password')];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($loginAttempts->recordFailure($request, $login)) {
                return $this->lockoutResponse($request, $loginAttempts, $login);
            }

            return back()->withErrors([
                'email' => 'The username/email or password is incorrect.',
            ])->onlyInput('email');
        }

        $loginAttempts->clear($request, $login);
        $request->session()->regenerate();

        return redirect()->intended($request->user()->homeRoute());
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

        return redirect()->route('login')
            ->withErrors(['email' => "Too many login attempts. Try again in {$retryAfter} seconds."])
            ->withInput([
                'email' => $login,
                'remember' => $request->boolean('remember'),
            ])
            ->with('login_retry_after', $retryAfter);
    }
}
