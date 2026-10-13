<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class MemberLimitExceeded extends DomainError
{
    public function __construct(string $message = 'El socio no puede tener más de 3 libros sin devolver.', array $details = [])
    {
        parent::__construct('MEMBER_LIMIT_EXCEEDED', $message, $details);
    }
}
