@extends('layouts.app')
@section('title', 'Order #'.$order->id)
@section('content')
<main class="online-receipt-page">
    <div class="online-receipt-wrap">
        <header class="receipt-heading">
            <div><p>ORDER RECEIVED</p><h1>Thank you, {{ auth()->user()->name }}.</h1></div>
        </header>

        <article class="online-receipt">
            <div class="receipt-brand">
                <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
                <div><strong>KERMIT'S</strong><span>Order Receipt</span></div>
            </div>

            <dl class="receipt-meta">
                <div><dt>Order</dt><dd>#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</dd></div>
                <div><dt>Date</dt><dd>{{ $order->created_at->format('M d, Y h:i A') }}</dd></div>
                <div><dt>Customer</dt><dd>{{ auth()->user()->name }}</dd></div>
                <div><dt>Payment</dt><dd>{{ $order->payment_method === 'cash' ? 'Walk In Pay' : 'GCash' }}</dd></div>
                <div><dt>Status</dt><dd>Pending {{ $order->payment_method === 'gcash' ? 'payment verification' : 'counter payment' }}</dd></div>
                @if($order->payment_reference)<div><dt>GCash reference</dt><dd>{{ $order->payment_reference }}</dd></div>@endif
            </dl>

            <div class="receipt-items">
                @foreach($order->items as $item)
                    <div><span><strong>{{ $item->product?->name ?? 'Product' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span><b>&#8369;{{ number_format($item->subtotal, 2) }}</b></div>
                @endforeach
            </div>

            <div class="receipt-total"><span>Total</span><strong>&#8369;{{ number_format($order->total, 2) }}</strong></div>
            <p class="receipt-note">{{ $order->payment_method === 'gcash' ? 'Your payment details are waiting for verification.' : 'Present this receipt and pay at the counter when collecting your food.' }}</p>
        </article>

        <div class="order-actions">
            <button class="button" type="button" id="print-order">Print receipt</button>
            <a class="logout" href="{{ route('shop') }}">Order more</a>
        </div>
        <p class="print-help" id="print-help" hidden>If the print dialog did not open, press Ctrl + P.</p>
    </div>
</main>
<script>
document.getElementById('print-order')?.addEventListener('click', () => {
    document.getElementById('print-help')?.removeAttribute('hidden');
    window.focus();
    window.print();
});
</script>
<style>
.online-receipt-page{min-height:100dvh;padding:34px 16px;background:#f4f5ee;color:#171817}.online-receipt-wrap{width:min(480px,100%);margin:0 auto}.receipt-heading{margin-bottom:16px}.receipt-heading p{margin:0 0 5px;color:#747d00;font-size:11px;font-weight:800;letter-spacing:.12em}.receipt-heading h1{margin:0;font-size:24px;letter-spacing:0}.online-receipt{padding:28px;border:1px solid #d7dacf;border-radius:8px;background:#fff;box-shadow:0 14px 38px rgba(23,24,23,.07)}.receipt-brand{display:flex;align-items:center;gap:12px;padding-bottom:17px;border-bottom:1px dashed #aeb5aa}.receipt-brand img{width:50px;height:50px;padding:3px;border:1px solid #e1e3dc;border-radius:50%;object-fit:contain}.receipt-brand div{display:grid;gap:3px}.receipt-brand strong{letter-spacing:.1em}.receipt-brand span,.receipt-meta dt,.receipt-items small{color:#73796f;font-size:11px}.receipt-meta{display:grid;gap:9px;margin:0;padding:17px 0}.receipt-meta div{display:grid;gap:2px}.receipt-meta dd{margin:0;font-size:13px;font-weight:750;overflow-wrap:anywhere}.receipt-items{padding:7px 0;border-block:1px dashed #aeb5aa}.receipt-items>div{display:flex;justify-content:space-between;gap:18px;padding:9px 0}.receipt-items span{display:grid;gap:3px}.receipt-items b{white-space:nowrap}.receipt-total{display:flex;justify-content:space-between;gap:18px;padding-top:17px;font-size:18px}.receipt-note{margin:24px 0 0;color:#73796f;text-align:center;font-size:12px;line-height:1.5}.order-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}.order-actions a{text-decoration:none}.print-help{margin:10px 0 0;color:#687286;text-align:center;font-size:12px}@media(max-width:520px){.online-receipt-page{padding:20px 12px}.online-receipt{padding:20px}.receipt-heading h1{font-size:20px}.order-actions{grid-template-columns:1fr}}
@page{margin:12mm}
@media print{*{print-color-adjust:exact;-webkit-print-color-adjust:exact}html,body{width:100%!important;height:auto!important;margin:0!important;background:#fff!important}.receipt-heading,.order-actions,.print-help{display:none!important}.online-receipt-page{display:block!important;width:100%!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}.online-receipt-wrap{width:80mm!important;margin:0 auto!important}.online-receipt{box-sizing:border-box;width:80mm!important;margin:0 auto!important;padding:4mm!important;border:0!important;border-radius:0!important;box-shadow:none!important}.receipt-brand img{width:44px!important;height:44px!important}.receipt-items>div{break-inside:avoid}}
</style>
@endsection
