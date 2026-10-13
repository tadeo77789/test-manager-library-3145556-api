<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Errors\ValidationFailed;

final readonly class DocumentNumber
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) > 30) {
            throw new ValidationFailed('El documento es obligatorio (máximo 30 caracteres).', [['field' => 'document_number', 'issue' => 'obligatorio, máximo 30 caracteres']]);
        }
        return new self($value);
    }
}
