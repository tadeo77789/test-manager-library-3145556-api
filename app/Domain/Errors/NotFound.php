<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class NotFound extends DomainError
{
    public function __construct(string $message = 'El recurso solicitado no existe.', array $details = [])
    {
        parent::__construct('NOT_FOUND', $message, $details);
    }
}
