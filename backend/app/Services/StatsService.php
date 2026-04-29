<?php

namespace App\Services;

use App\Models\Session;
use App\Models\UserStats;

class StatsService
{
    public function recordSuccess(Session $session): void
    {
        $duration = $session->durationSeconds();
        foreach ($session->participants as $p) {
            $stats = UserStats::firstOrCreate(['user_id' => $p->user_id]);

            $previousBest = $stats->best_session_seconds;
            $previousLastSuccessAt = $stats->last_success_at;

            $stats->total_success_seconds += $duration;
            $stats->successful_session_count++;
            if ($duration > $previousBest) {
                $stats->best_session_seconds = $duration;
            }

            // Streak: consecutive calendar days with at least one success.
            if ($previousLastSuccessAt && $previousLastSuccessAt->isSameDay(now())) {
                // already counted today; no streak change
            } elseif ($previousLastSuccessAt && $previousLastSuccessAt->copy()->addDay()->isSameDay(now())) {
                $stats->current_streak_count++;
            } else {
                $stats->current_streak_count = 1;
            }
            if ($stats->current_streak_count > $stats->longest_streak_count) {
                $stats->longest_streak_count = $stats->current_streak_count;
            }

            $stats->last_success_at = now();
            $stats->save();
        }
    }

    public function recordFailure(Session $session): void
    {
        foreach ($session->participants as $p) {
            $stats = UserStats::firstOrCreate(['user_id' => $p->user_id]);
            $stats->failed_session_count++;
            $stats->current_streak_count = 0;
            $stats->save();
        }
    }
}
