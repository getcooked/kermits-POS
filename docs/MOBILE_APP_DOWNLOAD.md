# Android app download

The Android APK is committed at `storage/app/releases/kermits.apk` and served at `/download-app` as `Kermits-Restaurant.apk`. The landing page and customer pages show **Download app** whenever that file exists and `APP_DOWNLOAD_ENABLED` is not `false` (it defaults to `true`). Deploying the repository is enough; the live `.env` needs no change.

## Publishing a new version

1. Increase `versionCode` and `versionName` in `android/app/build.gradle.kts`.
2. Build and copy the APK into place:

   ```powershell
   cd android
   ./gradlew :app:publishDownloadApk
   ```

   The task requires the `KERMITS_KEYSTORE_*` signing variables described in `android/README.md`. Always sign with the same key, or existing installs cannot update.
3. Commit `storage/app/releases/kermits.apk` and push.

The download links include the file's modification time, so browsers fetch the new file without a cache clear.

## If the live site still shows "App coming soon"

The server may be using cached configuration from before this change. From the hPanel terminal, in the Laravel project directory:

```sh
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

If the live `.env` contains `APP_DOWNLOAD_ENABLED=false`, remove that line or set it to `true` first.

## Hiding the download

Set `APP_DOWNLOAD_ENABLED=false` in the live `.env` and run the commands above.
