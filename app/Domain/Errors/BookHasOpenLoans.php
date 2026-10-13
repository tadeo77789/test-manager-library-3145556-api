<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class BookHasOpenLoans extends DomainError
{
    public function __construct(string $message = 'El libro tiene préstamos abiertos y no puede darse de baja.', array $details = [])
    {
        parent::__construct('BOOK_HAS_OPEN_LOANS', $message, $details);
    }
}
