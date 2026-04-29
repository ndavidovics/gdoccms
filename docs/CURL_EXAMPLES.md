# Mutual Offline Session — curl reference

Run the backend locally on port 8000, then:

```bash
BASE=http://localhost:8000

# Register Alex
ALEX=$(curl -s -X POST $BASE/api/register -H 'Content-Type: application/json' -d '{
  "name":"Alex","email":"alex@example.com",
  "password":"password","password_confirmation":"password"
}' | jq -r '.token')

JAMIE=$(curl -s -X POST $BASE/api/register -H 'Content-Type: application/json' -d '{
  "name":"Jamie","email":"jamie@example.com",
  "password":"password","password_confirmation":"password"
}' | jq -r '.token')

# Register devices
curl -s -X POST $BASE/api/devices/register -H "Authorization: Bearer $ALEX" -H 'Content-Type: application/json' \
  -d '{"device_uuid":"alex-ios","platform":"ios","device_name":"Alex iPhone","app_version":"1.0.0"}' | jq

curl -s -X POST $BASE/api/devices/register -H "Authorization: Bearer $JAMIE" -H 'Content-Type: application/json' \
  -d '{"device_uuid":"jamie-android","platform":"android","device_name":"Jamie Pixel","app_version":"1.0.0"}' | jq

# Host creates session
CREATE=$(curl -s -X POST $BASE/api/sessions -H "Authorization: Bearer $ALEX" -H 'Accept: application/json')
UUID=$(echo $CREATE | jq -r '.session.uuid')
TOKEN=$(echo $CREATE | jq -r '.join_token')
echo "Session $UUID join token $TOKEN"

# Guest joins
curl -s -X POST $BASE/api/sessions/join -H "Authorization: Bearer $JAMIE" -H 'Content-Type: application/json' \
  -d "{\"join_token\":\"$TOKEN\"}" | jq

# Both confirm lock
curl -s -X POST $BASE/api/sessions/$UUID/confirm-lock -H "Authorization: Bearer $ALEX" | jq
curl -s -X POST $BASE/api/sessions/$UUID/confirm-lock -H "Authorization: Bearer $JAMIE" | jq
# -> session.state == "active"

# Heartbeat (every 10s in real app)
HB() { local TOKEN=$1; local UUID=$2; local DEV=$3; local PLAT=$4; local VPN=${5:-true};
  curl -s -X POST $BASE/api/sessions/$UUID/heartbeat -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
    -d "{
      \"device_uuid\":\"$DEV\",\"vpn_active\":$VPN,\"dnd_active\":true,
      \"notification_suppression_active\":true,\"network_block_active\":$VPN,
      \"app_version\":\"1.0.0\",\"platform\":\"$PLAT\",
      \"client_timestamp\":\"$(date -u +%FT%TZ)\"
    }"
}
HB $ALEX $UUID alex-ios ios | jq
HB $JAMIE $UUID jamie-android android | jq

# End mutually
curl -s -X POST $BASE/api/sessions/$UUID/request-end -H "Authorization: Bearer $ALEX" | jq
curl -s -X POST $BASE/api/sessions/$UUID/confirm-end -H "Authorization: Bearer $ALEX" | jq
curl -s -X POST $BASE/api/sessions/$UUID/confirm-end -H "Authorization: Bearer $JAMIE" | jq
# -> session.state == "success"

# Stats
curl -s $BASE/api/profile/stats -H "Authorization: Bearer $ALEX" | jq

# Or simulate a failure: report VPN inactive
HB $JAMIE $UUID jamie-android android false | jq   # state -> "failed", reason "protection_disabled"
```

## Social

```bash
# Hash phone numbers locally then upload
H1=$(printf '+15551110002' | shasum -a 256 | cut -d' ' -f1)
H2=$(printf '+15559999999' | shasum -a 256 | cut -d' ' -f1)
curl -s -X POST $BASE/api/contacts/sync -H "Authorization: Bearer $ALEX" -H 'Content-Type: application/json' \
  -d "{\"hashes\":[\"$H1\",\"$H2\"]}" | jq

# Friend request flow
JAMIE_ID=$(curl -s $BASE/api/me -H "Authorization: Bearer $JAMIE" | jq '.user.id')
F=$(curl -s -X POST $BASE/api/friends/request -H "Authorization: Bearer $ALEX" -H 'Content-Type: application/json' \
  -d "{\"user_id\":$JAMIE_ID}" | jq '.friendship.id')
curl -s -X POST $BASE/api/friends/$F/accept -H "Authorization: Bearer $JAMIE" | jq

# Feed
curl -s $BASE/api/feed -H "Authorization: Bearer $ALEX" | jq
```
