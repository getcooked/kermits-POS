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
                    <span>Verify and confirm the payment for the complete order.</span>
                </div>
                <strong>{{ $orders->count() }} pending</strong>
            </header>

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
                            <div class="order-total"><span>Order total</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></div>
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
                                <span class="payment-method {{ $order->payment_method }}">{{ strtoupper($order->payment_method) }}</span>
                                @if($order->payment_reference)
                                    <span><b>GCash submitted</b> · Ref: {{ $order->payment_reference }}</span>
                                @else
                                    <span><b>Not yet paid</b> · Cash is required at the counter.</span>
                                @endif
                            </div>
                            <a class="review-order" href="{{ route('cashier.orders.review', $order) }}">Review order <span>→</span></a>
                        </footer>
                    </article>
                @empty
                    <section class="empty-orders">
                        <span aria-hidden="true">&#10003;</span>
                        <h2>All customer orders are cleared</h2>
                        <p>New customer orders waiting for payment will appear here.</p>
                    </section>
                @endforelse
            </div>
        </div>
    </main>
</div>
<style>
.order-workspace{background:#f3f4ed!important}.customer-orders{width:min(1040px,100%);margin:auto}.orders-head{display:flex;justify-content:space-between;align-items:center;gap:24px;margin-bottom:24px}.orders-head p,.order-card>header p{margin:0 0 6px;color:#7d8600;font-size:10px;font-weight:850;letter-spacing:.14em}.orders-head h1{margin:0;font-size:32px;letter-spacing:-.04em}.orders-head>div>span,.order-card>header>div>span{display:block;color:#747a71;margin-top:6px}.orders-head>strong{padding:9px 13px;border-radius:999px;background:#e8ebcf;color:#626a00;font-size:12px}.orders-error{padding:12px 14px;margin-bottom:18px;background:#fff0f0;border-radius:10px}.order-list{display:grid;gap:16px}.order-card{background:#fff;border:1px solid #daddd2;border-radius:18px;padding:22px;box-shadow:0 12px 35px rgba(25,27,23,.05)}.order-card>header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:18px;border-bottom:1px solid #e5e7df}.order-card h2{font-size:20px;margin:0}.order-card>header>div>span{font-size:12px}.order-total{text-align:right;display:grid;gap:3px}.order-total span{color:#767b73;font-size:11px}.order-total strong{font-size:23px}.order-items{padding:16px 0}.order-items h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#747a71}.order-items>div{display:grid;grid-template-columns:minmax(0,1fr) 55px 100px;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #eeefe9}.order-items>div>span{display:grid}.order-items small{color:#7c8179;margin-top:3px}.order-items>div>strong,.order-items>div>b{text-align:right}.order-card>footer{display:flex;justify-content:space-between;align-items:center;gap:20px;padding-top:4px}.payment-summary{display:flex;align-items:center;gap:12px;color:#676d64;font-size:12px}.payment-method{display:inline-flex;padding:6px 9px;border-radius:999px;background:#e7f2ec;color:#267058;font-size:10px;font-weight:850}.payment-method.gcash{background:#e6efff;color:#1762b8}.order-card form{margin:0}.order-card button{min-height:44px;border:0;border-radius:11px;padding:0 17px;background:#171817;color:#fff;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:22px}.empty-orders{min-height:310px;display:grid;place-content:center;text-align:center;background:#fff;border:1px dashed #cdd1c6;border-radius:18px;padding:30px}.empty-orders>span{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;margin:0 auto 14px;background:#e9ecd2;color:#687100;font-size:24px}.empty-orders h2{margin:0 0 7px}.empty-orders p{margin:0;color:#777d74}
@media(max-width:700px){.orders-head,.order-card>header,.order-card>footer{align-items:stretch;flex-direction:column}.orders-head>strong{align-self:flex-start}.order-total{text-align:left}.order-items>div{grid-template-columns:minmax(0,1fr) 42px 82px}.payment-summary{align-items:flex-start;flex-direction:column}.order-card button{width:100%;justify-content:space-between}.order-card{padding:17px}}
</style>
<style>
.review-order{min-height:44px;border-radius:11px;padding:0 17px;background:#171817;color:#fff!important;text-decoration:none;font-weight:800;display:flex;align-items:center;gap:22px}
@media(max-width:700px){.review-order{width:100%;justify-content:space-between}}
</style>
@endsection
