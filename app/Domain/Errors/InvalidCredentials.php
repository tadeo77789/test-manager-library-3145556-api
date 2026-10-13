<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class InvalidCredentials extends DomainError
{
    public function __construct(string $message = 'Credenciales inválidas.', array $details = [])
    {
        parent::__construct('INVALID_CREDENTIALS', $message, $details);
    }
}
