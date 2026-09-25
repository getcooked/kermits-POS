@php
    $reservationInputId = $reservationInputId ?? 'reservation_at';
    $reservationDateId = $reservationDateId ?? $reservationInputId.'_date';
    $reservationSlotsId = $reservationSlotsId ?? $reservationInputId.'_slots';
    $oldReservationAt = old('reservation_at', '');
    $oldReservationAt = is_string($oldReservationAt) ? $oldReservationAt : '';
    $oldReservationDate = preg_match('/^\d{4}-\d{2}-\d{2}/', $oldReservationAt)
        ? substr($oldReservationAt, 0, 10)
        : '';
@endphp
<div class="reservation-schedule" data-schedule data-url="{{ route('reservations.availability') }}">
    <label for="{{ $reservationDateId }}">Select date</label>
    <input class="control" id="{{ $reservationDateId }}" type="date" min="{{ now()->toDateString() }}" value="{{ $oldReservationDate }}" data-reservation-date required>
    <input id="{{ $reservationInputId }}" name="reservation_at" type="hidden" value="{{ $oldReservationAt }}" data-reservation-at>
    <p><strong>Open 8:00 AM–11:00 PM.</strong> Reservations last up to two hours. Last booking: 10:00–11:00 PM ({{ config('app.timezone') }}).</p>
    <label for="{{ $reservationSlotsId }}">Available times for your selected date</label>
    <select class="control" id="{{ $reservationSlotsId }}" data-slots aria-label="Available reservation times" required><option value="">Choose a date first</option></select>
    <small data-schedule-message role="status" aria-live="polite"></small>
</div>
<script nonce="{{ Vite::cspNonce() }}" src="{{ asset('js/reservation-schedule.js') }}?v={{ filemtime(public_path('js/reservation-schedule.js')) }}" defer></script>

@once
@push('styles')
<style>
.reservation-schedule{display:grid;gap:8px}.reservation-schedule>label{margin:0}.reservation-schedule>p{margin:2px 0;color:#687064;font-size:12px;line-height:1.5}.reservation-schedule>[data-schedule-message]{min-height:18px;color:#687064}.reservation-schedule select:disabled{background:#f0f1ec;color:#7a8077}
</style>
@endpush
@endonce
