@extends('layouts.app')
@section('title', 'Reservations')
@section('content')
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace"><div class="dashboard">
        <header class="topbar"><div><h1>Reservations</h1><p class="muted">Review and approve customer requests</p></div></header>
        @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error reservation-error">{{ $errors->first() }}</div>@endif
        <form class="welcome filters" method="GET">
            <div><label>Status</label><select class="control" name="status"><option value="">All statuses</option>@foreach(['pending','confirmed','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div><label>Type</label><select class="control" name="type"><option value="">All types</option>@foreach(['table','exclusive'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
            <button class="button">Filter</button>
        </form>
        <section class="reservation-list">
            @forelse($reservations as $reservation)
                <article class="welcome reservation-card">
                    <div class="reservation-date"><strong>{{ $reservation->reservation_at->format('d') }}</strong><span>{{ strtoupper($reservation->reservation_at->format('M')) }}</span><small>{{ $reservation->reservation_at->format('h:i A') }}</small></div>
                    <div class="reservation-details"><div><span class="type-pill">{{ str($reservation->type)->title() }}</span><span class="status-pill {{ $reservation->status }}">{{ ucfirst($reservation->status) }}</span></div><h2>{{ $reservation->customer_name }}</h2><p>@if($reservation->type === 'table'){{ $reservation->table_size }}-seater table @else{{ $reservation->guests }} guest(s) @endif · {{ $reservation->phone }} · {{ $reservation->email }}</p>@if($reservation->food_request)<small><b>Food instructions:</b> {{ $reservation->food_request }}</small>@endif @if($reservation->notes)<small><b>Notes:</b> {{ $reservation->notes }}</small>@endif<div class="reference">{{ $reservation->reference }}</div></div>
                    @if($reservation->items->isNotEmpty())<div class="preorder-items" style="grid-column:2;display:grid;gap:4px;background:#f4f5ef;border-radius:9px;padding:10px;font-size:12px"><b>Food Request</b>@foreach($reservation->items as $item)<span style="display:flex;justify-content:space-between">{{ $item->quantity }} × {{ $item->product?->name ?? 'Menu item' }} <strong>&#8369;{{ number_format($item->subtotal,2) }}</strong></span>@endforeach<span style="display:flex;justify-content:space-between;border-top:1px solid #daddd1;padding-top:5px"><b>Estimated total</b><strong>&#8369;{{ number_format($reservation->items->sum('subtotal'),2) }}</strong></span></div>@endif
                    <div class="payment-summary" style="grid-column:2;font-size:12px;color:#555"><b>Total: &#8369;{{ number_format($reservation->total_amount,2) }}</b> · Payment method: {{ strtoupper($reservation->payment_method) }} · {{ ucfirst($reservation->payment_status) }} @if($reservation->payment_method === 'gcash' && $reservation->payment_reference) · Ref: {{ $reservation->payment_reference }} @endif · <a href="{{ route('reservations.show',$reservation) }}">View reservation details</a></div>
                    @if($reservation->status === 'pending')
                        <form method="POST" action="{{ route('reservations.status', $reservation) }}">@csrf @method('PATCH')<label>Admin decision</label><select class="control" name="status"><option value="confirmed">Approve reservation</option><option value="cancelled">Decline reservation</option></select><button class="button">Save decision</button></form>
                    @elseif($reservation->status === 'confirmed')
                        <form method="POST" action="{{ route('reservations.status', $reservation) }}">@csrf @method('PATCH')<label>Approved booking</label><select class="control" name="status"><option value="completed">Mark completed</option><option value="cancelled">Cancel reservation</option></select><button class="button">Update</button></form>
                    @else
                        <div class="final-status">No further action</div>
                    @endif
                </article>
            @empty
                <div class="welcome"><p class="muted">No reservations found.</p></div>
            @endforelse
        </section>
    </div></main>
</div>
<style>
.topbar h1{font-size:26px}.topbar p{margin:4px 0}.filters{padding:16px;margin-bottom:18px;display:flex;gap:10px;align-items:end}.filters .button{width:auto}.reservation-error{background:#fff0f0;padding:12px;border-radius:9px;margin-bottom:14px}.reservation-list{display:grid;gap:13px}.reservation-card{padding:20px;display:grid;grid-template-columns:80px 1fr 190px;gap:18px;align-items:center}.reservation-date{display:grid;text-align:center;border-right:1px solid #e2e4dc;padding-right:18px}.reservation-date strong{font-size:30px}.reservation-date span{font-size:12px;color:#7b8308}.reservation-date small{color:#777b72}.reservation-details h2{font-size:18px;margin:8px 0 4px}.reservation-details p{color:#687286;margin:0 0 6px;font-size:13px}.reservation-details small{display:block;margin-top:5px}.type-pill,.status-pill{display:inline-block;padding:5px 8px;border-radius:20px;background:#eff1df;font-size:11px}.status-pill{background:#f0f1ed}.status-pill.confirmed{background:#e5f5e9;color:#267444}.status-pill.cancelled{background:#fff0f0;color:#c42b2b}.status-pill.completed{background:#e8eefc;color:#315efb}.reference,.final-status{font-size:11px;color:#777b72;margin-top:8px}.reservation-card form{display:grid;gap:8px}.reservation-card form .button{padding:10px}@media(max-width:850px){.reservation-card{grid-template-columns:65px 1fr}.reservation-card form,.final-status{grid-column:1/-1}}@media(max-width:520px){.filters{display:grid}.reservation-card{grid-template-columns:1fr}.reservation-date{display:flex;gap:7px;border-right:0;border-bottom:1px solid #e2e4dc;padding:0 0 10px}}
@media(min-width:851px){.reservation-card>form,.reservation-card>.final-status{grid-column:3;grid-row:1/span 2}.preorder-items{grid-column:2!important}}@media(max-width:850px){.preorder-items{grid-column:2!important}.reservation-card>form,.reservation-card>.final-status{grid-column:1/-1!important}}@media(max-width:520px){.preorder-items{grid-column:1!important}}
</style>

@endsection
