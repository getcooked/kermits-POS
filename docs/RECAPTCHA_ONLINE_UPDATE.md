# Enable reCAPTCHA on the online authentication forms

This update protects the online login, customer registration-code request, regular password recovery, and Super Admin password recovery forms. Verification occurs on the server before credentials are checked or email is sent.

## 1. Upload the application files

Upload the contents of `recaptcha-online-update.zip` into the Laravel project root while preserving the included paths. The package contains:

- `app/Http/Middleware/AddSecurityHeaders.php`
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/PasswordResetController.php`
- `app/Http/Requests/LoginRequest.php`
- `app/Rules/Recaptcha.php`
- `config/services.php`
- `resources/views/components/recaptcha.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`

Do not upload a local `.env` file and do not replace the live server's existing `.env`.

## 2. Configure the live environment

In Hostinger hPanel File Manager, open the Laravel project's existing `.env` file and add or update:

```dotenv
RECAPTCHA_ENABLED=true
RECAPTCHA_SITE_KEY=YOUR_SITE_KEY
RECAPTCHA_SECRET_KEY=YOUR_SECRET_KEY
```

Use the site key and secret key issued for this installation. Never place the secret key in a Blade template or any public file.

In the Google reCAPTCHA console, confirm that the key type is **reCAPTCHA v2 Checkbox** and the allowed domain includes:

```text
kermits-pos.com
```

Enter the domain without `https://`, `www`, a path, or a trailing slash. Domain validation should remain enabled.

## 3. Clear Laravel's old cached settings

From Hostinger hPanel's terminal, change to the Laravel project directory and run:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

If hPanel has no terminal, delete only the generated PHP files inside `bootstrap/cache` except `.gitignore`, then reload the site. Running the Artisan commands is preferred.

## 4. Verify

Open these pages in a private/incognito tab and confirm the checkbox appears:

- `https://kermits-pos.com/login`
- `https://kermits-pos.com/register`
- `https://kermits-pos.com/forgot-password`
- `https://kermits-pos.com/admin/forgot-password`

Submit once without checking it and confirm the page says `Please complete the reCAPTCHA checkbox.` Then complete the checkbox and submit normally.

If Google displays `ERROR for site owner: Invalid domain for site key`, add `kermits-pos.com` to that key in the reCAPTCHA console and wait a few minutes. If the page reports that reCAPTCHA cannot load, check browser content blockers and confirm the updated security-header middleware was uploaded.
