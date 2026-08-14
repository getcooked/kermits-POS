@extends('layouts.app')
@section('title','Order #'.$order->id)
@section('content')
<main class="page"><section class="card order-receipt" style="max-width:560px"><img class="logo" src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's"><p style="font-size:12px;letter-spacing:.14em;color:#747d00">ORDER RECEIVED</p><h1>Thank you, {{ auth()->user()->name }}.</h1><p class="muted">{{ $order->payment_method === 'gcash' ? 'Your GCash details were received and are waiting for verification.' : 'Show this order at the counter and pay in cash when collecting your food.' }}</p><div style="border-block:1px dashed #aeb5c2;padding:10px 0;margin-bottom:18px">@foreach($order->items as $item)<div style="display:flex;justify-content:space-between;padding:8px 0"><span>{{ $item->product->name }} &times; {{ $item->quantity }}</span><strong>&#8369;{{ number_format($item->subtotal,2) }}</strong></div>@endforeach</div><div style="display:flex;justify-content:space-between;font-size:21px;margin-bottom:22px"><strong>Total</strong><strong>&#8369;{{ number_format($order->total,2) }}</strong></div><div class="notice"><strong>Order #{{ str_pad($order->id,6,'0',STR_PAD_LEFT) }}</strong><br>Payment: {{ strtoupper($order->payment_method) }}@if($order->payment_reference)<br>GCash reference: {{ $order->payment_reference }}@endif<br>Status: Pending {{ $order->payment_method === 'gcash' ? 'payment verification' : 'counter payment' }}</div><div class="order-actions"><button class="button" type="button" id="print-order">Print receipt</button><a class="button" href="{{ route('shop') }}">Order more</a></div><p class="print-help" id="print-help" hidden>If the print dialog did not open, press Ctrl + P.</p></section></main>
<script>
document.getElementById('print-order')?.addEventListener('click', () => {
    document.getElementById('print-help')?.removeAttribute('hidden');
    window.focus();
    window.print();
});
</script>
<style>
.order-actions{display:grid;gap:10px;margin-top:16px}.order-actions a{text-align:center;text-decoration:none}.print-help{color:#687286;font-size:13px;margin:10px 0 0;text-align:center}@page{size:80mm auto;margin:5mm}@media print{*{print-color-adjust:exact;-webkit-print-color-adjust:exact}html,body{width:80mm!important;margin:0!important;background:#fff!important}.page{display:block!important;min-height:0!important;width:80mm!important;margin:0!important;padding:0!important;background:#fff!important}.order-receipt{width:80mm!important;max-width:none!important;box-sizing:border-box!important;margin:0!important;border:0!important;border-radius:0!important;box-shadow:none!important;padding:4mm!important}.order-receipt .logo{width:52px!important;height:52px!important}.order-actions,.print-help{display:none!important}}
</style>
@endsection
