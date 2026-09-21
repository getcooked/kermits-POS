# Kermit's Restaurant System

A Laravel capstone project for restaurant sales, inventory, customer ordering, and reservations. The application separates public customer features from protected staff tools and keeps business rules in dedicated service classes.

## Login reCAPTCHA

The website login supports Google reCAPTCHA v2 (the "I'm not a robot" checkbox). In `.env`, set `RECAPTCHA_ENABLED=true`, `RECAPTCHA_SITE_KEY`, and `RECAPTCHA_SECRET_KEY`, then run `php artisan config:clear`. Configure these values separately on the deployed server; `.env` is not committed to Git. Keep the secret key on the server only.

Register the website hostname in the [reCAPTCHA console](https://www.google.com/recaptcha/admin). For local development, allow `localhost` and open the site using `localhost` rather than `127.0.0.1`. Keep Google's domain validation enabled and use checkbox v2 keys. The server verifies each token and its hostname before checking login credentials. Missing/expired tokens and verification outages reject the login with a retry message. Existing login rate limits remain in effect. Registration, password reset, and the native Android API login are unchanged.

Automated tests disable reCAPTCHA by default; `RecaptchaLoginTest` explicitly enables it and mocks Google's responses to cover successful verification, rejection, expiry, hostname mismatch, and outages. For a live check, open `/login`, complete the checkbox, and sign in; also confirm submitting without completing the checkbox shows an error.

## PayMongo online checkout

The customer shop can create a [PayMongo Hosted Checkout v2](https://docs.paymongo.com/docs/payment-channels-hosted-checkout) session for the food order and table fee. The app saves the PayMongo checkout ID and marks both records paid only after it receives a matching, signed `checkout_session.payment.paid` webhook. Cash and manual GCash checkout remain available.

1. Run `php artisan migrate` to add the checkout fields to orders.
2. Get a **test** secret API key from the [PayMongo dashboard](https://dashboard.paymongo.com/). In `.env`, set `PAYMONGO_SECRET_KEY=sk_test_...` and `PAYMONGO_PAYMENT_METHODS=gcash,qrph,card` to the methods enabled for your account.
3. Register an HTTPS webhook endpoint at `https://YOUR-DOMAIN/api/paymongo/webhook` in the PayMongo dashboard. Subscribe to `checkout_session.payment.paid` and copy that endpoint's signing secret into `PAYMONGO_WEBHOOK_SECRET`. A local `127.0.0.1` URL cannot receive PayMongo webhooks; use a public test URL while developing.
4. Set `APP_URL` to the site's public HTTPS URL, set `PAYMONGO_ENABLED=true`, and run `php artisan config:clear` (or rebuild the production config cache). Create a test customer order and complete a test payment. Confirm the order and linked reservation show **Paid** before using live credentials.

Keep both secrets only on the server. Use the matching live API and webhook secrets when switching to live mode. A checkout redirect alone does not confirm payment. If a reservation is cancelled while a checkout is open and its payment later succeeds, the payment is recorded but staff must review the reservation and handle any refund in PayMongo. The Android API still uses its existing payment flow.

## Roles and access

| Role | Access |
| --- | --- |
| Customer | Shop, own orders, table/food/exclusive reservations |
| Cashier | Point of sale and staff receipts |
| Admin | No administrative web pages |
| Super admin | Dashboard, customers, inventory, reports, reservations, product management, and POS |

Public registration always creates a **customer** account. Staff roles must be assigned through an authorized administrative process or database seeder.

## Local setup

Requirements: PHP 8.2+, Composer, and the PHP SQLite extensions.

```powershell
cd C:\xampp\htdocs\C1\simple-login-system
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open `http://127.0.0.1:8000`. XAMPP's Apache service is not required when using `php artisan serve`; MySQL is also unnecessary while the app uses SQLite.

## Main code structure

- `app/Http/Requests` validates and authorizes incoming form data.
- `app/Http/Controllers` coordinates page and request flow.
- `app/Services` contains transactional ordering and inventory rules.
- `app/Models` defines database records, relationships, casts, and query scopes.
- `routes/web.php` groups routes by authentication and role.
- `resources/views` contains the responsive Blade interface.
- `tests/Feature` verifies roles, ordering, inventory, products, and reservations.

See [Architecture](docs/ARCHITECTURE.md) and [Security](docs/SECURITY.md) for the complete flow and deployment checklist.

## Quality checks

Run these before presenting or submitting the project:

```powershell
vendor\bin\pint --test
php artisan test
composer audit --locked
```

Current verified test run: **228 tests, 1669 assertions**.

## Mobile API readiness

The versioned customer API is available under `/api/v1` and supports customer login/logout, profile details, the live product catalog, ordering, order history, reservations, reservation history, and authenticated FCM installation registration. Mobile access tokens are hashed in the database, expire after 30 days, and are limited to five active devices per customer. Firebase installation IDs are encrypted at rest and removed with their mobile sessions.

The `/download-app` endpoint serves the native Kotlin + Jetpack Compose APK from `storage/app/releases/kermits.apk`. From `android/`, run `./gradlew :app:publishDownloadApk` to compile the native project and replace the downloadable artifact.

Before connecting an Android build to production, deploy the latest code and run:

```powershell
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan queue:work --tries=5
```

Set `APP_URL=https://kermits-pos.com` so API image and payment URLs use the public HTTPS domain. Reservation push delivery also requires `FCM_PROJECT_ID`, an external `GOOGLE_APPLICATION_CREDENTIALS` service-account JSON path, `android/app/google-services.json` at build time, and a continuously supervised Laravel queue worker. See `android/README.md` for activation steps.

## Production notes

Never deploy the local `.env` file. On the live server, use a new application key, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies, real mail credentials, database backups, and private production credentials. The manual GCash QR flow still needs cashier verification; displaying a QR image alone does not prove payment.
