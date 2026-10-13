<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class TokenClaims
{
    public function __construct(public string $userId, public string $role)
    {
    }
}
