<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class DocumentTaken extends DomainError
{
    public function __construct(string $message = 'Ya existe un socio con ese documento.', array $details = [])
    {
        parent::__construct('DOCUMENT_TAKEN', $message, $details);
    }
}
