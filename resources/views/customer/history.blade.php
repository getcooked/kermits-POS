@extends('layouts.app')
@section('title', 'My Activity | Kermit\'s')

@section('content')
@php
    $activeReservations = $reservations->whereIn('status', ['pending', 'confirmed'])->count();
    $paidOrders = $orders->where('payment_status', 'paid')->count();
    $paymentLabel = fn (?string $method): string => match ($method) {
        'cash' => 'Walk In Pay',
        'paymongo' => 'PayMongo online',
        default => 'GCash',
    };
@endphp

<main class="history-page customer-account-page">
    @include('customer.navigation', ['activeCustomerNav' => 'history'])

    <div class="history-body">
        <header class="history-header">
            <p class="history-eyebrow">MY ACCOUNT</p>
            <h1>Reservations and purchases</h1>
        </header>

        <section class="history-summary" aria-label="Activity summary">
            <div><span>Reservations</span><strong>{{ $reservations->count() }}</strong></div>
            <div><span>Active requests</span><strong>{{ $activeReservations }}</strong></div>
            <div><span>Purchases</span><strong>{{ $orders->count() }}</strong></div>
            <div><span>Paid orders</span><strong>{{ $paidOrders }}</strong></div>
        </section>

        <section class="today-summary" aria-label="Today's purchase summary">
            <div>
                <p class="history-eyebrow">TODAY</p>
                <h2>Your purchase activity</h2>
                <time datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
            </div>
            <dl>
                <div><dt>Orders today</dt><dd>{{ $todayOrderCount }}</dd></div>
                <div><dt>Paid today</dt><dd>&#8369;{{ number_format($todayPaidTotal, 2) }}</dd></div>
            </dl>
        </section>

        <div class="history-tabs" role="tablist" aria-label="Activity type">
            <button class="history-tab active" id="reservations-tab" type="button" role="tab" aria-selected="true" aria-controls="reservations-panel" data-history-tab="reservations">
                Reservations <span>{{ $reservations->count() }}</span>
            </button>
            <button class="history-tab" id="purchases-tab" type="button" role="tab" aria-selected="false" aria-controls="purchases-panel" data-history-tab="purchases" tabindex="-1">
                Purchases <span>{{ $orders->count() }}</span>
            </button>
        </div>

        <section class="history-panel" id="reservations-panel" role="tabpanel" aria-labelledby="reservations-tab" data-history-panel="reservations">
            <div class="panel-heading">
                <h2>Your reservations</h2>
                <p>Reservations are grouped by their scheduled date.</p>
            </div>

            <div class="activity-list">
                @forelse($reservations->groupBy(fn ($reservation) => $reservation->reservation_at->toDateString()) as $date => $dateReservations)
                    <section class="date-group">
                        <h3 class="date-heading"><time datetime="{{ $date }}">{{ $dateReservations->first()->reservation_at->format('l, F j, Y') }}</time></h3>

                        @foreach($dateReservations as $reservation)
                            @php
                                $reservationLabel = match ($reservation->booking_status) {
                                    'confirmed' => 'Confirmed',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled',
                                    'expired' => 'Expired',
                                    'rejected' => 'Rejected',
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
                                        <span class="status {{ $reservation->booking_status }}">{{ $reservationLabel }}</span>
                                    </div>

                                    <dl class="activity-details">
                                        <div><dt>Date</dt><dd>{{ $reservation->reservation_at->format('M d, Y') }}</dd></div>
                                        <div><dt>Time</dt><dd>{{ $reservation->arrival_time }}</dd></div>
                                        <div><dt>Party</dt><dd>{{ $reservation->type === 'table' ? $reservation->table_size.' seats' : $reservation->guests.' guests' }}</dd></div>
                                        @if($reservation->type === 'table')
                                            <div><dt>Table</dt><dd>{{ $reservation->table_label }}</dd></div>
                                        @endif
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

                                <div class="activity-actions">
                                    <span>{{ $paymentLabel($reservation->payment_method) }}@if($reservation->payment_reference) &middot; {{ $reservation->payment_reference }}@endif</span>
                                    <div>
                                        <a class="secondary-action" href="{{ route('reservations.show', $reservation) }}">View reservation</a>
                                        <a href="{{ route('reservations.receipt', $reservation) }}">Print receipt</a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @empty
                    <div class="history-empty">
                        <h3>No reservations yet</h3>
                        <p>Your reservation requests will appear here.</p>
                        <a href="{{ route('reservations.create') }}">Make a reservation</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="history-panel" id="purchases-panel" role="tabpanel" aria-labelledby="purchases-tab" data-history-panel="purchases" hidden>
            <div class="panel-heading">
                <h2>Your purchases</h2>
                <p>Open an order to review its complete summary.</p>
            </div>

            <div class="activity-list">
                @forelse($orders->groupBy(fn ($order) => $order->created_at->toDateString()) as $date => $dateOrders)
                    @php $groupDate = $dateOrders->first()->created_at; @endphp
                    <section class="date-group">
                        <h3 class="date-heading">
                            <time datetime="{{ $date }}">@if($groupDate->isToday())Today's purchases@elseif($groupDate->isYesterday())Yesterday@else{{ $groupDate->format('l, F j, Y') }}@endif</time>
                        </h3>

                        @foreach($dateOrders as $order)
                            @php
                                $orderStatusLabel = match ($order->payment_status) {
                                    'paid' => 'Paid',
                                    'rejected' => 'Rejected',
                                    default => 'Pending payment',
                                };
                            @endphp
                            <article class="activity-card">
                                <div class="activity-main">
                                    <div class="activity-title">
                                        <div>
                                            <span class="activity-kind">{{ $paymentLabel($order->payment_method) }} payment</span>
                                            <h3>Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</h3>
                                        </div>
                                        <span class="status {{ $order->payment_status }}">{{ $orderStatusLabel }}</span>
                                    </div>

                                    <dl class="activity-details">
                                        <div><dt>Ordered</dt><dd>{{ $order->created_at->format('M d, Y') }}</dd></div>
                                        <div><dt>Items</dt><dd>{{ $order->items->sum('quantity') }}</dd></div>
                                        <div><dt>Total</dt><dd>&#8369;{{ number_format($order->total, 2) }}</dd></div>
                                    </dl>

                                    <p class="activity-items">
                                        {{ $order->items->map(fn ($item) => $item->quantity.' x '.($item->product?->name ?? 'Product'))->join(', ') }}
                                    </p>
                                </div>

                                <div class="activity-actions">
                                    <span>{{ $order->created_at->format('h:i A') }}</span>
                                    <div>
                                        <a class="secondary-action" href="{{ route('shop.orders.show', $order) }}">View order</a>
                                        @if($order->payment_status !== 'rejected')
                                            <a href="{{ route('receipts.show', $order) }}">View receipt</a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @empty
                    <div class="history-empty">
                        <h3>No purchases yet</h3>
                        <p>Your completed orders will appear here.</p>
                        <a href="{{ route('shop') }}">Browse the menu</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @include('customer.account-styles')
</main>
@endsection

@push('styles')
<style>
/* Page body: one centered column so the header, summary and lists share the same edges. */
.history-body{
    --history-ink:#171817;
    --history-muted:#6e746a;
    --history-line:#d7dacf;
    --history-line-soft:#e3e5dd;
    --history-accent:#777f00;
    box-sizing:border-box;
    width:min(980px,calc(100% - 32px));
    margin-inline:auto;
    padding:44px 0 56px;
    color:var(--history-ink);
}
@media(min-width:901px){
    .history-page>.history-body{grid-column:2;width:min(980px,calc(100% - 80px))}
}
.history-eyebrow{margin:0;color:var(--history-accent);font-size:12px;font-weight:800;letter-spacing:.12em}

.history-header{padding-bottom:26px}
.history-header h1{margin:8px 0 0;font-size:44px;line-height:1.08;font-weight:800;letter-spacing:-.04em}

/* Summary strip */
.history-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-block:1px solid var(--history-line)}
.history-summary div{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:20px 22px}
.history-summary div+div{border-left:1px solid var(--history-line)}
.history-summary span{color:var(--history-muted);font-size:13px}
.history-summary strong{font-size:25px;font-weight:800}

