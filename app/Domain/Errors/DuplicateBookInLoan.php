<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class DuplicateBookInLoan extends DomainError
{
    public function __construct(string $message = 'No se puede repetir el mismo libro en un préstamo.', array $details = [])
    {
        parent::__construct('DUPLICATE_BOOK_IN_LOAN', $message, $details);
    }
}
