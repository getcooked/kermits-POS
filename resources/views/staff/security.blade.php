@extends('layouts.app')
@section('title', 'Security · Kermit’s')
@section('content')
<div class="admin-shell security-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace">
        <div class="security-page">
            <header><p>SUPER ADMIN</p><h1>Security</h1><span>Verify your email before setting a new password.</span></header>

            @if(session('status'))<div class="security-message success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="security-message error">{{ $errors->first() }}</div>@endif
            @if(session('verification_sent'))
                <div class="security-toast" id="security-toast" role="status" aria-live="polite">
                    <span class="security-toast-icon">✓</span>
                    <span>{{ session('verification_sent') }}</span>
                    <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">×</button>
                </div>
            @endif

            <article class="security-form">
                <div class="security-title"><span>@include('partials.nav-icon',['name'=>'security'])</span><div><h2>Change my password</h2><p>Use the code sent to your email to authorize the change.</p></div></div>

                <div class="verification-step">
                    <div><span><strong>Email verification</strong><small>Send a one-time code to {{ auth()->user()->email }}. The code expires after 10 minutes.</small></span></div>
                    <form method="POST" action="{{ route('superadmin.security.email-code') }}">
                        @csrf
                        <button class="verification-send" type="submit">Send verification code</button>
                    </form>
                </div>

                <form class="password-form" method="POST" action="{{ route('superadmin.security.password.update') }}">
                    @csrf @method('PUT')
                    <div class="field"><label for="verification_code">Email verification code</label><input class="control verification-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div>
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
.security-shell{background:#f5f6ef}.security-page{width:min(760px,100%)}.security-page>header{margin-bottom:22px}.security-page>header p{margin:0;color:#7a8300;font-size:11px;letter-spacing:.16em}.security-page>header h1{margin:6px 0 4px;font-size:30px}.security-page>header span,.security-title p,.security-form small{color:#687286}.security-message{padding:13px 15px;margin-bottom:16px;border-radius:8px}.security-message.success{background:#eaf8ef;color:#267444}.security-message.error{background:#fff0f0;color:#b42318}.security-toast{position:fixed;z-index:100;top:24px;right:24px;max-width:min(420px,calc(100vw - 32px));display:grid;grid-template-columns:34px minmax(0,1fr) 26px;align-items:center;gap:11px;padding:14px 15px;border:1px solid #bfd4c4;border-radius:13px;background:#fff;color:#244e30;box-shadow:0 18px 46px rgba(23,24,23,.18);animation:security-toast-in .24s ease-out}.security-toast-icon{width:34px;height:34px;display:grid!important;place-items:center;border-radius:50%;background:#e7f5e9;color:#27703d!important;font-weight:900}.security-toast button{width:26px;height:26px;border:0;border-radius:7px;background:transparent;color:#687286;font-size:21px;line-height:1;cursor:pointer}.security-toast button:hover{background:#eef0e9;color:#171817}.security-toast.is-hiding{opacity:0;transform:translateY(-8px);transition:opacity .2s ease,transform .2s ease}.security-form{padding:24px;border:1px solid #daddd1;border-radius:12px;background:#fff}.security-title{display:flex;gap:14px;align-items:center;padding-bottom:20px;border-bottom:1px solid #e5e7df}.security-title>span{width:48px;height:48px;display:grid;place-items:center;border-radius:10px;background:#e9ecd4;color:#626b00}.security-title svg{width:24px;height:24px}.security-title h2{margin:0;font-size:20px}.security-title p{margin:4px 0 0}.verification-step{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:20px 0;padding:16px;border:1px solid #e2e5d8;border-radius:10px;background:#f9faf5}.verification-step>div{display:flex;align-items:flex-start}.verification-step span{display:grid;gap:4px}.verification-step small{line-height:1.45}.verification-step form{margin:0}.verification-send{min-height:40px;padding:9px 15px;border:1px solid #c8ccc1;border-radius:9px;background:#fff;color:#292b27;font-weight:750;white-space:nowrap;box-shadow:none;cursor:pointer}.verification-send:hover{border-color:#9ca761;background:#f0f2df;color:#4e5700}.password-form{display:grid;gap:15px}.security-form .field{margin:0}.security-form .control{min-height:49px;border-radius:8px}.security-form small{display:block;margin-top:5px}.security-form .button{min-height:49px;border-radius:8px}.verification-code{max-width:190px;font-size:19px!important;font-weight:750;letter-spacing:.28em}.password-form>.button{margin-top:3px}@keyframes security-toast-in{from{opacity:0;transform:translateY(-10px) scale(.98)}to{opacity:1;transform:none}}@media(max-width:620px){.security-form{padding:18px}.verification-step{align-items:stretch;flex-direction:column}.verification-send{width:100%}.verification-code{max-width:none}.security-toast{top:12px;right:16px;left:16px;max-width:none}}
</style>
@endpush
@if(session('verification_sent'))
@push('scripts')
<script>
window.setTimeout(function(){const toast=document.getElementById('security-toast');if(!toast)return;toast.classList.add('is-hiding');window.setTimeout(function(){toast.remove()},220)},4200);
</script>
@endpush
@endif
@endsection
