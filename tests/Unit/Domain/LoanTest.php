<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\DuplicateBookInLoan;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\MemberLimitExceeded;
use App\Domain\Loan;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeClock;

final class LoanTest extends TestCase
{
    private function book(string $id, int $total = 2): Book
    {
        return Book::create($id, 'Libro ' . $id, 'Autor', Isbn::fromString('978030747472' . substr($id, -1)), new Category('c1', 'Novela'), Quantity::of($total));
    }

    private function loan(FakeClock $clock): Loan
    {
        return Loan::open('l1', 'm1', 'u1', $clock->now());
    }

    public function test_add_item_freezes_values_sets_14_day_due_and_withdraws(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $added = $this->loan($clock)->addItem($this->book('b1'), 'i1');
        $item = $added->loan->items[0];
        $this->assertSame('Libro b1', $item->bookTitle);
        $this->assertSame('Novela', $item->categoryName);
        $this->assertSame('2026-10-28T14:15:00Z', $item->dueAt->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame(1, $added->book->availableCopies);
    }

    public function test_status_open_overdue_closed_with_fake_clock(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $added = $this->loan($clock)->addItem($this->book('b1'), 'i1');
        $loan = $added->loan;

        $this->assertSame('open', $loan->status($clock->now()));
        $clock->advanceDays(14);
        $this->assertSame('open', $loan->status($clock->now()));
        $clock->advanceDays(1);
        $this->assertSame('overdue', $loan->status($clock->now()));
        $this->assertSame(1, $loan->items[0]->daysLate($clock->now()));

        $returned = $loan->returnItem('i1', $added->book, $clock->now());
        $this->assertSame('closed', $returned->loan->status($clock->now()));
        $this->assertSame(2, $returned->book->availableCopies);
        $this->assertSame(0, $returned->item->daysLate($clock->now()));
    }

    public function test_returning_twice_fails_and_does_not_restock_twice(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $added = $this->loan($clock)->addItem($this->book('b1'), 'i1');
        $first = $added->loan->returnItem('i1', $added->book, $clock->now());
        $this->expectException(ItemAlreadyReturned::class);
        $first->loan->returnItem('i1', $first->book, $clock->now());
    }

    public function test_maximum_three_items(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $loan = $this->loan($clock);
        foreach (['1', '2', '3'] as $n) {
            $loan = $loan->addItem($this->book('b' . $n), 'i' . $n)->loan;
        }
        $this->assertCount(3, $loan->items);
        $this->expectException(MemberLimitExceeded::class);
        $loan->addItem($this->book('b4'), 'i4');
    }

    public function test_duplicate_book_is_rejected(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $this->expectException(DuplicateBookInLoan::class);
        $this->loan($clock)->addItem($this->book('b1'), 'x')->loan->addItem($this->book('b1'), 'y');
    }

    public function test_book_without_copies_cannot_be_added(): void
    {
        $clock = FakeClock::at('2026-10-14T14:15:00Z');
        $this->expectException(BookNotAvailable::class);
        $this->loan($clock)->addItem($this->book('b1', 1)->withdraw(), 'i1');
    }
}
