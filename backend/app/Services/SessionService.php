<?php

namespace App\Services;

use App\Events\SessionCreated;
use App\Events\SessionEndRequested;
use App\Events\SessionFailed;
use App\Events\SessionJoined;
use App\Events\SessionLockConfirmed;
use App\Events\SessionStarted;
use App\Events\SessionSucceeded;
use App\Models\Device;
use App\Models\Session;
use App\Models\SessionEvent;
use App\Models\SessionParticipant;
use App\Models\User;
use App\Support\JoinTokenSigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SessionService
{
    public function __construct(
        private readonly JoinTokenSigner $signer,
        private readonly StatsService $stats,
        private readonly SocialService $social,
        private readonly HeartbeatTracker $heartbeats,
    ) {}

    public function createForHost(User $host, Device $hostDevice): array
    {
        return DB::transaction(function () use ($host, $hostDevice) {
            $session = Session::create([
                'uuid' => (string) Str::uuid(),
                'state' => Session::STATE_PENDING_SECOND_USER,
                'host_user_id' => $host->id,
            ]);

            SessionParticipant::create([
                'session_id' => $session->id,
                'user_id' => $host->id,
                'device_id' => $hostDevice->id,
                'role' => SessionParticipant::ROLE_HOST,
            ]);

            $this->logEvent($session, $host, 'session.created');

            $token = $this->signer->sign($session->uuid, $host->id);
            event(new SessionCreated($session->fresh('participants')));

            return [
                'session' => $session->fresh('participants'),
                'join_token' => $token,
                'join_qr_payload' => 'mos://join?t='.$token,
            ];
        });
    }

    public function joinByToken(User $guest, Device $guestDevice, string $token): Session
    {
        $payload = $this->signer->verify($token);

        return DB::transaction(function () use ($guest, $guestDevice, $payload) {
            /** @var Session $session */
            $session = Session::where('uuid', $payload['session_uuid'])->lockForUpdate()->firstOrFail();

            if ($session->state !== Session::STATE_PENDING_SECOND_USER) {
                abort(409, 'Session is not accepting joins.');
            }
            if ($session->host_user_id === $guest->id) {
                abort(422, 'Host cannot join their own session as guest.');
            }
            if ($session->participants()->count() >= 2) {
                abort(409, 'Session is full.');
            }

            SessionParticipant::create([
                'session_id' => $session->id,
                'user_id' => $guest->id,
                'device_id' => $guestDevice->id,
                'role' => SessionParticipant::ROLE_GUEST,
            ]);

            $session->fill(['state' => Session::STATE_READY_TO_LOCK])->save();
            $this->logEvent($session, $guest, 'session.joined');
            event(new SessionJoined($session->fresh('participants')));

            return $session->fresh('participants');
        });
    }

    public function confirmLock(User $user, Session $session): Session
    {
        return DB::transaction(function () use ($user, $session) {
            /** @var Session $session */
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($session->state, [Session::STATE_READY_TO_LOCK, Session::STATE_PENDING_SECOND_USER], true),
                409,
                'Cannot confirm lock in state '.$session->state
            );

            $participant = $session->participants()->where('user_id', $user->id)->firstOrFail();
            $participant->fill(['lock_confirmed_at' => $participant->lock_confirmed_at ?? now()])->save();
            $this->logEvent($session, $user, 'session.lock_confirmed');
            event(new SessionLockConfirmed($session->fresh('participants'), $user->id));

            $allConfirmed = $session->participants()->whereNull('lock_confirmed_at')->doesntExist();
            $bothPresent = $session->participants()->count() === 2;

            if ($allConfirmed && $bothPresent && $session->state === Session::STATE_READY_TO_LOCK) {
                $session->fill([
                    'state' => Session::STATE_ACTIVE,
                    'started_at' => now(),
                ])->save();
                $this->logEvent($session, null, 'session.started');
                $this->heartbeats->primeAll($session->fresh('participants'));
                event(new SessionStarted($session->fresh('participants')));
            }

            return $session->fresh('participants');
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function recordHeartbeat(User $user, Session $session, Device $device, array $payload): Session
    {
        return DB::transaction(function () use ($user, $session, $device, $payload) {
            /** @var Session $session */
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();

            $participant = $session->participants()->where('user_id', $user->id)->firstOrFail();

            // Device mismatch -> immediate fail.
            if ($participant->device_id !== $device->id) {
                return $this->failSession(
                    $session,
                    Session::FAILURE_DEVICE_MISMATCH,
                    failedByUserId: $user->id,
                    extra: ['expected_device_id' => $participant->device_id, 'actual_device_id' => $device->id],
                );
            }

            $protectionActive = (bool) ($payload['vpn_active'] ?? false)
                && (bool) ($payload['network_block_active'] ?? false);

            $participant->fill([
                'last_heartbeat_at' => now(),
                'last_integrity_state' => $payload,
                'protection_active' => $protectionActive,
            ])->save();

            $this->heartbeats->touch($session, $participant);

            // Only enforce protection requirement once session is active.
            if ($session->state === Session::STATE_ACTIVE && ! $protectionActive) {
                return $this->failSession(
                    $session,
                    Session::FAILURE_PROTECTION_DISABLED,
                    failedByUserId: $user->id,
                    extra: ['integrity' => $payload],
                );
            }

            return $session->fresh('participants');
        });
    }

    public function requestEnd(User $user, Session $session): Session
    {
        return DB::transaction(function () use ($user, $session) {
            /** @var Session $session */
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->state === Session::STATE_ACTIVE, 409, 'Session is not active.');

            if (! $session->end_requested_by_user_id) {
                $session->fill(['end_requested_by_user_id' => $user->id])->save();
                $this->logEvent($session, $user, 'session.end_requested');
            }
            event(new SessionEndRequested($session->fresh('participants'), $user->id));

            return $session->fresh('participants');
        });
    }

    public function confirmEnd(User $user, Session $session): Session
    {
        return DB::transaction(function () use ($user, $session) {
            /** @var Session $session */
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Allow either ACTIVE (peer hasn't confirmed yet) — fail otherwise.
            abort_unless($session->state === Session::STATE_ACTIVE, 409, 'Session is not active.');

            $participant = $session->participants()->where('user_id', $user->id)->firstOrFail();
            $participant->fill(['end_confirmed_at' => $participant->end_confirmed_at ?? now()])->save();
            $this->logEvent($session, $user, 'session.end_confirmed');

            $allConfirmed = $session->participants()->count() === 2
                && $session->participants()->whereNull('end_confirmed_at')->doesntExist();

            if ($allConfirmed) {
                $session->fill([
                    'state' => Session::STATE_SUCCESS,
                    'ended_at' => now(),
                ])->save();
                $this->heartbeats->clear($session);
                $this->logEvent($session, null, 'session.succeeded');

                $session->loadMissing('participants.user');
                $this->stats->recordSuccess($session);
                $this->social->onSessionSucceeded($session);

                event(new SessionSucceeded($session->fresh('participants')));
            }

            return $session->fresh('participants');
        });
    }

    public function emergencyExit(User $user, Session $session): Session
    {
        return DB::transaction(function () use ($user, $session) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->isTerminal()) {
                return $session;
            }

            return $this->failSession(
                $session,
                Session::FAILURE_EMERGENCY_EXIT,
                failedByUserId: $user->id,
            );
        });
    }

    /**
     * Public wrapper used by the watchdog command.
     */
    public function failForReason(Session $session, string $reason, ?int $failedByUserId = null, array $extra = []): Session
    {
        return DB::transaction(function () use ($session, $reason, $failedByUserId, $extra) {
            $session = Session::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->isTerminal()) {
                return $session;
            }

            return $this->failSession($session, $reason, $failedByUserId, $extra);
        });
    }

    /**
     * INTERNAL: must be called inside a transaction.
     */
    private function failSession(Session $session, string $reason, ?int $failedByUserId = null, array $extra = []): Session
    {
        if ($session->isTerminal()) {
            throw new RuntimeException('Session already terminal: '.$session->state);
        }

        $now = now();
        $session->fill([
            'state' => Session::STATE_FAILED,
            'failed_at' => $now,
            'failure_reason' => $reason,
            'failed_by_user_id' => $failedByUserId,
            'ended_at' => $session->ended_at ?? $now,
        ])->save();

        $this->heartbeats->clear($session);
        $this->logEvent($session, null, 'session.failed', ['reason' => $reason] + $extra);

        $session->loadMissing('participants.user');
        $this->stats->recordFailure($session);
        $this->social->onSessionFailed($session);

        event(new SessionFailed($session->fresh('participants'), $reason));

        return $session->fresh('participants');
    }

    private function logEvent(Session $session, ?User $user, string $type, array $payload = []): void
    {
        SessionEvent::create([
            'session_id' => $session->id,
            'user_id' => $user?->id,
            'type' => $type,
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }
}
