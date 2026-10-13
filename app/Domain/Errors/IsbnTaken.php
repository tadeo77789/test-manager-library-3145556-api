<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class IsbnTaken extends DomainError
{
    public function __construct(string $message = 'Ya existe un libro activo con ese ISBN.', array $details = [])
    {
        parent::__construct('ISBN_TAKEN', $message, $details);
    }
}
