@extends('layouts.app')
@section('title', 'Customer Orders · Kermit’s POS')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace order-workspace">
        <div class="customer-orders">
            <header class="orders-head">
                <div>
                    <p>ONLINE SALES</p>
                    <h1>Customer orders</h1>
                    <span>Verify GCash proofs, collect Walk In Pay cash, and track PayMongo payments.</span>
                </div>
                <strong>{{ $method === 'paymongo' ? $counts['paymongo'].' awaiting PayMongo' : $orders->count().' pending' }}</strong>
            </header>

            <nav class="payment-tabs" aria-label="Filter by payment method">
                @foreach(['all' => 'All pending', 'gcash' => 'GCash proofs', 'cash' => 'Walk In Pay', 'paymongo' => 'PayMongo'] as $tab => $label)
                    <a href="{{ route('cashier.orders.index', $tab === 'all' ? [] : ['method' => $tab]) }}" @class(['is-active' => $method === $tab]) @if($method === $tab) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$tab] }}</span></a>
                @endforeach
            </nav>

            @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="error orders-error">{{ $errors->first() }}</div>@endif

            <div class="order-list">
                @forelse($orders as $order)
                    <article class="order-card">
                        <header>
                            <div>
                                <p>ORDER #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
                                <h2>{{ $order->customer?->name ?? 'Deleted customer' }}</h2>
                                <span>{{ $order->customer?->email }} · {{ $order->created_at->format('M d, Y · h:i A') }}</span>
                            </div>
                            <div class="order-total"><span>Total due</span><strong>&#8369;{{ number_format($order->totalDue(), 2) }}</strong></div>
                        </header>

                        <section class="order-items">
                            <h3>Complete order</h3>
                            @foreach($order->items as $item)
                                <div>
                                    <span><b>{{ $item->product?->name ?? 'Unavailable product' }}</b><small>&#8369;{{ number_format($item->unit_price, 2) }} each</small></span>
                                    <strong>{{ $item->quantity }} ×</strong>
                                    <b>&#8369;{{ number_format($item->subtotal, 2) }}</b>
                                </div>
                            @endforeach
                        </section>

                        <footer>
                            <div class="payment-summary">
                                <span class="payment-method {{ $order->payment_method }}">{{ match($order->payment_method) { 'gcash' => 'GCASH', 'paymongo' => 'PAYMONGO', default => 'WALK IN PAY' } }}</span>
                                @if($order->payment_method === 'paymongo')
                                    @if($order->payment_status === 'paid')
                                        <span><b>Paid &middot; verified by PayMongo</b> · {{ $order->paid_at?->format('M d, Y · h:i A') }}</span>
                                    @elseif($order->payment_status === 'rejected')
                                        <span><b>Rejected</b> · Stock was released.</span>
                                    @elseif($order->paymongo_checkout_id)
                                        <span><b>Awaiting PayMongo</b> · The customer has not finished checkout yet. It is confirmed automatically once paid.</span>
                                    @else
                                        <span><b>PayMongo checkout failed</b> · No checkout session was created. Reject the order to release its stock.</span>
                                    @endif
                                @elseif($order->payment_method === 'gcash')
                                    @if($order->reservation?->payment_proof_path)
                                        <a class="proof-thumb" href="{{ route('cashier.orders.payment-proof', $order) }}" target="_blank" rel="noopener"><img src="{{ route('cashier.orders.payment-proof', $order) }}" alt="GCash proof for order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}" loading="lazy"></a>
                                    @endif
                                    <span><b>GCash proof submitted</b> · Ref: {{ $order->payment_reference ?? 'missing' }}@unless($order->reservation?->payment_proof_path) · <b class="proof-warning">No proof uploaded</b>@endunless</span>
                                @else
                                    <span><b>Not yet paid</b> · Collect cash at the counter.</span>
                                @endif
                            </div>
                            @if($order->payment_method === 'paymongo')
                                @if($order->payment_status === 'paid')
                                    <a class="review-order" href="{{ route('receipts.show', $order) }}">View receipt <span>→</span></a>
                                @elseif($order->payment_status === 'pending' && ! $order->paymongo_checkout_id)
                                <form method="POST" action="{{ route('cashier.orders.reject', $order) }}" data-ajax-form data-ajax-target=".customer-orders" data-ajax-loading="Rejecting..." data-confirm="Reject this unpaid order and release its stock?" data-confirm-title="Reject unpaid order?">@csrf @method('PATCH')<button type="submit">Reject unpaid order</button></form>
                                @endif
                            @else
                                <a class="review-order" href="{{ route('cashier.orders.review', $order) }}">Review order <span>→</span></a>
                            @endif
                        </footer>
                    </article>
                @empty
                    <section class="empty-orders">
                        <span aria-hidden="true">&#10003;</span>
                        <h2>{{ $method === 'paymongo' ? 'No PayMongo payments yet' : 'All customer orders are cleared' }}</h2>
                        <p>{{ $method === 'paymongo' ? 'Online PayMongo orders and their payment status will appear here.' : 'New customer orders waiting for payment will appear here.' }}</p>
                    </section>
                @endforelse
            </div>
        </div>
    </main>
