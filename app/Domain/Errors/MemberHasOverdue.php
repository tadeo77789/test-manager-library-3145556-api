<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class MemberHasOverdue extends DomainError
{
    public function __construct(string $message = 'El socio tiene libros vencidos y no puede pedir otro.', array $details = [])
    {
        parent::__construct('MEMBER_HAS_OVERDUE', $message, $details);
    }
}
