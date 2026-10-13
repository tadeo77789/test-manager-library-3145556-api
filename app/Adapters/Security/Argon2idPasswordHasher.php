<?php

declare(strict_types=1);

namespace App\Adapters\Security;

use App\Application\Ports\PasswordHasher;

final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
