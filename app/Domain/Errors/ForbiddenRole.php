<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class ForbiddenRole extends DomainError
{
    public function __construct(string $message = 'No tiene permisos para esta operación.', array $details = [])
    {
        parent::__construct('FORBIDDEN_ROLE', $message, $details);
    }
}
