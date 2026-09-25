@extends('layouts.app')
@section('title', 'My Account | Kermit\'s')

@section('content')
<main class="history-page customer-account-page">
    @include('customer.navigation')

    <header class="account-header">
        <p>MY ACCOUNT</p>
        <h1>{{ $accountSection === 'password' ? 'Change password' : 'Personal Information' }}</h1>
    </header>

    <section class="account-content">
        @if(session('status'))<div class="account-notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="account-error" role="alert">Please review the highlighted account details.</div>@endif

        <div class="account-grid profile-account-grid">
            @if($accountSection === 'personal')
            <section class="account-card" id="personal-information">
                <div class="account-section-heading">
                    <div>
                        <h2>Personal Information</h2>
                        <p>Update the details used to identify and contact you.</p>
                    </div>
                </div>

                <form class="account-form" method="POST" action="{{ route('customer.profile.update') }}" data-ajax-form data-ajax-loading="Saving...">
                    @csrf
                    @method('PUT')

                    <div class="field full">
                        <label for="name">Full name</label>
                        <input class="control" id="name" name="name" value="{{ old('name', $customer->name) }}" maxlength="100" autocomplete="name" required>
                        <small>Maximum 100 characters.</small>
                        @error('name')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="username">Username</label>
                        <input class="control" id="username" name="username" value="{{ old('username', $customer->username) }}" minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]+" autocomplete="username" required>
                        <small>3–30 characters: letters, numbers, dots, underscores, and hyphens.</small>
                        @error('username')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="phone">Phone number</label>
                        <input class="control" id="phone" name="phone" type="tel" inputmode="numeric" value="{{ old('phone', $customer->phone) }}" minlength="11" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel" placeholder="09XXXXXXXXX" required>
                        <small>11 digits starting with 09.</small>
                        @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field full">
                        <label for="email">Email address</label>
                        <input class="control" id="email" type="email" value="{{ $customer->email }}" readonly aria-describedby="email-help">
                        <small id="email-help">Your verified email address is visible here but cannot be changed.</small>
                    </div>

                    <div class="field">
                        <label for="birthday">Birthday</label>
                        <input class="control" id="birthday" name="birthday" type="date" value="{{ old('birthday', $customer->birthday?->format('Y-m-d')) }}" min="1900-01-01" max="{{ now()->toDateString() }}" required>
                        @error('birthday')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="age">Age</label>
                        <input class="control" id="age" type="text" value="{{ $customer->birthday ? $customer->birthday->age.' years old' : '' }}" placeholder="Calculated from birthday" readonly>
                        <small>Age is calculated automatically from your birthday.</small>
                    </div>

                    <div class="field">
                        <label for="sex">Sex</label>
                        <select class="control" id="sex" name="sex" required>
                            <option value="">Select sex</option>
                            <option value="male" @selected(old('sex', $customer->sex) === 'male')>Male</option>
                            <option value="female" @selected(old('sex', $customer->sex) === 'female')>Female</option>
                        </select>
                        @error('sex')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field full">
                        <label for="address">Present address</label>
                        <textarea class="control" id="address" name="address" rows="3" maxlength="500" placeholder="e.g. Binaobao, Bantayan, Cebu, Philippines" aria-describedby="address-help location-status" required>{{ old('address', $customer->address) }}</textarea>
                        <button class="profile-location-button" id="use-current-location" type="button">Use my current location</button>
                        <small id="address-help">Location data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.</small>
                        <small id="location-status" class="location-status" role="status" aria-live="polite"></small>
                        @error('address')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <button class="account-submit" type="submit">Save personal information</button>
                </form>
            </section>
            @else
            <section class="account-card" id="change-password">
                <div class="account-section-heading">
                    <div>
                        <h2>Change password</h2>
                        <p>Verify your email before choosing a new password.</p>
                    </div>
                </div>

                @if(session('verification_sent'))
                    <div class="account-verification-sent" role="status">{{ session('verification_sent') }}</div>
                @endif

                <div class="account-verification-step">
                    <div>
                        <strong>Email verification</strong>
                        <small>Send a one-time code to {{ $customer->email }}. The code expires after 10 minutes.</small>
                    </div>
                    <form method="POST" action="{{ route('customer.settings.password.email-code') }}" data-ajax-form data-ajax-loading="Sending..." data-ajax-success="A verification code was sent to your email.">
                        @csrf
                        <button type="submit">Send verification code</button>
                    </form>
                </div>

                <form class="account-form" method="POST" action="{{ route('customer.settings.password.update') }}" data-ajax-form data-ajax-loading="Updating..." data-ajax-reset="true">
                    @csrf
                    @method('PUT')

                    <div class="field full">
                        <label for="verification_code">Email verification code</label>
                        <input class="control verification-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" value="" required>
                        @error('verification_code')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input class="control" id="password" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password" required>
                        @error('password')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password" required>
                    </div>

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
.profile-account-grid{grid-template-columns:minmax(0,680px);align-items:start}.account-section-heading{margin-bottom:24px}.account-section-heading h2{margin:3px 0 5px}.account-section-heading p{margin:0;color:#6d7369;font-size:14px;line-height:1.5}.profile-location-button{width:100%;min-height:42px;margin-top:8px;border:1px solid #c8ccc1;border-radius:8px;background:#fff;color:#3f4700;font-weight:800;cursor:pointer}.profile-location-button:hover{border-color:#9ca761;background:#f0f2df}.profile-location-button:disabled{opacity:.55;cursor:not-allowed}.location-status{min-height:18px}.location-status.is-error{color:#b42318}.location-status.is-success{color:#267444}.account-verification-step{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:20px;padding:16px;border:1px solid #e2e5d8;border-radius:10px;background:#f9faf5}.account-verification-step>div{display:grid;gap:4px}.account-verification-step small{color:#6d7369;font-size:12px;line-height:1.45}.account-verification-step form{margin:0}.account-verification-step button{min-height:40px;padding:9px 14px;border:1px solid #c8ccc1;border-radius:8px;background:#fff;color:#292b27;font-weight:750;white-space:nowrap;cursor:pointer}.account-verification-step button:hover{border-color:#9ca761;background:#f0f2df}.account-verification-sent{margin-bottom:16px;padding:12px 14px;border-radius:8px;background:#e7f5e9;color:#236b3d;font-size:13px}.verification-code{max-width:190px;font-size:18px!important;font-weight:750;letter-spacing:.24em}
@media(max-width:620px){.account-verification-step{align-items:stretch;flex-direction:column}.account-verification-step button{width:100%}.verification-code{max-width:none}}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const birthday = document.getElementById('birthday');
    const age = document.getElementById('age');

    if (birthday && age) {
        const updateAge = () => {
            const parts = birthday.value.split('-').map(Number);
            if (parts.length !== 3 || parts.some(Number.isNaN)) {
                age.value = '';
                return;
            }

            const today = new Date();
            let years = today.getFullYear() - parts[0];
            if (today.getMonth() + 1 < parts[1] || (today.getMonth() + 1 === parts[1] && today.getDate() < parts[2])) years--;
            age.value = years >= 0 ? `${years} years old` : '';
        };

        birthday.addEventListener('change', updateAge);
        updateAge();
    }

    const address = document.getElementById('address');
    const locationButton = document.getElementById('use-current-location');
    const locationStatus = document.getElementById('location-status');
    const reverseGeocodingUrl = @json(route('location.reverse'));

    if (!address || !locationButton || !locationStatus) return;

    const setLocationStatus = (message, state = '') => {
        locationStatus.textContent = message;
        locationStatus.className = `location-status${state ? ` is-${state}` : ''}`;
    };

    locationButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setLocationStatus('Location is not supported by this browser. Enter your address manually.', 'error');
            return;
        }

        locationButton.disabled = true;
        setLocationStatus('Requesting permission to access your current location...');

        navigator.geolocation.getCurrentPosition(
            async ({ coords }) => {
                setLocationStatus('Finding the name of your current location...');

                try {
                    const url = new URL(reverseGeocodingUrl, window.location.origin);
                    url.searchParams.set('latitude', coords.latitude);
                    url.searchParams.set('longitude', coords.longitude);
                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const result = await response.json();

                    if (!response.ok || !result.address) throw new Error(result.message);

                    address.value = result.address;
                    setLocationStatus('');
                } catch (error) {
                    setLocationStatus(error.message || 'A named location could not be found. Enter your address manually.', 'error');
                }

                locationButton.disabled = false;
                address.focus();
            },
            (error) => {
                const message = error.code === error.PERMISSION_DENIED
                    ? 'Location permission was denied. Enter your address manually or allow location access in your browser.'
                    : 'Your location could not be detected. Enter your address manually.';
                setLocationStatus(message, 'error');
                locationButton.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    });
})();
</script>
@endpush
@endsection
