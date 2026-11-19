# NIELIT Bhubaneswar — Android app (Capacitor)

This folder wraps the **existing PHP portal** in a native Android shell. The app loads your live site (default: `https://nielitbhubaneswar.in`) — no PHP code is duplicated inside the APK.

## What you get

- Installable **APK** on student/coordinator phones
- Full portal: registration, student login, admin (in browser-style WebView)
- File uploads (documents) via system file picker
- Back button returns in WebView history, then exits the app

## Prerequisites (local build)

1. **Node.js** 18+ (you have this)
2. **Android Studio** (installs Android SDK + emulator)
3. **JDK 17** (Android Gradle Plugin 8+; upgrade from Java 8 if Gradle fails)

## One-time setup

```powershell
cd mobile-app
npm install
npx cap add android
npx cap sync android
```

### Point at local XAMPP (emulator)

Android emulator cannot use `localhost` on your PC. Use `10.0.2.2`:

```powershell
$env:CAPACITOR_SERVER_URL = "http://10.0.2.2/public_html"
npx cap sync android
```

Use your PC’s LAN IP for a physical device on the same Wi‑Fi.

### Production URL (default)

`capacitor.config.ts` uses `https://nielitbhubaneswar.in`. Override only when syncing:

```powershell
$env:CAPACITOR_SERVER_URL = "https://nielitbhubaneswar.in"
npx cap sync android
```

## Build APK (debug — for testing)

```powershell
cd mobile-app\android
.\gradlew.bat assembleDebug
```

APK path:

`mobile-app\android\app\build\outputs\apk\debug\app-debug.apk`

Share that file or sideload on a phone (enable “Install unknown apps” for your file manager).

## Release APK / Play Store

1. Open Android Studio: `npm run cap:open`
2. **Build → Generate Signed Bundle / APK**
3. Create a keystore (keep it safe — required for all future updates)
4. Prefer **AAB** for Google Play; **APK** for direct distribution

Update `versionCode` / `versionName` in `android/app/build.gradle` before each store release.

## CI build (GitHub Actions)

Workflow: `.github/workflows/android-apk.yml`

On push to `main` (when `mobile-app/**` changes) or manual **Run workflow**, GitHub builds a **debug APK** and uploads it as an artifact (**Actions → latest run → Artifacts**).

## App ID / branding

| Field | Value |
|-------|--------|
| Package | `in.nielitbhubaneswar.portal` |
| Display name | NIELIT Bhubaneswar |

Change `appId` in `capacitor.config.ts` before publishing if you need a different package name.

## Security notes

- The app trusts only the configured HTTPS origin; do not enable mixed content in production.
- Admin sessions use cookies like the website — use the same logout practices on shared devices.
- For Play Store, you may need a **privacy policy** URL (portal footer / NIELIT policy).

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Blank white screen | Check portal URL in browser; verify SSL certificate; run `npx cap sync` |
| Upload fails | Grant storage/camera permissions in Android settings |
| Login loops | Ensure cookies work on `nielitbhubaneswar.in` (no third-party cookie block) |
| Gradle / Java error | Install JDK 17 and set `JAVA_HOME` |