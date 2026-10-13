<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Errors\ValidationFailed;
use App\Domain\User;

/** HU-02: only librarians can be created through the API. */
final class CreateLibrarianService
{
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {
    }

    public function execute(string $username, string $password, string $role): User
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new ValidationFailed('La contraseña debe tener al menos 8 caracteres.', [['field' => 'password', 'issue' => 'mínimo 8 caracteres']]);
        }
        // Validates role/username before spending time hashing.
        $user = User::createLibrarian($this->ids->next(), $username, 'pending', $role);
        if ($this->users->findByUsername($user->username) !== null) {
            throw new UsernameTaken();
        }
        $user = User::createLibrarian($user->id, $user->username, $this->hasher->hash($password), $role);
        $this->users->add($user);

        return $user;
    }
}
