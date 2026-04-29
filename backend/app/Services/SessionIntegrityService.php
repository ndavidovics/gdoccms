<?php

namespace App\Services;

use App\Models\Session;

/**
 * Watchdog: scan all ACTIVE sessions and fail any whose participants have not
 * sent a heartbeat within HEARTBEAT_TIMEOUT_SECONDS, or whose participants
 * report protection inactive on their stored last_integrity_state.
 */
class SessionIntegrityService
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly HeartbeatTracker $heartbeats,
    ) {}

    public function sweep(): int
    {
        $failed = 0;
        $cutoff = now()->subSeconds(HeartbeatTracker::timeoutSeconds());

        Session::where('state', Session::STATE_ACTIVE)
            ->with('participants')
            ->chunkById(100, function ($sessions) use (&$failed, $cutoff) {
                foreach ($sessions as $session) {
                    foreach ($session->participants as $p) {
                        $stale = ! $p->last_heartbeat_at || $p->last_heartbeat_at->lt($cutoff);
                        if ($stale) {
                            $this->sessions->failForReason(
                                $session,
                                Session::FAILURE_HEARTBEAT_TIMEOUT,
                                failedByUserId: $p->user_id,
                                extra: ['last_heartbeat_at' => optional($p->last_heartbeat_at)->toIso8601String()],
                            );
                            $failed++;
                            break;
                        }
                    }
                }
            });

        return $failed;
    }
}
