# Table reservation capacity

The capacity pool contains three 2-seat spaces, four 4-seat spaces, and one 12-seat space. Table numbers are not stored or shown. Existing reservation pricing tiers are retained; an 8-seat reservation uses the available 12-seat capacity.

Bookings start between 8:00 AM and 10:00 PM in the application timezone (Asia/Manila). They normally last 120 minutes and end no later than 11:00 PM. The final booking is 10:00–11:00 PM. Suggested start times are shown at 30-minute intervals; other arrival minutes within opening hours are accepted and checked for overlap.

The smallest suitable capacity is reserved at submission. An exclusive booking conflicts with any active overlapping reservation. The intervals are half-open: a booking ending at 8:00 PM permits another starting at 8:00 PM. No cleanup buffer is configured.

Pending reservations hold availability for 30 minutes or until arrival, whichever is sooner. Confirmation or staff verification of payment removes the deadline. Cancelled, rejected, completed, and expired reservations release availability. Payment verification does not replace super-admin approval. An expired reservation cannot be approved or paid from a stale page.

All web and mobile booking, checkout, and approval writes share a transactional venue lock. SQLite uses a write to the lock row; databases with row locking use `FOR UPDATE`. Availability previews are advisory and checked again under the lock before saving. Checkout stock changes roll back if reservation allocation fails.

## Upgrade and maintenance

Run `php artisan migrate --force` and `php artisan optimize:clear` when deploying the code to another server. No reservations are deleted. Legacy reservations receive an end time but retain their original status and have no automatic hold deadline. The application temporarily falls back to derived periods and creation-time holds if a code-only deployment reaches the server before its migrations run.

Availability and displayed booking status release expired holds immediately, without a running scheduler. Run the normal Laravel scheduler (`php artisan schedule:run` every minute, or `php artisan schedule:work` locally) to persist expiry status and add history events. `php artisan reservations:expire` runs this maintenance manually.

Configuration is in `config/reservations.php`. Existing stored reservation periods retain their end time when duration configuration changes. The form descriptions reflect the agreed 8 AM–11 PM / two-hour / 30-minute policy.

The Android API adds `reservation_end_at` and `hold_expires_at`. Availability is available at authenticated `/api/v1/reservation-availability?date=YYYY-MM-DD&type=table&guests=2`. Deploy the API changes before distributing an Android build that uses availability previews.

Verification: `php artisan test` covers capacity, overlap, exclusivity, opening/closing boundaries, timezone offsets, expiry, approval, payments, web/mobile validation, code-only deployment compatibility, and competing SQLite subprocesses.
