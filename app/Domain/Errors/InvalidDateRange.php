<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class InvalidDateRange extends DomainError
{
    public function __construct(string $message = 'La fecha final no puede ser anterior a la inicial.', array $details = [])
    {
        parent::__construct('INVALID_DATE_RANGE', $message, $details);
    }
}
