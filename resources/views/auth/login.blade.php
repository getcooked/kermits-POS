@extends('layouts.app')
@section('title','Log in · Kermit’s')
@section('content')
<main class="login-page">
    <section class="login-shell">
        <div class="login-brand"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
            <div><span>RESTAURANT POS</span>
                <h1>Simple tools for<br>better service.</h1>
                <p>Manage sales, products, inventory, reports, and receipts from one reliable system.</p>
            </div><small>Time-honored recipes since 2000</small>
        </div>
        <div class="login-form">
            <div class="login-inner">
                <p class="eyebrow">WELCOME BACK</p>
                <h2 id="login-title">Log in to your account</h2>
                <p class="muted">Enter your details to continue to Kermit’s.</p>
                @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
                @php($loginRetryAfter = max(0, (int) session('login_retry_after', 0)))
                <form id="login-form" method="POST" action="{{ route('login.store') }}" data-retry-after="{{ $loginRetryAfter }}">@csrf
                    <div class="field"><label for="email">Username or email address</label><input class="control" id="email" name="email" type="text" value="{{ old('email') }}" autocomplete="username" placeholder="Username or name@gmail.com" required autofocus>@error('email')@if($loginRetryAfter > 0)<p class="error" id="login-lockout" role="status" aria-live="polite">Too many login attempts. Try again in <span id="login-retry-seconds">{{ $loginRetryAfter }}</span> seconds.</p>@else<p class="error">{{ $message }}</p>@endif @enderror</div>
                    <div class="field"><label for="password">Password</label><input class="control" id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>@error('password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div class="login-options"><label class="check" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))> Keep me signed in</label><a href="{{ route('password.request') }}">Forgot password?</a></div><button class="login-button" id="login-submit" type="submit"><span id="login-submit-label">{{ $loginRetryAfter > 0 ? "Try again in {$loginRetryAfter}s" : 'Log in' }}</span> <span>&rarr;</span></button>
                    <p style="text-align:center;margin:18px 0 0;color:#687286">New customer? <a href="{{ route('register') }}">Create an account</a></p>
                </form>
            </div>
        </div>
    </section>
</main>
<style>
    .login-page {
        min-height: 100dvh;
        display: grid;
        place-items: center;
        padding: 12px;
        background: #dfe2de
    }

    .login-shell {
        width: min(1100px, 100%);
        height: min(720px, calc(100dvh - 24px));
        min-height: 560px;
        display: grid;
        grid-template-columns: 1fr 1.05fr;
        border: 8px solid #181918;
        border-radius: 28px;
        overflow: hidden;
        background: #f5f6ef;
        box-shadow: 0 30px 80px rgba(22, 25, 22, .16)
    }

    .login-brand {
        background: #171817;
        color: #fff;
        padding: 48px;
        display: flex;
        flex-direction: column
    }

    .login-brand>img {
        width: 94px;
        height: 94px;
        border-radius: 50%;
        object-fit: contain;
        background: white;
        padding: 7px
    }

    .login-brand>div {
        margin: auto 0
    }

    .login-brand span,
    .eyebrow {
        font-size: 12px;
        letter-spacing: .16em;
        color: #aab514
    }

    .login-brand h1 {
        font-size: 42px;
        line-height: 1.08;
        margin: 12px 0 18px
    }

    .login-brand p {
        color: #b9bcb5;
        max-width: 370px;
        line-height: 1.7
    }

    .login-brand small {
        color: #858982
    }

    .login-form {
        display: grid;
        place-items: center;
        padding: 36px
    }

    .login-inner {
        width: min(420px, 100%)
    }

    .login-inner h2 {
        font-size: 30px;
        margin: 8px 0
    }

    .login-inner .muted {
        margin-bottom: 28px
    }

    .login-inner .control {
        padding: 13px 14px
    }

    .login-button {
        width: 100%;
        border: 0;
        border-radius: 11px;
        padding: 14px 17px;
        background: #171817;
        color: #fff;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        cursor: pointer
    }

    .login-button:hover {
        background: #30322e
    }

    .login-button:disabled {
        cursor: not-allowed;
        opacity: .58
    }

    @media(max-width:760px) {
        .login-page {
            padding: 0
        }

        .login-shell {
            height: auto;
            min-height: 100dvh;
            border: 0;
            border-radius: 0;
            grid-template-columns: 1fr
        }

        .login-brand {
            padding: 22px;
            min-height: 190px
        }

        .login-brand>img {
            width: 62px;
            height: 62px
        }

        .login-brand>div {
            margin: 22px 0 0
        }

        .login-brand h1 {
            font-size: 27px;
            margin: 8px 0
        }

        .login-brand p,
        .login-brand small {
            display: none
        }

        .login-form {
            padding: 30px 22px
        }
    }

    @media(max-height:650px) and (min-width:761px) {
        .login-brand {
            padding: 30px
        }

        .login-brand h1 {
            font-size: 34px
        }

        .login-brand>img {
            width: 70px;
            height: 70px
        }

        .login-form {
            padding: 25px
        }
    }

    body {
        background: #f5f5ef
    }

    .login-page {
        display: block;
        min-height: 100dvh;
        padding: 0;
        background: #f5f5ef
    }

    .login-shell {
        width: 100%;
        height: 100dvh;
        min-height: 620px;
        grid-template-columns: minmax(360px, 46%) minmax(0, 54%);
        border: 0;
        border-radius: 0;
        box-shadow: none;
        background: #f7f7f1
    }

    .login-brand {
        padding: clamp(38px, 5vw, 76px);
        background: radial-gradient(circle at 15% 15%, #30332b 0, transparent 28%), linear-gradient(145deg, #131413, #1c1e1a)
    }

    .login-brand>img {
        width: clamp(92px, 9vw, 132px);
        height: clamp(92px, 9vw, 132px);
        box-shadow: 0 15px 40px #0005
    }

    .login-brand>div {
        max-width: 520px
    }

    .login-brand h1 {
        font-size: clamp(42px, 4.3vw, 68px);
        letter-spacing: -.045em;
        line-height: 1.02
    }

    .login-brand p {
        font-size: clamp(16px, 1.3vw, 20px);
        max-width: 520px
    }

    .login-form {
        position: relative;
        display: grid;
        place-items: center;
        padding: clamp(28px, 6vw, 90px);
        background: radial-gradient(circle at 100% 0, #f0f1d8 0, transparent 32%), #f7f7f1;
        overflow-y: auto
    }

    .login-inner {
        width: min(520px, 100%)
    }

    .login-inner>.global-back {
        width: max-content;
        margin: 0 0 clamp(34px, 7vh, 72px);
        background: #fffefa;
        border-color: #d5d7cc;
        box-shadow: 0 8px 24px rgba(22, 24, 20, .08)
    }

    .login-inner h2 {
        font-size: clamp(32px, 3vw, 44px);
        letter-spacing: -.035em;
        margin: 10px 0
    }

    .login-inner .muted {
        font-size: 16px;
        margin-bottom: 34px
    }

    .login-inner .field {
        margin-bottom: 22px
    }

    .login-inner .control {
        min-height: 54px;
        border-radius: 13px;
        font-size: 16px
    }

    .login-inner .check {
        margin: 4px 0 24px
    }

    .login-button {
        min-height: 56px;
        border-radius: 13px;
        padding: 15px 20px;
        font-size: 16px;
        box-shadow: 0 10px 24px rgba(23, 24, 23, .16)
    }

    @media(max-width:800px) {
        .login-shell {
            height: auto;
            min-height: 100dvh;
            grid-template-columns: 1fr
        }

        .login-brand {
            min-height: 220px;
            padding: 24px
        }

        .login-brand>img {
            width: 70px;
            height: 70px
        }

        .login-brand>div {
            margin: 24px 0 0
        }

        .login-brand h1 {
            font-size: 31px
        }

        .login-brand p,
        .login-brand small {
            display: none
        }

        .login-form {
            min-height: calc(100dvh - 220px);
            padding: 28px 20px 48px;
            place-items: start center
        }

        .login-inner>.global-back {
            margin-bottom: 28px
        }

        .login-inner h2 {
            font-size: 31px
        }
    }

    @media(max-width:480px) {
        .login-brand {
            min-height: 170px
        }

        .login-brand>div {
            margin: 15px 0 0
        }

        .login-brand h1 {
            font-size: 26px
        }

        .login-form {
            min-height: calc(100dvh - 170px);
            padding: 20px 16px 40px
        }

        .login-inner>.global-back {
            height: 40px;
            margin-bottom: 22px
        }

        .login-inner .muted {
            margin-bottom: 24px
        }

        .login-inner .control {
            min-height: 50px
        }
    }

    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin: 4px 0 24px
    }

    .login-options .check {
        margin: 0 !important
    }

    .login-options>a {
        color: #626b00;
        font-size: 14px;
        font-weight: 750;
        text-underline-offset: 4px
    }

    @media(max-width:420px) {
        .login-options {
            align-items: flex-start;
            flex-direction: column;
            gap: 12px
        }
    }
</style>

@if($loginRetryAfter > 0)
<script>
    (() => {
        const form = document.getElementById('login-form');
        const button = document.getElementById('login-submit');
        const label = document.getElementById('login-submit-label');
        const lockout = document.getElementById('login-lockout');
        const seconds = document.getElementById('login-retry-seconds');
        const retryAfter = Number(form?.dataset.retryAfter ?? 0);
        const unlockAt = Date.now() + retryAfter * 1000;
        let timer = null;

        if (!form || !button || !label || retryAfter <= 0) return;

        button.disabled = true;

        const tick = () => {
            const remaining = Math.max(0, Math.ceil((unlockAt - Date.now()) / 1000));

            if (remaining <= 0) {
                button.disabled = false;
                label.textContent = 'Log in';
                if (lockout) lockout.textContent = 'You can try logging in again now.';
                if (timer !== null) clearInterval(timer);
                return;
            }

            label.textContent = `Try again in ${remaining}s`;
            if (seconds) seconds.textContent = String(remaining);
        };

        tick();
        timer = setInterval(tick, 1000);
    })();
</script>
@endif

@endsection
