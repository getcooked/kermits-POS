@extends('layouts.app')
@section('title', 'My Account | Kermit\'s')

@section('content')
<main class="history-page customer-account-page">
    @include('customer.navigation')

    <header class="account-header">
        <p>MY ACCOUNT</p>
        <h1>{{ $accountSection === 'password' ? 'Change password' : 'Personal information' }}</h1>
    </header>

    <section class="account-content">
        @if(session('status'))<div class="account-notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="account-error" role="alert">Please review the highlighted account details.</div>@endif

        <div class="account-grid profile-account-grid">
            @if($accountSection === 'personal')
            <section class="account-card" id="personal-information">
                <div class="account-section-heading">
                    <div>
                        <h2>Personal information</h2>
                        <p>Update the details used to identify and contact you.</p>
                    </div>
                </div>

                <form class="account-form" method="POST" action="{{ route('customer.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="field full">
                        <label for="name">Full name</label>
                        <input class="control" id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="120" autocomplete="name" required>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input class="control" id="username" name="username" value="{{ old('username', $customer->username) }}" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required>
                        <small>Letters, numbers, dots, underscores, and hyphens.</small>
                        @error('username')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="phone">Phone number</label>
                        <input class="control" id="phone" name="phone" type="tel" inputmode="numeric" value="{{ old('phone', $customer->phone) }}" minlength="11" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" placeholder="09XXXXXXXXX" required>
                        <small>11 digits starting with 09.</small>
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field full">
                        <label for="email">Verified email</label>
                        <input class="control" id="email" type="email" value="{{ $customer->email }}" readonly aria-describedby="email-help">
                        <small id="email-help">This verified address is used for sign-in and account recovery.</small>
                    </div>

                    <button class="account-submit" type="submit">Save personal information</button>
                </form>
            </section>
            @else
            <section class="account-card" id="change-password">
                <div class="account-section-heading">
                    <div>
                        <h2>Change password</h2>
                        <p>Confirm your current password before choosing a new one.</p>
                    </div>
                </div>

                <form class="account-form" method="POST" action="{{ route('customer.settings.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="field full">
                        <label for="current_password">Current password</label>
                        <input class="control" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input class="control" id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                    </div>

                    <div class="password-rules full">Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol. Changing your password signs out other web browsers and mobile app sessions.</div>
                    <button class="account-submit" type="submit">Change password</button>
                </form>
            </section>
            @endif
        </div>
    </section>
</main>

@push('styles')
    @include('customer.account-styles')
<style>
.profile-account-grid{grid-template-columns:minmax(0,680px);align-items:start}.account-section-heading{margin-bottom:24px}.account-section-heading h2{margin:3px 0 5px}.account-section-heading p{margin:0;color:#6d7369;font-size:14px;line-height:1.5}
</style>
@endpush
@endsection
