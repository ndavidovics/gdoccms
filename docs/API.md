# Mutual Offline Session — API Contract (V1)

All endpoints are JSON, prefixed with `/api`. Auth uses Laravel Sanctum bearer tokens (`Authorization: Bearer <token>`).

## Conventions

- Timestamps are ISO-8601 UTC.
- `state` values for sessions: `created | pending_second_user | ready_to_lock | active | success | failed | cancelled`.
- All session state changes emit broadcasted events on the `private-sessions.{uuid}` channel.
- Errors: `{ "message": "...", "errors": { ... } }` with appropriate 4xx/5xx codes.

## Auth

### POST /api/register
```json
{ "name": "Alex", "email": "alex@example.com", "password": "secret123", "password_confirmation": "secret123" }
```
Returns `{ "user": {...}, "token": "..." }`.

### POST /api/login
```json
{ "email": "alex@example.com", "password": "secret123" }
```
Returns `{ "user": {...}, "token": "..." }`.

### POST /api/logout
Revokes current token. Returns `{ "ok": true }`.

### GET /api/me
Returns `{ "user": {...}, "stats": {...} }`.

## Devices

### POST /api/devices/register
```json
{
  "device_uuid": "uuid-from-client",
  "platform": "ios",
  "device_name": "Alex's iPhone",
  "app_version": "1.0.0",
  "push_token": null
}
```
Registers (or re-activates) the device. Revokes any other active device for this user. Returns `{ "device": {...} }`.

### GET /api/devices/current
Returns the active device for the authed user.

### POST /api/devices/revoke
Revokes the current active device.

## Sessions

### POST /api/sessions
Creates a session with the authed user as host. Returns:
```json
{
  "session": { "uuid": "...", "state": "pending_second_user", ... },
  "join_token": "<short-lived signed token>",
  "join_qr_payload": "mos://join?t=<join_token>"
}
```
Join token is a signed `JsonWebToken`-like string (HMAC SHA-256), TTL 5 minutes, contains `{ session_uuid, host_user_id, exp, nonce }`.

### POST /api/sessions/join
```json
{ "join_token": "..." }
```
Accepts the QR token. Returns the updated session and adds caller as guest. Session moves to `ready_to_lock`.

### POST /api/sessions/{uuid}/confirm-lock
Marks the caller's participant as lock-confirmed. When both participants confirmed, session transitions to `active` and `started_at` is set.

### POST /api/sessions/{uuid}/heartbeat
```json
{
  "device_uuid": "...",
  "vpn_active": true,
  "dnd_active": true,
  "notification_suppression_active": true,
  "network_block_active": true,
  "app_version": "1.0.0",
  "platform": "ios",
  "client_timestamp": "2026-04-29T12:00:00Z"
}
```
- For iOS, `dnd_active` may be `null`.
- If `vpn_active=false` or `network_block_active=false` while session is `active`, session is failed immediately with `failure_reason=protection_disabled`.
- If `device_uuid` does not match participant's registered active device, session fails with `failure_reason=device_mismatch`.

### POST /api/sessions/{uuid}/request-end
Marks the caller as end-requested. Other participant is notified via broadcast.

### POST /api/sessions/{uuid}/confirm-end
Marks the caller's `end_confirmed_at`. When both participants confirmed end while `active` and no failure occurred, session transitions to `success`, `ended_at` is set, stats and feed item are updated.

### POST /api/sessions/{uuid}/emergency-exit
Immediately fails the session with `failure_reason=emergency_exit`, attributed to the caller.

### GET /api/sessions/{uuid}
Returns session with participants and recent events (auth user must be a participant).

### GET /api/profile/stats
Returns the authed user's stats row.

## Social

### POST /api/contacts/sync
```json
{ "hashes": ["sha256(normalized_phone_or_email)", "..."] }
```
Stores hashed contact identifiers (one row per upload). Returns `{ "imported": <count>, "matches": [{user_id, name, matched_hash}, ...] }`.

### GET /api/contacts/matches
Returns matches found among the most-recent upload.

### POST /api/friends/request
```json
{ "user_id": 42 }
```
Creates a `pending` friendship row (requester=auth, recipient=user_id). Idempotent.

### POST /api/friends/{id}/accept
Accepts a pending friendship where the auth user is the recipient. Emits `friend_joined` feed item for both users.

### POST /api/friends/{id}/reject
Rejects a pending friendship.

### DELETE /api/friends/{id}
Removes an accepted friendship in either direction.

### GET /api/friends
Returns accepted friendships and pending incoming/outgoing requests.

### GET /api/feed
Returns feed items visible to auth user — items from accepted friends with `visibility=friends`, plus auth user's own items. Paginated.

### POST /api/profile/privacy
```json
{ "share_successes": true, "share_failures": false, "share_streaks": true, "discoverable_by_contacts": true }
```
Updates the auth user's social privacy preferences.

## Broadcasting

- Channel: `private-sessions.{uuid}` — events: `SessionJoined`, `SessionLockConfirmed`, `SessionStarted`, `SessionEndRequested`, `SessionFailed`, `SessionSucceeded`.
- Channel: `private-users.{id}` — events: `FriendRequestReceived`, `FriendRequestAccepted`, `FeedItemCreated`.

## Failure reasons enum

- `protection_disabled`
- `heartbeat_timeout`
- `device_mismatch`
- `emergency_exit`
- `permission_revoked`
- `cancelled_before_start`
