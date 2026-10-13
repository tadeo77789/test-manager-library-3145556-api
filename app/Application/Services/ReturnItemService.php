<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\Errors\NotFound;
use App\Domain\LoanItem;

/** HU-07: return one line; restocks the book exactly once. */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly BookRepository $books,
        private readonly Clock $clock,
    ) {
    }

    public function execute(string $loanId, string $itemId): LoanItem
    {
        $loan = $this->loans->findById($loanId) ?? throw new NotFound('El préstamo no existe.');
        $item = $loan->item($itemId) ?? throw new NotFound('La línea del préstamo no existe.');
        // Fails fast with ITEM_ALREADY_RETURNED (409) before touching stock.
        $item->markReturned($this->clock->now());

        // A book with an unreturned line can never be soft-deleted (BOOK_HAS_OPEN_LOANS), so it is active here.
        $book = $this->books->findActiveById($item->bookId) ?? throw new NotFound('El libro no existe.');
        $result = $loan->returnItem($itemId, $book, $this->clock->now());
        $this->loans->saveReturn($loanId, $result->item, $result->book);

        return $result->item;
    }
}
