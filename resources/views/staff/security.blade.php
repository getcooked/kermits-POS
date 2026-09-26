@extends('layouts.app')
@section('title', 'Admin Account · Kermit’s')
@section('content')
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard admin-account">
    <header class="account-head"><div><p>SUPER ADMIN</p><h1>Admin Account</h1><span>Create and manage the accounts that can use the admin panel.</span></div></header>
    @include('partials.account-tabs')

    @if(session('status'))<div class="notice account-message">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error account-message">{{ $errors->first() }}</div>@endif

    <section class="account-summary-cards">
        <div class="welcome"><span>Admin Accounts</span><h2>{{ $admins->count() }}</h2></div>
        <div class="welcome"><span>Active Admin Accounts</span><h2>{{ $activeAdminCount }}</h2></div>
        <div class="welcome"><span>Disabled Admin Accounts</span><h2>{{ $disabledAdminCount }}</h2></div>
    </section>

    <div class="account-grid">
        <section class="welcome account-card create-admin">
            <p class="account-eyebrow">NEW ADMIN</p>
            <h2>Create Admin Account</h2>
            <form method="POST" action="{{ route('superadmin.admins.store') }}">@csrf
                {{-- Pressing Enter uses the first submit button, so it must be "create", not "Send code". --}}
                <button class="default-submit" type="submit" tabindex="-1" aria-hidden="true">Create Admin Account</button>
                <div class="field"><label for="name">Full name</label><input class="control" id="name" name="name" value="{{ old('name') }}" maxlength="100" required><small>Maximum 100 characters.</small></div>
                <div class="field"><label for="username">Username</label><input class="control" id="username" name="username" value="{{ old('username') }}" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]+" autocomplete="off" required><small>3–30 characters: letters, numbers, dots, underscores, and hyphens.</small></div>
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="email-verify-row">
                        <input class="control" id="email" name="email" type="email" value="{{ old('email', $verificationEmail) }}" autocomplete="off" required>
                        <button class="send-code" type="submit" formaction="{{ route('superadmin.admins.email-code') }}" formnovalidate>Send code</button>
                    </div>
                    <small>{{ $verificationEmail ? "A code was sent to {$verificationEmail}. It expires after 10 minutes." : 'The new admin must verify this email. Send a code, then enter it below.' }}</small>
                </div>
                <div class="field"><label for="verification_code">Email verification code</label><input class="control verification-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div>
                <div class="field"><label for="phone">Phone number</label><input class="control" id="phone" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" required><small>11 digits starting with 09.</small></div>
                <div class="field"><label for="password">Password</label><input class="control" id="password" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password" required><small>8-23 characters with uppercase, lowercase, number, and symbol.</small></div>
                <div class="field"><label for="password_confirmation">Confirm password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password" required></div>
                <button class="button" type="submit">Create Admin Account</button>
            </form>
        </section>

        <section class="welcome account-card">
            <div class="account-card-head"><div><p class="account-eyebrow">ADMIN PROFILE</p><h2>Admin accounts</h2></div><strong>{{ $admins->count() }}</strong></div>
            <div>
                @forelse($admins as $admin)
                    @php($isMe = $admin->is(auth()->user()))
                    <article @class(['account-record', 'is-disabled' => $admin->isDisabled()])>
                        <div class="account-record-summary">
                            <span>{{ strtoupper(substr($admin->name, 0, 1)) }}</span>
                            <div><strong>{{ $admin->name }}@if($isMe) <em>(you)</em>@endif</strong><small>{{ $admin->username ?: 'No username' }} · {{ $admin->email }}</small><small>{{ $admin->phone ?: 'No phone' }} · Created {{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></div>
                            <b @class(['account-badge', 'disabled' => $admin->isDisabled()])>{{ $admin->isDisabled() ? 'Disabled' : 'Super Admin' }}</b>
                        </div>
                        <details>
                            <summary>Edit account</summary>
                            <form class="edit-admin" method="POST" action="{{ route('superadmin.admins.update', $admin) }}" data-ajax-form data-ajax-target=".admin-account" data-ajax-loading="Saving...">@csrf @method('PUT')
                                <div class="field"><label>Full name</label><input class="control" name="name" value="{{ $admin->name }}" maxlength="100" required></div>
                                <div class="field"><label>Username</label><input class="control" name="username" value="{{ $admin->username }}" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]+"></div>
                                <div class="field"><label>Email</label><input class="control" name="email" type="email" value="{{ $admin->email }}" required></div>
                                <div class="field"><label>Phone</label><input class="control" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ $admin->phone }}"></div>
                                <div class="field"><label>New password <small>(optional)</small></label><input class="control" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                                <div class="field"><label>Confirm password</label><input class="control" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                                <button class="button" type="submit">Save changes</button>
                            </form>
                            @unless($isMe)
                                @if($admin->isDisabled())
                                    <form class="toggle-admin" method="POST" action="{{ route('superadmin.admins.enable', $admin) }}" data-ajax-form data-ajax-target=".admin-account" data-ajax-loading="Enabling...">@csrf @method('PATCH')<button class="enable" type="submit">Enable admin account</button></form>
                                @else
                                    <form class="toggle-admin" method="POST" action="{{ route('superadmin.admins.disable', $admin) }}" data-ajax-form data-ajax-target=".admin-account" data-ajax-loading="Disabling..." data-confirm="Disable this admin account? They will be signed out and cannot log in until it is enabled again." data-confirm-title="Disable admin account?">@csrf @method('PATCH')<button type="submit">Disable admin account</button></form>
                                @endif
                            @endunless
                        </details>
                    </article>
                @empty
                    <div class="account-empty">No admin accounts yet.</div>
                @endforelse
            </div>
        </section>
    </div>
</div></main></div>
@include('partials.account-page-styles')
@push('styles')
<style>
.default-submit{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}.create-admin .field{margin-bottom:13px}.create-admin small{display:block;color:#687286;margin-top:5px}
.email-verify-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}.send-code{min-height:44px;padding:0 15px;border:1px solid #c8ccc1;border-radius:9px;background:#fff;color:#292b27;font-weight:750;white-space:nowrap;cursor:pointer}.send-code:hover{border-color:#9ca761;background:#f0f2df;color:#4e5700}
.verification-code{max-width:190px;font-weight:750;letter-spacing:.28em}
.account-record strong em{font-style:normal;font-weight:600;color:#687286;font-size:13px}.account-record.is-disabled .account-record-summary{opacity:.6}.account-badge.disabled{background:#fff0f0;color:#b42318}
.account-record details{margin:11px 0 0 56px}.account-record summary{width:max-content;color:#596100;font-weight:800;font-size:13px;cursor:pointer}
.edit-admin{display:grid;grid-template-columns:1fr 1fr;gap:9px 12px;margin-top:13px;padding:15px;background:#f5f6ef;border-radius:11px}.edit-admin .field{margin:0}.edit-admin .button{grid-column:1/-1}
.toggle-admin{margin-top:10px;text-align:right}.toggle-admin button{border:1px solid #b42318;border-radius:9px;background:#fff7f7;color:#b42318;padding:9px 12px;font-weight:800;cursor:pointer}.toggle-admin button.enable{border-color:#267444;background:#edf8f0;color:#267444}
@media(max-width:600px){.edit-admin{grid-template-columns:1fr}.edit-admin .button{grid-column:auto}.account-record details{margin-left:0}.email-verify-row{grid-template-columns:1fr}.verification-code{max-width:none}}
</style>
@endpush
@endsection
