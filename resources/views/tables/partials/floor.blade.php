    @if($floor['tables'] !== [])
    <section class="welcome floor">
        <div class="floor-heading">
            <div>
                <h2>Today's tables</h2>
                <p class="table-help">Mark a table occupied when guests sit down and free when they leave. Customers only choose an arrival time, so a freed table can be booked again after the cleanup time.</p>
            </div>
            <a class="row-add" href="{{ url()->current() }}">Refresh</a>
        </div>
        @if($floor['crowded'])
            <div class="floor-alert" role="status">Upcoming reservations may not all have a free table while these tables are occupied. Free tables whose guests have left, or seat arriving guests elsewhere.</div>
        @endif
        <div class="floor-grid">
            @foreach($floor['tables'] as $card)
                @php($table = $card['table'])
                <article @class(['floor-card', 'is-occupied' => $table->isOccupied(), 'is-warning' => $card['overstaying'] || $card['next_soon'], 'is-off' => ! $table->active])>
                    <header>
                        <strong>{{ $table->label() }}</strong>
                        <span>{{ $table->seats }} seats</span>
                    </header>
                    @if($table->isOccupied())
                        <p class="floor-status occupied">Occupied</p>
                        <p class="floor-who">{{ $card['reservation'] ? $card['reservation']->reference.' · '.$card['reservation']->customer_name.' · '.$card['reservation']->guests.' guests' : 'Walk-in party' }}</p>
                        <p class="floor-meta">Since {{ $table->occupied_at->format('g:i A') }} · usually free by {{ $table->expected_free_at?->format('g:i A') }}</p>
                        @if($card['overstaying'])<p class="floor-note">Staying longer than usual.</p>@endif
                        @if($card['next_soon'])<p class="floor-note">Next booking for this table: {{ $card['next']->reference }} at {{ $card['next']->reservation_at->format('g:i A') }}. Seat them elsewhere if it's still occupied.</p>@endif
                        <form method="POST" action="{{ route('tables.free', $table) }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Freeing...">
                            @csrf
                            <button class="button" type="submit">Mark free</button>
                        </form>
                    @else
                        <p class="floor-status free">Free</p>
                        @if($card['cleaning_until'])<p class="floor-meta">Cleanup until {{ $card['cleaning_until']->format('g:i A') }}</p>@endif
                        @if($card['next'])<p class="floor-meta">Booked for {{ $card['next']->reservation_at->format('g:i A') }} ({{ $card['next']->reference }})</p>@endif
                        @if(! $table->active)<p class="floor-meta">Not bookable online</p>@endif
                        @php($fits = $floor['arrivals']->filter(fn ($arrival) => $arrival->guests <= $table->seats))
                        <form method="POST" action="{{ route('tables.seat', $table) }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving...">
                            @csrf
                            <label class="visually-hidden" for="seat_{{ $table->id }}">Who is sitting at {{ $table->label() }}</label>
                            <select class="control" id="seat_{{ $table->id }}" name="reservation_id">
                                <option value="">Walk-in party</option>
                                @foreach($fits as $arrival)
                                    <option value="{{ $arrival->id }}" @selected($card['next']?->id === $arrival->id)>{{ $arrival->reservation_at->format('g:i A') }} · {{ $arrival->reference }} · {{ $arrival->customer_name }} ({{ $arrival->guests }})</option>
                                @endforeach
                            </select>
                            <button class="button" type="submit">Mark occupied</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <h3>Arriving today</h3>
        @if($floor['arrivals']->isEmpty())
            <p class="table-help">No approved table bookings are waiting to be seated today.</p>
        @else
            <ul class="arrival-list">
                @foreach($floor['arrivals'] as $arrival)
                    <li>
                        <strong>{{ $arrival->reservation_at->format('g:i A') }}</strong>
                        <span>{{ $arrival->reference }} · {{ $arrival->customer_name }} · {{ $arrival->guests }} guests · {{ $arrival->table_label }}</span>
                        @if($arrival->reservation_at->lt(now()->subMinutes($lateMinutes)))<em>Late</em>@endif
                    </li>
                @endforeach
            </ul>
            <p class="table-help">Seat a booking by choosing it on a free table.@if(auth()->user()->hasRole('super_admin')) Cancel no-shows from Reservations.@endif</p>
        @endif
    </section>
    @endif
