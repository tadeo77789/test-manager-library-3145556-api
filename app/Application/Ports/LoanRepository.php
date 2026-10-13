<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\BookChange;
use App\Application\Dto\LoanSearch;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

interface LoanRepository
{
    /**
     * Saves the loan and its lines and applies every book change in ONE transaction.
     * Each book is updated only if its stored available copies still equal the "before" value.
     *
     * @param list<BookChange> $changes
     * @throws \App\Application\Errors\ConcurrentUpdate when a book changed since it was read
     */
    public function create(Loan $loan, array $changes): void;

    /**
     * Marks the line returned (only if still unreturned) and restocks the book in ONE transaction.
     *
     * @throws \App\Domain\Errors\ItemAlreadyReturned
     */
    public function saveReturn(string $loanId, LoanItem $returned, Book $restocked): void;

    public function findById(string $id): ?Loan;

    /** @return Page<Loan> */
    public function search(LoanSearch $search, DateTimeImmutable $now): Page;

    public function countUnreturnedByMember(string $memberId): int;

    public function hasOverdueByMember(string $memberId, DateTimeImmutable $now): bool;

    /** @return list<OverdueLine> */
    public function overdueLines(DateTimeImmutable $now): array;
}
