<?php

declare(strict_types=1);

namespace App\Domain;

/** Result of Loan::addItem: the loan with the new line and the book with one copy fewer. */
final readonly class ItemAdded
{
    public function __construct(public Loan $loan, public Book $book)
    {
    }
}
