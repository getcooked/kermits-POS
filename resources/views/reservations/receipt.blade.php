@extends('layouts.app')
@section('title', 'Reservation Receipt '.$reservation->reference)
@section('content')
@php
    $viewer = auth()->user();
    $isCustomer = $viewer->hasRole(\App\Models\User::ROLE_CUSTOMER);
@endphp
<main class="reservation-receipt-page">
    <div class="reservation-receipt-wrap">
        <header class="receipt-heading">
            <div><p>RESERVATION RECEIPT</p><h1>{{ $reservation->reference }}</h1></div>
            <a href="{{ $isCustomer ? route('customer.history') : route('reservations.index') }}">Back</a>
        </header>

        <article class="reservation-receipt">
            <div class="receipt-brand">
                <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
                <div><strong>KERMIT'S</strong><span>Reservation Receipt</span></div>
            </div>

            <dl class="receipt-meta">
                <div><dt>Reference</dt><dd>{{ $reservation->reference }}</dd></div>
                <div><dt>Guest</dt><dd>{{ $reservation->customer_name }}</dd></div>
                <div><dt>Schedule</dt><dd>{{ $reservation->reservation_at->format('M d, Y h:i A') }}</dd></div>
                <div><dt>Reservation</dt><dd>{{ $reservation->type === 'table' ? $reservation->table_size.'-seater table' : $reservation->guests.' guests, exclusive venue' }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucfirst($reservation->status) }}</dd></div>
                <div><dt>Payment</dt><dd>{{ strtoupper($reservation->payment_method) }} &middot; {{ ucfirst($reservation->payment_status) }}</dd></div>
                @if($reservation->payment_reference)<div><dt>Payment reference</dt><dd>{{ $reservation->payment_reference }}</dd></div>@endif
            </dl>

            <div class="receipt-items">
                @forelse($reservation->items as $item)
                    <div><span><strong>{{ $item->product?->name ?? 'Menu item' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span><b>&#8369;{{ number_format($item->subtotal, 2) }}</b></div>
                @empty
                    <p>No food items included.</p>
                @endforelse
            </div>

            <div class="receipt-totals">
                <span>Reservation fee <b>&#8369;{{ number_format($reservation->reservation_fee, 2) }}</b></span>
                <span>Food total <b>&#8369;{{ number_format($reservation->food_total, 2) }}</b></span>
                <strong>Total <b>&#8369;{{ number_format($reservation->total_amount, 2) }}</b></strong>
            </div>
            <p class="receipt-note">Please present this receipt when you arrive.</p>
        </article>

        <div class="receipt-actions">
            <button type="button" id="print-reservation">Print receipt</button>
            <a href="{{ route('reservations.show', $reservation) }}">View reservation</a>
        </div>
    </div>
</main>
<script>document.getElementById('print-reservation')?.addEventListener('click',()=>{window.focus();window.print()})</script>
<style>
.reservation-receipt-page{min-height:100dvh;padding:34px 16px;background:#f4f5ee;color:#171817}.reservation-receipt-wrap{width:min(480px,100%);margin:0 auto}.receipt-heading{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:16px}.receipt-heading p{margin:0 0 5px;color:#747d00;font-size:11px;font-weight:800;letter-spacing:.12em}.receipt-heading h1{margin:0;font-size:22px;letter-spacing:0;overflow-wrap:anywhere}.receipt-heading>a,.receipt-actions a{min-height:42px;padding:0 15px;border:1px solid #cfd2c8;border-radius:7px;background:#fff;color:#171817;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:800}.reservation-receipt{padding:28px;border:1px solid #d7dacf;border-radius:8px;background:#fff;box-shadow:0 14px 38px rgba(23,24,23,.07)}.receipt-brand{display:flex;align-items:center;gap:12px;padding-bottom:17px;border-bottom:1px dashed #aeb5aa}.receipt-brand img{width:50px;height:50px;padding:3px;border:1px solid #e1e3dc;border-radius:50%;object-fit:contain}.receipt-brand div{display:grid;gap:3px}.receipt-brand strong{letter-spacing:.1em}.receipt-brand span,.receipt-meta dt,.receipt-items small{color:#73796f;font-size:11px}.receipt-meta{display:grid;gap:9px;margin:0;padding:17px 0}.receipt-meta div{display:grid;gap:2px}.receipt-meta dd{margin:0;font-size:13px;font-weight:750;overflow-wrap:anywhere}.receipt-items{padding:7px 0;border-block:1px dashed #aeb5aa}.receipt-items>div{display:flex;justify-content:space-between;gap:18px;padding:9px 0}.receipt-items span{display:grid;gap:3px}.receipt-items b{white-space:nowrap}.receipt-items p{margin:8px 0;color:#73796f;font-size:12px}.receipt-totals{display:grid;gap:8px;padding-top:16px;font-size:13px}.receipt-totals span,.receipt-totals>strong{display:flex;justify-content:space-between;gap:18px}.receipt-totals>strong{padding-top:11px;border-top:1px solid #e2e4dd;font-size:18px}.receipt-note{margin:24px 0 0;color:#73796f;text-align:center;font-size:12px}.receipt-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}.receipt-actions button{min-height:44px;border:0;border-radius:7px;background:#171817;color:#fff;font-size:13px;font-weight:800;cursor:pointer}@media(max-width:520px){.reservation-receipt-page{padding:20px 12px}.reservation-receipt{padding:20px}.receipt-heading h1{font-size:18px}.receipt-heading>a{display:none}.receipt-actions{grid-template-columns:1fr}}
@page{margin:12mm}
@media print{*{print-color-adjust:exact;-webkit-print-color-adjust:exact}html,body{width:100%!important;height:auto!important;margin:0!important;background:#fff!important}.receipt-heading,.receipt-actions{display:none!important}.reservation-receipt-page{display:block!important;width:100%!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}.reservation-receipt-wrap{width:80mm!important;margin:0 auto!important}.reservation-receipt{box-sizing:border-box;width:80mm!important;margin:0 auto!important;padding:4mm!important;border:0!important;border-radius:0!important;box-shadow:none!important}.receipt-brand img{width:44px!important;height:44px!important}.receipt-items>div{break-inside:avoid}}
</style>
@endsection
