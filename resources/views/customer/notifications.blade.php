@extends('layouts.app')
@section('title', 'Notifications | Kermit\'s')

@section('content')
<main class="history-page customer-account-page customer-notifications-page">
    @include('customer.navigation', ['activeCustomerNav' => 'notifications'])

    <header class="account-header">
        <p>MY ACCOUNT</p>
        <h1>Notifications</h1>
    </header>

    <section class="account-content notification-content">
        <div class="notification-heading">
            <div>
                <h2>Order updates</h2>
                <p>Cashier decisions for your submitted orders appear here.</p>
            </div>
            <span>{{ $orderNotifications->count() }} {{ Str::plural('update', $orderNotifications->count()) }}</span>
        </div>

        <div class="notification-list">
            @forelse($orderNotifications as $order)
                @php($accepted = $order->payment_status === 'paid')
                <article class="order-notification {{ $accepted ? 'accepted' : 'rejected' }}">
                    <div class="notification-state" aria-hidden="true">
                        @if($accepted)
                            <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                        @else
                            <svg viewBox="0 0 24 24"><path d="m7 7 10 10M17 7 7 17"/></svg>
                        @endif
                    </div>
                    <div class="notification-copy">
                        <div class="notification-title">
                            <h2>Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }} {{ $accepted ? 'accepted' : 'rejected' }}</h2>
                            <span>{{ $order->updated_at->diffForHumans() }}</span>
                        </div>
                        <p>{{ $accepted ? 'Your order was accepted by the cashier.' : 'Your order was rejected by the cashier.' }}</p>
                        <div class="notification-meta">
                            <span>{{ $order->items->sum('quantity') }} {{ Str::plural('item', $order->items->sum('quantity')) }}</span>
                            <span>&#8369;{{ number_format($order->total, 2) }}</span>
                            <span>{{ strtoupper($order->payment_method) }}</span>
                        </div>
                    </div>
                    <a class="notification-action" href="{{ route('shop.orders.show', $order) }}">View order</a>
                </article>
            @empty
                <div class="notification-empty">
                    <div aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    </div>
                    <h2>No order notifications yet</h2>
                    <p>You will see an update here after the cashier accepts or rejects your order.</p>
                    <a href="{{ route('shop') }}">Browse the menu</a>
                </div>
            @endforelse
        </div>
    </section>
</main>

@push('styles')
    @include('customer.account-styles')
<style>
.notification-content{padding-bottom:50px}.notification-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:16px}.notification-heading h2{margin:0;font-size:21px}.notification-heading p{margin:5px 0 0;color:#73796f;font-size:14px}.notification-heading>span{padding:7px 10px;border-radius:999px;background:#e8eadf;color:#555d00;font-size:12px;font-weight:800;white-space:nowrap}.notification-list{display:grid;gap:10px}.order-notification{display:grid;grid-template-columns:46px minmax(0,1fr) auto;align-items:center;gap:15px;padding:18px;border:1px solid #d7dacf;border-radius:10px;background:#fff}.notification-state{width:46px;height:46px;border-radius:50%;display:grid;place-items:center}.notification-state svg,.notification-empty svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round}.order-notification.accepted .notification-state{background:#e5f4e9;color:#257342}.order-notification.rejected .notification-state{background:#fdeaea;color:#b72c2c}.notification-title{display:flex;align-items:baseline;justify-content:space-between;gap:16px}.notification-title h2{margin:0;font-size:17px}.notification-title>span{color:#858a81;font-size:12px;white-space:nowrap}.notification-copy>p{margin:6px 0 10px;color:#555b52;font-size:14px}.notification-meta{display:flex;gap:8px;flex-wrap:wrap}.notification-meta span{padding-right:8px;border-right:1px solid #d9dcd2;color:#73796f;font-size:12px}.notification-meta span:last-child{border:0}.notification-action{min-height:38px;padding:0 14px;border:1px solid #cfd2c8;border-radius:7px;background:#fff;color:#292b28;display:inline-flex;align-items:center;text-decoration:none;font-size:13px;font-weight:800;white-space:nowrap}.notification-action:hover{background:#f3f4ee}.notification-empty{padding:65px 24px;border:1px dashed #cbd0c3;border-radius:10px;text-align:center}.notification-empty>div{width:54px;height:54px;margin:0 auto 14px;border-radius:50%;background:#e8eadf;color:#6e7700;display:grid;place-items:center}.notification-empty h2{margin:0;font-size:19px}.notification-empty p{margin:8px auto 18px;max-width:440px;color:#73796f;font-size:14px}.notification-empty>a{min-height:40px;padding:0 15px;border-radius:6px;background:#171817;color:#fff;display:inline-flex;align-items:center;text-decoration:none;font-size:13px;font-weight:800}
@media(max-width:700px){.order-notification{grid-template-columns:42px minmax(0,1fr)}.notification-state{width:42px;height:42px}.notification-action{grid-column:2;width:max-content}.notification-title{display:grid;gap:4px}.notification-title>span{white-space:normal}}
@media(max-width:520px){.notification-heading{align-items:flex-start}.order-notification{padding:15px;gap:12px}.notification-action{width:100%;justify-content:center}.notification-empty{padding:48px 18px}}
</style>
@endpush
@endsection
