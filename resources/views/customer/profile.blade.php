@extends('layouts.app')
@section('title', 'My Profile | Kermit\'s')

@section('content')
<main class="history-page customer-account-page">
    @include('customer.navigation', ['activeCustomerNav' => 'profile'])

    <header class="account-header">
        <p>MY ACCOUNT</p>
        <h1>Profile</h1>
    </header>

    <section class="account-content">
        @if(session('status'))<div class="account-notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="account-error" role="alert">Please review the highlighted profile details.</div>@endif

        <div class="account-grid">
            <section class="account-card">
                <h2>Personal information</h2>
                <p>Your verified email is protected. You can update the details used to identify and contact you.</p>

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

                    <button class="account-submit" type="submit">Save profile</button>
                </form>
            </section>

            <aside class="identity-panel">
                <div class="identity-card">
                    <div class="identity-avatar" aria-hidden="true">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                    <h2>{{ $customer->name }}</h2>
                    <p>{{ '@'.($customer->username ?: 'customer') }}</p>
                    <dl class="identity-list">
                        <div><dt>Account</dt><dd>Customer</dd></div>
                        <div><dt>Email</dt><dd class="verified-badge">Verified</dd></div>
                        <div><dt>Member since</dt><dd>{{ $customer->created_at->format('M Y') }}</dd></div>
                    </dl>
                </div>
            </aside>
        </div>
    </section>
</main>
@push('styles')
    @include('customer.account-styles')
@endpush
@endsection
