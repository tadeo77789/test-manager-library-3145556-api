<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Book;

/** A book state transition: the adapter persists it only if the stored copies still equal "before". */
final readonly class BookChange
{
    public function __construct(public Book $before, public Book $after)
    {
    }
}
