<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\IssuedToken;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\Errors\InvalidCredentials;

/** HU-01: the error never says whether the username or the password failed (CA-01.2). */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    public function execute(string $username, string $password): IssuedToken
    {
        $user = $this->users->findByUsername(mb_strtolower(trim($username)));
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials();
        }
        return $this->tokens->issue($user);
    }
}
