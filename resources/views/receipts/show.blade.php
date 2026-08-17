@extends('layouts.app')
@section('title', 'Receipt #'.$order->id)
@section('content')
@php
    $viewer = auth()->user();
    $isCustomerReceipt = $viewer?->hasRole(\App\Models\User::ROLE_CUSTOMER);
    $backRoute = $isCustomerReceipt
        ? route('customer.history')
        : ($viewer?->hasRole(\App\Models\User::ROLE_CASHIER, \App\Models\User::ROLE_SUPER_ADMIN)
            ? route('cashier')
            : route('reports'));
@endphp
<div class="{{ $isCustomerReceipt ? 'customer-receipt-shell' : 'admin-shell receipt-shell' }}">
    @unless($isCustomerReceipt)
        @include('partials.admin-sidebar')
    @endunless

    <main class="{{ $isCustomerReceipt ? 'customer-receipt-workspace' : 'admin-workspace' }}">
        <div class="receipt-wrap">
            <header class="receipt-page-head">
                <div><p>TRANSACTION COMPLETE</p><h1>Receipt #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</h1></div>
                <a class="logout" href="{{ $backRoute }}">Back</a>
            </header>

            @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif

            <article class="receipt-card">
                <div class="receipt-brand">
                    <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
                    <div><strong>KERMIT'S</strong><span>Official Receipt</span></div>
                </div>

                <dl class="receipt-meta">
                    <div><dt>Receipt</dt><dd>#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
                    <div><dt>Date</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
                    <div><dt>Customer</dt><dd>{{ $order->customer?->name ?? $order->user?->name ?? 'Walk-in Customer' }}</dd></div>
                    <div><dt>Cashier</dt><dd>{{ $order->user?->hasRole(\App\Models\User::ROLE_CASHIER, \App\Models\User::ROLE_SUPER_ADMIN) ? $order->user->name : 'Online order' }}</dd></div>
                    <div><dt>Payment</dt><dd>{{ ucfirst($order->payment_method) }}</dd></div>
                </dl>

                <div class="receipt-items">
                    @foreach($order->items as $item)
                        <div><span><strong>{{ $item->product?->name ?? 'Product' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span><b>&#8369;{{ number_format($item->subtotal, 2) }}</b></div>
                    @endforeach
                </div>

                <div class="receipt-totals">
                    <strong><span>Total</span><b>&#8369;{{ number_format($order->total, 2) }}</b></strong>
                    @if($order->cash_received !== null)
                        <span>Cash received <b>&#8369;{{ number_format($order->cash_received, 2) }}</b></span>
                        <span>Change <b>&#8369;{{ number_format($order->change_due, 2) }}</b></span>
                    @elseif($order->payment_reference)
                        <span>GCash reference <b>{{ $order->payment_reference }}</b></span>
                    @endif
                </div>
                <p class="receipt-note">Thank you for your purchase!</p>
            </article>

            <div class="receipt-actions">
                <button class="button" type="button" id="print-receipt">Print receipt</button>
                <a class="logout" href="{{ $backRoute }}">Back</a>
            </div>
            <p class="print-help" id="print-help" hidden>If the print dialog did not open, press Ctrl + P.</p>
        </div>
    </main>
</div>
<script>
document.getElementById('print-receipt')?.addEventListener('click', () => {
    document.getElementById('print-help')?.removeAttribute('hidden');
    window.focus();
    window.print();
});
</script>
<style>
.customer-receipt-shell{min-height:100dvh;padding:36px 18px;background:#f4f4ed}.customer-receipt-workspace{width:100%}.receipt-wrap{width:min(480px,100%);margin:0 auto}.receipt-page-head{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:16px}.receipt-page-head p{margin:0 0 5px;color:#747d00;font-size:11px;font-weight:800;letter-spacing:.12em}.receipt-page-head h1{margin:0;font-size:24px;letter-spacing:0}.receipt-page-head a,.receipt-actions a{text-decoration:none}.receipt-card{padding:28px;border:1px solid #d7dacf;border-radius:8px;background:#fff;color:#171817;box-shadow:0 14px 38px rgba(23,24,23,.07)}.receipt-brand{display:flex;align-items:center;gap:12px;padding-bottom:17px;border-bottom:1px dashed #aeb5aa}.receipt-brand img{width:50px;height:50px;padding:3px;border:1px solid #e1e3dc;border-radius:50%;object-fit:contain}.receipt-brand div{display:grid;gap:3px}.receipt-brand strong{letter-spacing:.1em}.receipt-brand span,.receipt-meta dt,.receipt-items small{color:#73796f;font-size:11px}.receipt-meta{display:grid;gap:9px;margin:0;padding:17px 0}.receipt-meta div{display:grid;gap:2px}.receipt-meta dd{margin:0;font-size:13px;font-weight:750;overflow-wrap:anywhere}.receipt-items{padding:7px 0;border-block:1px dashed #aeb5aa}.receipt-items>div{display:flex;justify-content:space-between;gap:18px;padding:9px 0}.receipt-items span{display:grid;gap:3px}.receipt-items b{white-space:nowrap}.receipt-totals{display:grid;gap:8px;padding-top:16px;font-size:13px}.receipt-totals>span,.receipt-totals>strong{display:flex;justify-content:space-between;gap:18px}.receipt-totals>strong{padding-bottom:11px;border-bottom:1px solid #e2e4dd;font-size:18px}.receipt-note{margin:24px 0 0;color:#73796f;text-align:center;font-size:12px}.receipt-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}.receipt-actions .logout{width:100%}.print-help{margin:10px 0 0;color:#687286;text-align:center;font-size:12px}@media(max-width:520px){.customer-receipt-shell{padding:20px 12px}.receipt-card{padding:20px}.receipt-page-head h1{font-size:20px}.receipt-page-head>a{display:none}.receipt-actions{grid-template-columns:1fr}}
@page{margin:12mm}
@media print{*{print-color-adjust:exact;-webkit-print-color-adjust:exact}html,body{width:100%!important;height:auto!important;margin:0!important;background:#fff!important}.admin-sidebar,.receipt-page-head,.notice,.receipt-actions,.print-help{display:none!important}.admin-shell,.customer-receipt-shell{display:block!important;width:100%!important;height:auto!important;min-height:0!important;margin:0!important;border:0!important;background:#fff!important;padding:0!important}.admin-workspace,.customer-receipt-workspace{display:block!important;width:100%!important;height:auto!important;margin:0!important;padding:0!important;overflow:visible!important;background:#fff!important}.receipt-wrap{width:80mm!important;margin:0 auto!important}.receipt-card{box-sizing:border-box;width:80mm!important;margin:0 auto!important;padding:4mm!important;border:0!important;border-radius:0!important;box-shadow:none!important}.receipt-brand img{width:44px!important;height:44px!important}.receipt-items>div{break-inside:avoid}.receipt-note{margin-top:20px!important}}
</style>
@endsection
