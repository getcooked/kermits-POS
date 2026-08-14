<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use RuntimeException;

class WebSeederController extends Controller
{
    public function show(): View
    {
        $this->ensureEnabled();

        return view('seeder', ['complete' => File::exists($this->lockPath())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        if (File::exists($this->lockPath())) {
            return back()->with('status', 'Account setup has already been completed.');
        }

        $validated = $request->validate([
            'deployment_key' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if (! hash_equals((string) config('web-seeder.key'), $validated['deployment_key'])) {
            return back()->withErrors(['deployment_key' => 'The deployment key is invalid.']);
        }

        DB::transaction(function () use ($validated): void {
            foreach ($this->accounts() as $account) {
                $user = User::withTrashed()->updateOrCreate(
                    ['email' => $account['email']],
                    [...$account, 'password' => $validated['password'], 'email_verified_at' => now()],
                );

                if ($user->trashed()) {
                    $user->restore();
                }
            }
        });

        File::ensureDirectoryExists(dirname($this->lockPath()));

        if (File::put($this->lockPath(), now()->toIso8601String()) === false) {
            throw new RuntimeException('Unable to lock the account setup page.');
        }

        return redirect()->route('web-seeder.show')->with('status', 'System accounts created. The setup page is now locked.');
    }

    private function ensureEnabled(): void
    {
        abort_unless(
            config('web-seeder.enabled') && strlen((string) config('web-seeder.key')) >= 32,
            404,
        );
    }

    private function lockPath(): string
    {
        return storage_path('app/web-seeder.lock');
    }

    private function accounts(): array
    {
        return [
            ['name' => 'Super Administrator', 'username' => 'superadmin', 'email' => 'superadmin@gmail.com', 'phone' => '09170000001', 'role' => User::ROLE_SUPER_ADMIN],
            ['name' => 'Administrator', 'username' => 'admin', 'email' => 'admin@gmail.com', 'phone' => '09170000002', 'role' => User::ROLE_ADMIN],
            ['name' => 'Cashier', 'username' => 'cashier', 'email' => 'cashier@gmail.com', 'phone' => '09170000003', 'role' => User::ROLE_CASHIER],
            ['name' => 'Customer', 'username' => 'customer', 'email' => 'customer@gmail.com', 'phone' => '09170000004', 'role' => User::ROLE_CUSTOMER],
        ];
    }
}
