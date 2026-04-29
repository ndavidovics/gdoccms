# Mutual Offline Session — V1 MVP

Two users start a shared "offline" session by QR code. While the session is active, both devices block internet (via VPN-based protection) and suppress notifications where the OS allows. The session **succeeds only if both users mutually end it**. If either user disables protection, loses required permissions, swaps devices, or stops sending heartbeats, the backend marks the session **failed**.

This repository is the V1 working skeleton — three deliverables:

| Path | Stack |
| --- | --- |
| [`backend/`](backend/) | Laravel 11, MySQL, Redis, Sanctum, Reverb |
| [`ios/`](ios/MutualOffline/) | Swift 5.10 + SwiftUI (Xcode 15+) |
| [`android/`](android/) | Kotlin 1.9 + Jetpack Compose, Gradle 8.5 (AGP 8.5) |
| [`docs/API.md`](docs/API.md) | Full HTTP + WebSocket API contract |

> **Honest about platform limits.** iOS apps cannot toggle Airplane Mode. Android user apps cannot toggle Airplane Mode. iOS notification suppression is largely outside our control without a Focus filter intent extension; we treat DND as advisory on iOS and surface guidance instead. The enforceable core for both platforms is **VPN-based blocking + heartbeat-based failure detection**. We make **no claim** that V1 is impossible to bypass — a determined user can disable a VPN profile or remove the app. The session simply *fails* when they do.

---

## What's in V1

### Core lifecycle
- Sanctum-token auth, single active device per user, signed short-lived QR join tokens.
- Session state machine: `created → pending_second_user → ready_to_lock → active → success | failed | cancelled`. Server is the source of truth; the client never decides success.
- Heartbeats every 10s reporting `vpn_active`, `network_block_active`, `dnd_active`, etc. Backend fails the session if protection drops, the device UUID stops matching, or heartbeats are silent for >45s (configurable).
- Stats: total successful seconds, longest streak, current streak, success/failure counts.
- Real-time updates broadcast on `private-sessions.{uuid}` and `private-users.{id}` channels via Laravel Reverb.

### Social layer (V1)
- Hashed contact sync (SHA-256 of normalized phone/email — raw contacts never leave the device).
- Friend requests + accept/reject + remove.
- Feed of session successes/failures and friend joins, scoped to accepted friends. Failures are **private by default**.
- Per-user privacy toggles: `share_successes`, `share_failures`, `share_streaks`, `discoverable_by_contacts`.

### Explicitly out of scope for V1
Payments • public social feed • NFC • BLE proximity • group sessions (>2) • full per-flow VPN packet filtering • app blocking.

---

## Backend setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve                         # http://localhost:8000

# In separate terminals:
php artisan reverb:start                  # WebSocket server
php artisan queue:work redis              # background jobs
php artisan schedule:work                 # runs sessions:sweep every 10s
```

### Required env vars

| Var | Purpose |
| --- | --- |
| `DB_*` | MySQL connection |
| `REDIS_HOST/REDIS_PORT` | Heartbeat TTL store + queue + cache |
| `JOIN_TOKEN_SECRET` | HMAC secret for signing QR join tokens. **Rotate to invalidate outstanding QRs.** |
| `JOIN_TOKEN_TTL_SECONDS` | QR validity window (default 300s) |
| `HEARTBEAT_TIMEOUT_SECONDS` | Max gap before a session is failed (default 45s) |
| `HEARTBEAT_INTERVAL_SECONDS` | Documented client cadence (default 10s) |
| `REVERB_*` | Laravel Reverb broadcasting |
| `CONTACT_HASH_PEPPER` | Reserved (defense-in-depth; not used for matching in V1) |

### Demo / smoke flow

```bash
# Seeds two demo users (alex@example.com / jamie@example.com, password: "password")
php artisan migrate:fresh --seed

# Run the full lifecycle in-process (happy path):
php artisan demo:flow

# Run the failure path (Jamie's VPN drops mid-session):
php artisan demo:flow --fail

# Watch the watchdog detect a stale heartbeat (in another shell, then wait 45s):
php artisan sessions:sweep
```

### Tests

```bash
cd backend
php artisan test
# Or specifically: vendor/bin/phpunit
```

Covered:
- Full happy-path two-user success.
- Protection-disabled heartbeat fails the session.
- Device-mismatch on heartbeat fails the session.
- Emergency-exit fails the session.
- Watchdog fails sessions whose heartbeats have gone stale.
- One-active-device-per-user enforcement.
- Contacts sync matches existing users only when they're discoverable.
- Friend requests + accept emits feed items.
- Feed shows accepted friends only; failed sessions are private by default.
- Join token signer round-trip / tamper / expiry.

---

## iOS setup

The iOS code lives at `ios/MutualOffline/` as a set of Swift sources. To run:

1. Open Xcode → File → New → Project → iOS App, name it `MutualOffline`, language Swift, interface SwiftUI.
2. In the Xcode project, add the `ios/MutualOffline/` folder structure to the project (drag the source folders, choose "Create groups").
3. Set the deployment target to iOS 17.
4. Add framework `NetworkExtension` (used as a stub-only reference in V1).
5. (Optional, for real packet filtering): add a second target *Network Extension → Packet Tunnel*, request the Network Extension entitlement. The V1 stub `IOSVpnProtectionService` is structured so a real `NEPacketTunnelProvider` subclass can be wired in cleanly.
6. In `App/APIConfig.swift`, set `baseURL` to your backend (`http://localhost:8000` for local; use your machine's LAN IP if running on a real device).
7. Build & run.

