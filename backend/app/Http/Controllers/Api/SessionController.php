<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Services\DeviceService;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly DeviceService $devices,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $user->activeDevice;
        abort_if(! $device, 422, 'No active device registered.');

        $payload = $this->sessions->createForHost($user, $device);

        return response()->json([
            'session' => $this->serializeSession($payload['session'], $user->id),
            'join_token' => $payload['join_token'],
            'join_qr_payload' => $payload['join_qr_payload'],
        ], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'join_token' => ['required', 'string'],
        ]);
        $user = $request->user();
        $device = $user->activeDevice;
        abort_if(! $device, 422, 'No active device registered.');

        try {
            $session = $this->sessions->joinByToken($user, $device, $data['join_token']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['session' => $this->serializeSession($session, $user->id)]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locateSessionForUser($request, $uuid);

        return response()->json(['session' => $this->serializeSession($session, $request->user()->id, withEvents: true)]);
    }

    public function confirmLock(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locateSessionForUser($request, $uuid);
        $updated = $this->sessions->confirmLock($request->user(), $session);

        return response()->json(['session' => $this->serializeSession($updated, $request->user()->id)]);
    }

    public function heartbeat(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string'],
            'vpn_active' => ['required', 'boolean'],
            'dnd_active' => ['nullable', 'boolean'],
            'notification_suppression_active' => ['nullable', 'boolean'],
            'network_block_active' => ['required', 'boolean'],
            'app_version' => ['required', 'string'],
            'platform' => ['required', 'in:ios,android'],
            'client_timestamp' => ['required', 'date'],
        ]);

        $user = $request->user();
        $device = $this->devices->assertActiveDevice($user, $data['device_uuid']);
        $session = $this->locateSessionForUser($request, $uuid);
        $updated = $this->sessions->recordHeartbeat($user, $session, $device, $data);

        return response()->json(['session' => $this->serializeSession($updated, $user->id)]);
    }

    public function requestEnd(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locateSessionForUser($request, $uuid);
        $updated = $this->sessions->requestEnd($request->user(), $session);

        return response()->json(['session' => $this->serializeSession($updated, $request->user()->id)]);
    }

    public function confirmEnd(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locateSessionForUser($request, $uuid);
        $updated = $this->sessions->confirmEnd($request->user(), $session);

        return response()->json(['session' => $this->serializeSession($updated, $request->user()->id)]);
    }

    public function emergencyExit(Request $request, string $uuid): JsonResponse
    {
        $session = $this->locateSessionForUser($request, $uuid);
        $updated = $this->sessions->emergencyExit($request->user(), $session);

        return response()->json(['session' => $this->serializeSession($updated, $request->user()->id)]);
    }

    private function locateSessionForUser(Request $request, string $uuid): Session
    {
        $session = Session::where('uuid', $uuid)->with('participants.user')->firstOrFail();
        $belongsToUser = $session->participants->contains(fn ($p) => $p->user_id === $request->user()->id);
        abort_unless($belongsToUser, 403, 'You are not a participant in this session.');

        return $session;
    }

    private function serializeSession(Session $session, int $forUserId, bool $withEvents = false): array
    {
        $session->loadMissing('participants.user');
        $data = [
            'uuid' => $session->uuid,
            'state' => $session->state,
            'host_user_id' => $session->host_user_id,
            'started_at' => optional($session->started_at)->toIso8601String(),
            'ended_at' => optional($session->ended_at)->toIso8601String(),
            'failed_at' => optional($session->failed_at)->toIso8601String(),
            'failure_reason' => $session->failure_reason,
            'failed_by_user_id' => $session->failed_by_user_id,
            'end_requested_by_user_id' => $session->end_requested_by_user_id,
            'duration_seconds' => $session->durationSeconds(),
            'participants' => $session->participants->map(fn ($p) => [
                'user_id' => $p->user_id,
                'user_name' => $p->user?->name,
                'role' => $p->role,
                'lock_confirmed' => (bool) $p->lock_confirmed_at,
                'end_confirmed' => (bool) $p->end_confirmed_at,
                'protection_active' => $p->protection_active,
                'last_heartbeat_at' => optional($p->last_heartbeat_at)->toIso8601String(),
                'is_self' => $p->user_id === $forUserId,
            ])->all(),
        ];
        if ($withEvents) {
            $data['recent_events'] = $session->events()->limit(20)->get()->map(fn ($e) => [
                'type' => $e->type,
                'user_id' => $e->user_id,
                'payload' => $e->payload,
                'created_at' => optional($e->created_at)->toIso8601String(),
            ])->all();
        }

        return $data;
    }
}
