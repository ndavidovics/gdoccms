<?php

namespace Tests\Unit;

use App\Support\JoinTokenSigner;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class JoinTokenSignerTest extends TestCase
{
    public function test_round_trip(): void
    {
        $signer = new JoinTokenSigner('secret', 60);
        $token = $signer->sign('uuid-1', 7);
        $payload = $signer->verify($token);
        $this->assertSame('uuid-1', $payload['session_uuid']);
        $this->assertSame(7, $payload['host_user_id']);
    }

    public function test_tampered_signature_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $signer = new JoinTokenSigner('secret', 60);
        $token = $signer->sign('uuid-1', 7);
        // flip last char of signature
        $bad = substr($token, 0, -1).(substr($token, -1) === 'A' ? 'B' : 'A');
        $signer->verify($bad);
    }

    public function test_different_secret_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $signer = new JoinTokenSigner('secret-a', 60);
        $token = $signer->sign('uuid-1', 7);
        $other = new JoinTokenSigner('secret-b', 60);
        $other->verify($token);
    }

    public function test_expired_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Carbon::setTestNow('2026-01-01 00:00:00');
        $signer = new JoinTokenSigner('secret', 60);
        $token = $signer->sign('uuid-1', 7);
        Carbon::setTestNow('2026-01-01 00:02:00');
        $signer->verify($token);
    }
}
