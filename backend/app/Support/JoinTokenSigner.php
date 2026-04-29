<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Compact signed-token format: base64url(payload).base64url(hmac).
 * Payload includes session_uuid, host_user_id, exp, nonce.
 *
 * Tokens are short-lived (default 5 minutes) and single-purpose: joining a
 * specific session. Rotating JOIN_TOKEN_SECRET invalidates all outstanding QRs.
 */
class JoinTokenSigner
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 300,
    ) {
        if ($secret === '' || $secret === 'change-me-in-production') {
            // Allow it in non-production but make it loud.
            if (app()->environment('production')) {
                throw new RuntimeException('JOIN_TOKEN_SECRET must be configured in production.');
            }
        }
    }

    public function sign(string $sessionUuid, int $hostUserId): string
    {
        $payload = [
            'session_uuid' => $sessionUuid,
            'host_user_id' => $hostUserId,
            'exp' => now()->addSeconds($this->ttlSeconds)->timestamp,
            'nonce' => Str::random(16),
        ];
        $body = $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $sig = $this->b64(hash_hmac('sha256', $body, $this->secret, true));

        return $body.'.'.$sig;
    }

    /**
     * @return array{session_uuid:string, host_user_id:int, exp:int, nonce:string}
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Malformed join token.');
        }
        [$body, $sig] = $parts;
        $expected = $this->b64(hash_hmac('sha256', $body, $this->secret, true));
        if (! hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('Invalid join token signature.');
        }
        $payload = json_decode($this->ub64($body), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ! isset($payload['session_uuid'], $payload['host_user_id'], $payload['exp'])) {
            throw new InvalidArgumentException('Invalid join token payload.');
        }
        if ($payload['exp'] < now()->timestamp) {
            throw new InvalidArgumentException('Join token expired.');
        }

        return $payload;
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function ub64(string $b64): string
    {
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($b64, '-_', '+/'), true) ?: '';
    }
}
