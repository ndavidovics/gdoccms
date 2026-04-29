<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Session;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixtureUserWithDevice(string $name, string $platform = 'ios'): array
    {
        $email = strtolower($name).'@example.com';
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret-password',
            'phone_e164' => '+1555000'.random_int(1000, 9999),
        ]);
        UserStats::create(['user_id' => $user->id]);
        $device = Device::create([
            'user_id' => $user->id,
            'device_uuid' => 'd-'.strtolower($name),
            'platform' => $platform,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        return [$user, $device, $token];
    }

    private function authed(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    private function happyPathHeartbeat(Device $device, bool $vpn = true, bool $netBlock = true): array
    {
        return [
            'device_uuid' => $device->device_uuid,
            'vpn_active' => $vpn,
            'dnd_active' => true,
            'notification_suppression_active' => true,
            'network_block_active' => $netBlock,
            'app_version' => '1.0.0',
            'platform' => $device->platform,
            'client_timestamp' => now()->toIso8601String(),
        ];
    }

    public function test_full_happy_path_two_users_succeed(): void
    {
        [$alex, $alexDevice, $alexToken] = $this->fixtureUserWithDevice('Alex', 'ios');
        [$jamie, $jamieDevice, $jamieToken] = $this->fixtureUserWithDevice('Jamie', 'android');

        $create = $this->postJson('/api/sessions', [], $this->authed($alexToken))->assertCreated();
        $uuid = $create->json('session.uuid');
        $token = $create->json('join_token');
        $this->assertSame('pending_second_user', $create->json('session.state'));

        $this->postJson('/api/sessions/join', ['join_token' => $token], $this->authed($jamieToken))
            ->assertOk()
            ->assertJsonPath('session.state', 'ready_to_lock');

        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($alexToken))
            ->assertOk()
            ->assertJsonPath('session.state', 'ready_to_lock');

        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($jamieToken))
            ->assertOk()
            ->assertJsonPath('session.state', 'active');

        // Heartbeats keep it alive.
        $this->postJson("/api/sessions/{$uuid}/heartbeat", $this->happyPathHeartbeat($alexDevice), $this->authed($alexToken))->assertOk();
        $this->postJson("/api/sessions/{$uuid}/heartbeat", $this->happyPathHeartbeat($jamieDevice), $this->authed($jamieToken))->assertOk();

        // Both confirm end -> success.
        $this->postJson("/api/sessions/{$uuid}/request-end", [], $this->authed($alexToken))->assertOk();
        $this->postJson("/api/sessions/{$uuid}/confirm-end", [], $this->authed($alexToken))->assertOk();
        $this->postJson("/api/sessions/{$uuid}/confirm-end", [], $this->authed($jamieToken))
            ->assertOk()
            ->assertJsonPath('session.state', 'success');

        $this->assertSame(1, UserStats::where('user_id', $alex->id)->value('successful_session_count'));
        $this->assertSame(1, UserStats::where('user_id', $jamie->id)->value('successful_session_count'));
    }

    public function test_protection_inactive_heartbeat_fails_session(): void
    {
        [$alex, $alexDevice, $alexToken] = $this->fixtureUserWithDevice('Alex', 'ios');
        [$jamie, $jamieDevice, $jamieToken] = $this->fixtureUserWithDevice('Jamie', 'android');

        $create = $this->postJson('/api/sessions', [], $this->authed($alexToken))->assertCreated();
        $uuid = $create->json('session.uuid');
        $token = $create->json('join_token');
        $this->postJson('/api/sessions/join', ['join_token' => $token], $this->authed($jamieToken))->assertOk();
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($alexToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($jamieToken));

        // Jamie reports VPN inactive -> fail.
        $resp = $this->postJson(
            "/api/sessions/{$uuid}/heartbeat",
            $this->happyPathHeartbeat($jamieDevice, vpn: false),
            $this->authed($jamieToken)
        )->assertOk();

        $this->assertSame('failed', $resp->json('session.state'));
        $this->assertSame('protection_disabled', $resp->json('session.failure_reason'));
        $this->assertSame($jamie->id, $resp->json('session.failed_by_user_id'));

        $this->assertSame(0, UserStats::where('user_id', $alex->id)->value('successful_session_count'));
        $this->assertSame(1, UserStats::where('user_id', $alex->id)->value('failed_session_count'));
    }

    public function test_device_mismatch_on_heartbeat_fails_session(): void
    {
        [$alex, $alexDevice, $alexToken] = $this->fixtureUserWithDevice('Alex', 'ios');
        [$jamie, $jamieDevice, $jamieToken] = $this->fixtureUserWithDevice('Jamie', 'android');

        $create = $this->postJson('/api/sessions', [], $this->authed($alexToken))->assertCreated();
        $uuid = $create->json('session.uuid');
        $token = $create->json('join_token');
        $this->postJson('/api/sessions/join', ['join_token' => $token], $this->authed($jamieToken))->assertOk();
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($alexToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($jamieToken));

        // Alex re-registers a *new* device — old one is now revoked by DeviceService.
        $this->postJson('/api/devices/register', [
            'device_uuid' => 'd-alex-new',
            'platform' => 'ios',
            'app_version' => '1.0.0',
        ], $this->authed($alexToken))->assertCreated();

        // Heartbeat with the OLD device_uuid -> 403 (no longer the active device).
        $this->postJson(
            "/api/sessions/{$uuid}/heartbeat",
            $this->happyPathHeartbeat($alexDevice),
            $this->authed($alexToken)
        )->assertForbidden();

        // Heartbeat with the NEW device_uuid -> device_id no longer matches participant.device_id -> fail.
        $newDevice = Device::where('device_uuid', 'd-alex-new')->first();
        $resp = $this->postJson(
            "/api/sessions/{$uuid}/heartbeat",
            $this->happyPathHeartbeat($newDevice),
            $this->authed($alexToken)
        )->assertOk();

        $this->assertSame('failed', $resp->json('session.state'));
        $this->assertSame('device_mismatch', $resp->json('session.failure_reason'));
    }

    public function test_emergency_exit_fails_session(): void
    {
        [$alex, $alexDevice, $alexToken] = $this->fixtureUserWithDevice('Alex', 'ios');
        [$jamie, $jamieDevice, $jamieToken] = $this->fixtureUserWithDevice('Jamie', 'android');

        $create = $this->postJson('/api/sessions', [], $this->authed($alexToken))->assertCreated();
        $uuid = $create->json('session.uuid');
        $token = $create->json('join_token');
        $this->postJson('/api/sessions/join', ['join_token' => $token], $this->authed($jamieToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($alexToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($jamieToken));

        $resp = $this->postJson("/api/sessions/{$uuid}/emergency-exit", [], $this->authed($alexToken))->assertOk();
        $this->assertSame('failed', $resp->json('session.state'));
        $this->assertSame('emergency_exit', $resp->json('session.failure_reason'));
    }

    public function test_heartbeat_timeout_sweep_fails_session(): void
    {
        [$alex, $alexDevice, $alexToken] = $this->fixtureUserWithDevice('Alex', 'ios');
        [$jamie, $jamieDevice, $jamieToken] = $this->fixtureUserWithDevice('Jamie', 'android');

        $create = $this->postJson('/api/sessions', [], $this->authed($alexToken))->assertCreated();
        $uuid = $create->json('session.uuid');
        $token = $create->json('join_token');
        $this->postJson('/api/sessions/join', ['join_token' => $token], $this->authed($jamieToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($alexToken));
        $this->postJson("/api/sessions/{$uuid}/confirm-lock", [], $this->authed($jamieToken));

        // Backdate Alex's heartbeat to 60s ago (> 45s timeout).
        $session = Session::where('uuid', $uuid)->firstOrFail();
        $session->participants()->where('user_id', $alex->id)->update(['last_heartbeat_at' => now()->subSeconds(60)]);
        $session->participants()->where('user_id', $jamie->id)->update(['last_heartbeat_at' => now()]);

        $this->artisan('sessions:sweep')->assertSuccessful();

        $session->refresh();
        $this->assertSame('failed', $session->state);
        $this->assertSame('heartbeat_timeout', $session->failure_reason);
    }

    public function test_user_cannot_create_session_without_active_device(): void
    {
        $user = User::create([
            'name' => 'NoDevice',
            'email' => 'nd@example.com',
            'password' => 'pw-pw-pw-pw',
        ]);
        UserStats::create(['user_id' => $user->id]);
        $token = $user->createToken('t')->plainTextToken;

        $this->postJson('/api/sessions', [], $this->authed($token))
            ->assertStatus(422);
    }

    public function test_one_active_device_per_user_enforced(): void
    {
        $user = User::create([
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'pw-pw-pw-pw',
        ]);
        UserStats::create(['user_id' => $user->id]);
        $token = $user->createToken('t')->plainTextToken;

        $this->postJson('/api/devices/register', [
            'device_uuid' => 'first',
            'platform' => 'ios',
        ], $this->authed($token))->assertCreated();

        $this->postJson('/api/devices/register', [
            'device_uuid' => 'second',
            'platform' => 'ios',
        ], $this->authed($token))->assertCreated();

        $active = $user->devices()->where('is_active', true)->get();
        $this->assertCount(1, $active);
        $this->assertSame('second', $active->first()->device_uuid);
    }
}
