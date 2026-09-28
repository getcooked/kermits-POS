# Enable the Android app download online

The live site already contains the `/download-app` route and the **Download app** links. They stay hidden ("App coming soon") until the server has both the APK file and the enable setting. Neither is deployed through Git: `storage/app/releases/*.apk` is ignored and the live `.env` is never replaced.

## 1. Build the APK locally

```powershell
cd android
./gradlew :app:publishDownloadApk
```

The task writes `storage/app/releases/kermits.apk`, locked to `https://kermits-pos.com/api/v1/`. It requires the `KERMITS_KEYSTORE_*` signing variables described in `android/README.md`.

## 2. Upload the APK

In Hostinger hPanel File Manager, open the Laravel project directory and go to `storage/app/`. Create the `releases` folder if it does not exist, then upload the local `storage/app/releases/kermits.apk` into it. The file name must be exactly `kermits.apk`.

Do not place the APK in `public/` or `public_html/`. Laravel serves it through `/download-app` as `Kermits-Restaurant.apk`.

## 3. Turn the download on

Open the live `.env` in File Manager and add or update:

```dotenv
APP_DOWNLOAD_ENABLED=true
```

## 4. Clear Laravel's cached settings

From the hPanel terminal, in the Laravel project directory:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

If hPanel has no terminal, delete only the generated PHP files inside `bootstrap/cache` except `.gitignore`, then reload the site.

## 5. Verify

- `https://kermits-pos.com/` shows **Download app** instead of **App coming soon**.
- `https://kermits-pos.com/download-app` downloads `Kermits-Restaurant.apk`.
- On an Android phone, install the file and sign in.

## Publishing a newer version

Increase `versionCode` and `versionName` in `android/app/build.gradle.kts`, rebuild, and upload the new `kermits.apk` over the old one. Keep the same signing key, or existing installs cannot update. The download links include the file's modification time, so browsers fetch the new file without a cache clear.

To hide the download again, set `APP_DOWNLOAD_ENABLED=false` and repeat step 4.