/* Today card */
.today-summary{margin-top:22px;padding:22px 24px;border:1px solid var(--history-line);border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:space-between;gap:24px}
.today-summary .history-eyebrow{font-size:11px}
.today-summary h2{margin:6px 0 4px;font-size:21px;font-weight:800}
.today-summary time{color:var(--history-muted);font-size:13px}
.today-summary dl{display:grid;grid-template-columns:repeat(2,minmax(140px,auto));margin:0}
.today-summary dl div{padding:4px 24px}
.today-summary dl div+div{border-left:1px solid var(--history-line-soft)}
.today-summary dt{color:var(--history-muted);font-size:13px}
.today-summary dd{margin:6px 0 0;font-size:23px;font-weight:800}

/* Tabs */
.history-tabs{display:flex;gap:4px;width:max-content;margin-top:32px;padding:4px;border:1px solid var(--history-line);border-radius:8px;background:#e9ebe3}
.history-page .history-tab{min-height:44px;padding:0 18px;border:0;border-radius:5px;background:transparent;color:#5c6259;font:700 15px/1 Arial,sans-serif;cursor:pointer}
.history-page .history-tab span{margin-left:8px;color:#777d72;font-size:12px}
.history-page .history-tab.active{background:#fff;color:var(--history-ink);box-shadow:0 1px 4px rgba(24,25,22,.1)}
.history-page .history-tab:focus-visible{outline:3px solid rgba(174,187,25,.4);outline-offset:1px}

/* Panels and date groups */
.panel-heading{margin:30px 0 14px}
.panel-heading h2{margin:0;font-size:22px;font-weight:800}
.panel-heading p{margin:6px 0 0;color:var(--history-muted);font-size:14px}
.activity-list,.date-group{display:grid;gap:10px}
.activity-list{gap:22px}
.date-heading{margin:0;padding:10px 14px;border-radius:7px;background:#e8eadf;color:#34382d;font-size:14px;font-weight:800}

/* Activity cards */
.activity-card{overflow:hidden;border:1px solid var(--history-line);border-radius:10px;background:#fff}
.activity-main{padding:20px 22px 16px}
.activity-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.activity-kind{display:block;margin-bottom:5px;color:var(--history-muted);font-size:13px;font-weight:700}
.activity-title h3{margin:0;font-size:19px;font-weight:800;letter-spacing:.01em;overflow-wrap:anywhere}
.status{flex:0 0 auto;padding:6px 11px;border-radius:999px;background:#eff0ec;color:#5d6259;font-size:12px;font-weight:800}
.status.confirmed,.status.paid{background:#e5f4e9;color:#257342}
.status.completed{background:#e9eefb;color:#315ec9}
.status.cancelled,.status.rejected{background:#fdeaea;color:#b72c2c}

/* Details: every field sits on one row, however many fields a card has. */
.activity-details{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(0,1fr);margin:18px 0 0;padding:14px 0;border-block:1px solid var(--history-line-soft)}
.activity-details div{display:grid;align-content:start;gap:4px;min-width:0}
.activity-details div+div{padding-left:18px;border-left:1px solid var(--history-line-soft)}
.activity-details dt{color:var(--history-muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.activity-details dd{margin:0;font-size:15px;font-weight:800;overflow-wrap:anywhere}
.activity-items{margin:14px 0 0;color:#454941;font-size:13px;line-height:1.5}
.activity-update{margin:12px 0 0;color:#858a81;font-size:12px}

.activity-actions{min-height:54px;padding:8px 12px 8px 22px;border-top:1px solid var(--history-line-soft);background:#fafaf7;display:flex;align-items:center;justify-content:space-between;gap:16px}
.activity-actions>span{min-width:0;color:#666c62;font-size:12px;overflow-wrap:anywhere}
.activity-actions>div{display:flex;gap:8px}
.activity-actions a,.history-empty a{min-height:38px;padding:0 15px;border:1px solid var(--history-ink);border-radius:7px;background:var(--history-ink);color:#fff;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:800;white-space:nowrap}
.activity-actions a.secondary-action{border-color:#cfd2c8;background:#fff;color:#292b28}

.history-empty{padding:56px 24px;border:1px dashed #cbd0c3;border-radius:10px;text-align:center}
.history-empty h3{margin:0;font-size:18px}
.history-empty p{margin:8px 0 18px;color:var(--history-muted);font-size:14px}

@media(max-width:900px){
    .history-body{padding-top:30px}
}
@media(max-width:700px){
    .history-header h1{font-size:34px}
    .history-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
    .history-summary div:nth-child(3){border-left:0}
    .history-summary div:nth-child(n+3){border-top:1px solid var(--history-line)}
    .today-summary{flex-direction:column;align-items:stretch}
    .today-summary dl{grid-template-columns:1fr 1fr}
    .today-summary dl div{padding:4px 12px 4px 0}
    .today-summary dl div+div{padding-left:14px}
    .activity-details{grid-auto-flow:row;grid-template-columns:repeat(2,minmax(0,1fr));row-gap:12px}
    .activity-details div+div{padding-left:0;border-left:0}
    .activity-details div:nth-child(even){padding-left:16px;border-left:1px solid var(--history-line-soft)}
    .activity-details div:nth-child(n+3){padding-top:12px;border-top:1px solid var(--history-line-soft)}
    .activity-details div:last-child:nth-child(odd){grid-column:1/-1}
}
@media(max-width:520px){
    .history-body{width:calc(100% - 32px)}
    .history-header{padding-bottom:20px}
    .history-header h1{font-size:30px}
    .history-summary div{padding:14px}
    .history-summary span{font-size:12px}
    .history-summary strong{font-size:20px}
    .today-summary{padding:18px}
    .today-summary dd{font-size:19px}
    .history-tabs{width:auto}
    .history-page .history-tab{flex:1;padding:0 10px}
    .activity-main{padding:16px}
    .activity-title h3{font-size:16px}
    .activity-actions{flex-direction:column;align-items:stretch;padding:12px}
    .activity-actions>div{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr))}
}
</style>
@endpush

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const tabs = [...document.querySelectorAll('[data-history-tab]')];
    const panels = [...document.querySelectorAll('[data-history-panel]')];

    function selectTab(name) {
        tabs.forEach((tab) => {
            const selected = tab.dataset.historyTab === name;
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.historyPanel !== name;
        });
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab.dataset.historyTab));
        tab.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
            const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            selectTab(next.dataset.historyTab);
            next.focus();
        });
    });
})();
</script>
@endpush
