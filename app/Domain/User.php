<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Errors\ValidationFailed;

final readonly class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_LIBRARIAN = 'librarian';

    public function __construct(
        public string $id,
        public string $username,
        public string $passwordHash,
        public string $role,
    ) {
        if (! in_array($role, [self::ROLE_ADMIN, self::ROLE_LIBRARIAN], true)) {
            throw new ValidationFailed('Rol inválido.', [['field' => 'role', 'issue' => 'debe ser admin o librarian']]);
        }
    }

    public static function normalizeUsername(string $raw): string
    {
        $name = mb_strtolower(trim($raw));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationFailed('El nombre de usuario es obligatorio (máximo 120 caracteres).', [['field' => 'username', 'issue' => 'obligatorio, máximo 120 caracteres']]);
        }
        return $name;
    }

    /** Public API path: only librarians can be requested at runtime (CA-02.3). */
    public static function createLibrarian(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== self::ROLE_LIBRARIAN) {
            throw new ValidationFailed('Solo se puede crear el rol librarian.', [['field' => 'role', 'issue' => 'solo se admite librarian']]);
        }
        return new self($id, self::normalizeUsername($username), $passwordHash, self::ROLE_LIBRARIAN);
    }

    /** Provisioned by the deployment from environment variables; never reachable through the API. */
    public static function createAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, self::normalizeUsername($username), $passwordHash, self::ROLE_ADMIN);
    }
}
