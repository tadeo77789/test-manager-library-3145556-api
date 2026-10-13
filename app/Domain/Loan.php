<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Errors\DuplicateBookInLoan;
use App\Domain\Errors\MemberLimitExceeded;
use App\Domain\Errors\NotFound;
use DateTimeImmutable;

/** Loan aggregate: 1 to 3 lines, 14-day term, status always computed (Art. VII). Immutable. */
final readonly class Loan
{
    public const MAX_ITEMS = 3;
    public const TERM_DAYS = 14;
    public const STATUS_OPEN = 'open';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CLOSED = 'closed';

    /** @param list<LoanItem> $items */
    public function __construct(
        public string $id,
        public string $memberId,
        public string $loanedByUserId,
        public DateTimeImmutable $loanedAt,
        public array $items = [],
    ) {
    }

    public static function open(string $id, string $memberId, string $loanedByUserId, DateTimeImmutable $loanedAt): self
    {
        return new self($id, $memberId, $loanedByUserId, $loanedAt);
    }

    /** Withdraws one copy from the book and adds the frozen line: one single operation (architecture section 3). */
    public function addItem(Book $book, string $itemId): ItemAdded
    {
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new MemberLimitExceeded('Un préstamo admite como máximo 3 libros.', [['field' => 'items', 'issue' => 'máximo 3 elementos']]);
        }
        foreach ($this->items as $item) {
            if ($item->bookId === $book->id) {
                throw new DuplicateBookInLoan('No se puede repetir el mismo libro en un préstamo.', [['field' => 'items', 'issue' => 'libro repetido']]);
            }
        }
        $updatedBook = $book->withdraw();
        $line = LoanItem::freeze($itemId, $book, $this->loanedAt);

        return new ItemAdded(
            new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, [...$this->items, $line]),
            $updatedBook,
        );
    }

    /** Marks one line as returned and restocks the book exactly once. */
    public function returnItem(string $itemId, Book $book, DateTimeImmutable $now): ItemReturned
    {
        $returned = null;
        $items = [];
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                $returned = $item->markReturned($now);
                $items[] = $returned;
            } else {
                $items[] = $item;
            }
        }
        if ($returned === null) {
            throw new NotFound();
        }
        return new ItemReturned(
            new self($this->id, $this->memberId, $this->loanedByUserId, $this->loanedAt, $items),
            $book->restock(),
            $returned,
        );
    }

    public function item(string $itemId): ?LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }
        return null;
    }

    public function status(DateTimeImmutable $now): string
    {
        $open = array_filter($this->items, static fn (LoanItem $i): bool => ! $i->isReturned());
        if ($open === []) {
            return self::STATUS_CLOSED;
        }
        foreach ($open as $item) {
            if ($item->isOverdue($now)) {
                return self::STATUS_OVERDUE;
            }
        }
        return self::STATUS_OPEN;
    }
}
