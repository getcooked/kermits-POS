@extends('layouts.app')
@section('title', 'Customer Information')
@section('content')
@php
    $activeCount = $customers->whereNull('disabled_at')->count();
    $disabledCount = $customers->whereNotNull('disabled_at')->count();
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard ad-page customer-accounts" data-ad-page>
    <header class="topbar ad-head">
        <div><h1>Customer Accounts</h1><p>Registered customer accounts and their activity.</p></div>
        <div class="ad-head-actions">
            <x-search-field id="customer-search" placeholder="Search customers by name, email or phone" data-ad-search />
        </div>
    </header>
    @include('partials.account-tabs')

    @if(session('status'))<div class="notice ad-message">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error ad-message">{{ $errors->first() }}</div>@endif

    <section class="ad-stats" aria-label="Filter by status">
        <button type="button" class="ad-stat" data-ad-status="all" aria-pressed="true"><span>Customer Accounts</span><strong>{{ $customers->count() }}</strong></button>
        <button type="button" class="ad-stat" data-ad-status="active" aria-pressed="false"><span>Active Customer Accounts</span><strong>{{ $activeCount }}</strong></button>
        <button type="button" @class(["ad-stat", "ad-stat-disabled" => $disabledCount > 0]) data-ad-status="disabled" aria-pressed="false"><span>Disabled Customer Accounts</span><strong>{{ $disabledCount }}</strong></button>
    </section>

    <section class="ad-list" aria-label="Customer accounts">
        @forelse($customers as $customer)
            @php
                $reservationCount = $reservationCounts[$customer->email] ?? 0;
            @endphp
            <article @class(['ad-row', 'is-disabled' => $customer->isDisabled()]) data-ad-row data-status="{{ $customer->isDisabled() ? 'disabled' : 'active' }}" data-search="{{ Str::lower(implode(' ', array_filter([$customer->name, $customer->email, $customer->phone]))) }}">
                <span class="ad-avatar" aria-hidden="true">{{ Str::upper(Str::substr($customer->name, 0, 1)) }}</span>
                <div class="ad-identity">
                    <strong>{{ $customer->name }}</strong>
                    <small>{{ $customer->email }}</small>
                    <small>{{ $customer->phone ?: 'No phone' }} · Joined {{ $customer->created_at?->format('M d, Y') ?? '—' }}</small>
                </div>
                <div class="ad-chips">
                    <span class="ad-chip">{{ $customer->orders_count }} {{ Str::plural('order', $customer->orders_count) }}</span>
                    <span class="ad-chip">{{ $reservationCount }} {{ Str::plural('reservation', $reservationCount) }}</span>
                    <span class="ad-chip is-money">₱{{ number_format((float) ($customer->orders_sum_total ?? 0), 2) }}</span>
                </div>
                <div class="ad-actions">
                    @if($customer->isDisabled())
                        <form method="POST" action="{{ route('customers.enable', $customer) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Enabling...">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="false" aria-label="Enable {{ $customer->name }}"><span class="ad-status-track" aria-hidden="true"></span>Disabled</button></form>
                    @else
                        <form method="POST" action="{{ route('customers.disable', $customer) }}" data-ajax-form data-ajax-target="[data-ad-page]" data-ajax-loading="Disabling..." data-confirm="Disable {{ $customer->name }}'s account? They will be signed out of the website and mobile app and cannot order or reserve until it is enabled again." data-confirm-title="Disable customer account?">@csrf @method('PATCH')<button class="ad-status" type="submit" role="switch" aria-checked="true" aria-label="Disable {{ $customer->name }}"><span class="ad-status-track" aria-hidden="true"></span>Active</button></form>
                    @endif
                    <a class="ad-edit" href="{{ route('customers.show', $customer) }}">View details</a>
                </div>
            </article>
        @empty
            <div class="ad-empty">No customer accounts yet.</div>
        @endforelse
        <div class="ad-empty" data-ad-empty hidden>No customers match your search.</div>
    </section>
</div></main></div>
@include('partials.account-directory')
@endsection
