<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Errors\ValidationFailed;

/** A strictly positive count of copies. */
final readonly class Quantity
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $value, string $field = 'total_copies'): self
    {
        if ($value < 1) {
            throw new ValidationFailed('La cantidad debe ser mayor que cero.', [['field' => $field, 'issue' => 'debe ser mayor o igual a 1']]);
        }
        return new self($value);
    }
}
