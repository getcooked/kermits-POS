@extends('layouts.app')
@section('title','Admin Accounts · Kermit’s')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace admin-accounts-page">
        <div class="dashboard">
            <header class="accounts-head"><div><p>SUPER ADMIN</p><h1>Admin accounts</h1><span>Reset an Admin password and revoke their active sessions.</span></div><strong>{{ $admins->count() }}</strong></header>
            @if(session('status'))<div class="account-message success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="account-message error">{{ $errors->first() }}</div>@endif

            <section class="admin-account-list">
                @forelse($admins as $admin)
                <article>
                    <header><span>{{ strtoupper(substr($admin->name,0,1)) }}</span><div><h2>{{ $admin->name }}</h2><p>{{ $admin->username ?: 'No username' }} · {{ $admin->email }}</p></div><b>Admin</b></header>
                    <form method="POST" action="{{ route('admins.password.update',$admin) }}">
                        @csrf @method('PUT')
                        <div class="field"><label for="password-{{ $admin->id }}">New password</label><input class="control" id="password-{{ $admin->id }}" name="password" type="password" minlength="12" autocomplete="new-password" required><small>12+ characters with uppercase, lowercase, number, and symbol.</small></div>
                        <div class="field"><label for="password-confirmation-{{ $admin->id }}">Confirm new password</label><input class="control" id="password-confirmation-{{ $admin->id }}" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
                        <button class="button" type="submit">Change password</button>
                    </form>
                </article>
                @empty
                <div class="empty-admins"><strong>No Admin accounts found</strong><span>There are no Admin passwords available to reset.</span></div>
                @endforelse
            </section>
        </div>
    </main>
</div>
<style>
.admin-accounts-page{background:#f5f6ef}.accounts-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}.accounts-head p{margin:0;color:#7a8300;font-size:11px;letter-spacing:.16em}.accounts-head h1{margin:6px 0 4px;font-size:30px}.accounts-head span,.admin-account-list header p,.admin-account-list small,.empty-admins span{color:#687286}.accounts-head>strong{width:44px;height:44px;display:grid;place-items:center;border-radius:8px;background:#e9ecd4;color:#626b00}.account-message{padding:12px 14px;margin-bottom:16px;border-radius:8px}.account-message.success{background:#eaf8ef;color:#267444}.account-message.error{background:#fff0f0;color:#b42318}.admin-account-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.admin-account-list article{padding:22px;border:1px solid #daddd1;border-radius:8px;background:#fff}.admin-account-list article>header{display:grid;grid-template-columns:46px minmax(0,1fr) auto;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid #e5e7df}.admin-account-list header>span{width:46px;height:46px;display:grid;place-items:center;border-radius:50%;background:#e9ecd4;color:#626b00;font-weight:800}.admin-account-list h2{margin:0;font-size:18px}.admin-account-list header p{margin:4px 0 0;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-account-list header b{padding:5px 8px;border-radius:999px;background:#f0f1ed;font-size:11px}.admin-account-list form{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}.admin-account-list .field{margin:0}.admin-account-list .control{min-height:46px;border-radius:8px}.admin-account-list small{display:block;margin-top:5px;font-size:11px}.admin-account-list .button{grid-column:1/-1;min-height:46px;border-radius:8px}.empty-admins{grid-column:1/-1;padding:40px;border:1px dashed #cfd2c7;border-radius:8px;background:#fff;text-align:center;display:grid;gap:5px}@media(max-width:980px){.admin-account-list{grid-template-columns:1fr}}@media(max-width:560px){.admin-account-list form{grid-template-columns:1fr}.admin-account-list .button{grid-column:auto}.admin-account-list article{padding:17px}.admin-account-list article>header{grid-template-columns:42px minmax(0,1fr)}.admin-account-list header b{grid-column:2;width:max-content}}
</style>
@endsection
