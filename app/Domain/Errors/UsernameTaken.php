<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class UsernameTaken extends DomainError
{
    public function __construct(string $message = 'El nombre de usuario ya existe.', array $details = [])
    {
        parent::__construct('USERNAME_TAKEN', $message, $details);
    }
}
