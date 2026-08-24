@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<main class="reset-page">
    <section class="reset-brand">
        <a href="{{ route('home') }}"><img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><strong>KERMIT'S</strong></a>
        <div><p>SECURE ACCOUNT</p><h1>Create a new<br>password.</h1><span>Choose a password that is difficult to guess and different from passwords used elsewhere.</span></div>
        <small>Time-honored recipes since 2000</small>
    </section>
    <section class="reset-form"><div class="reset-inner">
        <p class="reset-eyebrow">PASSWORD RESET</p>
        <h2>Choose a new password</h2>
        <p class="muted">Use 12 or more characters with uppercase, lowercase, a number, and a symbol.</p>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field"><label for="email">Email address</label><input class="control" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" maxlength="160" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">New password</label><input class="control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password_confirmation">Confirm new password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required></div>
            <button class="reset-button" type="submit">Save new password <span>&rarr;</span></button>
        </form>
        <a class="return-login" href="{{ route('login') }}">&larr; Return to login</a>
    </div></section>
</main>
@include('auth.password-reset-styles')
@endsection
