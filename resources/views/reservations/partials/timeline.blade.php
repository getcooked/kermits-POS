@php
    $hourCount = max(1, $timeline['hours']->count() - 1);
    $dayName = $date->isToday() ? 'Today' : ($date->isTomorrow() ? 'Tomorrow' : ($date->isYesterday() ? 'Yesterday' : $date->format('l')));
    $dayUrl = fn ($day) => route('reservations.index', ['view' => 'timeline', 'date' => $day->toDateString()]);
@endphp
<section class="tl-bar">
    <h2 class="tl-day">{{ $dayName }}, {{ $date->format('F d, Y') }}<small>{{ $timeline['count'] }} active {{ Str::plural('booking', $timeline['count']) }} · hover a block for details</small></h2>
    <form class="tl-nav" method="GET" action="{{ route('reservations.index') }}">
        <input type="hidden" name="view" value="timeline">
        <a href="{{ $dayUrl($date->copy()->subDay()) }}" aria-label="Previous day">←</a>
        <a @class(['active' => $date->isToday()]) href="{{ $dayUrl(today()) }}">Today</a>
        <a href="{{ $dayUrl($date->copy()->addDay()) }}" aria-label="Next day">→</a>
        <label class="visually-hidden" for="timeline-date">Choose a date</label>
        <input class="control" id="timeline-date" name="date" type="date" value="{{ $date->toDateString() }}" data-timeline-date>
        <noscript><button class="rsv-btn primary" type="submit">Go</button></noscript>
    </form>
</section>

<div class="tl-legend" style="margin-bottom:12px">
    <span><i class="pending"></i>Pending</span><span><i class="confirmed"></i>Confirmed</span><span><i class="completed"></i>Completed</span><span><i style="outline:2px solid #d0453b;outline-offset:-2px"></i>Overlapping on one table</span>
</div>

@foreach($timeline['exclusive'] as $reservation)
    <div class="tl-exclusive"><b>Exclusive Venue</b><span>{{ $reservation->customer_name }} has booked the whole restaurant for this day · {{ $reservation->guests }} guest(s) · {{ ucfirst($reservation->booking_status) }}</span><a href="{{ route('reservations.show', $reservation) }}">View Details →</a></div>
@endforeach

<section class="tl-card" aria-label="Table bookings timeline">
    <div class="tl-grid" style="--hours:{{ $hourCount }}">
        <div class="tl-row tl-head">
            <div class="tl-label">TABLE</div>
            <div class="tl-track">
                @foreach($timeline['hours'] as $hour)
                    <span class="tl-hour" style="left:{{ $loop->index / $hourCount * 100 }}%">{{ $hour->format('g A') }}</span>
                @endforeach
            </div>
        </div>
        @forelse($timeline['rows'] as $row)
            <div @class(['tl-row', 'conflict' => $row['conflict']])>
                <div class="tl-label">{{ $row['label'] }}<small>{{ $row['conflict'] ? 'Overlapping bookings' : ($row['seats'] ? $row['seats'].' seats' : 'Not yet assigned') }}</small></div>
                <div class="tl-track" style="--lanes:{{ $row['lanes'] }}">
                    @if($timeline['now'] !== null)<span class="tl-now" style="left:{{ $timeline['now'] }}%" aria-hidden="true"></span>@endif
                    @foreach($row['items'] as $item)
                        @php($reservation = $item['reservation'])
                        @php($status = $reservation->booking_status)
                        <a @class(['tl-block', $status, 'overlap' => $row['conflict']]) href="{{ route('reservations.show', $reservation) }}" style="left:{{ $item['left'] }}%;width:{{ $item['width'] }}%;--lane:{{ $item['lane'] }}" title="{{ $reservation->customer_name }} · {{ $item['start']->format('h:i A') }}–{{ $item['end']->format('h:i A') }} · {{ $reservation->table_size }}-seater · {{ ucfirst($status) }} · {{ $reservation->reference }}">
                            <strong>{{ $reservation->customer_name }}</strong>
                            <small>{{ $item['start']->format('g:i A') }} · {{ $reservation->table_size }} seats</small>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="tl-empty">No tables are set up yet. Add them in Table Management.</p>
        @endforelse
    </div>
</section>
@if($timeline['count'] === 0)
    <p class="tl-empty">No active bookings on this day.</p>
@endif
