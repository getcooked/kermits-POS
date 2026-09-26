<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminAccountRequest;
use App\Http\Requests\UpdateAdminAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SuperAdminAccountController extends Controller
{
    private const VERIFICATION_SESSION_KEY = 'admin_email_verification';

    private const VERIFICATION_MINUTES = 10;

    private const MAX_CODE_ATTEMPTS = 5;

    public function index(Request $request): View
    {
        $admins = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->orderByRaw('disabled_at is not null')
            ->orderBy('name')
            ->get();

        return view('staff.security', [
            'admins' => $admins,
            'activeAdminCount' => $admins->whereNull('disabled_at')->count(),
            'disabledAdminCount' => $admins->whereNotNull('disabled_at')->count(),
            'verificationEmail' => $request->session()->get(self::VERIFICATION_SESSION_KEY.'.email'),
        ]);
    }

    public function sendVerificationCode(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $email = $request->validate([
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
        ])['email'];
        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::VERIFICATION_SESSION_KEY, [
            'email' => $email,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::VERIFICATION_MINUTES)->timestamp,
        ]);

        try {
            Mail::raw(
                "Your Kermit's admin account verification code is {$code}. It expires in ".self::VERIFICATION_MINUTES.' minutes.',
                fn ($message) => $message->to($email)->subject("Kermit's admin account verification code"),
            );
        } catch (TransportExceptionInterface $exception) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);
            Log::warning('Admin account verification email delivery failed.', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return back()
                ->withInput($this->safeInput($request))
                ->withErrors(['email' => 'The verification email could not be sent. Please try again later.']);
        }

        return back()
            ->withInput($this->safeInput($request))
            ->with('status', "We sent a 6-digit verification code to {$email}.");
    }

    public function store(StoreAdminAccountRequest $request): RedirectResponse
    {
        $this->ensureEmailVerified($request);

        User::query()->create([
            ...$request->safe()->except(['verification_code', 'password_confirmation']),
            'role' => User::ROLE_SUPER_ADMIN,
            'email_verified_at' => now(),
        ]);
        $request->session()->forget(self::VERIFICATION_SESSION_KEY);

        return redirect()->route('superadmin.security.edit')->with('status', 'Admin account created successfully.');
    }

    public function update(UpdateAdminAccountRequest $request, User $admin): RedirectResponse
    {
        abort_unless($admin->hasRole(User::ROLE_SUPER_ADMIN), 404);

        $data = $request->safe()->except(['password', 'password_confirmation']);
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }
        $admin->update($data);

        if ($request->filled('password')) {
            $this->revokeAccess($admin, keepSessionId: $admin->is($request->user()) ? $request->session()->getId() : null);
        }

        return back()->with('status', 'Admin account updated successfully.');
    }

    public function disable(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->hasRole(User::ROLE_SUPER_ADMIN), 404);

        if ($admin->is($request->user())) {
            return back()->withErrors(['admin' => 'You cannot disable your own account.']);
        }

        $admin->forceFill(['disabled_at' => now()])->save();
        $this->revokeAccess($admin);

        return back()->with('status', "{$admin->name}'s admin account was disabled.");
    }

    public function enable(User $admin): RedirectResponse
    {
        abort_unless($admin->hasRole(User::ROLE_SUPER_ADMIN), 404);

        $admin->forceFill(['disabled_at' => null])->save();

        return back()->with('status', "{$admin->name}'s admin account was enabled.");
    }

    private function ensureEmailVerified(StoreAdminAccountRequest $request): void
    {
        $verification = $request->session()->get(self::VERIFICATION_SESSION_KEY);
        $fail = fn (string $message) => throw ValidationException::withMessages(['verification_code' => $message]);

        if (! $verification || ($verification['email'] ?? null) !== $request->validated('email')) {
            $fail('Send a verification code to this email address first.');
        }

        if (($verification['expires_at'] ?? 0) < now()->timestamp || ($verification['attempts'] ?? 0) >= self::MAX_CODE_ATTEMPTS) {
            $request->session()->forget(self::VERIFICATION_SESSION_KEY);
            $fail('The verification code expired or had too many attempts. Please send a new code.');
        }

        if (! Hash::check($request->validated('verification_code'), $verification['code_hash'] ?? '')) {
            $verification['attempts'] = (int) ($verification['attempts'] ?? 0) + 1;
            $request->session()->put(self::VERIFICATION_SESSION_KEY, $verification);
            $fail('The verification code is incorrect.');
        }
    }

    /**
     * Signs the admin out everywhere, except the current browser when they changed their own password.
     */
    private function revokeAccess(User $admin, ?string $keepSessionId = null): void
    {
        $admin->forceFill(['remember_token' => Str::random(60)])->save();
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $admin->id)
            ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
        DB::table('mobile_api_tokens')->where('user_id', $admin->id)->delete();
        DB::table('password_reset_tokens')->where('email', $admin->email)->delete();
    }

    private function safeInput(Request $request): array
    {
        return $request->except(['password', 'password_confirmation', 'verification_code', '_token']);
    }
}
