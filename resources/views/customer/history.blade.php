@extends('layouts.app')
@section('title', 'My Activity | Kermit\'s')

@section('content')
@php
    $activeReservations = $reservations->whereIn('status', ['pending', 'confirmed'])->count();
    $paidOrders = $orders->where('payment_status', 'paid')->count();
@endphp

<main class="history-page">
    <nav aria-label="Customer navigation">
        <a class="history-brand" href="{{ route('home') }}">
            <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
            <strong>KERMIT'S</strong>
        </a>

        <div class="history-actions">
            <a href="{{ route('shop') }}">Shop</a>
            <a class="active" href="{{ route('customer.history') }}" aria-current="page">History</a>
            <a href="{{ route('reservations.create') }}">Reserve</a>
            <span>Hi, {{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-icon" type="submit" title="Log out" aria-label="Log out">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9"/></svg>
                </button>
            </form>
        </div>
    </nav>

    <header class="history-header">
        <div>
            <p>MY ACCOUNT</p>
            <h1>Reservations and purchases</h1>
        </div>
        <a class="new-reservation" href="{{ route('reservations.create') }}">New reservation</a>
    </header>

    <section class="history-summary" aria-label="Activity summary">
        <div><span>Reservations</span><strong>{{ $reservations->count() }}</strong></div>
        <div><span>Active requests</span><strong>{{ $activeReservations }}</strong></div>
        <div><span>Purchases</span><strong>{{ $orders->count() }}</strong></div>
        <div><span>Paid orders</span><strong>{{ $paidOrders }}</strong></div>
    </section>

    <section class="history-content">
        <div class="history-tabs" role="tablist" aria-label="Activity type">
            <button class="history-tab active" id="reservations-tab" type="button" role="tab" aria-selected="true" aria-controls="reservations-panel" data-history-tab="reservations">
                Reservations <span>{{ $reservations->count() }}</span>
            </button>
            <button class="history-tab" id="purchases-tab" type="button" role="tab" aria-selected="false" aria-controls="purchases-panel" data-history-tab="purchases">
                Purchases <span>{{ $orders->count() }}</span>
            </button>
        </div>

        <div class="history-panel" id="reservations-panel" role="tabpanel" aria-labelledby="reservations-tab" data-history-panel="reservations">
            <div class="panel-heading">
                <div><h2>Your reservations</h2><p>Newest requests appear first.</p></div>
            </div>

            <div class="activity-list">
                @forelse($reservations as $reservation)
                    @php
                        $reservationLabel = match ($reservation->status) {
                            'confirmed' => 'Confirmed',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            default => 'Awaiting review',
                        };
                        $latestEvent = $reservation->statusHistories->last();
                    @endphp
                    <article class="activity-card">
                        <div class="activity-main">
                            <div class="activity-title">
                                <div>
                                    <span class="activity-kind">{{ $reservation->type === 'table' ? 'Table reservation' : 'Exclusive reservation' }}</span>
                                    <h3>{{ $reservation->reference }}</h3>
                                </div>
                                <span class="status {{ $reservation->status }}">{{ $reservationLabel }}</span>
                            </div>

                            <dl class="activity-details">
                                <div><dt>Date</dt><dd>{{ $reservation->reservation_at->format('M d, Y') }}</dd></div>
                                <div><dt>Time</dt><dd>{{ $reservation->reservation_at->format('h:i A') }}</dd></div>
                                <div><dt>Party</dt><dd>{{ $reservation->type === 'table' ? $reservation->table_size.' seats' : $reservation->guests.' guests' }}</dd></div>
                                <div><dt>Total</dt><dd>&#8369;{{ number_format($reservation->total_amount, 2) }}</dd></div>
                            </dl>

                            @if($reservation->items->isNotEmpty())
                                <p class="activity-items">
                                    {{ $reservation->items->map(fn ($item) => $item->quantity.' x '.($item->product?->name ?? 'Menu item'))->join(', ') }}
                                </p>
                            @endif

                            <p class="activity-update">
                                {{ $latestEvent ? 'Updated '.$latestEvent->created_at->diffForHumans() : 'Submitted '.$reservation->created_at->diffForHumans() }}
                                @if($latestEvent?->changedBy && $latestEvent->changed_by !== auth()->id())
                                    &middot; Updated by admin
                                @endif
                            </p>
                        </div>

                        <div class="activity-actions-row reservation-actions">
                            <span>{{ strtoupper($reservation->payment_method) }}@if($reservation->payment_reference) &middot; {{ $reservation->payment_reference }}@endif</span>
                            <div>
                                <a class="secondary-action" href="{{ route('reservations.show', $reservation) }}">View reservation</a>
                                <a href="{{ route('reservations.receipt', $reservation) }}">Print receipt</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="history-empty">
                        <h3>No reservations yet</h3>
                        <p>Your reservation requests will appear here.</p>
                        <a href="{{ route('reservations.create') }}">Make a reservation</a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="history-panel" id="purchases-panel" role="tabpanel" aria-labelledby="purchases-tab" data-history-panel="purchases" hidden>
            <div class="panel-heading">
                <div><h2>Your purchases</h2><p>Open an order to review its complete summary.</p></div>
            </div>

            <div class="activity-list">
                @forelse($orders as $order)
                    <article class="activity-card purchase-card">
                        <div class="activity-main">
                            <div class="activity-title">
                                <div>
                                    <span class="activity-kind">{{ strtoupper($order->payment_method) }} payment</span>
                                    <h3>Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</h3>
                                </div>
                                <span class="status {{ $order->payment_status }}">{{ $order->payment_status === 'paid' ? 'Paid' : 'Pending payment' }}</span>
                            </div>

                            <dl class="activity-details purchase-details">
                                <div><dt>Ordered</dt><dd>{{ $order->created_at->format('M d, Y') }}</dd></div>
                                <div><dt>Items</dt><dd>{{ $order->items->sum('quantity') }}</dd></div>
                                <div><dt>Total</dt><dd>&#8369;{{ number_format($order->total, 2) }}</dd></div>
                            </dl>

                            <p class="activity-items">
                                {{ $order->items->map(fn ($item) => $item->quantity.' x '.($item->product?->name ?? 'Product'))->join(', ') }}
                            </p>
                        </div>

                        <div class="activity-actions-row purchase-actions">
                            <span>{{ $order->created_at->format('h:i A') }}</span>
                            <div>
                                <a class="secondary-action" href="{{ route('shop.orders.show', $order) }}">View order</a>
                                @if($order->payment_status === 'paid')
                                    <a href="{{ route('receipts.show', $order) }}">Print receipt</a>
                                @else
                                    <a href="{{ route('shop.orders.show', $order) }}">Print order</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="history-empty">
                        <h3>No purchases yet</h3>
                        <p>Your completed orders will appear here.</p>
                        <a href="{{ route('shop') }}">Browse the menu</a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</main>

<style>
.history-page{min-height:100dvh;background:#f4f5ee;padding-bottom:52px;color:#171817}.history-page nav{height:78px;width:min(1180px,calc(100% - 30px));margin:auto;display:flex;align-items:center;justify-content:space-between}.history-page nav>a,.history-page nav>div{display:flex;align-items:center;gap:14px}.history-page nav a{text-decoration:none}.history-page nav img{width:48px;height:48px;border-radius:50%;background:#fff}.history-page nav form{margin:0}.history-page nav button{border:1px solid #ccd0c5;border-radius:9px;background:#fff;padding:9px 12px}.history-header,.history-summary,.history-content{width:min(980px,calc(100% - 30px));margin-inline:auto}.history-header{padding:42px 0 24px;display:flex;align-items:end;justify-content:space-between;gap:24px}.history-header p{margin:0;color:#777f00;font-size:12px;font-weight:800;letter-spacing:.12em}.history-header h1{margin:7px 0 0;font:500 40px/1.1 Georgia,serif;letter-spacing:0}.new-reservation{min-height:44px;padding:0 18px;border-radius:7px;background:#171817;color:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px;font-weight:800;white-space:nowrap}.history-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-block:1px solid #d6d9ce}.history-summary div{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 20px}.history-summary div+div{border-left:1px solid #d6d9ce}.history-summary span{color:#687064;font-size:13px}.history-summary strong{font-size:24px}.history-content{padding-top:28px}.history-tabs{display:flex;gap:4px;width:max-content;padding:4px;border:1px solid #d6d9ce;border-radius:8px;background:#e9ebe3}.history-tab{min-height:40px;padding:0 17px;border:0!important;border-radius:5px!important;background:transparent!important;color:#5c6259;font:700 14px/1 Arial,sans-serif;cursor:pointer}.history-tab span{margin-left:7px;color:#777d72;font-size:12px}.history-tab.active{background:#fff!important;color:#171817;box-shadow:0 1px 4px rgba(24,25,22,.1)}.panel-heading{display:flex;align-items:end;justify-content:space-between;margin:26px 0 12px}.panel-heading h2{margin:0;font-size:21px}.panel-heading p{margin:5px 0 0;color:#73796f;font-size:13px}.activity-list{display:grid;gap:10px}.activity-card{overflow:hidden;border:1px solid #d7dacf;border-radius:8px;background:#fff}.activity-main{padding:18px 20px 15px}.activity-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.activity-kind{display:block;margin-bottom:4px;color:#73796f;font-size:12px;font-weight:700}.activity-title h3{margin:0;font-size:18px;letter-spacing:0}.status{flex:0 0 auto;border-radius:999px;padding:6px 10px;background:#eff0ec;color:#5d6259;font-size:11px;font-weight:800}.status.confirmed,.status.paid{background:#e5f4e9;color:#257342}.status.completed{background:#e9eefb;color:#315ec9}.status.cancelled{background:#fdeaea;color:#b72c2c}.activity-details{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:17px 0 0;padding:13px 0;border-block:1px solid #e3e5dd}.activity-details div{display:grid;gap:3px}.activity-details div+div{padding-left:16px;border-left:1px solid #e3e5dd}.activity-details dt{color:#767c72;font-size:11px;font-weight:700;text-transform:uppercase}.activity-details dd{margin:0;font-size:14px;font-weight:750}.purchase-details{grid-template-columns:repeat(3,minmax(0,1fr))}.activity-items{margin:13px 0 0;color:#454941;font-size:13px;line-height:1.5}.activity-update{margin:10px 0 0;color:#858a81;font-size:12px}.activity-actions-row{min-height:52px;padding:8px 10px 8px 20px;border-top:1px solid #e3e5dd;background:#fafaf7;display:flex;align-items:center;justify-content:space-between;gap:16px}.activity-actions-row>span{min-width:0;color:#666c62;font-size:12px;overflow-wrap:anywhere}.activity-actions-row a{min-height:36px;padding:0 14px;border-radius:6px;background:#171817;color:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:800}.purchase-actions>div,.reservation-actions>div{display:flex;gap:7px}.activity-actions-row .secondary-action{border:1px solid #cfd2c8;background:#fff;color:#292b28}.history-empty{padding:56px 24px;border:1px dashed #cbd0c3;border-radius:8px;text-align:center}.history-empty h3{margin:0;font-size:18px}.history-empty p{margin:8px 0 18px;color:#73796f;font-size:14px}.history-empty a{display:inline-flex;min-height:40px;padding:0 15px;border-radius:6px;background:#171817;color:#fff;align-items:center;text-decoration:none;font-size:13px;font-weight:800}
@media(max-width:700px){.history-header{align-items:flex-start}.history-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.history-summary div:nth-child(3){border-left:0;border-top:1px solid #d6d9ce}.history-summary div:nth-child(4){border-top:1px solid #d6d9ce}.activity-details{grid-template-columns:repeat(2,minmax(0,1fr));row-gap:12px}.activity-details div:nth-child(3){padding-left:0;border-left:0}.activity-details div:nth-child(n+3){padding-top:12px;border-top:1px solid #e3e5dd}.purchase-details{grid-template-columns:repeat(3,minmax(0,1fr));row-gap:0}.purchase-details div:nth-child(3){padding:0 0 0 16px;border-top:0;border-left:1px solid #e3e5dd}}
@media(max-width:520px){.history-header{padding-top:28px;display:grid}.history-header h1{font-size:32px}.new-reservation{width:100%}.history-summary div{padding:14px}.history-summary span{font-size:12px}.history-summary strong{font-size:20px}.history-tabs{width:100%}.history-tab{flex:1;padding:0 10px}.activity-main{padding:16px}.activity-details,.purchase-details{grid-template-columns:1fr 1fr}.purchase-details div:nth-child(3){grid-column:1/-1;padding:12px 0 0;border-top:1px solid #e3e5dd;border-left:0}.activity-actions-row{align-items:stretch;padding:10px;flex-direction:column}.activity-actions-row a{width:100%}.purchase-actions>div,.reservation-actions>div{display:grid;grid-template-columns:1fr 1fr}.activity-title h3{font-size:16px}}
@media(max-width:520px){.history-page>nav{gap:8px}.history-page>nav>.history-brand{flex:0 0 48px;gap:0}.history-page>nav>.history-brand strong{display:none}.history-page>nav>.history-actions{min-width:0;gap:3px}.history-page>nav>.history-actions>a{padding:8px 5px!important;font-size:13px!important}.history-page>nav>.history-actions .logout-icon{width:40px!important;height:40px!important;min-width:40px!important;min-height:40px!important}}
@media(min-width:901px){.history-page>nav>.history-actions{grid-template-columns:minmax(0,1fr) 42px!important;grid-template-rows:auto auto auto 1fr auto!important;align-items:stretch!important;min-height:0;flex:1}.history-page>nav>.history-actions>a{grid-column:1/-1}.history-page>nav>.history-actions>a.active{background:#34372f!important;box-shadow:inset 4px 0 #b5c019;color:#fff}.history-page>nav>.history-actions>span{grid-row:5!important;grid-column:1;padding:15px 8px 0 12px!important;border-top:1px solid #343630;color:#aeb2a9;font-size:13px;display:flex;align-items:center;min-height:58px}.history-page>nav>.history-actions>form{grid-row:5!important;grid-column:2;margin:0!important;padding-top:15px;border-top:1px solid #343630;display:flex!important;align-items:center;justify-content:flex-end!important}.history-page>nav>.history-actions .logout-icon{width:42px!important;height:42px!important;min-width:42px!important;min-height:42px!important}.history-page>nav>.history-brand{box-sizing:border-box!important;width:100%;min-height:87px;display:flex!important;align-items:center!important;gap:10px!important;padding:4px 8px 28px!important;border-bottom:1px solid #343630!important;color:#fff!important;text-decoration:none!important}.history-page>nav>.history-brand img{width:58px!important;height:58px!important;min-width:58px;object-fit:contain!important;border-radius:50%!important;background:#fff!important}.history-page>nav>.history-brand strong{color:#fff!important;letter-spacing:.1em!important;white-space:nowrap}.history-page>.history-header,.history-page>.history-summary,.history-page>.history-content{grid-column:2}}
@media(max-width:900px){.history-actions>span{display:none}}
</style>



<script>
(() => {
    const tabs = [...document.querySelectorAll('[data-history-tab]')];
    const panels = [...document.querySelectorAll('[data-history-panel]')];

    function selectTab(name) {
        tabs.forEach((tab) => {
            const selected = tab.dataset.historyTab === name;
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.historyPanel !== name;
        });
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.historyTab)));
})();
</script>
@endsection
