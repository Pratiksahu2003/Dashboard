# Deploy SuGanta Partner App to Google Play Store

This guide walks you through building a signed release and publishing the app on the Android Play Store.

---

## Prerequisites

- [Flutter SDK](https://flutter.dev/docs/get-started/install) installed and on PATH
- [Android Studio](https://developer.android.com/studio) (or Android SDK command-line tools)
- **Google Play Developer account** ([sign up](https://play.google.com/console/signup)) — one-time $25 fee
- App content ready: store listing text, screenshots, privacy policy URL (if required)

---

## 1. Create an upload keystore (one-time)

You need a keystore to sign the release build. **Keep this file and passwords safe;** you need them for every future update.

### Option A: Using keytool (recommended)

On Windows, `keytool` is not on PATH by default — use the full path to `keytool.exe` (from Android Studio or JDK). The keystore can be created in your user folder so it stays in one place and is easy to back up.

**Windows (PowerShell) — keystore in your user profile (recommended):**

```powershell
# Run from any folder. Keystore will be created at e.g. C:\Users\YourName\upload-keystore.jks
& "${env:ProgramFiles}\Android\Android Studio\jbr\bin\keytool.exe" -genkey -v -keystore $env:USERPROFILE\upload-keystore.jks `
  -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 `
  -alias upload
```

If Android Studio is installed elsewhere, replace `"${env:ProgramFiles}\Android\Android Studio\jbr\bin\keytool.exe"` with your path. If you have **JAVA_HOME** set, you can use:

```powershell
& "$env:JAVA_HOME\bin\keytool.exe" -genkey -v -keystore $env:USERPROFILE\upload-keystore.jks `
  -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 `
  -alias upload
```

**Windows — keystore inside the project (android/ folder):**

```powershell
cd android
& "${env:ProgramFiles}\Android\Android Studio\jbr\bin\keytool.exe" -genkey -v -keystore upload-keystore.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

**macOS / Linux:**

```bash
keytool -genkey -v -keystore upload-keystore.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

(Optional: use `$HOME/upload-keystore.jks` instead of `upload-keystore.jks` to put the keystore in your home directory.)

- When prompted, enter a **store password** and **key password** (you can use the same for both).
- Fill in your name/organization and city/country as needed.
- **Back up** the `.jks` file and the passwords in a secure place (e.g. password manager). If you lose them, you cannot update the app on Play Store with the same application ID.

### Option B: Using Android Studio

1. **Build → Generate Signed Bundle / APK**
2. Choose **Android App Bundle**, click **Next**
3. **Create new...** to create a new keystore; choose a path (e.g. `android/upload-keystore.jks`) and fill in passwords and alias (e.g. `upload`).
4. You can cancel the wizard after the keystore is created; we will build from the command line.

---

## 2. Configure signing (key.properties)

1. In the `android/` folder, copy the example file:

   ```bash
   cd android
   copy key.properties.example key.properties   # Windows
   # cp key.properties.example key.properties  # macOS/Linux
   ```

2. Open `key.properties` and set your values:

   **If the keystore is in your user profile** (e.g. `C:\Users\YourName\upload-keystore.jks`):

   ```properties
   storeFile=C:/Users/YourName/upload-keystore.jks
   storePassword=YOUR_STORE_PASSWORD
   keyAlias=upload
   keyPassword=YOUR_KEY_PASSWORD
   ```

   Replace `YourName` with your Windows username, or use the full path with forward slashes (e.g. `C:/Users/NXTGN/upload-keystore.jks`).

   **If the keystore is in the project** (e.g. `android/upload-keystore.jks`):

   ```properties
   storeFile=upload-keystore.jks
   storePassword=YOUR_STORE_PASSWORD
   keyAlias=upload
   keyPassword=YOUR_KEY_PASSWORD
   ```

   - `storeFile`: relative to `android/` (e.g. `upload-keystore.jks`) or **absolute path** (use forward slashes: `C:/Users/You/upload-keystore.jks`).
   - `storePassword` and `keyPassword`: the passwords you used when creating the keystore.
   - `keyAlias`: the alias you used (e.g. `upload`).

3. **Do not commit `key.properties` or `*.jks`** — they are already in `.gitignore`.

---

## 3. Set application ID and version (optional)

- **Application ID** (package name) is in `android/app/build.gradle.kts`:

  ```kotlin
  defaultConfig {
      applicationId = "suganta.international"
      ...
  }
  ```

  For production, use your own ID (e.g. `com.yourcompany.suganta_partner`). Changing it later is possible but requires a new Play Store listing.

- **Version** is in `pubspec.yaml`:

  ```yaml
  version: 1.0.0+1
  ```

  - `1.0.0` → **versionName** (shown to users).
  - `1` → **versionCode** (integer; must increase for each Play Store upload).

  For the next release (every Play upload):
  - increase `versionCode` (the number after `+`) every time
  - optionally bump `versionName` (the `A.B.C` part) when you want a new visible version

  Example: if you are currently on `1.0.1+3`, the next release should be `1.0.2+4`.

---

## 4. Build the release App Bundle (AAB)

Google Play requires the **Android App Bundle** (`.aab`) format.

From the **project root** (not `android/`):

```bash
flutter clean
flutter pub get
flutter build appbundle --release
```

Output path:

```
build/app/outputs/bundle/release/app-release.aab
```

- If `key.properties` is missing or invalid, the release build may use the debug keystore (fine for local testing; **do not upload that to Play Console as a production release**).
- To force a specific version for this build:

  ```bash
  flutter build appbundle --release --build-name=1.0.0 --build-number=1
  ```

---

## 5. Play Console setup (first time)

1. Go to [Google Play Console](https://play.google.com/console).
2. **Create app** → Enter app name (e.g. “SuGanta Partner”), default language, and choose app or game.
3. Complete **App content** (required for publishing):
   - **Privacy policy**: add a URL if your app collects user data.
   - **Ads**: declare if the app contains ads.
   - **Content rating**: complete the questionnaire and assign a rating.
   - **Target audience**: set age groups.
   - **News app**: declare if applicable.
   - **COVID-19 contact tracing / status**: declare if applicable.
4. **Store listing** (Main store listing):
   - Short and full description.
   - Screenshots (phone at least; 7″ and 10″ tablet if you support them).
   - App icon (512×512).
   - Feature graphic (1024×500).
   - Optional: video, contact email, etc.
5. **Release → Production** (or a testing track first):
   - Create a new release.
   - Upload `app-release.aab` from `build/app/outputs/bundle/release/`.
   - Add release name (e.g. “1.0.0 (1)”) and release notes.
   - Review and roll out.

---

## 6. Upload the AAB and publish

1. In Play Console: **Release → Production** (or **Testing → Internal/Closed testing**).
2. **Create new release**.
3. **Upload** `build/app/outputs/bundle/release/app-release.aab`.
4. Fill in **Release name** and **Release notes**.
5. **Save** then **Review release**.
6. Fix any errors (e.g. permissions, content rating, store listing).
7. **Start rollout to Production** (or to your chosen track).

After review, the app will go live according to the track you selected.

---

## 7. Future updates

1. Update app version in `pubspec.yaml`:
   - `versionName` (optional): shown to users
   - `versionCode` (required): must be higher than every previous uploaded build
2. Rebuild the signed Android App Bundle (AAB):
   ```bash
   flutter build appbundle --release
   ```
3. Create a new release in Play Console and upload:
   - `build/app/outputs/bundle/release/app-release.aab`
4. Use the **same keystore** and **same application ID**; do not lose `android/key.properties` or the keystore file.

### Version verification (optional)
This app shows the current build version inside the UI on the **Need Support** screen (bottom text: `App version: <versionName>+<versionCode>`). Install your newly built release and check the value to confirm it matches the `pubspec.yaml` version.

---

## Troubleshooting

| Issue | What to do |
|-------|------------|
| Build fails: “Keystore was tampered with, or password was incorrect” | Check `storePassword` and `keyPassword` in `key.properties`; ensure no extra spaces. |
| Build uses debug signing | Ensure `android/key.properties` exists and paths/passwords are correct; run `flutter clean` then `flutter build appbundle --release` again. |
| Play Console rejects AAB: “Version code X has already been used” | Increase the build number in `pubspec.yaml` (the number after `+`) and rebuild. |
| “App not signed” or signing errors | Confirm `storeFile` path in `key.properties` is correct (relative to `android/` or absolute). |
| Keystore file missing | If `key.properties` points to a keystore file that doesn’t exist, the release build/upload will fail. Put the keystore at that exact path (or update `storeFile` to the correct path) and rebuild. |

---

## File reference

| File | Purpose |
|------|--------|
| `android/key.properties` | Signing credentials (git-ignored). Create from `key.properties.example`. |
| `android/upload-keystore.jks` | Upload keystore (git-ignored). Back up securely. |
| `android/app/build.gradle.kts` | Reads `key.properties` and configures release signing. |
| `pubspec.yaml` | `version: A.B.C+N` → versionName (A.B.C) and versionCode (N). |
| `build/app/outputs/bundle/release/app-release.aab` | Output of `flutter build appbundle --release`. Upload this to Play Console. |

---

For more detail, see:

- [Flutter: Android release](https://docs.flutter.dev/deployment/android)
- [Google Play: App signing](https://support.google.com/googleplay/android-developer/answer/9842756)
- [Play Console help](https://support.google.com/googleplay/android-developer)
