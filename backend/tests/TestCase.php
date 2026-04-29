<?php

namespace Tests;

use App\Services\HeartbeatTracker;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Replace HeartbeatTracker with a no-op so tests don't depend on Redis.
        $this->app->singleton(HeartbeatTracker::class, function () {
            $m = Mockery::mock(HeartbeatTracker::class)->shouldIgnoreMissing();
            $m->shouldReceive('isAlive')->andReturn(true)->byDefault();

            return $m;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
