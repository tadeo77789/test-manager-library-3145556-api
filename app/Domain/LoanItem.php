<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Errors\ItemAlreadyReturned;
use DateTimeImmutable;

/** One book inside a loan. Title, ISBN and category name are frozen at lending time (RN-4). */
final readonly class LoanItem
{
    public function __construct(
        public string $id,
        public string $bookId,
        public string $bookTitle,
        public string $isbn,
        public string $categoryName,
        public DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $returnedAt = null,
    ) {
    }

    public static function freeze(string $id, Book $book, DateTimeImmutable $loanedAt): self
    {
        return new self(
            $id,
            $book->id,
            $book->title,
            $book->isbn->value,
            $book->category->name,
            $loanedAt->modify('+' . Loan::TERM_DAYS . ' days'),
        );
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function isOverdue(DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    /** Days late, rounded up: any overdue item reports at least 1 day. 0 when not overdue. */
    public function daysLate(DateTimeImmutable $now): int
    {
        if (! $this->isOverdue($now)) {
            return 0;
        }
        return (int) ceil(($now->getTimestamp() - $this->dueAt->getTimestamp()) / 86400);
    }

    public function markReturned(DateTimeImmutable $now): self
    {
        if ($this->isReturned()) {
            throw new ItemAlreadyReturned();
        }
        return new self($this->id, $this->bookId, $this->bookTitle, $this->isbn, $this->categoryName, $this->dueAt, $now);
    }
}
