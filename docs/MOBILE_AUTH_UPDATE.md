# Mobile authentication update - 1.0.20

The Android download APK is `storage/app/releases/kermits.apk` (version code 21, version 1.0.20). It uses the production API at `https://kermits-pos.com/api/v1/` and the same signing certificate as the previous download, so it can update that installation. Registration and Personal Information can resolve the device's present location into a readable address.

## Deploy the online fixes

Upload these server files to the corresponding paths in the existing Laravel application:

- `app/Http/Controllers/Api/MobileRegistrationController.php`
- `app/Http/Controllers/Api/MobilePasswordResetController.php`
- `routes/api.php`
- `storage/app/releases/kermits.apk`

The server-only ZIP contains the three PHP files and this guide. It does not contain `.env` or credentials. Preserve the live server's existing `.env` and database.

In the live application's directory, run:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

Confirm the server uses the public HTTPS `APP_URL` and a working email provider. SMTP host, port, scheme, username, password, and sender belong in the server configuration. A `log` or `array` mailer does not deliver real email. These authentication emails are sent synchronously; they do not require a queue worker.

## Fixed behavior

- Registration email, code verification, account creation, and password reset have independent request limits. Resending a code or correcting it no longer consumes the account-creation or password-reset limit.
- The app can resend a code and change the email. Expired verification restarts the email step while retaining entered account details.
- Specific validation errors, such as a taken username, appear in the app. Account creation success appears on the login screen.
- Password recovery scrolls when the keyboard is open and explains how to complete the email link flow.
- An SMTP failure returns an actionable error. A failed reset email removes its undelivered token, allowing a retry rather than silently throttling it.
- Incorrect verification attempts do not extend the original code expiry.

## Verification

Eleven Android authentication tests and seventeen Laravel registration/password-reset tests passed. They cover resend and wrong-code recovery, account creation followed by mobile login, and password reset followed by mobile login. APK signature verification passed.

Live endpoint checks used empty requests and confirmed both authentication routes return JSON validation errors. No real email was sent by those checks, so delivery to a real inbox and a physical-device walkthrough remain to be checked after deployment.

Use your own customer email for a final check: request a registration code, test resend, create the account, and log in. Request a reset link, open it from the email, choose a new password, and return to the app to log in. Also check the spam folder.

The website's reCAPTCHA protects web login only; the native mobile registration and recovery flows do not use it.
