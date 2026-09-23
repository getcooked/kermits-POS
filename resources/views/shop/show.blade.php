@extends('layouts.app')
@section('title', 'Order #'.$order->id)
@section('content')
@php
    $reservation = $order->reservation;
    $isRejected = $order->payment_status === 'rejected';
    $isAccepted = $order->payment_status === 'paid';
@endphp
<main class="online-receipt-page">
    <div class="online-receipt-wrap">
        <header class="receipt-heading">
            <div><p>{{ $isRejected ? 'ORDER NOT APPROVED' : ($isAccepted ? ($order->payment_method === 'paymongo' ? 'PAYMENT CONFIRMED' : 'ORDER ACCEPTED') : 'ORDER & RESERVATION RECEIVED') }}</p><h1>{{ $isRejected ? 'Your order was rejected.' : ($isAccepted ? ($order->payment_method === 'paymongo' ? 'Your payment was received.' : 'Your order was accepted.') : 'Thank you, '.auth()->user()->name.'.') }}</h1></div>
        </header>

        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if(session('payment_error'))<div class="notice" role="alert">{{ session('payment_error') }}</div>@endif

        <article class="online-receipt">
            <div class="receipt-brand">
                <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
                <div><strong>KERMIT'S</strong><span>Order Receipt</span></div>
            </div>

            <dl class="receipt-meta">
                <div><dt>Order</dt><dd>#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
                <div><dt>Date</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
                <div><dt>Customer</dt><dd>{{ auth()->user()->name }}</dd></div>
                @if($reservation)
                    <div><dt>Reservation</dt><dd>{{ $reservation->reference }}</dd></div>
                    <div><dt>Table</dt><dd>{{ $reservation->table_size }} {{ $reservation->table_size === 1 ? 'seat' : 'seats' }}</dd></div>
                    <div><dt>Schedule</dt><dd>{{ $reservation->reservation_at->format('M d, Y').' - '.$reservation->time_range }}</dd></div>
                @endif
                <div><dt>Payment</dt><dd>{{ match($order->payment_method) { 'cash' => 'Walk In Pay', 'paymongo' => 'PayMongo online', default => 'GCash' } }}</dd></div>
                <div><dt>Status</dt><dd>{{ $isRejected ? 'Rejected' : ($isAccepted ? ($order->payment_method === 'paymongo' ? 'Paid' : 'Accepted') : 'Pending '.match($order->payment_method) { 'gcash' => 'payment verification', 'paymongo' => 'PayMongo payment', default => 'counter payment' }) }}</dd></div>
                @if($order->payment_reference)<div><dt>{{ $order->payment_method === 'paymongo' ? 'PayMongo payment ID' : 'GCash reference' }}</dt><dd>{{ $order->payment_reference }}</dd></div>@endif
            </dl>

            <div class="receipt-items">
                @foreach($order->items as $item)
                    <div><span><strong>{{ $item->product?->name ?? 'Product' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span><b>&#8369;{{ number_format($item->subtotal, 2) }}</b></div>
                @endforeach
            </div>

            <div class="receipt-totals">
                <div><span>Food order</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></div>
                @if($reservation)<div><span>Table reservation</span><strong>&#8369;{{ number_format($reservation->total_amount, 2) }}</strong></div>@endif
                <div class="receipt-total"><span>{{ $isRejected ? 'Order total' : 'Total due' }}</span><strong>&#8369;{{ number_format($order->totalDue(), 2) }}</strong></div>
            </div>
            <p class="receipt-note">{{ $isRejected ? 'This order was rejected. No payment was recorded, and its reserved stock was returned.' : ($isAccepted ? ($order->payment_method === 'paymongo' ? 'PayMongo confirmed your payment. Your reservation is awaiting approval.' : 'Your order has been accepted and payment was confirmed by the cashier.') : match($order->payment_method) { 'gcash' => 'Your payment details are waiting for verification.', 'paymongo' => 'Your PayMongo payment is pending. This page will show Paid after PayMongo confirms it.', default => 'Present this receipt and pay at the counter when collecting your food.' }) }}</p>
        </article>

        <div class="order-actions">
            @if($order->payment_method === 'paymongo' && $order->payment_status === 'pending')
                <form method="POST" action="{{ route('shop.orders.paymongo', $order) }}">@csrf<button class="button" type="submit">Continue to PayMongo</button></form>
            @endif
            <a class="button" href="{{ route('shop.orders.receipt', $order) }}" download>Download receipt</a>
            <a class="logout" href="{{ route('shop') }}">Order more</a>
        </div>
    </div>
</main>
<script>
@if(session('clear_customer_cart'))
try { localStorage.removeItem(@json('kermits-customer-cart-v1-'.auth()->id())); } catch (_) {}
@endif
</script>
@push('styles')
<style>
.online-receipt-page{min-height:100dvh;padding:34px 16px;background:#f4f5ee;color:#171817}.online-receipt-wrap{width:min(480px,100%);margin:0 auto}.receipt-heading{margin-bottom:16px}.receipt-heading p{margin:0 0 5px;color:#747d00;font-size:11px;font-weight:800;letter-spacing:.12em}.receipt-heading h1{margin:0;font-size:24px;letter-spacing:0}.online-receipt{padding:28px;border:1px solid #d7dacf;border-radius:8px;background:#fff;box-shadow:0 14px 38px rgba(23,24,23,.07)}.receipt-brand{display:flex;align-items:center;gap:12px;padding-bottom:17px;border-bottom:1px dashed #aeb5aa}.receipt-brand img{width:50px;height:50px;padding:3px;border:1px solid #e1e3dc;border-radius:50%;object-fit:contain}.receipt-brand div{display:grid;gap:3px}.receipt-brand strong{letter-spacing:.1em}.receipt-brand span,.receipt-meta dt,.receipt-items small{color:#73796f;font-size:11px}.receipt-meta{display:grid;gap:9px;margin:0;padding:17px 0}.receipt-meta div{display:grid;gap:2px}.receipt-meta dd{margin:0;font-size:13px;font-weight:750;overflow-wrap:anywhere}.receipt-items{padding:7px 0;border-block:1px dashed #aeb5aa}.receipt-items>div{display:flex;justify-content:space-between;gap:18px;padding:9px 0}.receipt-items span{display:grid;gap:3px}.receipt-items b{white-space:nowrap}.receipt-totals{display:grid;gap:8px;padding-top:15px}.receipt-totals>div{display:flex;justify-content:space-between;gap:18px;color:#656b62;font-size:13px}.receipt-totals .receipt-total{margin-top:3px;padding-top:12px;border-top:1px dashed #aeb5aa;color:#171817;font-size:18px}.receipt-note{margin:24px 0 0;color:#73796f;text-align:center;font-size:12px;line-height:1.5}.order-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}.order-actions a{text-decoration:none}.order-actions .button{display:flex;align-items:center;justify-content:center}@media(max-width:520px){.online-receipt-page{padding:20px 12px}.online-receipt{padding:20px}.receipt-heading h1{font-size:20px}.order-actions{grid-template-columns:1fr}}
@page{margin:12mm}
</style>
@endpush
@endsection
