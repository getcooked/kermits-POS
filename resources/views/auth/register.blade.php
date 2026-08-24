@extends('layouts.app')
@section('title','Create customer account')
@section('content')
@php
    $verifiedEmail = $verifiedEmail ?? null;
    $pendingVerification = $pendingVerification ?? null;
@endphp
<main class="register-page">
    <section class="register-shell">
        <aside>
            <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
            <div><span>CUSTOMER ACCOUNT</span><h1>Order your<br>favorites.</h1><p>Verify your Gmail first, then create your customer account securely.</p></div>
        </aside>
        <div class="register-form">
            <div>
                <p class="eyebrow">SIGN UP</p>
                <h2>Create your account</h2>
                <p class="muted">Already registered? <a href="{{ route('login') }}">Log in</a>.</p>
                @if(session('status'))<div class="notice register-notice">{{ session('status') }}</div>@endif
                @if($errors->any())<div class="error register-error">{{ $errors->first() }}</div>@endif

                <section class="verify-card">
                    <div class="verify-head">
                        <span class="{{ $verifiedEmail ? 'done' : '' }}">{{ $verifiedEmail ? 'Verified' : 'Step 1' }}</span>
                        <div><strong>Gmail verification</strong><small>Use a Gmail address you can open now.</small></div>
                    </div>
                    @unless($verifiedEmail)
                        <form method="POST" action="{{ route('register.email') }}">
                            @csrf
                            <div class="field"><label for="verify-email">Gmail address</label><input class="control" id="verify-email" name="email" type="email" value="{{ old('email', $pendingVerification) }}" autocomplete="email" placeholder="name@gmail.com" required></div>
                            <button class="verify-button" type="submit">Send code</button>
                        </form>
                        @if($pendingVerification)
                            <form method="POST" action="{{ route('register.email.verify') }}">
                                @csrf
                                <div class="field"><label for="code">Verification code</label><input class="control code-input" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="6-digit code" required></div>
                                <button class="verify-button dark" type="submit">Verify Gmail</button>
                            </form>
                        @endif
                    @else
                        <div class="verified-email">{{ $verifiedEmail }}</div>
                    @endunless
                </section>

                <form method="POST" action="{{ route('register.store') }}" class="{{ $verifiedEmail ? '' : 'locked-form' }}">
                    @csrf
                    <div class="field"><label>Full name</label><input class="control" name="name" value="{{ old('name') }}" maxlength="120" required @disabled(! $verifiedEmail)></div>
                    <div class="field"><label>Username</label><input class="control" name="username" value="{{ old('username') }}" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required @disabled(! $verifiedEmail)><small>Letters, numbers, dots, underscores, and hyphens only.</small></div>
                    <div class="field"><label>Email address</label><input class="control" name="email" type="email" value="{{ old('email', $verifiedEmail) }}" autocomplete="email" readonly required></div>
                    <div class="field"><label>Phone number</label><input class="control" name="phone" type="tel" inputmode="numeric" value="{{ old('phone') }}" minlength="11" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" required @disabled(! $verifiedEmail)><small>11 digits starting with 09.</small></div>
                    <div class="field"><label>Password</label><input class="control" name="password" type="password" minlength="12" autocomplete="new-password" required @disabled(! $verifiedEmail)><small>12+ characters with uppercase, lowercase, number, and symbol.</small></div>
                    <div class="field"><label>Confirm password</label><input class="control" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required @disabled(! $verifiedEmail)></div>
                    @unless($verifiedEmail)<p class="locked-note">Verify your Gmail first to unlock account creation.</p>@endunless
                    <button class="register-button" @disabled(! $verifiedEmail)>Create account <span>&rarr;</span></button>
                </form>
            </div>
        </div>
    </section>
