<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\User;

/** T-17: creates the initial admin from the deployment environment if it does not exist yet (idempotent). */
final class EnsureAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    /** @return bool true when the admin was created, false when it already existed */
    public function execute(string $username, string $password): bool
    {
        $normalized = User::normalizeUsername($username);
        if ($this->users->findByUsername($normalized) !== null) {
            return false;
        }
        $this->users->add(User::createAdmin($this->ids->next(), $normalized, $this->hasher->hash($password)));

        return true;
    }
}
