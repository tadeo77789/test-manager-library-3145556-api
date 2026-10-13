<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Domain\Category;

final class ListCategoriesService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    /** @return list<Category> */
    public function execute(): array
    {
        return $this->books->categories();
    }
}
