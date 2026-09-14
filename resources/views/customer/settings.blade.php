@extends('layouts.app')
@section('title', 'Settings | Kermit\'s')

@section('content')
<main class="history-page customer-account-page">
    @include('customer.navigation', ['activeCustomerNav' => 'settings'])

    <header class="account-header">
        <p>MY ACCOUNT</p>
        <h1>Settings</h1>
        <span>Manage your sign-in security and review account information.</span>
    </header>

    <section class="account-content">
        @if(session('status'))<div class="account-notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="account-error" role="alert">{{ $errors->first() }}</div>@endif

        <div class="account-grid">
            <section class="account-card">
                <h2>Change password</h2>
                <p>Confirm your current password before choosing a new one.</p>

                <form class="account-form" method="POST" action="{{ route('customer.settings.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="field full">
                        <label for="current_password">Current password</label>
                        <input class="control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input class="control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                    </div>

                    <div class="password-rules full">Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol. Changing your password signs out other web browsers and mobile app sessions.</div>
                    <button class="account-submit" type="submit">Update password</button>
                </form>
            </section>

            <aside class="identity-panel">
                <div class="identity-card">
                    <div class="identity-avatar" aria-hidden="true">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                    <h2>Account security</h2>
                    <p>{{ $customer->email }}</p>
                    <dl class="identity-list">
                        <div><dt>Email status</dt><dd class="verified-badge">Verified</dd></div>
                        <div><dt>Last updated</dt><dd>{{ $customer->updated_at->format('M d, Y') }}</dd></div>
                    </dl>
                </div>
                <div class="security-list">
                    <article><strong>Verified email</strong><span>Your email is protected as the primary sign-in and recovery address.</span></article>
                    <article><strong>Session protection</strong><span>A password change revokes other active sessions and remembered logins.</span></article>
                </div>
            </aside>
        </div>
    </section>
</main>
@push('styles')
    @include('customer.account-styles')
@endpush
@endsection
