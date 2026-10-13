<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Category
{
    public function __construct(public string $id, public string $name)
    {
    }
}
