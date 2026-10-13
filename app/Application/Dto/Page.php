<?php

declare(strict_types=1);

namespace App\Application\Dto;

/** @template T */
final readonly class Page
{
    /** @param list<T> $items */
    public function __construct(public array $items, public int $page, public int $size, public int $total)
    {
    }
}
