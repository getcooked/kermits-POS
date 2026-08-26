@extends('layouts.app')
@section('title', 'Reservation '.$reservation->reference)
@section('content')
@php
    $isStaff = auth()->user()->hasRole('super_admin', 'admin');
    $statusLabel = match ($reservation->status) {
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => 'Awaiting review',
    };
@endphp

<div class="{{ $isStaff ? 'admin-shell reservation-detail-shell' : 'reservation-view-shell' }}">
    @if($isStaff)
        @include('partials.admin-sidebar')
    @endif

    <main class="{{ $isStaff ? 'admin-workspace reservation-view-workspace' : 'reservation-view-workspace' }}">
        <div class="reservation-view">
            <header class="reservation-view-header">
                <div>
                    <p>RESERVATION DETAILS</p>
                    <h1>{{ $reservation->reference }}</h1>
                    <span>Submitted {{ $reservation->created_at->format('M d, Y') }}</span>
                </div>
                <div class="header-actions">
                    <a class="button-secondary" href="{{ $isStaff ? route('reservations.index') : route('customer.history') }}">Back</a>
                    <a class="button-primary" href="{{ route('reservations.receipt', $reservation) }}">Print receipt</a>
                </div>
            </header>

            <section class="reservation-overview" aria-label="Reservation overview">
                <div>
                    <span>Date</span>
                    <strong>{{ $reservation->reservation_at->format('M d, Y') }}</strong>
                </div>
                <div>
                    <span>Time</span>
                    <strong>{{ $reservation->reservation_at->format('h:i A') }}</strong>
                </div>
                <div>
                    <span>Party</span>
                    <strong>{{ $reservation->type === 'table' ? $reservation->table_size.' seats' : $reservation->guests.' guests' }}</strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong class="reservation-status {{ $reservation->status }}">{{ $statusLabel }}</strong>
                </div>
            </section>

            <div class="reservation-sections">
                <section class="detail-section">
                    <div class="section-heading">
                        <div><p>GUEST</p><h2>Contact details</h2></div>
                    </div>
                    <dl class="clean-detail-list">
                        <div><dt>Name</dt><dd>{{ $reservation->customer_name }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $reservation->email }}</dd></div>
                        <div><dt>Phone</dt><dd>{{ $reservation->phone }}</dd></div>
                        <div><dt>Reservation type</dt><dd>{{ $reservation->type === 'table' ? $reservation->table_size.'-seater table' : 'Exclusive venue' }}</dd></div>
                    </dl>
                </section>

                <section class="detail-section">
                    <div class="section-heading">
                        <div><p>PAYMENT</p><h2>Payment details</h2></div>
                    </div>
                    <dl class="clean-detail-list">
                        <div><dt>Method</dt><dd>{{ $reservation->payment_method === 'cash' ? 'Walk In Pay' : 'GCash' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ ucfirst($reservation->payment_status) }}</dd></div>
                        <div class="wide-detail"><dt>Reference code</dt><dd>{{ $reservation->payment_reference ?: 'Not provided' }}</dd></div>
                    </dl>
                </section>

                @if($reservation->food_request || $reservation->notes)
                    <section class="detail-section full-section">
                        <div class="section-heading"><div><p>NOTES</p><h2>Special requests</h2></div></div>
                        <div class="request-notes">
                            @if($reservation->food_request)<p><strong>Food instructions</strong><span>{{ $reservation->food_request }}</span></p>@endif
                            @if($reservation->notes)<p><strong>Reservation notes</strong><span>{{ $reservation->notes }}</span></p>@endif
                        </div>
                    </section>
                @endif

                <section class="detail-section full-section food-section">
                    <div class="section-heading">
                        <div><p>ORDER</p><h2>Food request</h2></div>
                        <span>{{ $reservation->items->sum('quantity') }} item(s)</span>
                    </div>

                    <div class="food-list">
                        @forelse($reservation->items as $item)
                            <div>
                                <span><strong>{{ $item->product?->name ?? 'Menu item' }}</strong><small>{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}</small></span>
                                <b>&#8369;{{ number_format($item->subtotal, 2) }}</b>
                            </div>
                        @empty
                            <p class="empty-food">No food items included.</p>
                        @endforelse
                    </div>

                    <div class="reservation-totals">
                        <span>Reservation fee <b>&#8369;{{ number_format($reservation->reservation_fee, 2) }}</b></span>
                        <span>Food total <b>&#8369;{{ number_format($reservation->food_total, 2) }}</b></span>
                        <strong>Total <b>&#8369;{{ number_format($reservation->total_amount, 2) }}</b></strong>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>

