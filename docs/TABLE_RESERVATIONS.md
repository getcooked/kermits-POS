# Table reservation capacity

The restaurant's tables are stored in `dining_tables` (number, seats, bookable flag) and managed by the super admin under **Table Management** (`/tables`). The migration creates Tables 1–8 from `config/reservations.php` (three 2-seat, four 4-seat, one 12-seat); after that, the config list is only a fallback for code-only deployments that have not run the migration. An 8-seat reservation uses the 12-seat table.

The table options customers choose from (guest number and reservation price) are managed on the same page. Guest numbers must be unique and cannot exceed the largest bookable table. Options are stored as JSON in the `reservation_table_types` system setting; until that page is first saved, the previous per-size prices (`reservation_table_fee_{size}` settings, then `config/reservations.php`) are used. Web and mobile booking accept only the current guest numbers. Existing reservations keep the table size and fee they were booked with.

## Choosing a table

Customers may leave the table as **Any available table** (the default) or request a specific numbered table that seats their party (`reservations.dining_table_id`). Requested tables are held for that booking. Bookings without a preference are not tied to a table: each availability check re-seats them at the smallest free table that fits, so they can move aside for a request and capacity is not wasted. Staff see the requested table, or "Any available table", on reservation, receipt and cashier screens.

Table Management refuses changes that would strand a booking: removing a table with any reservation history (untick **Bookable** instead), making a requested table unbookable or too small for its upcoming booking, leaving no bookable table large enough for the largest option, or any change after which the upcoming bookings no longer fit. Saves take the reservation lock.

## Other double-booking rules

- A customer cannot hold two active reservations whose times overlap (web, shop checkout and mobile). Cancelled, rejected, completed and expired reservations do not count.
- Availability shows how many more parties of the chosen size fit in each time slot ("3 tables left"). The web booking form refreshes the list every minute and when the tab regains focus; the Android app refreshes every minute.
- **Cleanup time** (default 15 minutes, `config('reservations.turnover_minutes')`, editable in Table Management and stored in the `reservation_turnover_minutes` setting) keeps a table empty after each booking. It also applies to exclusive-venue bookings.

Bookings start between 8:00 AM and 10:00 PM in the application timezone (Asia/Manila). They normally last 120 minutes and end no later than 11:00 PM. The final booking is 10:00–11:00 PM. Suggested start times are shown at 30-minute intervals; other arrival minutes within opening hours are accepted and checked for overlap.

The smallest suitable capacity is reserved at submission. An exclusive booking conflicts with any active overlapping reservation. The intervals are half-open and extended by the cleanup time: with the default 15 minutes, a booking ending at 8:00 PM frees its table for another starting at 8:15 PM. Set the cleanup time to 0 to allow back-to-back bookings.

Pending reservations hold availability for 30 minutes or until arrival, whichever is sooner. Confirmation or staff verification of payment removes the deadline. Cancelled, rejected, completed, and expired reservations release availability. Payment verification does not replace super-admin approval. An expired reservation cannot be approved or paid from a stale page.

All web and mobile booking, checkout, and approval writes share a transactional venue lock. SQLite uses a write to the lock row; databases with row locking use `FOR UPDATE`. Availability previews are advisory and checked again under the lock before saving. Checkout stock changes roll back if reservation allocation fails.

## Upgrade and maintenance

Run `php artisan migrate --force` and `php artisan optimize:clear` when deploying the code to another server. No reservations are deleted. Legacy reservations receive an end time but retain their original status and have no automatic hold deadline. The application temporarily falls back to derived periods and creation-time holds if a code-only deployment reaches the server before its migrations run.

Availability and displayed booking status release expired holds immediately, without a running scheduler. Run the normal Laravel scheduler (`php artisan schedule:run` every minute, or `php artisan schedule:work` locally) to persist expiry status and add history events. `php artisan reservations:expire` runs this maintenance manually.

Configuration is in `config/reservations.php`. Existing stored reservation periods retain their end time when duration configuration changes. The form descriptions reflect the agreed 8 AM–11 PM / two-hour / 30-minute policy.

The Android API adds `reservation_end_at` and `hold_expires_at`. Availability is available at authenticated `/api/v1/reservation-availability?date=YYYY-MM-DD&type=table&guests=2`; add `&table={dining_table_id}` to check one table. Each slot includes `tables_left` (null for exclusive bookings). `/api/v1/products` lists bookable `tables`, and reservation and order requests accept an optional `dining_table_id`; responses include `table_label`. Deploy the API changes before distributing an Android build that uses availability previews.

Verification: `php artisan test` covers capacity, overlap, exclusivity, opening/closing boundaries, timezone offsets, expiry, approval, payments, web/mobile validation, code-only deployment compatibility, and competing SQLite subprocesses.
