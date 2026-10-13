<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\DocumentNumber;

final readonly class Member
{
    public function __construct(
        public string $id,
        public string $fullName,
        public DocumentNumber $documentNumber,
    ) {
    }

    public static function create(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        $name = trim($fullName);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new ValidationFailed('El nombre es obligatorio (máximo 200 caracteres).', [['field' => 'full_name', 'issue' => 'obligatorio, máximo 200 caracteres']]);
        }
        return new self($id, $name, $documentNumber);
    }
}