<style>
.reservation-view-shell{min-height:100dvh;background:#f4f5ee}.reservation-view-workspace{min-width:0;min-height:100dvh;padding:36px 24px 56px;background:#f4f5ee}.reservation-view{width:min(980px,100%);margin:auto;color:#171817}.reservation-view-header{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding-bottom:24px}.reservation-view-header p,.section-heading p{margin:0 0 6px;color:#777f00;font-size:11px;font-weight:800;letter-spacing:.12em}.reservation-view-header h1{margin:0;font-size:30px;letter-spacing:0}.reservation-view-header>div>span{display:block;margin-top:6px;color:#747a70;font-size:13px}.header-actions{display:flex;gap:8px}.header-actions a{min-height:42px;padding:0 16px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:800;white-space:nowrap}.button-primary{border:1px solid #171817;background:#171817;color:#fff}.button-secondary{border:1px solid #cfd2c8;background:#fff;color:#171817}.reservation-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-block:1px solid #d5d9ce}.reservation-overview>div{min-width:0;padding:18px 20px}.reservation-overview>div+div{border-left:1px solid #d5d9ce}.reservation-overview span{display:block;margin-bottom:5px;color:#747a70;font-size:12px}.reservation-overview strong{font-size:16px;overflow-wrap:anywhere}.reservation-status{display:inline-flex!important;width:max-content;padding:5px 9px;border-radius:999px;background:#eff0ec;color:#5d6259;font-size:11px!important}.reservation-status.confirmed{background:#e5f4e9;color:#257342}.reservation-status.completed{background:#e9eefb;color:#315ec9}.reservation-status.cancelled{background:#fdeaea;color:#b72c2c}.reservation-sections{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding-top:24px}.detail-section{min-width:0;padding:20px;border:1px solid #d7dacf;border-radius:8px;background:#fff}.full-section{grid-column:1/-1}.section-heading{display:flex;align-items:end;justify-content:space-between;gap:16px;padding-bottom:15px;border-bottom:1px solid #e4e6df}.section-heading h2{margin:0;font-size:19px;letter-spacing:0}.section-heading>span{color:#747a70;font-size:12px}.clean-detail-list{display:grid;grid-template-columns:1fr 1fr;margin:0}.clean-detail-list>div{min-width:0;padding:15px 0}.clean-detail-list>div:nth-child(even){padding-left:18px;border-left:1px solid #e4e6df}.clean-detail-list>div:nth-child(n+3){border-top:1px solid #e4e6df}.clean-detail-list dt{margin-bottom:5px;color:#747a70;font-size:11px;text-transform:uppercase}.clean-detail-list dd{margin:0;font-size:14px;font-weight:750;overflow-wrap:anywhere}.wide-detail{grid-column:1/-1;padding-left:0!important;border-left:0!important}.request-notes{display:grid;gap:12px;padding-top:15px}.request-notes p{display:grid;gap:5px;margin:0}.request-notes strong{font-size:12px}.request-notes span{color:#555b52;font-size:14px;line-height:1.5}.food-list{padding:7px 0}.food-list>div{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:12px 0;border-bottom:1px solid #e4e6df}.food-list>div>span{display:grid;gap:4px}.food-list small{color:#747a70}.empty-food{margin:15px 0;color:#747a70;font-size:13px}.reservation-totals{width:min(390px,100%);margin:12px 0 0 auto;display:grid;gap:10px}.reservation-totals span,.reservation-totals>strong{display:flex;justify-content:space-between;gap:20px}.reservation-totals>strong{padding-top:12px;border-top:1px solid #d7dacf;font-size:18px}
@media(max-width:760px){.reservation-detail-shell{box-sizing:border-box;width:100%!important;min-width:0!important;overflow-x:hidden}.reservation-detail-shell>.admin-sidebar{box-sizing:border-box;width:100%!important;max-width:100vw!important;min-width:0!important}.reservation-detail-shell>.admin-sidebar>nav{box-sizing:border-box;width:100%!important;max-width:100%!important;min-width:0!important;overflow-x:auto!important}.reservation-view-workspace,.admin-workspace.reservation-view-workspace{box-sizing:border-box;width:100%!important;min-width:0!important;height:auto!important;padding:24px 15px 44px!important;overflow:visible!important}.reservation-view-header{align-items:flex-start;display:grid}.header-actions{width:100%}.header-actions a{flex:1}.reservation-overview{grid-template-columns:1fr 1fr}.reservation-overview>div:nth-child(3){border-left:0;border-top:1px solid #d5d9ce}.reservation-overview>div:nth-child(4){border-top:1px solid #d5d9ce}.reservation-sections{grid-template-columns:1fr}.detail-section,.full-section{grid-column:1}.reservation-view-header h1{font-size:24px}}
@media(max-width:460px){.reservation-overview>div{padding:14px}.detail-section{padding:16px}.clean-detail-list{grid-template-columns:1fr}.clean-detail-list>div,.clean-detail-list>div:nth-child(even),.clean-detail-list>div:nth-child(n+3){padding:13px 0;border-top:1px solid #e4e6df;border-left:0}.clean-detail-list>div:first-child{border-top:0}.wide-detail{grid-column:1}.food-list>div{align-items:flex-start}.reservation-totals{width:100%}}
</style>
@endsection
