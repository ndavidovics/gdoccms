<?php

namespace App\Providers;

use App\Services\HeartbeatTracker;
use App\Support\JoinTokenSigner;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JoinTokenSigner::class, function () {
            return new JoinTokenSigner(
                secret: (string) config('mutualoffline.join_token_secret'),
                ttlSeconds: (int) config('mutualoffline.join_token_ttl_seconds', 300),
            );
        });

        $this->app->singleton(HeartbeatTracker::class, function () {
            return new HeartbeatTracker(
                timeoutSeconds: (int) config('mutualoffline.heartbeat_timeout_seconds', 45),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
