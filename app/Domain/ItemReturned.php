<?php

declare(strict_types=1);

namespace App\Domain;

/** Result of Loan::returnItem: the loan, the restocked book and the line just returned. */
final readonly class ItemReturned
{
    public function __construct(public Loan $loan, public Book $book, public LoanItem $item)
    {
    }
}
