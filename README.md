# Kermit's Restaurant System

A Laravel capstone project for restaurant sales, inventory, customer ordering, and reservations. The application separates public customer features from protected staff tools and keeps business rules in dedicated service classes.

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

Current verified baseline: **100 tests, 612 assertions**, with no known Composer security advisories.

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

Never deploy the local `.env` file. On the live server, use a new application key, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies, real mail credentials, database backups, and private production credentials. Real GCash or Maya payments require their official merchant APIs and server-side webhook verification; displaying a QR image alone does not prove payment.
