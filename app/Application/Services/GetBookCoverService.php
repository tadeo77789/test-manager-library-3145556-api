<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\StoredCover;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Domain\Errors\NotFound;

final class GetBookCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $covers)
    {
    }

    public function execute(string $bookId): StoredCover
    {
        $book = $this->books->findActiveById($bookId) ?? throw new NotFound('El libro no existe.');
        if ($book->coverKey === null) {
            throw new NotFound('El libro no tiene portada.');
        }

        return $this->covers->read($book->coverKey) ?? throw new NotFound('El libro no tiene portada.');
    }
}
