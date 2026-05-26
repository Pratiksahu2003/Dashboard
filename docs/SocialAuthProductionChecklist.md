# Social Auth Production Checklist

Use this checklist before generating production APK/AAB/IPA.

## Firebase Console

- Project: `suganta-tutors`
- Android package: `suganta.international`
- Enabled providers: Google, Facebook, GitHub, Apple
- Add both debug + release SHA-1/SHA-256 fingerprints
- Download fresh `google-services.json` after SHA changes

Current local fingerprints to register:

- Debug SHA-1: `2B:34:D9:A0:BB:70:E5:C7:23:7A:AD:94:2E:AA:03:7A:37:F2:F1:28`
- Debug SHA-256: `32:88:01:A8:22:58:A7:51:0F:54:50:B6:80:43:E3:ED:71:77:EF:A8:34:0C:9A:20:61:67:8C:96:CE:7E:93:E5`
- Release SHA-1: `3F:CC:53:80:DE:9F:1F:F5:BA:09:E4:E5:63:7E:78:F3:3F:A3:BE:5D`
- Release SHA-256: `0E:17:9F:C0:AA:10:FE:B0:72:06:6C:52:17:2A:7B:4C:3D:9D:DF:B6:A6:AA:78:88:32:91:63:3F:E5:E1:F6:D2`

## Google Sign-In

- Use Web Client ID in app runtime:
  - `768518919024-451e6lp6mvojf2b1er7bc6ekd3lps5nj.apps.googleusercontent.com`
- Keep Firebase Web client as fallback:
  - `893066645136-d0jakqpsaafclvl4t9najs8knrbqtep0.apps.googleusercontent.com`

## Facebook Setup

- Firebase Facebook provider must use the same Facebook App:
  - App ID: `2020616218667623`
- Callback URL in Facebook app:
  - `https://auth.firebase.com/__/auth/handler`
- Add Android package: `suganta.international`
- Add key hashes for all signing paths (debug, release, and Google Play app signing)

### Production Hash Matrix (Required)

- **Debug hash**: local emulator/device testing with debug builds.
- **Release hash**: sideloaded signed APK/AAB using your own release keystore.
- **Google Play App Signing hash**: production installs from Play Store (most common missing hash).

If any one of these is missing in Facebook App settings, users can see `Invalid key hash` and Facebook login will fail for that distribution path.

### Generate debug key hash (Windows PowerShell)

```powershell
keytool -exportcert -alias androiddebugkey -keystore "$env:USERPROFILE\.android\debug.keystore" -storepass android -keypass android | openssl sha1 -binary | openssl base64
```

Current debug key hash:

- `KzTZoLtw5ccjeq2ULqoDejfy8Sg=`

### Generate release key hash

```powershell
keytool -exportcert -alias "<RELEASE_ALIAS>" -keystore "<PATH_TO_RELEASE_KEYSTORE>" -storepass "<STORE_PASSWORD>" -keypass "<KEY_PASSWORD>" | openssl sha1 -binary | openssl base64
```

### Google Play App Signing key hash (Production)

1. Open Play Console -> **App integrity** -> **App signing key certificate**.
2. Copy SHA-1 certificate fingerprint.
3. Convert SHA-1 certificate to Facebook key hash and add it in Facebook Developer Console -> Settings -> Basic -> Android -> **Key Hashes**.
4. Keep this hash together with debug and release hashes (do not replace one with another).

### Post-change verification checklist

- Save Facebook app settings after adding hashes.
- Wait 5-10 minutes for propagation.
- Confirm Firebase Facebook provider uses the same App ID/Secret.
- Uninstall and reinstall target build variant before testing.
- Test all three paths:
  - Debug build login
  - Local signed release login
  - Play Store installed production login

## GitHub Setup

- OAuth App callback URL:
  - `https://auth.firebase.com/__/auth/handler`
- Set Client ID + Secret in Firebase GitHub provider

## Apple Setup

- Apple Developer: enable Sign In with Apple on Bundle ID
- Configure Service ID + redirect in Apple portal
- Firebase Apple provider needs Team ID, Service ID, Key ID, private key
- iOS target must include `Runner.entitlements` with `com.apple.developer.applesignin`

## iOS Required File

- Ensure `ios/Runner/GoogleService-Info.plist` is present and from the same Firebase project as Android.
- Ensure URL scheme includes `REVERSED_CLIENT_ID` from that file.

## Backend Social Login Contract

Endpoint: `POST /auth/social-login`

Payload sent by app:

```json
{
  "provider": "google|facebook|github|apple",
  "token": "<firebase_id_token>",
  "id_token": "<firebase_id_token>"
}
```

Backend should accept `token` or `id_token`, verify with Firebase Admin SDK, then return app auth token and onboarding/payment flags.
