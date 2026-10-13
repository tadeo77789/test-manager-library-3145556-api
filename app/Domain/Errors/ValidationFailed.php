<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class ValidationFailed extends DomainError
{
    public function __construct(string $message = 'Los datos enviados no son válidos.', array $details = [])
    {
        parent::__construct('VALIDATION_ERROR', $message, $details);
    }
}
