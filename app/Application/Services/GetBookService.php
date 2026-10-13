<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\Errors\NotFound;

final class GetBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function execute(string $id): Book
    {
        return $this->books->findActiveById($id) ?? throw new NotFound('El libro no existe.');
    }
}
