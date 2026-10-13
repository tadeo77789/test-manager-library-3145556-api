<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Errors\ValidationFailed;

final readonly class Isbn
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw, string $field = 'isbn'): self
    {
        $value = trim($raw);
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw new ValidationFailed('El ISBN debe tener exactamente 13 dígitos.', [['field' => $field, 'issue' => 'debe tener 13 dígitos']]);
        }
        return new self($value);
    }
}
