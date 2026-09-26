@extends('layouts.app')
@section('title', 'Customer Information')
@section('content')
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard customer-accounts">
    <header class="account-head"><div><p>SUPER ADMIN</p><h1>Customers</h1><span>Registered customer accounts and their activity.</span></div></header>
    @include('partials.account-tabs')
    @if(session('status'))<div class="notice account-message">{{ session('status') }}</div>@endif

    <section class="account-summary-cards">
        <div class="welcome"><span>Registered customers</span><h2>{{ $customers->count() }}</h2></div>
        <div class="welcome"><span>Customer orders</span><h2>{{ $customers->sum('orders_count') }}</h2></div>
        <div class="welcome"><span>Customer order value</span><h2>&#8369;{{ number_format($customers->sum('orders_sum_total'), 2) }}</h2></div>
    </section>

    <section class="welcome account-card customer-list">
        <div class="account-card-head">
            <div><p class="account-eyebrow">REGISTERED CUSTOMERS</p><h2>Customer accounts</h2></div>
            <strong>{{ $customers->count() }}</strong>
        </div>
        @if($customers->isNotEmpty())
            <div class="customer-search">
                <label class="visually-hidden" for="customer-search">Search customers</label>
                <input class="control" id="customer-search" type="search" placeholder="Search by name, username, email, or phone" autocomplete="off" data-customer-search>
            </div>
        @endif
        <div data-customer-records>
            @forelse($customers as $customer)
                @php($reservationCount = $reservationCounts[$customer->email] ?? 0)
                <article class="account-record" data-search="{{ strtolower(implode(' ', array_filter([$customer->name, $customer->username, $customer->email, $customer->phone]))) }}">
                    <div class="account-record-summary">
                        <span>{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                        <div><strong>{{ $customer->name }}</strong><small>{{ $customer->username ?: 'No username' }} · {{ $customer->email }}</small><small>{{ $customer->phone ?: 'No phone' }} · Joined {{ $customer->created_at->format('M d, Y') }}</small></div>
                        <div class="account-stats">
                            <b class="account-badge">{{ $customer->orders_count }} {{ Str::plural('order', $customer->orders_count) }}</b>
                            <b class="account-badge">{{ $reservationCount }} {{ Str::plural('reservation', $reservationCount) }}</b>
                            <b class="account-badge total">&#8369;{{ number_format($customer->orders_sum_total ?? 0, 2) }}</b>
                        </div>
                    </div>
                    <a class="account-record-link" href="{{ route('customers.show', $customer) }}">View details</a>
                </article>
            @empty
                <div class="account-empty">No customer accounts yet.</div>
            @endforelse
            <div class="account-empty" data-customer-no-match hidden>No customers match your search.</div>
        </div>
    </section>
</div></main></div>
@include('partials.account-page-styles')
@push('styles')
<style>.customer-search{margin:0 0 6px}.customer-search .control{width:100%}.account-record-summary>.account-stats{display:flex;flex-wrap:nowrap;justify-content:flex-end;gap:6px}.account-record-link{text-decoration:none}.account-record-link:hover{text-decoration:underline}@media(max-width:760px){.account-record-summary>.account-stats{flex-wrap:wrap}}.account-badge.total{background:#e9ecd4;color:#4e5700}.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}@media(max-width:520px){.account-stats{justify-content:flex-start;width:auto!important}}</style>
@endpush
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const search = document.querySelector('[data-customer-search]');
    if (!search) return;
    const records = [...document.querySelectorAll('[data-customer-records] [data-search]')];
    const noMatch = document.querySelector('[data-customer-no-match]');

    search.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let shown = 0;
        records.forEach(record => {
            const matches = record.dataset.search.includes(term);
            record.hidden = !matches;
            if (matches) shown++;
        });
        noMatch.hidden = shown > 0;
    });
})();
</script>
@endpush
@endsection
