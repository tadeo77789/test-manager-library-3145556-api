<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface IdGenerator
{
    /** A new UUID v4 as text. */
    public function next(): string;
}
