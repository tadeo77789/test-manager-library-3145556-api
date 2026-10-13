<?php

declare(strict_types=1);

namespace App\Adapters\Security;

use App\Application\Dto\IssuedToken;
use App\Application\Dto\TokenClaims;
use App\Application\Ports\Clock;
use App\Application\Ports\TokenIssuer;
use App\Domain\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/** HS256 JWT, 8 hours, claims: sub (user id) and role only. */
final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly Clock $clock,
        private readonly int $ttlSeconds = 28800,
    ) {
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be set and at least 32 bytes long.');
        }
    }

    public function issue(User $user): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $jwt = JWT::encode(
            ['sub' => $user->id, 'role' => $user->role, 'iat' => $now, 'exp' => $now + $this->ttlSeconds],
            $this->secret,
            'HS256',
        );

        return new IssuedToken($jwt, $this->ttlSeconds, $user->role);
    }

    public function verify(string $token): ?TokenClaims
    {
        try {
            JWT::$timestamp = $this->clock->now()->getTimestamp();
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = null;
        }
        if (! isset($claims->sub, $claims->role) || ! is_string($claims->sub) || ! is_string($claims->role)) {
            return null;
        }

        return new TokenClaims($claims->sub, $claims->role);
    }
}
