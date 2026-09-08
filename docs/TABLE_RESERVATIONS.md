# Numbered table reservations

The initial inventory is Table 1–3 (2 seats each), Table 4–7 (4 seats each), and Table 8 (12 seats). Super admins manage table numbers, capacity, and service status under **Tables**. Existing reservation pricing tiers are retained; an 8-seat reservation uses the available 12-seat table.

Bookings start between 8:00 AM and 10:00 PM in the application timezone (Asia/Manila). They normally last 120 minutes and end no later than 11:00 PM. The final booking is 10:00–11:00 PM. Suggested start times are shown at 30-minute intervals; other arrival minutes within opening hours are accepted and checked for overlap.

The smallest suitable available table is assigned at submission. An exclusive booking conflicts with any active overlapping reservation. The intervals are half-open: a booking ending at 8:00 PM permits another starting at 8:00 PM. No cleanup buffer is configured.

Pending reservations hold availability for 30 minutes or until arrival, whichever is sooner. Confirmation or staff verification of payment removes the deadline. Cancelled, rejected, completed, and expired reservations release availability. Payment verification does not replace super-admin approval. An expired reservation cannot be approved or paid from a stale page.

All web and mobile booking/checkout writes, approval, reassignment, and table updates share a transactional venue lock. SQLite uses a write to the lock row; databases with row locking use `FOR UPDATE`. Availability previews are advisory and checked again under the lock before saving. Checkout stock changes roll back if reservation allocation fails.

Use **Reservations → View reservation details → Assign or change table** for reassignment. The target must be in service, fit the reserved party, and be free for the entire period. Tables with active future assignments cannot be disabled or reduced below the reserved party size.

## Upgrade and maintenance

Run `php artisan migrate --force` and `php artisan optimize:clear` when deploying the code to another server. No reservations are deleted. Legacy reservations receive an end time but retain their original status and have no automatic hold deadline. Unassigned active legacy bookings conservatively block all overlapping availability until reviewed and assigned by staff.

Availability and displayed booking status release expired holds immediately, without a running scheduler. Run the normal Laravel scheduler (`php artisan schedule:run` every minute, or `php artisan schedule:work` locally) to persist expiry status and add history events. `php artisan reservations:expire` runs this maintenance manually.

Configuration is in `config/reservations.php`. Existing stored reservation periods retain their end time when duration configuration changes. The form descriptions reflect the agreed 8 AM–11 PM / two-hour / 30-minute policy.

The Android API adds `table_number`, `reservation_end_at`, and `hold_expires_at`. Availability is available at authenticated `/api/v1/reservation-availability?date=YYYY-MM-DD&type=table&guests=2`. Deploy the API changes before distributing an Android build that uses availability previews.

Verification: `php artisan test` covers capacity, overlap, exclusivity, opening/closing boundaries, timezone offsets, expiry, approval, reassignment, payments, web/mobile validation, and competing SQLite subprocesses.