</main>
<style>
.register-page{min-height:100dvh;display:grid;place-items:center;padding:12px;background:#dfe2de}.register-shell{width:min(1050px,100%);height:min(760px,calc(100dvh - 24px));min-height:620px;display:grid;grid-template-columns:1fr 1.05fr;border:8px solid #181918;border-radius:28px;overflow:hidden;background:#f5f6ef}.register-shell aside{background:#171817;color:#fff;padding:42px;display:flex;flex-direction:column}.register-shell aside img{width:86px;height:86px;border-radius:50%;object-fit:contain;background:#fff;padding:7px}.register-shell aside div{margin:auto 0}.register-shell aside span,.eyebrow{font-size:12px;letter-spacing:.16em;color:#aab514}.register-shell aside h1{font-size:40px;line-height:1.08;margin:12px 0}.register-shell aside p{color:#b9bcb5;line-height:1.7}.register-form{display:grid;place-items:center;padding:26px;overflow:auto}.register-form>div{width:min(410px,100%)}.register-form h2{font-size:29px;margin:7px 0}.register-error,.register-notice{padding:12px;border-radius:9px;margin-bottom:14px}.register-error{background:#fff0f0}.register-notice{background:#edf8f0;border:1px solid #b8dbc4}.register-button{width:100%;border:0;border-radius:11px;padding:14px 17px;background:#171817;color:#fff;font-weight:700;display:flex;justify-content:space-between}.register-button:disabled{opacity:.45;cursor:not-allowed}.verify-card{border:1px solid #daddd1;border-radius:14px;background:#fffefa;padding:14px;margin:18px 0}.verify-head{display:flex;gap:10px;align-items:center;margin-bottom:12px}.verify-head>span{width:52px;height:28px;border-radius:999px;background:#f0f1ed;color:#667000;display:grid;place-items:center;font-size:11px;font-weight:800}.verify-head>span.done{background:#e5f5e9;color:#267444}.verify-head div{display:grid}.verify-head small{color:#687286;margin-top:2px}.verify-button{width:100%;border:1px solid #cfd2c8;border-radius:10px;background:#f5f6ef;color:#171817;padding:12px 14px;font-weight:800}.verify-button.dark{background:#171817;color:#fff;border-color:#171817}.code-input{text-align:center;font-size:22px!important;letter-spacing:.24em}.verified-email{padding:12px;border-radius:10px;background:#e5f5e9;color:#267444;font-weight:800;text-align:center}.locked-form{opacity:.62}.locked-note{text-align:center;color:#687286;font-size:13px;margin:8px 0 12px}@media(max-width:760px){.register-page{padding:0}.register-shell{height:auto;min-height:100dvh;border:0;border-radius:0;grid-template-columns:1fr}.register-shell aside{padding:18px;min-height:140px}.register-shell aside img{width:54px;height:54px}.register-shell aside div{margin:10px 0}.register-shell aside h1{font-size:23px;margin:4px 0}.register-shell aside p{display:none}.register-form{padding:24px 20px}}
body{background:#f5f5ef}.register-page{display:block;min-height:100dvh;padding:0}.register-shell{width:100%;height:100dvh;min-height:680px;grid-template-columns:minmax(350px,44%) minmax(0,56%);border:0;border-radius:0;background:#f7f7f1}.register-shell aside{padding:clamp(36px,5vw,72px);background:radial-gradient(circle at 15% 15%,#30332b 0,transparent 28%),linear-gradient(145deg,#131413,#1c1e1a)}.register-shell aside img{width:clamp(88px,8vw,120px);height:clamp(88px,8vw,120px)}.register-shell aside h1{font-size:clamp(42px,4vw,62px);letter-spacing:-.04em}.register-form{padding:clamp(28px,5vw,72px);background:radial-gradient(circle at 100% 0,#f0f1d8 0,transparent 32%),#f7f7f1}.register-form>div{width:min(520px,100%)}.register-form>div>.global-back{width:max-content;margin-bottom:34px;background:#fffefa;box-shadow:0 8px 24px rgba(22,24,20,.08)}.register-form h2{font-size:clamp(31px,3vw,42px)}.register-form .control{min-height:50px;border-radius:12px}.register-button{min-height:54px;border-radius:13px;font-size:16px;box-shadow:0 10px 24px rgba(23,24,23,.16)}
@media(max-width:800px){.register-shell{height:auto;min-height:100dvh;grid-template-columns:1fr}.register-shell aside{min-height:170px;padding:22px}.register-shell aside img{width:62px;height:62px}.register-shell aside div{margin:14px 0 0}.register-shell aside h1{font-size:27px}.register-shell aside p{display:none}.register-form{overflow:visible;padding:24px 18px 44px}.register-form>div>.global-back{margin-bottom:24px}}
</style>

@endsection
