@extends('layouts.app')
@section('title', 'Table Management')
@section('content')
@php
    $optionRows = old('tables', collect($tableFees)->map(fn ($fee, $guests) => ['guests' => $guests, 'fee' => number_format($fee, 2, '.', '')])->values()->all());
    $tablesById = $diningTables->keyBy('id');
    $layoutRows = old('dining_tables', $diningTables->map(fn ($table) => ['id' => $table->id, 'number' => $table->number, 'seats' => $table->seats, 'active' => $table->active])->all());
    $seatSummary = $diningTables->where('active', true)->countBy('seats')->sortKeys();
@endphp
<div class="admin-shell">@include('partials.admin-sidebar')<main class="admin-workspace"><div class="dashboard table-management">
    <header>
        <h1>Table Management</h1>
    </header>
    @if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="error table-error" role="alert">{{ $errors->first() }}</div>@endif

    @include('tables.partials.floor')

    <div class="table-settings-grid">
    <section class="welcome">
        <h2>Table options</h2>
        <p class="table-help">The party sizes customers choose from when booking, and the reservation price for each.</p>
        <form method="POST" action="{{ route('tables.update') }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving..." data-rows-form data-max-rows="{{ $maxTables }}">
            @csrf @method('PUT')
            <div class="table-list-scroll">
                <table class="table-list">
                    <thead><tr><th scope="col">Guest number</th><th scope="col">Price (&#8369;)</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr></thead>
                    <tbody data-rows>
                        @foreach($optionRows as $index => $row)
                            <tr class="editable-row">
                                <td><label class="visually-hidden" for="table_guests_{{ $index }}">Guest number</label><input class="control" id="table_guests_{{ $index }}" name="tables[{{ $index }}][guests]" type="number" min="1" max="{{ $maxGuests }}" step="1" value="{{ $row['guests'] ?? '' }}" required></td>
                                <td><label class="visually-hidden" for="table_fee_{{ $index }}">Price</label><input class="control" id="table_fee_{{ $index }}" name="tables[{{ $index }}][fee]" type="number" min="0" max="999999.99" step="0.01" value="{{ $row['fee'] ?? '' }}" required></td>
                                <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>
                <tr class="editable-row">
                    <td><label class="visually-hidden" for="table_guests___INDEX__">Guest number</label><input class="control" id="table_guests___INDEX__" name="tables[__INDEX__][guests]" type="number" min="1" max="{{ $maxGuests }}" step="1" required></td>
                    <td><label class="visually-hidden" for="table_fee___INDEX__">Price</label><input class="control" id="table_fee___INDEX__" name="tables[__INDEX__][fee]" type="number" min="0" max="999999.99" step="0.01" required></td>
                    <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                </tr>
            </template>
            <p class="table-help">Guest number is the most guests the option allows, up to {{ $maxGuests }} (the largest bookable table). Changes apply to new bookings only; existing reservations keep their original price.</p>
            <div class="table-actions">
                <button class="row-add" type="button" data-row-add>+ Add option</button>
                <button class="button" type="submit">Save table options</button>
            </div>
        </form>
    </section>

    <section class="welcome">
        <h2>Exclusive Venue</h2>
        <p class="table-help">Customers reserve all of Kermit's for a whole day ({{ \Carbon\Carbon::parse(config('reservations.opening_time'))->format('g:i A') }}–{{ \Carbon\Carbon::parse(config('reservations.closing_time'))->format('g:i A') }}). They pay at least {{ $downpaymentPercent }}% of the total online (GCash or PayMongo) before you can approve it; the balance is collected on the event day.</p>
        <form method="POST" action="{{ route('tables.exclusive.update') }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving...">
            @csrf @method('PUT')
            <label for="exclusive_fee">Whole-day price (&#8369;)</label>
            <input class="control" id="exclusive_fee" name="exclusive_fee" type="number" min="1" max="999999.99" step="0.01" value="{{ old('exclusive_fee', number_format($exclusiveFee, 2, '.', '')) }}" required>
            <p class="table-help">Food requests are added to this price. Changes apply to new bookings only.</p>
            <div class="table-actions">
                <button class="button" type="submit">Save Exclusive Venue price</button>
            </div>
        </form>
    </section>

    <section class="welcome">
        <h2>Tables in the restaurant</h2>
        <p class="table-help">Each numbered table and how many people it seats. Customers are seated at the smallest free table that fits, or at the table they request.</p>
        @if($seatSummary->isNotEmpty())
            <p class="seat-summary">@foreach($seatSummary as $seats => $count)<span>{{ $count }} &times; {{ $seats }}-seat</span>@endforeach</p>
        @endif
        <form method="POST" action="{{ route('tables.layout.update') }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving..." data-rows-form data-max-rows="{{ $maxDiningTables }}">
            @csrf @method('PUT')
            <div class="table-list-scroll">
                <table class="table-list layout-list">
                    <thead><tr><th scope="col">Table number</th><th scope="col">Seats</th><th scope="col">Bookable</th><th scope="col">Delete</th></tr></thead>
                    <tbody data-rows>
                        @foreach($layoutRows as $index => $row)
                            @php($inUse = filled($row['id'] ?? null) && ($tablesById->get((int) $row['id'])?->reservations_count ?? 0) > 0)
                            <tr class="editable-row">
                                <td>@if(filled($row['id'] ?? null))<input type="hidden" name="dining_tables[{{ $index }}][id]" value="{{ $row['id'] }}">@endif<label class="visually-hidden" for="dining_table_number_{{ $index }}">Table number</label><input class="control" id="dining_table_number_{{ $index }}" name="dining_tables[{{ $index }}][number]" type="number" min="1" max="999" step="1" value="{{ $row['number'] ?? '' }}" required data-table-number></td>
                                <td><label class="visually-hidden" for="dining_table_seats_{{ $index }}">Seats</label><input class="control" id="dining_table_seats_{{ $index }}" name="dining_tables[{{ $index }}][seats]" type="number" min="1" max="{{ $maxSeats }}" step="1" value="{{ $row['seats'] ?? '' }}" required></td>
                                @php($active = filter_var($row['active'] ?? false, FILTER_VALIDATE_BOOLEAN))
                                <td><input type="hidden" name="dining_tables[{{ $index }}][active]" value="{{ $active ? 1 : 0 }}">@if(! filled($row['id'] ?? null))<span @class(['table-state', 'is-free' => $active, 'is-off' => ! $active])>{{ $active ? 'Available' : 'Unavailable' }}</span>@elseif(in_array((int) $row['id'], $bookedTableIds, true))<span class="table-state is-booked" title="This table has an upcoming booking or guests seated now.">Booked</span>@elseif($tablesById->get((int) $row['id'])?->active)<button class="table-state is-free" type="submit" form="table_availability_{{ $row['id'] }}" title="Customers can book this table. Click to make it unavailable.">Available</button>@else<button class="table-state is-off" type="submit" form="table_availability_{{ $row['id'] }}" title="Customers can't book this table. Click to make it available again.">Unavailable</button>@endif</td>
                                <td><button class="row-remove" type="button" data-row-remove @if($inUse) data-locked disabled title="This table has reservations, so it can't be removed. Make it Unavailable instead." @endif>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>
                <tr class="editable-row">
                    <td><label class="visually-hidden" for="dining_table_number___INDEX__">Table number</label><input class="control" id="dining_table_number___INDEX__" name="dining_tables[__INDEX__][number]" type="number" min="1" max="999" step="1" required data-table-number></td>
                    <td><label class="visually-hidden" for="dining_table_seats___INDEX__">Seats</label><input class="control" id="dining_table_seats___INDEX__" name="dining_tables[__INDEX__][seats]" type="number" min="1" max="{{ $maxSeats }}" step="1" value="4" required></td>
                    <td><input type="hidden" name="dining_tables[__INDEX__][active]" value="1"><span class="table-state is-free">Available</span></td>
                    <td><button class="row-remove" type="button" data-row-remove>Remove</button></td>
                </tr>
            </template>
            <div class="field turnover-field">
                <label for="stay_minutes">Estimated stay (minutes)</label>
                <input class="control" id="stay_minutes" name="stay_minutes" type="number" min="{{ $minStayMinutes }}" max="{{ $maxStayMinutes }}" step="15" value="{{ old('stay_minutes', $stayMinutes) }}" required>
                <small>Customers only choose an arrival time and never see this. It spaces out bookings on the same table until staff mark it free. Existing bookings keep their estimate.</small>
            </div>
            <div class="field turnover-field">
                <label for="turnover_minutes">Cleanup time between bookings (minutes)</label>
                <input class="control" id="turnover_minutes" name="turnover_minutes" type="number" min="0" max="{{ $maxTurnoverMinutes }}" step="5" value="{{ old('turnover_minutes', $turnoverMinutes) }}" required>
                <small>A table stays free for this long after it is marked free (or after a booking's estimated stay) so staff can clear and reset it. Use 0 for back-to-back bookings.</small>
            </div>
            <p class="table-help">Click Available or Unavailable to switch whether customers can book a table; new tables are available once saved. Tables with reservations can't be removed; make them Unavailable instead. Changes that would leave an upcoming reservation without a table are refused.</p>
            <div class="table-actions">
                <button class="row-add" type="button" data-row-add>+ Add table</button>
                <button class="button" type="submit">Save tables</button>
            </div>
        </form>
        {{-- The Available/Unavailable buttons sit inside the layout form, so they submit these instead. --}}
        @foreach($diningTables as $table)
            <form id="table_availability_{{ $table->id }}" method="POST" action="{{ route('tables.availability', $table) }}" data-ajax-form data-ajax-target=".table-management" data-ajax-loading="Saving..." hidden>@csrf @method('PATCH')</form>
        @endforeach
    </section>
    </div>
</div></main></div>
@push('styles')
@include('tables.partials.styles')
@endpush
@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    let nextIndex = Date.now();

    const refresh = form => {
        const rows = form.querySelectorAll('[data-rows] .editable-row');
        const max = Number(form.dataset.maxRows || 20);
        rows.forEach(row => {
            const remove = row.querySelector('[data-row-remove]');
            if (remove) remove.disabled = remove.hasAttribute('data-locked') || rows.length <= 1;
        });
        form.querySelector('[data-row-add]').disabled = rows.length >= max;
    };

    document.addEventListener('click', event => {
        const addButton = event.target.closest('[data-row-add]');
        const removeButton = event.target.closest('[data-row-remove]');
        const form = (addButton || removeButton)?.closest('[data-rows-form]');
        if (!form) return;

        if (addButton) {
            const markup = form.querySelector('[data-row-template]').innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            const body = form.querySelector('[data-rows]');
            body.insertAdjacentHTML('beforeend', markup);
            const row = body.lastElementChild;
            const numberInput = row.querySelector('[data-table-number]');
            if (numberInput) {
                const numbers = [...form.querySelectorAll('[data-table-number]')].map(input => Number(input.value) || 0);
                numberInput.value = Math.max(0, ...numbers) + 1;
            }
            row.querySelector('input:not([type="hidden"])')?.focus();
        } else if (form.querySelectorAll('[data-rows] .editable-row').length > 1) {
            removeButton.closest('.editable-row').remove();
        }

        refresh(form);
    });

    const init = () => document.querySelectorAll('.table-management [data-rows-form]').forEach(refresh);
    document.addEventListener('ajax:content-updated', init);
    init();
})();
</script>
@endpush
@endsection
