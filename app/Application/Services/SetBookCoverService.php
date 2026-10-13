<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Domain\Book;
use App\Domain\Errors\NotFound;

/** CA-03.4: stores the binary (validated by content) and keeps only an opaque key on the book. */
final class SetBookCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $covers)
    {
    }

    public function execute(string $bookId, string $bytes): Book
    {
        $book = $this->books->findActiveById($bookId) ?? throw new NotFound('El libro no existe.');
        $key = $this->covers->store($bytes);
        $updated = $book->withCoverKey($key);
        $this->books->save($updated);
        if ($book->coverKey !== null) {
            $this->covers->delete($book->coverKey);
        }

        return $updated;
    }
}
