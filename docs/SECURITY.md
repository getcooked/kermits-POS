# Security review

## Controls implemented

- Passwords are hashed by Laravel before storage.
- Login regenerates the session; logout invalidates it and regenerates the CSRF token.
- Public registration always assigns the customer role.
- Role middleware protects customer and cashier routes. The dashboard permits read-only admin access, while operational administrative routes require the super-admin role.
- Login, registration, and public reservation submissions are rate-limited.
- Laravel CSRF protection covers state-changing web forms.
- Reservation confirmation links are temporary and cryptographically signed.
- Form Request classes provide centralized validation and authorization.
- Order totals are recalculated from database prices, never accepted from the browser.
- Orders and stock updates use transactions and row locks to prevent partial writes and overselling.
- Customers can only view orders that belong to their account.
- Stock changes create an auditable movement record.
- Unpaid orders are excluded from sales reporting and cannot produce staff receipts.
- Product image uploads are restricted to validated image files and bounded sizes.
- Automated access-control and workflow tests cover the critical paths.

## Deployment checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set the correct HTTPS `APP_URL` and enable `SESSION_SECURE_COOKIE=true`.
- Generate a unique `APP_KEY`; never reuse or publish `.env`.
- Use a least-privilege database account and schedule encrypted backups.
- Configure real SMTP credentials for email verification and password recovery.
- Put the web server document root on Laravel's `public` directory only.
- Run `composer install --no-dev --optimize-autoloader` and Laravel cache commands during deployment.
- Run `php artisan test` and `composer audit --locked` before each release.
- Add centralized logs and periodically review failed logins, inventory movements, and privileged changes.

## Items required before accepting online payments

A static QR code is not sufficient payment verification. Use an approved GCash/Maya merchant or payment-gateway account, create payments on the server, verify signed webhooks, make webhook handling idempotent, store provider reference IDs, and update an order to paid only after server-to-server confirmation. Keep API secrets only in server environment variables.

## Recommended next security milestones

- Verify production SMTP delivery and add a verified-phone flow when an SMS provider is selected.
- Protect the last super-admin account from deletion and define a retention policy for staff activity logs and IP addresses.
- Add optional staff multi-factor authentication.
- Add expiry or cancellation rules for abandoned pending customer orders so reserved stock is restored safely.
