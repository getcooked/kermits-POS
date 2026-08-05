@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<main class="reset-page">
    <section class="reset-brand">
        <a href="{{ route('home') }}"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></a>
        <div><p>ACCOUNT RECOVERY</p><h1>Let’s get you<br>back in.</h1><span>Enter the email connected to your account. We’ll send you a secure, expiring reset link.</span></div>
        <small>Time-honored recipes since 2000</small>
    </section>
    <section class="reset-form"><div class="reset-inner">
        <p class="reset-eyebrow">FORGOT PASSWORD</p>
        <h2>Reset your password</h2>
        <p class="muted">We’ll email instructions to the address registered with Kermit’s.</p>
        @if(session('status'))<div class="reset-success">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field"><label for="email">Email address</label><input class="control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@gmail.com" maxlength="160" required autofocus>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <button class="reset-button" type="submit">Send reset link <span>&rarr;</span></button>
        </form>
        <a class="return-login" href="{{ route('login') }}">&larr; Return to login</a>
    </div></section>
</main>
@include('auth.password-reset-styles')
@endsection
