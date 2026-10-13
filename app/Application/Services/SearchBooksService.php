<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Ports\BookRepository;

/** HU-04: partial text search over title/author, category filter, active only, ordered by title. */
final class SearchBooksService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    /** @return Page<\App\Domain\Book> */
    public function execute(?string $q, ?string $categoryId, int $page, int $size): Page
    {
        return $this->books->search($q !== null && trim($q) !== '' ? trim($q) : null, $categoryId, $page, $size);
    }
}
