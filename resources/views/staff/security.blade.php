@extends('layouts.app')
@section('title', 'Security · Kermit’s')
@section('content')
<div class="admin-shell security-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace">
        <div class="security-page">
            <header><p>SUPER ADMIN</p><h1>Security</h1><span>Change your own password without using email recovery.</span></header>

            @if(session('status'))<div class="security-message success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="security-message error">{{ $errors->first() }}</div>@endif

            <section class="security-grid">
                <article class="security-form">
                    <div class="security-title"><span>@include('partials.nav-icon',['name'=>'security'])</span><div><h2>Change my password</h2><p>Confirm your current password before choosing a new one.</p></div></div>
                    <form method="POST" action="{{ route('superadmin.security.password.update') }}">
                        @csrf @method('PUT')
                        <div class="field"><label for="current_password">Current password</label><input class="control" id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                        <div class="field"><label for="password">New password</label><input class="control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required><small>12+ characters with uppercase, lowercase, a number, and a symbol.</small></div>
                        <div class="field"><label for="password_confirmation">Confirm new password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
                        <button class="button" type="submit">Change my password</button>
                    </form>
                </article>

                <aside class="security-notes">
                    <p>AFTER YOU SAVE</p>
                    <h2>Your account stays protected</h2>
                    <ul><li>This browser remains signed in.</li><li>Other web sessions are signed out.</li><li>Mobile access tokens are revoked.</li><li>Unused reset links are deleted.</li></ul>
                    <div><strong>Super Admin only</strong><span>Admins, Cashiers, and Customers cannot open or submit this page.</span></div>
                </aside>
            </section>
        </div>
    </main>
</div>
<style>
.security-shell{background:#f5f6ef}.security-page{width:min(1060px,100%)}.security-page>header{margin-bottom:22px}.security-page>header p,.security-notes>p{margin:0;color:#7a8300;font-size:11px;letter-spacing:.16em}.security-page>header h1{margin:6px 0 4px;font-size:30px}.security-page>header span,.security-title p,.security-form small,.security-notes li,.security-notes div span{color:#687286}.security-message{padding:13px 15px;margin-bottom:16px;border-radius:8px}.security-message.success{background:#eaf8ef;color:#267444}.security-message.error{background:#fff0f0;color:#b42318}.security-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:18px}.security-form,.security-notes{padding:24px;border:1px solid #daddd1;border-radius:8px;background:#fff}.security-title{display:flex;gap:14px;align-items:center;padding-bottom:20px;border-bottom:1px solid #e5e7df}.security-title>span{width:48px;height:48px;display:grid;place-items:center;border-radius:8px;background:#e9ecd4;color:#626b00}.security-title svg{width:24px;height:24px}.security-title h2,.security-notes h2{margin:0;font-size:20px}.security-title p{margin:4px 0 0}.security-form form{display:grid;gap:15px;margin-top:20px}.security-form .field{margin:0}.security-form .control{min-height:49px;border-radius:8px}.security-form small{display:block;margin-top:5px}.security-form .button{min-height:49px;border-radius:8px}.security-notes{align-self:start;background:#f9faf5}.security-notes h2{margin:8px 0 16px}.security-notes ul{display:grid;gap:12px;margin:0;padding-left:20px}.security-notes div{display:grid;gap:5px;margin-top:22px;padding:14px;border-radius:8px;background:#e9ecd4}.security-notes div span{font-size:13px;line-height:1.45}@media(max-width:900px){.security-grid{grid-template-columns:1fr}}@media(max-width:560px){.security-form,.security-notes{padding:18px}}
</style>
@endsection
