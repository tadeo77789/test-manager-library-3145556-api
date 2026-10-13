<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class BookNotAvailable extends DomainError
{
    public function __construct(string $message = 'No quedan ejemplares disponibles.', array $details = [])
    {
        parent::__construct('BOOK_NOT_AVAILABLE', $message, $details);
    }
}
