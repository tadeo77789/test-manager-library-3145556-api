<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Domain\User;

interface UserRepository
{
    /** @throws \App\Domain\Errors\UsernameTaken */
    public function add(User $user): void;

    public function findByUsername(string $normalizedUsername): ?User;
}
