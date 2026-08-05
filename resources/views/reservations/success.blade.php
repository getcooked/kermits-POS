@extends('layouts.app')
@section('title', 'Reservation status')
@section('content')
@php
    $isApproved = in_array($reservation->status, ['confirmed', 'completed'], true);
    $isCancelled = $reservation->status === 'cancelled';
@endphp
<main class="page"><section class="card reservation-result">
    <img class="logo" src="{{ asset('kermits-logo.jpg') }}" alt="Kermit's">
    <div class="status-icon {{ $reservation->status }}">{{ $isApproved ? '✓' : ($isCancelled ? '×' : '⌛') }}</div>
    @if($isApproved)
        <p class="status-label approved">ADMIN APPROVED</p><h1>Reservation confirmed</h1><p class="muted">Your reservation has been approved. We look forward to welcoming you, {{ $reservation->customer_name }}.</p>
    @elseif($isCancelled)
        <p class="status-label cancelled">NOT APPROVED</p><h1>Reservation cancelled</h1><p class="muted">This request could not be approved. Please contact Kermit's or submit another schedule.</p>
    @else
        <p class="status-label pending">AWAITING ADMIN APPROVAL</p><h1>Request submitted</h1><p class="muted">Your request is pending. It is not a successful reservation until an admin approves it.</p>
    @endif
    <div class="reservation-summary"><strong>Reference: {{ $reservation->reference }}</strong><span>{{ str($reservation->type)->replace('_', ' ')->title() }}</span><span>{{ $reservation->reservation_at->format('M d, Y h:i A') }}</span><span>@if($reservation->type === 'table'){{ $reservation->table_size }}-seater table @else{{ $reservation->guests }} guest(s) @endif</span><span>Status: {{ ucfirst($reservation->status) }}</span></div>
    @if($reservation->items->isNotEmpty())<div class="reservation-summary"><strong>Selected food</strong>@foreach($reservation->items as $item)<span>{{ $item->quantity }} × {{ $item->product?->name ?? 'Menu item' }} — &#8369;{{ number_format($item->subtotal,2) }}</span>@endforeach<strong>Estimated total: &#8369;{{ number_format($reservation->items->sum('subtotal'),2) }}</strong></div>@endif
    <div class="reservation-summary"><span>Reservation fee <strong>&#8369;{{ number_format($reservation->reservation_fee,2) }}</strong></span><span>Food total <strong>&#8369;{{ number_format($reservation->food_total,2) }}</strong></span><span>Total <strong>&#8369;{{ number_format($reservation->total_amount,2) }}</strong></span><span>Payment <strong>{{ strtoupper($reservation->payment_method) }} · {{ ucfirst($reservation->payment_status) }}</strong></span>@if($reservation->payment_method === 'gcash' && $reservation->payment_reference)<span>GCash reference <strong>{{ $reservation->payment_reference }}</strong></span>@endif @if($reservation->payment_proof_path)<a target="_blank" href="{{ route('reservations.payment-proof',$reservation) }}">View uploaded payment proof</a>@endif</div><a class="button" href="{{ route('customer.history') }}">View reservation history</a><a style="display:block;margin-top:12px" href="{{ route('reservations.create') }}">Make another request</a>
</section></main>
<style>.reservation-result{text-align:center;max-width:540px}.reservation-result .logo{margin-inline:auto}.status-icon{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;margin:18px auto 12px;font-size:25px;font-weight:800;background:#fff4cc;color:#8a6500}.status-icon.confirmed,.status-icon.completed{background:#e5f5e9;color:#267444}.status-icon.cancelled{background:#fff0f0;color:#c42b2b}.status-label{font-size:11px;letter-spacing:.14em;font-weight:800}.status-label.approved{color:#267444}.status-label.pending{color:#8a6500}.status-label.cancelled{color:#c42b2b}.reservation-summary{display:grid;gap:7px;text-align:left;background:#f4f5ef;border-radius:12px;padding:16px;margin:20px 0}.reservation-summary span{color:#656a61}.reservation-result .button{display:block;text-decoration:none}</style>
@endsection
