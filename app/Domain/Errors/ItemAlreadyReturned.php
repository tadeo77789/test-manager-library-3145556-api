<?php

declare(strict_types=1);

namespace App\Domain\Errors;

final class ItemAlreadyReturned extends DomainError
{
    public function __construct(string $message = 'Esta línea ya fue devuelta.', array $details = [])
    {
        parent::__construct('ITEM_ALREADY_RETURNED', $message, $details);
    }
}