Permissions configured at runtime: camera (QR scan), contacts (Find Friends — opt-in), VPN profile install (when "Enable Protection" is tapped). iOS DND/Focus is best-effort and surfaced as guidance only.

---

## Android setup

```bash
cd android
./gradlew assembleDebug    # builds app-debug.apk under app/build/outputs/apk/debug/
./gradlew installDebug     # installs on a connected device or emulator
```

Default `API_BASE_URL` in `app/build.gradle.kts` is `http://10.0.2.2:8000` (Android emulator's host loopback). Override via build flavor or change the BuildConfig field for a physical device.

Required permissions (declared in `AndroidManifest.xml`, requested at runtime):
- `INTERNET`, `ACCESS_NETWORK_STATE` — API + status checks.
- `BIND_VPN_SERVICE` — declared on `LocalVpnService`; system-bound.
- `FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_SPECIAL_USE` — for the heartbeat foreground service.
- `READ_CONTACTS` — opt-in for Find Friends.
- `POST_NOTIFICATIONS` — for the foreground-service notification.
- Notification Policy Access (DND) — handed off to system Settings via `ACTION_NOTIFICATION_POLICY_ACCESS_SETTINGS`.

The V1 `LocalVpnService` is a real `VpnService` subclass that establishes a tunnel and drops all packets — this gives us a true `vpn_active` signal. Production-grade per-app / per-domain filtering is not in V1.

---

## API flow at a glance

```
A) Register/login → POST /api/register or /api/login → token
B) Register device → POST /api/devices/register {device_uuid, platform}
C) HOST creates session → POST /api/sessions → returns join_token + qr_payload
D) HOST shows QR → GUEST scans → POST /api/sessions/join {join_token}
E) Both POST /api/sessions/{uuid}/confirm-lock → session goes ACTIVE
F) Each app POST /api/sessions/{uuid}/heartbeat every 10s with integrity state
   - vpn_active=false  -> session FAILED (protection_disabled)
   - device_uuid mismatch -> session FAILED (device_mismatch)
   - 45s of silence -> session FAILED (heartbeat_timeout)  [watchdog]
G) Either user POST /api/sessions/{uuid}/request-end (UI signal to peer)
H) Both POST /api/sessions/{uuid}/confirm-end → session SUCCESS, stats updated
   Or POST /api/sessions/{uuid}/emergency-exit → session FAILED (emergency_exit)
```

Full request/response shapes: [`docs/API.md`](docs/API.md).

---

## Architecture notes

- **Server owns everything.** Clients never compute duration or success. They only report integrity state.
- **DB transactions on every state transition.** `Session::lockForUpdate()` prevents two participants racing on confirm-lock or confirm-end.
- **Redis for heartbeat TTLs.** The `HeartbeatTracker` writes per-participant `setex` keys; the watchdog command (`sessions:sweep`, scheduled every 10s) does the canonical check against `last_heartbeat_at` in MySQL — Redis is an optimization, not the source of truth.
- **QR join tokens are HMAC-SHA-256 signed**, 5-minute TTL by default, scoped to a single session UUID. Rotating `JOIN_TOKEN_SECRET` invalidates all outstanding tokens.
- **Contact matching is one-way and hashed.** Apps SHA-256 each normalized phone/email locally before upload; raw contacts never leave the device. Matching only finds users with `discoverable_by_contacts = true`.

---

## Known platform limits (read carefully)

| Concern | iOS | Android |
| --- | --- | --- |
| Toggle Airplane Mode | **Not possible** for normal apps | **Not possible** for normal apps |
| Block all internet | NEPacketTunnelProvider extension required (entitlement). V1 ships a stub. | Real `VpnService` subclass; V1 establishes a tunnel but drops packets. |
| Suppress notifications | Best-effort: Focus filter intent or guidance only | Notification Policy Access (DND); requires user grant in Settings |
| Detect bypass attempts | Heartbeat+VPN status; user can still uninstall the app | Heartbeat+VPN status; user can still revoke VPN approval |

V1 makes **no** claim that protection is impossible to bypass. Bypass = the session fails. That's the whole design.

---

## License

MIT.
