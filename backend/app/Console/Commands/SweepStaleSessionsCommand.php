<?php

namespace App\Console\Commands;

use App\Services\SessionIntegrityService;
use Illuminate\Console\Command;

class SweepStaleSessionsCommand extends Command
{
    protected $signature = 'sessions:sweep';

    protected $description = 'Fail any active session whose heartbeats have gone stale.';

    public function handle(SessionIntegrityService $integrity): int
    {
        $failed = $integrity->sweep();
        $this->info("Failed {$failed} stale session(s).");

        return self::SUCCESS;
    }
}
