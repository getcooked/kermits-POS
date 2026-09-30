@extends('layouts.app')
@section('title', 'Reservations')
@section('content')
@php
    $statusTabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected', 'expired' => 'Expired'];
    $typeTabs = ['' => 'All Types', 'table' => 'Table', 'exclusive' => 'Exclusive Venue'];
    $whenTabs = ['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All Dates'];
    $activeStatus = (string) request('status', '');
    $activeType = (string) request('type', '');
    $search = $search ?? '';
    // Keeps the current filters when one of them changes; empty values are dropped from the URL.
    $listUrl = fn (array $changes = []) => route('reservations.index', array_filter(array_merge(
        ['when' => $when === 'upcoming' ? null : $when, 'status' => $activeStatus, 'type' => $activeType, 'search' => $search],
        $changes,
    ), fn ($value) => $value !== null && $value !== ''));
    $dayLabel = fn ($date) => $date->isToday() ? 'Today' : ($date->isTomorrow() ? 'Tomorrow' : ($date->isYesterday() ? 'Yesterday' : $date->format('l')));
@endphp
<div class="admin-shell">
    @include('partials.admin-sidebar')
    <main class="admin-workspace reservations-workspace"><div class="dashboard reservations-page">
        <header class="rsv-head">
            <div><h1>Reservations</h1><p>Review, approve, and track customer bookings.</p></div>
            <nav class="rsv-views" aria-label="Reservation view">
                <a @class(['active' => $view === 'list']) href="{{ route('reservations.index') }}" @if($view === 'list') aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"></path></svg>List</a>
                <a @class(['active' => $view === 'timeline']) href="{{ route('reservations.index', ['view' => 'timeline']) }}" @if($view === 'timeline') aria-current="page" @endif><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M3 10h18M8 2v4M16 2v4M7 14h4M13 17h4"></path></svg>Timeline</a>
            </nav>
        </header>

        @if(session('status'))<div class="notice rsv-message">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="error rsv-message rsv-error">{{ $errors->first() }}</div>@endif

        <section class="rsv-stats" aria-label="Reservation summary">
            <a class="rsv-stat attention" href="{{ route('reservations.index', ['status' => 'pending']) }}"><span>Needs Approval</span><strong>{{ $stats['pending'] }}</strong><small>Waiting for your decision</small></a>
            <a class="rsv-stat" href="{{ route('reservations.index', ['view' => 'timeline']) }}"><span>Arriving Today</span><strong>{{ $stats['today'] }}</strong><small>Pending or confirmed</small></a>
            <a class="rsv-stat" href="{{ route('reservations.index', ['status' => 'confirmed']) }}"><span>Upcoming Confirmed</span><strong>{{ $stats['confirmed'] }}</strong><small>Approved bookings ahead</small></a>
            <a class="rsv-stat" href="{{ route('reservations.index', ['when' => 'all', 'status' => 'completed']) }}"><span>Completed</span><strong>{{ $stats['completed'] }}</strong><small>Guests served</small></a>
        </section>

        @if($view === 'timeline')
            @include('reservations.partials.timeline')
        @else
        <section class="rsv-toolbar">
            <div class="rsv-tools">
                <nav class="rsv-types" aria-label="Filter by date">
                    @foreach($whenTabs as $key => $label)
                        <a @class(['active' => $when === $key]) href="{{ $listUrl(['when' => $key === 'upcoming' ? null : $key]) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <nav class="rsv-types" aria-label="Filter by type">
                    @foreach($typeTabs as $type => $label)
                        <a @class(['active' => $activeType === $type]) href="{{ $listUrl(['type' => $type]) }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
            <nav class="rsv-tabs" aria-label="Filter by status">
                @foreach($statusTabs as $status => $label)
                    @php($count = $status === '' ? $totalCount : ($statusCounts[$status] ?? 0))
                    <a @class(['active' => $activeStatus === $status]) href="{{ $listUrl(['status' => $status]) }}" @if($activeStatus === $status) aria-current="page" @endif>{{ $label }} <b>{{ $count }}</b></a>
                @endforeach
            </nav>
            <form class="rsv-search" method="GET" action="{{ route('reservations.index') }}" role="search">
                @foreach(['when' => $when === 'upcoming' ? '' : $when, 'status' => $activeStatus, 'type' => $activeType] as $name => $value)@if($value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
                <label class="visually-hidden" for="reservation-search">Search reservations</label>
                <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                <input class="control" id="reservation-search" name="search" type="search" value="{{ $search }}" placeholder="Search name, reference, phone, or email" maxlength="100" autocomplete="off">
                <button type="submit">Search</button>
                @if($search !== '')<a href="{{ $listUrl(['search' => null]) }}">Clear</a>@endif
            </form>
        </section>

        <section class="rsv-list">
            @forelse($reservations->getCollection()->groupBy(fn ($reservation) => $reservation->reservation_at->toDateString()) as $date => $dayReservations)
                @php($day = $dayReservations->first()->reservation_at)
                <div class="rsv-day">
                    <h2 @class(['today' => $day->isToday()])><span>{{ $dayLabel($day) }}</span>{{ $day->format('M d, Y') }}<b>{{ $dayReservations->count() }} {{ Str::plural('booking', $dayReservations->count()) }}</b></h2>
                    @foreach($dayReservations as $reservation)
                        @php($status = $reservation->booking_status)
                        <article @class(['rsv-card', 'is-'.$status])>
                            <div class="rsv-time"><strong>{{ $reservation->arrival_time }}</strong><span>{{ strtoupper($reservation->reservation_at->format('M d')) }}</span></div>

                            <div class="rsv-main">
                                <div class="rsv-title">
                                    <h3>{{ $reservation->customer_name }}</h3>
                                    <span class="rsv-pill type">{{ $reservation->type === 'exclusive' ? $reservation->type_label : 'Table' }}</span>
                                    <span class="rsv-pill status {{ $status }}">{{ ucfirst($status) }}</span>
                                </div>
                                <ul class="rsv-meta">
                                    @if($reservation->type === 'table')
                                        <li><b>{{ $reservation->table_size }}-seater table</b> · {{ $reservation->table_label }}</li>
                                    @else
                                        <li><b>{{ $reservation->guests }} guest(s)</b> · whole day</li>
                                    @endif
                                    <li>{{ $reservation->phone }}</li>
                                    <li>{{ $reservation->email }}</li>
                                </ul>

                                @if($reservation->food_request || $reservation->notes)
                                    <div class="rsv-notes">
                                        @if($reservation->food_request)<p><b>Food Instructions</b>{{ $reservation->food_request }}</p>@endif
                                        @if($reservation->notes)<p><b>Notes</b>{{ $reservation->notes }}</p>@endif
                                    </div>
                                @endif

                                @if($reservation->items->isNotEmpty())
                                    <details class="rsv-food">
                                        <summary>Food Request · {{ $reservation->items->sum('quantity') }} {{ Str::plural('item', $reservation->items->sum('quantity')) }} <b>&#8369;{{ number_format($reservation->items->sum('subtotal'), 2) }}</b></summary>
                                        <div>
                                            @foreach($reservation->items as $item)<span>{{ $item->quantity }} × {{ $item->product?->name ?? 'Menu item' }}<strong>&#8369;{{ number_format($item->subtotal, 2) }}</strong></span>@endforeach
                                            <span class="total">Estimated Total<strong>&#8369;{{ number_format($reservation->items->sum('subtotal'), 2) }}</strong></span>
                                        </div>
                                    </details>
                                @endif

                                <dl class="rsv-payment">
                                    <div><dt>Total</dt><dd>&#8369;{{ number_format($reservation->total_amount, 2) }}</dd></div>
                                    <div><dt>Payment</dt><dd>{{ strtoupper($reservation->payment_method) }} · {{ $reservation->payment_status_label }}</dd></div>
                                    @if($reservation->type === 'exclusive' && $reservation->downpayment_amount !== null)
                                        <div><dt>{{ (float) $reservation->downpayment_amount < (float) $reservation->total_amount ? 'Downpayment' : 'Full Payment' }}</dt><dd>&#8369;{{ number_format($reservation->downpayment_amount, 2) }}</dd></div>
                                        <div><dt>Balance</dt><dd>&#8369;{{ number_format($reservation->balance_due, 2) }}</dd></div>
                                    @endif
                                    @if($reservation->payment_method === 'gcash' && $reservation->payment_reference)
                                        <div><dt>GCash Ref</dt><dd>{{ $reservation->payment_reference }}</dd></div>
                                    @endif
                                </dl>

                                <div class="rsv-foot"><code>{{ $reservation->reference }}</code><a href="{{ route('reservations.show', $reservation) }}">View Details →</a></div>
                            </div>

                            <div class="rsv-actions">
                                @if($status === 'pending')
                                    @if($reservation->hold_expires_at)<p class="rsv-deadline">Decide by <b>{{ $reservation->hold_expires_at->format('M d, h:i A') }}</b></p>@endif
                                    <form method="POST" action="{{ route('reservations.status', $reservation) }}" data-ajax-form data-ajax-target=".dashboard" data-ajax-loading="Approving...">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="rsv-btn primary" type="submit">{{ $reservation->type === 'exclusive' && ! in_array($reservation->payment_status, ['partial', 'paid'], true) ? 'Approve · GCash Downpayment Verified' : 'Approve Reservation' }}</button></form>
                                    <form method="POST" action="{{ route('reservations.status', $reservation) }}" data-ajax-form data-ajax-target=".dashboard" data-ajax-loading="Declining..." data-confirm="Decline this reservation request from {{ $reservation->customer_name }}?" data-confirm-title="Decline Reservation?">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="rsv-btn danger" type="submit">Decline</button></form>
                                @elseif($status === 'confirmed')
                                    <form method="POST" action="{{ route('reservations.status', $reservation) }}" data-ajax-form data-ajax-target=".dashboard" data-ajax-loading="Updating...">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="rsv-btn primary" type="submit">{{ $reservation->type === 'exclusive' && $reservation->balance_due > 0 ? 'Mark Completed · Balance ₱'.number_format($reservation->balance_due, 2).' Collected' : 'Mark Completed' }}</button></form>
                                    <form method="POST" action="{{ route('reservations.status', $reservation) }}" data-ajax-form data-ajax-target=".dashboard" data-ajax-loading="Cancelling..." data-confirm="Cancel the confirmed reservation for {{ $reservation->customer_name }}?" data-confirm-title="Cancel Reservation?">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="rsv-btn ghost" type="submit">Cancel Reservation</button></form>
                                @else
                                    <p class="rsv-final">No further action</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @empty
                <div class="rsv-empty"><strong>No reservations found</strong><span>{{ $search !== '' ? 'No bookings match “'.$search.'”. Try a different name, reference, phone, or email.' : ($activeStatus !== '' || $activeType !== '' || $when !== 'all' ? 'Try another date, status, or type filter.' : 'New customer bookings will appear here.') }}</span></div>
            @endforelse
        </section>

        @if($reservations->hasPages())
            <nav class="rsv-pager" aria-label="Reservation pages">
                <span>Showing {{ $reservations->firstItem() }}–{{ $reservations->lastItem() }} of {{ $reservations->total() }}</span>
                <div>
                    @if($reservations->onFirstPage())<span class="disabled">← Previous</span>@else<a href="{{ $reservations->previousPageUrl() }}" rel="prev">← Previous</a>@endif
                    @foreach($reservations->getUrlRange(max(1, $reservations->currentPage() - 2), min($reservations->lastPage(), $reservations->currentPage() + 2)) as $page => $url)
                        <a @class(['active' => $page === $reservations->currentPage()]) href="{{ $url }}" @if($page === $reservations->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                    @endforeach
                    @if($reservations->hasMorePages())<a href="{{ $reservations->nextPageUrl() }}" rel="next">Next →</a>@else<span class="disabled">Next →</span>@endif
                </div>
            </nav>
        @endif
        @endif
    </div></main>
</div>
@push('styles')
<style>
.reservations-workspace{background:#f5f6ef}.reservations-page{max-width:1280px;margin:auto}
.rsv-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;margin-bottom:20px}.rsv-head h1{margin:0;font-size:30px;letter-spacing:-.035em}.rsv-head p{margin:5px 0 0;color:#687286}.rsv-views{display:flex;gap:4px;padding:4px;border:1px solid #daddd1;border-radius:12px;background:#e9ebe4}.rsv-views a{display:flex;align-items:center;gap:7px;min-height:36px;padding:0 14px;border-radius:9px;color:#5d635a;font-size:13px;font-weight:800;text-decoration:none}.rsv-views a:hover{background:#f5f6f1;color:#292b27}.rsv-views a.active{background:#171817;color:#fff}.rsv-views svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.rsv-message{padding:12px 14px;margin-bottom:14px;border-radius:10px}.rsv-error{background:#fff0f0}
.rsv-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.rsv-stat{display:grid;gap:3px;padding:17px 18px;border:1px solid #e1e3da;border-radius:14px;background:#fff;color:inherit;text-decoration:none;transition:border-color .15s,transform .15s}a.rsv-stat:hover{border-color:#afb91a;transform:translateY(-1px)}.rsv-stat span{color:#687286;font-size:12px;font-weight:750}.rsv-stat strong{font-size:28px;letter-spacing:-.03em}.rsv-stat small{color:#8a8f86;font-size:11px}.rsv-stat.attention{background:#171817;border-color:#171817;color:#fff}.rsv-stat.attention span,.rsv-stat.attention small{color:#c9cdbf}.rsv-stat.attention strong{color:#d8e24a}
.rsv-toolbar{display:grid;gap:10px;margin-bottom:18px}.rsv-tabs,.rsv-types{display:flex;gap:4px;padding:4px;border:1px solid #daddd1;border-radius:12px;background:#e9ebe4;overflow-x:auto;scrollbar-width:none}.rsv-tabs::-webkit-scrollbar,.rsv-types::-webkit-scrollbar{display:none}.rsv-tabs a,.rsv-types a{flex:0 0 auto;display:flex;align-items:center;gap:6px;min-height:36px;padding:0 13px;border-radius:9px;color:#5d635a;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap}.rsv-tabs a:hover,.rsv-types a:hover{background:#f5f6f1;color:#292b27}.rsv-tabs a.active,.rsv-types a.active{background:#171817;color:#fff}.rsv-tabs b{min-width:20px;padding:1px 6px;border-radius:20px;background:rgba(0,0,0,.07);font-size:11px;text-align:center}.rsv-tabs a.active b{background:rgba(255,255,255,.18)}
.rsv-tools{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.rsv-search{position:relative;display:flex;gap:8px;align-items:center}.rsv-search svg{position:absolute;left:12px;top:50%;width:16px;height:16px;transform:translateY(-50%);fill:none;stroke:#80867c;stroke-width:2;stroke-linecap:round;pointer-events:none}.rsv-search .control{flex:1;min-width:0;margin:0;padding-left:36px;background:#fff}.rsv-search button{min-height:44px;padding:0 18px;border:0;border-radius:10px;background:#171817;color:#fff;font:inherit;font-size:13px;font-weight:800;cursor:pointer}.rsv-search a{color:#596100;font-size:13px;font-weight:800;text-decoration:none}
.rsv-pager{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-top:20px;color:#687286;font-size:13px}.rsv-pager div{display:flex;flex-wrap:wrap;gap:4px}.rsv-pager a,.rsv-pager span.disabled{min-width:38px;min-height:38px;padding:0 12px;display:grid;place-items:center;border:1px solid #daddd1;border-radius:9px;background:#fff;color:#4f554c;font-weight:800;text-decoration:none}.rsv-pager a:hover{border-color:#afb91a}.rsv-pager a.active{background:#171817;border-color:#171817;color:#fff}.rsv-pager span.disabled{opacity:.45}
.tl-bar{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}.tl-nav{display:flex;flex-wrap:wrap;align-items:center;gap:6px}.tl-nav a{min-height:40px;padding:0 13px;display:grid;place-items:center;border:1px solid #daddd1;border-radius:10px;background:#fff;color:#4f554c;font-size:13px;font-weight:800;text-decoration:none}.tl-nav a:hover{border-color:#afb91a}.tl-nav a.active{background:#171817;border-color:#171817;color:#fff}.tl-nav .control{width:auto;margin:0;background:#fff}.tl-day{margin:0;font-size:18px}.tl-day small{display:block;color:#687286;font-size:12px;font-weight:600}
.tl-legend{display:flex;flex-wrap:wrap;gap:12px;color:#687286;font-size:12px}.tl-legend span{display:flex;align-items:center;gap:6px}.tl-legend i{width:12px;height:12px;border-radius:4px}
.tl-exclusive{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;padding:13px 15px;margin-bottom:12px;border-radius:12px;background:#171817;color:#fff;font-size:13px}.tl-exclusive b{color:#d8e24a}.tl-exclusive a{color:#fff;margin-left:auto;font-weight:800}
.tl-card{border:1px solid #e1e3da;border-radius:14px;background:#fff;overflow-x:auto}.tl-grid{min-width:880px}.tl-row{display:grid;grid-template-columns:150px minmax(0,1fr);border-bottom:1px solid #eceee6}.tl-row:last-child{border-bottom:0}.tl-label{display:grid;align-content:center;gap:2px;padding:10px 14px;border-right:1px solid #eceee6;background:#fafbf7;font-size:13px;font-weight:800}.tl-label small{color:#80867c;font-size:11px;font-weight:600}.tl-row.conflict .tl-label{background:#fff4f3}.tl-row.conflict .tl-label small{color:#b42318;font-weight:800}
.tl-track{position:relative;min-height:calc(var(--lanes) * 46px + 12px);background-image:linear-gradient(to right,#eef0e9 1px,transparent 1px);background-size:calc(100% / var(--hours)) 100%}.tl-head .tl-track{min-height:34px;background:none}.tl-head .tl-label{background:#f3f4ef;color:#80867c;font-size:11px}.tl-hour{position:absolute;top:10px;transform:translateX(-50%);color:#80867c;font-size:11px;font-weight:750;white-space:nowrap}.tl-hour:first-child{transform:none}.tl-hour:last-child{transform:translateX(-100%)}
.tl-block{position:absolute;top:calc(var(--lane) * 46px + 6px);height:40px;min-width:34px;padding:4px 8px;display:grid;align-content:center;border-left:4px solid;border-radius:8px;overflow:hidden;color:#292b27;font-size:12px;line-height:1.2;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.06)}.tl-block strong,.tl-block small{overflow:hidden;white-space:nowrap;text-overflow:ellipsis}.tl-block small{color:#5d635a;font-size:11px}.tl-block:hover{z-index:2;box-shadow:0 6px 16px rgba(0,0,0,.14)}.tl-block.pending,.tl-legend .pending{background:#fff4dc;border-color:#d49b12}.tl-block.confirmed,.tl-legend .confirmed{background:#e5f5e9;border-color:#2f9a58}.tl-block.completed,.tl-legend .completed{background:#e8eefc;border-color:#4f74e0}.tl-block.overlap{outline:2px solid #d0453b;outline-offset:-1px}
.tl-now{position:absolute;top:0;bottom:0;width:2px;background:#d0453b;z-index:1;pointer-events:none}.tl-now::before{content:'';position:absolute;top:-1px;left:-4px;width:10px;height:10px;border-radius:50%;background:#d0453b}
.tl-empty{padding:26px;color:#687286;font-size:13px;text-align:center}
.rsv-list{display:grid;gap:22px}.rsv-day{display:grid;gap:10px}.rsv-day h2{display:flex;align-items:center;gap:9px;margin:0;font-size:14px;color:#4f554c}.rsv-day h2 span{color:#171817;font-size:16px}.rsv-day h2.today span{color:#6f7800}.rsv-day h2 b{margin-left:auto;color:#8a8f86;font-size:11px;font-weight:700}
.rsv-card{display:grid;grid-template-columns:92px minmax(0,1fr) 220px;gap:18px;padding:18px;border:1px solid #e1e3da;border-left:4px solid #c8ccc1;border-radius:14px;background:#fff}.rsv-card.is-pending{border-left-color:#d49b12}.rsv-card.is-confirmed{border-left-color:#2f9a58}.rsv-card.is-completed{border-left-color:#4f74e0}.rsv-card.is-cancelled,.rsv-card.is-rejected{border-left-color:#d0453b}.rsv-card.is-expired{border-left-color:#9aa093}
.rsv-time{display:grid;align-content:start;gap:4px;padding-right:16px;border-right:1px solid #eceee6}.rsv-time strong{font-size:17px;line-height:1.15}.rsv-time span{color:#7a8300;font-size:11px;font-weight:850;letter-spacing:.08em}
.rsv-main{display:grid;gap:10px;min-width:0;overflow-wrap:anywhere}.rsv-title{display:flex;flex-wrap:wrap;align-items:center;gap:7px}.rsv-title h3{margin:0 6px 0 0;font-size:18px}.rsv-pill{padding:4px 9px;border-radius:20px;font-size:11px;font-weight:800}.rsv-pill.type{background:#eff1df;color:#5b6300}.rsv-pill.status{background:#f0f1ed;color:#5d635a}.rsv-pill.pending{background:#fff4dc;color:#95680a}.rsv-pill.confirmed{background:#e5f5e9;color:#267444}.rsv-pill.completed{background:#e8eefc;color:#315efb}.rsv-pill.cancelled,.rsv-pill.rejected{background:#fff0f0;color:#c42b2b}
.rsv-meta{display:flex;flex-wrap:wrap;gap:6px 16px;margin:0;padding:0;list-style:none;color:#687286;font-size:13px}.rsv-meta b{color:#292b27}
.rsv-notes{display:grid;gap:6px;padding:10px 12px;border-radius:10px;background:#fbf8ee;font-size:13px}.rsv-notes p{margin:0}.rsv-notes b{display:block;color:#7a6a2f;font-size:10px;letter-spacing:.1em;text-transform:uppercase;margin-bottom:2px}
.rsv-food{border:1px solid #e6e8e0;border-radius:10px;background:#f7f8f3;font-size:13px}.rsv-food summary{display:flex;gap:8px;align-items:center;padding:10px 12px;font-weight:800;cursor:pointer}.rsv-food summary b{margin-left:auto}.rsv-food>div{display:grid;gap:5px;padding:0 12px 11px}.rsv-food span{display:flex;justify-content:space-between;gap:10px}.rsv-food span.total{padding-top:6px;border-top:1px solid #daddd1;font-weight:800}
.rsv-payment{display:flex;flex-wrap:wrap;gap:8px;margin:0}.rsv-payment div{padding:7px 10px;border-radius:9px;background:#f5f6f1}.rsv-payment dt{color:#80867c;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.rsv-payment dd{margin:2px 0 0;font-size:13px;font-weight:750}
.rsv-foot{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:8px;font-size:12px}.rsv-foot code{color:#80867c}.rsv-foot a{color:#596100;font-weight:800;text-decoration:none}.rsv-foot a:hover{text-decoration:underline}
.rsv-actions{display:grid;align-content:start;gap:8px}.rsv-actions form{margin:0}.rsv-btn{width:100%;min-height:42px;padding:9px 12px;border:1px solid transparent;border-radius:10px;font:inherit;font-size:13px;font-weight:800;line-height:1.25;cursor:pointer}.rsv-btn.primary{background:#171817;color:#fff}.rsv-btn.primary:hover{background:#343632}.rsv-btn.danger{border-color:#e7b7b2;background:#fff7f7;color:#b42318}.rsv-btn.danger:hover{background:#ffeceb}.rsv-btn.ghost{border-color:#d6d9cf;background:#fff;color:#4f554c}.rsv-btn.ghost:hover{border-color:#b42318;color:#b42318}.rsv-deadline{margin:0;padding:8px 10px;border-radius:9px;background:#fff4dc;color:#7c5708;font-size:12px}.rsv-final{margin:0;padding:10px;border-radius:9px;background:#f5f6f1;color:#80867c;font-size:12px;text-align:center}
.reservations-page .visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.rsv-empty{display:grid;gap:4px;padding:34px;border:1px dashed #cfd3c6;border-radius:14px;background:#fff;text-align:center}.rsv-empty span{color:#687286;font-size:13px}.rsv-empty[hidden]{display:none}
@media(max-width:1100px){.rsv-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.rsv-card{grid-template-columns:80px minmax(0,1fr)}.rsv-actions{grid-column:2;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}.rsv-deadline,.rsv-final{grid-column:1/-1}}
@media(max-width:760px){.rsv-head{flex-direction:column;align-items:flex-start}.rsv-tools{flex-direction:column;align-items:stretch}.rsv-card{grid-template-columns:minmax(0,1fr);gap:12px}.rsv-time{display:flex;align-items:baseline;gap:10px;padding:0 0 10px;border-right:0;border-bottom:1px solid #eceee6}.rsv-actions{grid-column:auto;grid-template-columns:1fr}}
@media(max-width:480px){.rsv-stats{grid-template-columns:1fr 1fr;gap:8px}.rsv-stat{padding:13px}.rsv-stat strong{font-size:23px}.rsv-card{padding:14px}}
</style>
@endpush
<script nonce="{{ Vite::cspNonce() }}">
// Picking a day on the timeline loads it straight away.
document.addEventListener('change', event => {
    if (event.target.matches('[data-timeline-date]') && event.target.value) event.target.form.requestSubmit();
});
</script>
@endsection
