@extends('layouts.app')
@section('title', 'Admin Account · Kermit’s')
@section('content')
@php($admin = auth()->user())
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard admin-account">
    <header class="account-head"><div><p>SUPER ADMIN</p><h1>Admin Account</h1><span>Manage the Super Admin account and password security.</span></div></header>
    @include('partials.account-tabs')

    @if(session('status'))<div class="notice account-message">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error account-message">{{ $errors->first() }}</div>@endif
    @if(session('verification_sent'))
        <div id="security-toast" data-page-toast data-toast-title="Verification code sent" hidden>{{ session('verification_sent') }}</div>
    @endif

    <section class="account-summary-cards">
        <div class="welcome"><span>Cashier accounts</span><h2>{{ $cashierCount }}</h2></div>
        <div class="welcome"><span>Registered customers</span><h2>{{ $customerCount }}</h2></div>
        <div class="welcome"><span>Admin since</span><h2>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</h2></div>
    </section>

    <div class="account-grid">
        <section class="welcome account-card security-card">
            <p class="account-eyebrow">SECURITY</p>
            <h2>Change my password</h2>
            <p class="security-intro">Use the code sent to your email to authorize the change.</p>

            <div class="verification-step">
                <span><strong>Email verification</strong><small>Send a one-time code to {{ $admin->email }}. The code expires after 10 minutes.</small></span>
                <form method="POST" action="{{ route('superadmin.security.email-code') }}" data-ajax-form data-ajax-loading="Sending..." data-ajax-success="A verification code was sent to your email.">
                    @csrf
                    <button class="verification-send" type="submit">Send verification code</button>
                </form>
            </div>

            <form class="password-form" method="POST" action="{{ route('superadmin.security.password.update') }}" data-ajax-form data-ajax-loading="Updating..." data-ajax-reset="true">
                @csrf @method('PUT')
                <div class="field"><label for="verification_code">Email verification code</label><input class="control verification-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div>
                <div class="field"><label for="password">New password</label><input class="control" id="password" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password" required><small>8-23 characters with uppercase, lowercase, a number, and a symbol.</small></div>
                <div class="field"><label for="password_confirmation">Confirm new password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password" required></div>
                <button class="button" type="submit">Verify and change password</button>
            </form>
        </section>

        <section class="welcome account-card">
            <div class="account-card-head"><div><p class="account-eyebrow">YOUR ACCOUNT</p><h2>Admin profile</h2></div></div>
            <article class="account-record">
                <div class="account-record-summary">
                    <span>{{ strtoupper(substr($admin->name, 0, 1)) }}</span>
                    <div><strong>{{ $admin->name }}</strong><small>{{ $admin->username ?: 'No username' }} · {{ $admin->email }}</small><small>{{ $admin->phone ?: 'No phone' }} · Created {{ $admin->created_at?->format('M d, Y') ?? '—' }}</small></div>
                    <b class="account-badge">Super Admin</b>
                </div>
            </article>
            <dl class="profile-details">
                <div><dt>Sign-in email</dt><dd>{{ $admin->email }}</dd></div>
                <div><dt>Username</dt><dd>{{ $admin->username ?: 'Not set' }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $admin->phone ?: 'Not set' }}</dd></div>
                <div><dt>Role</dt><dd>Super Admin · full access</dd></div>
            </dl>
            <p class="profile-note">Password verification codes are sent to the sign-in email above.</p>
        </section>
    </div>
</div></main></div>
@include('partials.account-page-styles')
@push('styles')
<style>
.security-intro,.admin-account .field small,.profile-note{color:#687286}.security-intro{margin:-10px 0 0}.admin-account .field small{display:block;margin-top:5px}
.verification-step{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:18px 0;padding:16px;border:1px solid #e2e5d8;border-radius:10px;background:#f9faf5}.verification-step span{display:grid;gap:4px}.verification-step small{color:#687286;line-height:1.45}.verification-step form{margin:0}
.verification-send{min-height:40px;padding:9px 15px;border:1px solid #c8ccc1;border-radius:9px;background:#fff;color:#292b27;font-weight:750;white-space:nowrap;box-shadow:none;cursor:pointer}.verification-send:hover{border-color:#9ca761;background:#f0f2df;color:#4e5700}
.password-form{display:grid;gap:13px}.password-form .field{margin:0}.verification-code{max-width:190px;font-size:19px!important;font-weight:750;letter-spacing:.28em}
.profile-details{display:grid;gap:0;margin:4px 0 0;border-top:1px solid #e7eaf0}.profile-details div{display:grid;grid-template-columns:140px minmax(0,1fr);gap:12px;padding:12px 0;border-bottom:1px solid #eef0ea}.profile-details dt{color:#687286}.profile-details dd{margin:0;font-weight:700;overflow-wrap:anywhere}.profile-note{font-size:13px;margin:14px 0 0}
.security-toast{position:fixed;z-index:100;top:24px;right:24px;max-width:min(420px,calc(100vw - 32px));display:grid;grid-template-columns:34px minmax(0,1fr) 26px;align-items:center;gap:11px;padding:14px 15px;border:1px solid #bfd4c4;border-radius:13px;background:#fff;color:#244e30;box-shadow:0 18px 46px rgba(23,24,23,.18);animation:security-toast-in .24s ease-out}.security-toast-icon{width:34px;height:34px;display:grid!important;place-items:center;border-radius:50%;background:#e7f5e9;color:#27703d!important;font-weight:900}.security-toast button{width:26px;height:26px;border:0;border-radius:7px;background:transparent;color:#687286;font-size:21px;line-height:1;cursor:pointer}.security-toast button:hover{background:#eef0e9;color:#171817}.security-toast.is-hiding{opacity:0;transform:translateY(-8px);transition:opacity .2s ease,transform .2s ease}@keyframes security-toast-in{from{opacity:0;transform:translateY(-10px) scale(.98)}to{opacity:1;transform:none}}
@media(max-width:620px){.verification-step{align-items:stretch;flex-direction:column}.verification-send{width:100%}.verification-code{max-width:none}.profile-details div{grid-template-columns:1fr;gap:3px}.security-toast{top:12px;right:16px;left:16px;max-width:none}}
</style>
@endpush
@endsection
