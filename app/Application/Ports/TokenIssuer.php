<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\IssuedToken;
use App\Application\Dto\TokenClaims;
use App\Domain\User;

interface TokenIssuer
{
    public function issue(User $user): IssuedToken;

    /** Returns the claims of a valid, unexpired token, or null. */
    public function verify(string $token): ?TokenClaims;
}
