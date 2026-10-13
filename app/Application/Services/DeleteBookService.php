<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Domain\Errors\BookHasOpenLoans;
use App\Domain\Errors\NotFound;

/** CA-03.5: soft delete; refused while the book has unreturned lines. */
final class DeleteBookService
{
    public function __construct(private readonly BookRepository $books, private readonly Clock $clock)
    {
    }

    public function execute(string $id): void
    {
        $book = $this->books->findActiveById($id) ?? throw new NotFound('El libro no existe.');
        if ($this->books->hasOpenLoans($id)) {
            throw new BookHasOpenLoans();
        }
        $this->books->save($book->markDeleted($this->clock->now()));
    }
}
