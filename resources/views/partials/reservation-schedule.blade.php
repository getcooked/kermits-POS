<div class="reservation-schedule" data-schedule data-url="{{ route('reservations.availability') }}">
    <p><strong>Open 8:00 AM–11:00 PM.</strong> Reservations last up to two hours. Last booking: 10:00–11:00 PM ({{ config('app.timezone') }}).</p>
    <p>A suitable numbered table is assigned when you submit. Pending reservations are held for 30 minutes, or until arrival if sooner, while awaiting approval.</p>
    <label>Available times for your selected date<select class="control" data-slots aria-label="Available reservation times"><option value="">Choose a date first</option></select></label>
    <small data-schedule-message role="status" aria-live="polite"></small>
</div>
<script src="{{ asset('js/reservation-schedule.js') }}" defer></script>
