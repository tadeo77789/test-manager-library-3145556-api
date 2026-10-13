<?php

declare(strict_types=1);

namespace App\Adapters\System;

use App\Application\Ports\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function next(): string
    {
        return (string) Str::uuid();
    }
}
