# LendTrack Mobile

Flutter Android app for the LendTrack Laravel API. This folder contains the latest source uploaded by the project owner, including the calendar-month loan process, payment suggestions, one-page PDF receipts, and form labels.

## Open the correct project

Open this `mobile` folder in Android Studio. The repository root is the Laravel backend, deployed separately on Render. Keep the backend at the repository root.

## Requirements

Use a Flutter stable SDK with Dart 3.13.7 or newer (within Dart 3.x), Android SDK, and a compatible JDK. The Android project targets Java 17 and uses the checked-in Gradle wrapper. Local SDK paths are intentionally not committed; Flutter/Android Studio recreates `android/local.properties` on each machine.

## Check and run

From this folder:

```powershell
flutter pub get
flutter analyze
flutter test
flutter run --dart-define=API_BASE_URL=https://lendtrack-backend-ftxj.onrender.com/api
```

## Build an APK

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=https://lendtrack-backend-ftxj.onrender.com/api
```

Output: `build/app/outputs/flutter-apk/app-debug.apk`.

Include API_BASE_URL on every online build. Without it, the current source defaults to the Android emulator's local server. Do not paste Markdown link syntax into the command.

The current release configuration uses debug signing. Production signing keys must be configured privately before a store release. Keep the app ID unchanged when updating an installed app.

## Project files

- `lib/`: screens, API/session handling, and receipt generation.
- `assets/`: bundled font and its license.
- `android/`: Android project and Gradle wrapper.
- `test/`: Flutter tests supplied with the project.
- `pubspec.yaml` and `pubspec.lock`: dependencies and resolved versions.

This upload contains the Android platform only. Other platform projects are not required for Android. Build output, caches, local SDK settings, crash logs, credentials, signing keys, and generated plugin registration are intentionally excluded.

User accounts and lending records live in the hosted database, not this repository. Do not upload database records or passwords to GitHub.
