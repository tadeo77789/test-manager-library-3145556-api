<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class Unauthenticated extends DomainError
{
    public function __construct(string $message = 'Autenticación requerida o token inválido.', array $details = [])
    {
        parent::__construct('UNAUTHENTICATED', $message, $details);
    }
}
