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
            <div>
                <p>RESERVATION RECEIPT</p>
                <h1>{{ $reservation->reference }}</h1>
            </div>
            <a href="{{ $isCustomer ? route('customer.history') : route('reservations.index') }}">Back</a>
        </header>

        <article class="reservation-receipt">
            <div class="receipt-brand">
                <img src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
                <div><strong>KERMIT'S</strong><span>Reservation summary</span></div>
            </div>

            <dl class="receipt-meta">
                <div><dt>Guest</dt><dd>{{ $reservation->customer_name }}</dd></div>
                <div><dt>Schedule</dt><dd>{{ $reservation->reservation_at->format('M d, Y h:i A') }}</dd></div>
                <div><dt>Reservation</dt><dd>{{ $reservation->type === 'table' ? $reservation->table_size.'-seater table' : $reservation->guests.' guests, exclusive venue' }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucfirst($reservation->status) }}</dd></div>
                <div><dt>Payment</dt><dd>{{ strtoupper($reservation->payment_method) }} · {{ ucfirst($reservation->payment_status) }}</dd></div>
                <div><dt>Reference code</dt><dd>{{ $reservation->payment_reference ?: 'Not provided' }}</dd></div>
            </dl>

            <div class="receipt-items">
                @forelse($reservation->items as $item)
                    <div>
                        <span><strong>{{ $item->product?->name ?? 'Menu item' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span>
                        <b>&#8369;{{ number_format($item->subtotal, 2) }}</b>
                    </div>
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
.reservation-receipt-page{min-height:100dvh;padding:34px 16px;background:#f4f5ee;color:#171817}.reservation-receipt-wrap{width:min(680px,100%);margin:auto}.receipt-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px}.receipt-heading p{margin:0 0 5px;color:#777f00;font-size:11px;font-weight:800;letter-spacing:.12em}.receipt-heading h1{margin:0;font-size:26px;letter-spacing:0}.receipt-heading>a,.receipt-actions a{min-height:40px;padding:0 15px;border:1px solid #cfd2c8;border-radius:7px;background:#fff;color:#171817;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:800}.reservation-receipt{padding:28px;border:1px solid #d7dacf;border-radius:8px;background:#fff}.receipt-brand{display:flex;align-items:center;gap:13px;padding-bottom:18px;border-bottom:1px dashed #afb5aa}.receipt-brand img{width:58px;height:58px;border:1px solid #e1e3dc;border-radius:50%;object-fit:contain}.receipt-brand div{display:grid;gap:3px}.receipt-brand strong{letter-spacing:.1em}.receipt-brand span,.receipt-meta dt,.receipt-items small{color:#73796f;font-size:12px}.receipt-meta{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 0;margin:0}.receipt-meta div{min-width:0}.receipt-meta dt{margin-bottom:4px}.receipt-meta dd{margin:0;font-size:14px;font-weight:750;overflow-wrap:anywhere}.receipt-items{padding:9px 0;border-block:1px dashed #afb5aa}.receipt-items>div{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:9px 0}.receipt-items span{display:grid;gap:3px}.receipt-items p{margin:8px 0;color:#73796f;font-size:13px}.receipt-totals{display:grid;gap:9px;padding-top:18px}.receipt-totals span,.receipt-totals>strong{display:flex;justify-content:space-between;gap:20px}.receipt-totals>strong{padding-top:12px;border-top:1px solid #e2e4dd;font-size:19px}.receipt-note{margin:24px 0 0;color:#73796f;text-align:center;font-size:13px}.receipt-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:12px}.receipt-actions button{min-height:44px;border:0;border-radius:7px;background:#171817;color:#fff;font-size:13px;font-weight:800;cursor:pointer}@media(max-width:520px){.reservation-receipt-page{padding:22px 12px}.reservation-receipt{padding:20px}.receipt-heading h1{font-size:20px}.receipt-heading>a{display:none}.receipt-meta{grid-template-columns:1fr}.receipt-actions{grid-template-columns:1fr}}@page{size:80mm auto;margin:5mm}@media print{html,body{width:80mm!important;margin:0!important;background:#fff!important}.receipt-heading,.receipt-actions{display:none!important}.reservation-receipt-page,.reservation-receipt-wrap{width:80mm!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}.reservation-receipt{box-sizing:border-box;width:100%!important;padding:4mm!important;border:0!important;border-radius:0!important}.receipt-meta{grid-template-columns:1fr!important;gap:8px!important}.receipt-items>div{break-inside:avoid}}
</style>
@endsection
