@extends('layouts.app')
@section('title', 'Security · Kermit’s')
@section('content')
<div class="admin-shell security-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace">
        <div class="security-page">
            <header><p>SUPER ADMIN</p><h1>Security</h1><span>Verify your email and current password before setting a new password.</span></header>

            @if(session('status'))<div class="security-message success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="security-message error">{{ $errors->first() }}</div>@endif

            <article class="security-form">
                <div class="security-title"><span>@include('partials.nav-icon',['name'=>'security'])</span><div><h2>Change my password</h2><p>Complete both verification steps to protect this account.</p></div></div>

                <div class="verification-step">
                    <div><b>1</b><span><strong>Verify your email</strong><small>Send a one-time code to {{ auth()->user()->email }}. The code expires after 10 minutes.</small></span></div>
                    <form method="POST" action="{{ route('superadmin.security.email-code') }}">
                        @csrf
                        <button class="button" type="submit">Send verification code</button>
                    </form>
                </div>

                <form class="password-form" method="POST" action="{{ route('superadmin.security.password.update') }}">
                    @csrf @method('PUT')
                    <div class="field"><label for="verification_code">Email verification code</label><input class="control verification-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div>
                    <div class="step-divider"><b>2</b><span>Confirm password and update</span></div>
                    <div class="field"><label for="current_password">Current password</label><input class="control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                    <div class="field"><label for="password">New password</label><input class="control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required><small>12+ characters with uppercase, lowercase, a number, and a symbol.</small></div>
                    <div class="field"><label for="password_confirmation">Confirm new password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
                    <button class="button" type="submit">Verify and change password</button>
                </form>
            </article>
        </div>
    </main>
</div>
@push('styles')
<style>
.security-shell{background:#f5f6ef}.security-page{width:min(760px,100%)}.security-page>header{margin-bottom:22px}.security-page>header p{margin:0;color:#7a8300;font-size:11px;letter-spacing:.16em}.security-page>header h1{margin:6px 0 4px;font-size:30px}.security-page>header span,.security-title p,.security-form small{color:#687286}.security-message{padding:13px 15px;margin-bottom:16px;border-radius:8px}.security-message.success{background:#eaf8ef;color:#267444}.security-message.error{background:#fff0f0;color:#b42318}.security-form{padding:24px;border:1px solid #daddd1;border-radius:12px;background:#fff}.security-title{display:flex;gap:14px;align-items:center;padding-bottom:20px;border-bottom:1px solid #e5e7df}.security-title>span{width:48px;height:48px;display:grid;place-items:center;border-radius:10px;background:#e9ecd4;color:#626b00}.security-title svg{width:24px;height:24px}.security-title h2{margin:0;font-size:20px}.security-title p{margin:4px 0 0}.verification-step{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:20px 0;padding:16px;border:1px solid #e2e5d8;border-radius:10px;background:#f9faf5}.verification-step>div{display:flex;align-items:flex-start;gap:12px}.verification-step b,.step-divider b{flex:0 0 auto;width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:#171817;color:#fff;font-size:12px}.verification-step span{display:grid;gap:4px}.verification-step small{line-height:1.45}.verification-step form{margin:0}.verification-step .button{min-height:42px;white-space:nowrap}.password-form{display:grid;gap:15px}.security-form .field{margin:0}.security-form .control{min-height:49px;border-radius:8px}.security-form small{display:block;margin-top:5px}.security-form .button{min-height:49px;border-radius:8px}.verification-code{max-width:190px;font-size:19px!important;font-weight:750;letter-spacing:.28em}.step-divider{display:flex;align-items:center;gap:10px;padding-top:2px;font-weight:750}.password-form>.button{margin-top:3px}@media(max-width:620px){.security-form{padding:18px}.verification-step{align-items:stretch;flex-direction:column}.verification-step .button{width:100%}.verification-code{max-width:none}}
</style>
@endpush
@endsection
