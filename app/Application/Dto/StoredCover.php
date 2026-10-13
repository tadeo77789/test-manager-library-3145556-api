<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class StoredCover
{
    public function __construct(public string $bytes, public string $mime)
    {
    }
}
