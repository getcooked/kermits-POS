<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ManagesAccountAccess
{
    /**
     * Disabling signs the account out everywhere (web sessions, "remember me", the mobile app)
     * and blocks future sign-ins until it is enabled again.
     */
    private function disableAccount(User $user, string $label, string $errorKey = 'account'): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return back()->withErrors([$errorKey => 'You cannot disable your own account.']);
        }

        $user->forceFill(['disabled_at' => now()])->save();
        $this->revokeAccess($user);

        return back()->with('status', "{$user->name}'s {$label} account was disabled.");
    }

    private function enableAccount(User $user, string $label): RedirectResponse
    {
        $user->forceFill(['disabled_at' => null])->save();

        return back()->with('status', "{$user->name}'s {$label} account was enabled.");
    }

    private function revokeAccess(User $user, ?string $keepSessionId = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
        DB::table('mobile_api_tokens')->where('user_id', $user->id)->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }
}
