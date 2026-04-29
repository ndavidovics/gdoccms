<?php

namespace App\Services;

use App\Models\Session;
use App\Models\SessionParticipant;
use Illuminate\Support\Facades\Redis;

/**
 * Per-participant Redis TTL keys: session:{uuid}:hb:{user_id}.
 * Set with EX = HEARTBEAT_TIMEOUT_SECONDS on every received heartbeat. The
 * watchdog command scans active sessions and asks "are all participants'
 * heartbeat keys present?". Missing key -> failure.
 *
 * Redis here is purely an optimization for fast TTL lookups; the source of
 * truth is `session_participants.last_heartbeat_at`.
 */
class HeartbeatTracker
{
    public function __construct(private readonly int $timeoutSeconds = 45) {}

    public static function timeoutSeconds(): int
    {
        return (int) config('mutualoffline.heartbeat_timeout_seconds', 45);
    }

    public function primeAll(Session $session): void
    {
        foreach ($session->participants as $p) {
            $this->touch($session, $p);
        }
    }

    public function touch(Session $session, SessionParticipant $participant): void
    {
        Redis::setex($this->key($session, $participant->user_id), self::timeoutSeconds(), (string) now()->timestamp);
    }

    public function isAlive(Session $session, int $userId): bool
    {
        return (bool) Redis::exists($this->key($session, $userId));
    }

    public function clear(Session $session): void
    {
        foreach ($session->participants as $p) {
            Redis::del($this->key($session, $p->user_id));
        }
    }

    private function key(Session $session, int $userId): string
    {
        return "session:{$session->uuid}:hb:{$userId}";
    }
}
