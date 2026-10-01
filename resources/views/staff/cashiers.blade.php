@extends('layouts.app')
@section('title','Cashier Accounts')
@section('content')
@php
    $createOpen = $errors->any() && (filled(old('name')) || filled(old('email')));
    $activeCount = $cashiers->whereNull('disabled_at')->count();
    $disabledCount = $cashiers->whereNotNull('disabled_at')->count();
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard ad-page" data-ad-page>
    <header class="topbar ad-head">
        <div><h1>Cashier Accounts</h1><p>Create and manage staff access to the point of sale.</p></div>
        <div class="ad-head-actions">
            <x-search-field id="cashier-search" placeholder="Search cashiers by name, email or phone" data-ad-search />
            <button type="button" class="ad-primary" data-ad-create-toggle data-label="Add Cashier" aria-controls="cashier-create-panel" aria-expanded="{{ $createOpen ? 'true' : 'false' }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg><span>{{ $createOpen ? 'Close Form' : 'Add Cashier' }}</span></button>
        </div>
    </header>
    @include('partials.account-tabs')

    @if(session('status'))<div class="notice ad-message">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error ad-message">{{ $errors->first() }}</div>@endif

    <section id="cashier-create-panel" class="welcome ad-create" @unless($createOpen) hidden @endunless>
        <h2>Create Cashier Account</h2>
        <p>The cashier signs in with this email and temporary password.</p>
        <form method="POST" action="{{ route('cashiers.store') }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Creating...">@csrf
            <div class="ad-form-grid">
                <div class="field"><label for="name">Full Name</label><input class="control" id="name" name="name" value="{{ old('name') }}" maxlength="100" required><small>Maximum 100 characters.</small></div>
                <div class="field"><label for="email">Email Address</label><input class="control" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                <div class="field"><label for="phone">Phone Number</label><input class="control" id="phone" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ old('phone') }}" placeholder="09XXXXXXXXX" required><small>11 digits starting with 09.</small></div>
                <div class="field"><label for="password">Temporary Password</label><input class="control" id="password" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password" required><small>8-23 characters with uppercase, lowercase, number, and symbol.</small></div>
                <div class="field"><label for="password_confirmation">Confirm Password</label><input class="control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password" required></div>
                <div class="ad-span ad-form-actions"><button class="button" type="submit">Create Cashier Account</button></div>
            </div>
        </form>
    </section>

    <section class="ad-stats" aria-label="Filter by status">
        <button type="button" class="ad-stat" data-ad-status="all" aria-pressed="true"><span>Cashier Accounts</span><strong>{{ $cashiers->count() }}</strong></button>
        <button type="button" class="ad-stat" data-ad-status="active" aria-pressed="false"><span>Active Cashier Accounts</span><strong>{{ $activeCount }}</strong></button>
        <button type="button" @class(["ad-stat", "ad-stat-disabled" => $disabledCount > 0]) data-ad-status="disabled" aria-pressed="false"><span>Disabled Cashier Accounts</span><strong>{{ $disabledCount }}</strong></button>
    </section>

    <section class="ad-list" aria-label="Cashier accounts">
        @forelse($cashiers as $cashier)
            <article @class(['ad-row', 'is-disabled' => $cashier->isDisabled()]) data-ad-row data-status="{{ $cashier->isDisabled() ? 'disabled' : 'active' }}" data-search="{{ Str::lower(implode(' ', array_filter([$cashier->name, $cashier->email, $cashier->phone]))) }}">
                <span class="ad-avatar" aria-hidden="true">{{ Str::upper(Str::substr($cashier->name, 0, 1)) }}</span>
                <div class="ad-identity">
                    <strong>{{ $cashier->name }}</strong>
                    <small>{{ $cashier->email }}</small>
                    <small>{{ $cashier->phone ?: 'No phone' }} · Created {{ $cashier->created_at?->format('M d, Y') ?? '—' }}</small>
                </div>
                <div class="ad-chips">
                    <span class="ad-chip">{{ $cashier->paid_sales_count }} {{ Str::plural('sale', $cashier->paid_sales_count) }}</span>
                    <span class="ad-chip is-money">₱{{ number_format((float) ($cashier->paid_sales_total ?? 0), 2) }}</span>
                </div>
                <div class="ad-actions">
                    @if($cashier->isDisabled())
                        <form method="POST" action="{{ route('cashiers.enable', $cashier) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Enabling...">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="false" aria-label="Enable {{ $cashier->name }}"><span class="ad-status-track" aria-hidden="true"></span>Disabled</button></form>
                    @else
                        <form method="POST" action="{{ route('cashiers.disable', $cashier) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Disabling..." data-confirm="Disable {{ $cashier->name }}'s cashier account? They will be signed out of the POS and cannot log in until it is enabled again." data-confirm-title="Disable cashier account?">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="true" aria-label="Disable {{ $cashier->name }}"><span class="ad-status-track" aria-hidden="true"></span>Active</button></form>
                    @endif
                    <button type="button" class="ad-edit" data-ad-open="cashier-editor-{{ $cashier->id }}" aria-haspopup="dialog"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z"></path><path d="m13.5 6.5 4 4"></path></svg>Edit Account</button>
                </div>
            </article>
        @empty
            <div class="ad-empty">No cashier accounts yet.</div>
        @endforelse
        <div class="ad-empty" data-ad-empty hidden>No cashier accounts match your search.</div>
    </section>

    @foreach($cashiers as $cashier)
    <div id="cashier-editor-{{ $cashier->id }}" class="ad-drawer" role="dialog" aria-modal="true" aria-labelledby="cashier-editor-{{ $cashier->id }}-title" hidden>
        <div class="ad-drawer-backdrop" data-ad-close></div>
        <div class="ad-drawer-panel">
            <header class="ad-drawer-head">
                <div><small>Edit cashier account</small><h2 id="cashier-editor-{{ $cashier->id }}-title">{{ $cashier->name }}</h2></div>
                <button type="button" class="ad-close" data-ad-close aria-label="Close"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
            </header>
            <div class="ad-drawer-body">
                <form method="POST" action="{{ route('cashiers.update', $cashier) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Saving...">@csrf @method('PUT')
                    <div class="ad-form-grid">
                        <div class="field"><label for="cashier-name-{{ $cashier->id }}">Full Name</label><input class="control" id="cashier-name-{{ $cashier->id }}" name="name" value="{{ $cashier->name }}" maxlength="100" required></div>
                        <div class="field"><label for="cashier-email-{{ $cashier->id }}">Email</label><input class="control" id="cashier-email-{{ $cashier->id }}" name="email" type="email" value="{{ $cashier->email }}" required></div>
                        <div class="field"><label for="cashier-phone-{{ $cashier->id }}">Phone</label><input class="control" id="cashier-phone-{{ $cashier->id }}" name="phone" type="tel" inputmode="numeric" minlength="11" maxlength="11" pattern="09[0-9]{9}" value="{{ $cashier->phone }}" required></div>
                        <div class="field"><label for="cashier-password-{{ $cashier->id }}">New Password <small>(optional)</small></label><input class="control" id="cashier-password-{{ $cashier->id }}" name="password" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                        <div class="field"><label for="cashier-password-confirmation-{{ $cashier->id }}">Confirm Password</label><input class="control" id="cashier-password-confirmation-{{ $cashier->id }}" name="password_confirmation" type="password" minlength="8" maxlength="23" autocomplete="new-password"></div>
                        <button class="button" type="submit">Save Changes</button>
                    </div>
                </form>
                <form class="ad-danger" method="POST" action="{{ route('cashiers.destroy', $cashier) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Deleting..." data-confirm="Delete this cashier account? Login access will be removed, but sales records will remain." data-confirm-title="Delete Cashier Account?">@csrf @method('DELETE')
                    <div><strong>Delete cashier account</strong><small>Prefer Disable if they may return. Sales records are kept either way.</small></div>
                    <button type="submit">Delete Cashier Account</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div></main></div>
@include('partials.account-directory')
@endsection
