<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Domain\Errors\NotFound;

/** Data-model retention rule: null the key and commit first, then delete the binary. */
final class RemoveBookCoverService
{
    public function __construct(private readonly BookRepository $books, private readonly CoverStorage $covers)
    {
    }

    public function execute(string $bookId): void
    {
        $book = $this->books->findActiveById($bookId) ?? throw new NotFound('El libro no existe.');
        if ($book->coverKey === null) {
            return;
        }
        $this->books->save($book->withCoverKey(null));
        $this->covers->delete($book->coverKey);
    }
}
