# Kermit's Customer Android App

Native Kotlin + Jetpack Compose customer application for the Laravel `/api/v1` API. It renders entirely with Android Compose UI and does not embed the website in a WebView.

## Open and run

Open the `android/` directory in Android Studio. Let Gradle sync, select an Android 8.0+ emulator or device, and run the `app` configuration.

The release build uses `https://kermits-pos.com/api/v1/` by default. For a local Laravel server, change `API_BASE_URL` in `app/build.gradle.kts` to a reachable URL ending in `/api/v1/` (for an emulator, the host machine is commonly `10.0.2.2`). Never ship a local or HTTP endpoint in release configuration.

```powershell
cd android
./gradlew assembleRelease
```

To build an installable APK that is locked to the production HTTPS API and publish that exact artifact to Laravel's `/download-app` route, run:

```powershell
cd android
./gradlew :app:publishDownloadApk
```

The task copies `app/build/outputs/apk/download/app-download.apk` to `storage/app/releases/kermits.apk`. The dedicated `download` build never inherits `debug.api.base.url` from `local.properties`, so a private development address cannot leak into the distributed app. Laravel continues to serve it as `Kermits-Restaurant.apk`.

## Included customer flow

- Customer login with username or Gmail address
- Encrypted bearer-token persistence and logout/revocation
- Live product catalog with stock-aware cart
- Cash or GCash order submission
- Order history and reservation history
- HTTPS-only release default, shrinking, resource optimization, and debug-only HTTP logging
- Web-aligned customer UI with circular Kermit's branding, dark navigation, search, category filters, menu cards, and cart summary

## Release checklist

1. Deploy the Laravel API and set its public `APP_URL` to the HTTPS production domain.
2. Confirm API throttling, mail delivery, backups, and storage permissions in production.
3. Add a real app signing key through Android Studio or CI; do not commit it.
4. Run `assembleRelease`, install the signed artifact on a clean device, and exercise login, ordering, logout, and history flows against staging.
5. Add Crashlytics or an equivalent crash reporting service before publishing.

The build requires JDK 17 or newer and an Android SDK with API 35. Android Studio's bundled runtime is suitable when `JAVA_HOME` points to a complete installation.
