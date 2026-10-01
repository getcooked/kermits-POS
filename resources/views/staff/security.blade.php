@extends('layouts.app')
@section('title', 'Admin Account · Kermit’s')
@section('content')
@php
    $createOpen = filled($verificationEmail) || filled(old('name')) || filled(old('email'));
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard ad-page" data-ad-page>
    <header class="topbar ad-head">
        <div><h1>Admin Account</h1><p>Create and manage the accounts that can use the admin panel.</p></div>
        <div class="ad-head-actions">
            <x-search-field id="admin-search" placeholder="Search admins by name, email or phone" data-ad-search />
            <button type="button" class="ad-primary" data-ad-create-toggle data-label="Add Admin" aria-controls="admin-create-panel" aria-expanded="{{ $createOpen ? 'true' : 'false' }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg><span>{{ $createOpen ? 'Close Form' : 'Add Admin' }}</span></button>
        </div>
    </header>
    @include('partials.account-tabs')

    @if(session('status'))<div class="notice ad-message">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error ad-message">{{ $errors->first() }}</div>@endif

    <section id="admin-create-panel" class="welcome ad-create" @unless($createOpen) hidden @endunless>
        <h2>Create Admin Account</h2>
        <p>The new admin must verify their email. Send a code, then enter it below.</p>
        <form method="POST" action="{{ route('superadmin.admins.store') }}">@csrf
            {{-- Pressing Enter uses the first submit button, so it must be "create", not "Send code". --}}
            <button class="ad-sr" type="submit" tabindex="-1" aria-hidden="true">Create Admin Account</button>
            <div class="ad-form-grid">
                <div class="field"><label for="name">Full Name</label><input class="control" id="name" name="name" value="{{ old('name') }}" maxlength="100" required><small>Maximum 100 characters.</small></div>
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="ad-email-row">
                        <input class="control" id="email" name="email" type="email" value="{{ old('email', $verificationEmail) }}" autocomplete="off" required>
                        <button class="ad-send-code" type="submit" formaction="{{ route('superadmin.admins.email-code') }}" formnovalidate>Send code</button>
                    </div>
                    <small>{{ $verificationEmail ? "A code was sent to {$verificationEmail}. It expires after 10 minutes." : 'A 6-digit code will be emailed to this address.' }}</small>
                </div>
                <div class="field"><label for="verification_code">Email verification code</label><input class="control ad-code" id="verification_code" name="verification_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div>
                <div class="field"><label for="phone">Phone Number</label><input class="control" id="phone" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" required><small>11 digits starting with 09.</small></div>
                <div class="field"><label for="password">Password</label><input class="control" id="password" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password" required><small>8-23 characters with uppercase, lowercase, number, and symbol.</small></div>
                <div class="field"><label for="password_confirmation">Confirm Password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password" required></div>
                <div class="ad-span ad-form-actions"><button class="button" type="submit">Create Admin Account</button></div>
            </div>
        </form>
    </section>

    <section class="ad-stats" aria-label="Filter by status">
        <button type="button" class="ad-stat" data-ad-status="all" aria-pressed="true"><span>Admin Accounts</span><strong>{{ $admins->count() }}</strong></button>
        <button type="button" class="ad-stat" data-ad-status="active" aria-pressed="false"><span>Active Admin Accounts</span><strong>{{ $activeAdminCount }}</strong></button>
        <button type="button" @class(["ad-stat", "ad-stat-disabled" => $disabledAdminCount > 0]) data-ad-status="disabled" aria-pressed="false"><span>Disabled Admin Accounts</span><strong>{{ $disabledAdminCount }}</strong></button>
    </section>

    <section class="ad-list" aria-label="Admin accounts">
        @forelse($admins as $admin)
            @php $isMe = $admin->is(auth()->user()); @endphp
            <article @class(['ad-row', 'is-disabled' => $admin->isDisabled()]) data-ad-row data-status="{{ $admin->isDisabled() ? 'disabled' : 'active' }}" data-search="{{ Str::lower(implode(' ', array_filter([$admin->name, $admin->email, $admin->phone]))) }}">
                <span class="ad-avatar" aria-hidden="true">{{ Str::upper(Str::substr($admin->name, 0, 1)) }}</span>
                <div class="ad-identity">
                    <strong>{{ $admin->name }}@if($isMe) <em>(you)</em>@endif</strong>
                    <small>{{ $admin->email }}</small>
                    <small>{{ $admin->phone ?: 'No phone' }} · Created {{ $admin->created_at?->format('M d, Y') ?? '—' }}</small>
                </div>
                <div class="ad-chips"><span class="ad-chip">Admin</span></div>
                <div class="ad-actions">
                    @if($isMe)
                        <span class="ad-self">Signed in</span>
                    @elseif($admin->isDisabled())
                        <form method="POST" action="{{ route('superadmin.admins.enable', $admin) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Enabling...">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="false" aria-label="Enable {{ $admin->name }}"><span class="ad-status-track" aria-hidden="true"></span>Disabled</button></form>
                    @else
                        <form method="POST" action="{{ route('superadmin.admins.disable', $admin) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Disabling..." data-confirm="Disable {{ $admin->name }}'s admin account? They will be signed out and cannot log in until it is enabled again." data-confirm-title="Disable admin account?">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="true" aria-label="Disable {{ $admin->name }}"><span class="ad-status-track" aria-hidden="true"></span>Active</button></form>
                    @endif
                    <button type="button" class="ad-edit" data-ad-open="admin-editor-{{ $admin->id }}" aria-haspopup="dialog"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z"></path><path d="m13.5 6.5 4 4"></path></svg>Edit Account</button>
                </div>
            </article>
        @empty
            <div class="ad-empty">No admin accounts yet.</div>
        @endforelse
        <div class="ad-empty" data-ad-empty hidden>No admin accounts match your search.</div>
    </section>

    @foreach($admins as $admin)
    <div id="admin-editor-{{ $admin->id }}" class="ad-drawer" role="dialog" aria-modal="true" aria-labelledby="admin-editor-{{ $admin->id }}-title" hidden>
        <div class="ad-drawer-backdrop" data-ad-close></div>
        <div class="ad-drawer-panel">
            <header class="ad-drawer-head">
                <div><small>Edit admin account</small><h2 id="admin-editor-{{ $admin->id }}-title">{{ $admin->name }}</h2></div>
                <button type="button" class="ad-close" data-ad-close aria-label="Close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
            </header>
            <div class="ad-drawer-body">
                <form method="POST" action="{{ route('superadmin.admins.update', $admin) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Saving...">@csrf @method('PUT')
                    <div class="ad-form-grid">
                        <div class="field"><label for="admin-name-{{ $admin->id }}">Full Name</label><input class="control" id="admin-name-{{ $admin->id }}" name="name" value="{{ $admin->name }}" maxlength="100" required></div>
                        <div class="field"><label for="admin-email-{{ $admin->id }}">Email</label><input class="control" id="admin-email-{{ $admin->id }}" name="email" type="email" value="{{ $admin->email }}" required></div>
                        <div class="field"><label for="admin-phone-{{ $admin->id }}">Phone</label><input class="control" id="admin-phone-{{ $admin->id }}" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ $admin->phone }}"></div>
                        <div class="field"><label for="admin-password-{{ $admin->id }}">New Password <small>(optional)</small></label><input class="control" id="admin-password-{{ $admin->id }}" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                        <div class="field"><label for="admin-password-confirmation-{{ $admin->id }}">Confirm Password</label><input class="control" id="admin-password-confirmation-{{ $admin->id }}" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                        <button class="button" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div></main></div>
@include('partials.account-directory')
@push('styles')
<style>
.ad-email-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px}
.ad-send-code{min-height:44px;padding:0 15px;border:1px solid #c8ccc1;border-radius:10px;background:#fff;color:#292b27;font:inherit;font-weight:750;white-space:nowrap;cursor:pointer}
.ad-send-code:hover{border-color:#9ca761;background:#f0f2df;color:#4e5700}
.ad-code{max-width:190px;font-weight:750;letter-spacing:.28em}
@media(max-width:600px){.ad-email-row{grid-template-columns:1fr}.ad-code{max-width:none}}
</style>
@endpush
@endsection