</div>
@push('styles')
<style>
.order-workspace{background:#f3f4ed!important}.customer-orders{width:min(1040px,100%);margin:auto}.orders-head{display:flex;justify-content:space-between;align-items:center;gap:24px;margin-bottom:24px}.orders-head p,.order-card>header p{margin:0 0 6px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.orders-head h1{margin:0;font-size:32px;letter-spacing:-.04em}.orders-head>div>span,.order-card>header>div>span{display:block;color:#747a71;margin-top:6px}.orders-head>strong{padding:9px 13px;border-radius:999px;background:#e8ebcf;color:#626a00;font-size:12px}.orders-error{padding:12px 14px;margin-bottom:18px;background:#fff0f0;border-radius:10px}.order-list{display:grid;gap:16px}.order-card{background:#fff;border:1px solid #daddd2;border-radius:18px;padding:22px;box-shadow:0 12px 35px rgba(25,27,23,.05)}.order-card>header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:18px;border-bottom:1px solid #e5e7df}.order-card h2{font-size:20px;margin:0}.order-card>header>div>span{font-size:12px}.order-total{text-align:right;display:grid;gap:3px}.order-total span{color:#767b73;font-size:11px}.order-total strong{font-size:23px}.order-items{padding:16px 0}.order-items h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#747a71}.order-items>div{display:grid;grid-template-columns:minmax(0,1fr) 55px 100px;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #eeefe9}.order-items>div>span{display:grid}.order-items small{color:#7c8179;margin-top:3px}.order-items>div>strong,.order-items>div>b{text-align:right}.order-card>footer{display:flex;justify-content:space-between;align-items:center;gap:20px;padding-top:4px}.payment-summary{display:flex;align-items:center;gap:12px;color:#676d64;font-size:12px}.payment-method{display:inline-flex;padding:6px 9px;border-radius:999px;background:#e7f2ec;color:#267058;font-size:10px;font-weight:850}.payment-method.gcash{background:#e6efff;color:#1762b8}.order-card form{margin:0}.order-card button{min-height:44px;border:0;border-radius:11px;padding:0 17px;background:#171817;color:#fff;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:22px}.empty-orders{min-height:310px;display:grid;place-content:center;text-align:center;background:#fff;border:1px dashed #cdd1c6;border-radius:18px;padding:30px}.empty-orders>span{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;background:#e9ecd2;color:#687100;font-size:24px}.empty-orders h2{margin:0 0 7px}.empty-orders p{margin:0;color:#777d74}
@media(max-width:700px){.orders-head,.order-card>header,.order-card>footer{align-items:stretch;flex-direction:column}.orders-head>strong{align-self:flex-start}.order-total{text-align:left}.order-items>div{grid-template-columns:minmax(0,1fr) 42px 82px}.payment-summary{align-items:flex-start;flex-direction:column}.order-card button{width:100%;justify-content:space-between}.order-card{padding:17px}}
.payment-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:-6px 0 20px}.payment-tabs a{display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 14px;border:1px solid #d5d9cc;border-radius:999px;background:#fff;color:#454a42;font-size:12px;font-weight:800;text-decoration:none}.payment-tabs a span{min-width:20px;padding:2px 6px;border-radius:999px;background:#eef0e6;font-size:10px;text-align:center}.payment-tabs a:hover{border-color:#879000}.payment-tabs a.is-active{background:#171817;border-color:#171817;color:#fff}.payment-tabs a.is-active span{background:#3a3c38;color:#fff}.payment-method.paymongo{background:#efe9ff;color:#5b3cc4}.payment-method.cash{background:#fff1e8;color:#9b4b16}.proof-thumb{flex:0 0 auto;display:block;width:52px;height:52px;border:1px solid #d7dde8;border-radius:9px;overflow:hidden;background:#f3f5f8}.proof-thumb img{width:100%;height:100%;object-fit:cover}.proof-thumb:hover{border-color:#1762b8}.proof-warning{color:#b42318}
.review-order{min-height:44px;border-radius:11px;padding:0 17px;background:#171817;color:#fff!important;text-decoration:none;font-weight:800;display:flex;align-items:center;gap:22px}
@media(max-width:700px){.review-order{width:100%;justify-content:space-between}}
</style>
@endpush

@endsection
