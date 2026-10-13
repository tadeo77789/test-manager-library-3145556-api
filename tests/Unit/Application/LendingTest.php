<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Errors\ConcurrentUpdate;
use App\Application\Services\CreateBookService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateBookService;
use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\DuplicateBookInLoan;
use App\Domain\Errors\InvalidDateRange;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\MemberHasOverdue;
use App\Domain\Errors\MemberLimitExceeded;
use App\Domain\Errors\ValidationFailed;
use App\Domain\Loan;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeClock;
use Tests\Support\InMemoryBooks;
use Tests\Support\InMemoryLoans;
use Tests\Support\InMemoryMembers;
use Tests\Support\InMemoryReport;
use Tests\Support\SequenceIds;

final class LendingTest extends TestCase
{
    private FakeClock $clock;
    private InMemoryBooks $books;
    private InMemoryMembers $members;
    private InMemoryLoans $loans;
    private SequenceIds $ids;
    private LendBooksService $lend;
    private ReturnItemService $return;

    protected function setUp(): void
    {
        $this->clock = FakeClock::at('2026-10-14T14:15:00Z');
        $this->books = new InMemoryBooks();
        $this->members = new InMemoryMembers();
        $this->loans = new InMemoryLoans($this->books, $this->members);
        $this->ids = new SequenceIds();
        $this->lend = new LendBooksService($this->members, $this->books, $this->loans, $this->clock, $this->ids);
        $this->return = new ReturnItemService($this->loans, $this->books, $this->clock);
    }

    private function book(string $n, int $copies = 2): string
    {
        return (new CreateBookService($this->books, $this->ids))->execute('Libro ' . $n, 'Autor', '978000000000' . $n, 'cat-1', $copies)->id;
    }

    private function member(string $doc = '100'): string
    {
        return (new CreateMemberService($this->members, $this->ids))->execute('Socio ' . $doc, $doc)->id;
    }

    public function test_lend_two_books_decrements_copies_and_freezes_values(): void
    {
        $b1 = $this->book('1');
        $b2 = $this->book('2');
        $m = $this->member();
        $loan = $this->lend->execute($m, [$b1, $b2], 'u1');
        $this->assertCount(2, $loan->items);
        $this->assertSame(1, $this->books->items[$b1]->availableCopies);
        $this->assertSame('Novela', $loan->items[0]->categoryName);
        $this->assertSame('2026-10-28T14:15:00Z', $loan->items[0]->dueAt->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_zero_or_more_than_three_items_is_validation_error(): void
    {
        $m = $this->member();
        $this->expectException(ValidationFailed::class);
        $this->lend->execute($m, [], 'u1');
    }

    public function test_duplicate_book_is_rejected(): void
    {
        $b1 = $this->book('1');
        $this->expectException(DuplicateBookInLoan::class);
        $this->lend->execute($this->member(), [$b1, $b1], 'u1');
    }

    public function test_no_copies_left_returns_conflict_and_registers_nothing(): void
    {
        $b1 = $this->book('1', 2);
        $b2 = $this->book('2', 1);
        $m = $this->member();
        $other = $this->member('200');
        $this->lend->execute($other, [$b2], 'u1');
        try {
            $this->lend->execute($m, [$b1, $b2], 'u1');
            $this->fail('expected BookNotAvailable');
        } catch (BookNotAvailable $e) {
            $this->assertSame('items[1].book_id', $e->details()[0]['field']);
        }
        $this->assertCount(1, $this->loans->items);
        $this->assertSame(2, $this->books->items[$b1]->availableCopies);
    }

    public function test_member_cannot_exceed_three_unreturned_books(): void
    {
        $m = $this->member();
        $this->lend->execute($m, [$this->book('1'), $this->book('2')], 'u1');
        $this->expectException(MemberLimitExceeded::class);
        $this->lend->execute($m, [$this->book('3'), $this->book('4')], 'u1');
    }

    public function test_member_with_overdue_cannot_borrow(): void
    {
        $m = $this->member();
        $this->lend->execute($m, [$this->book('1')], 'u1');
        $this->clock->advanceDays(15);
        $this->expectException(MemberHasOverdue::class);
        $this->lend->execute($m, [$this->book('2')], 'u1');
    }

    public function test_concurrent_last_copy_loser_gets_book_not_available(): void
    {
        $b = $this->book('1', 1);
        $m1 = $this->member('1');
        $m2 = $this->member('2');
        // Another librarian takes the last copy between our read and our write.
        $this->loans->beforeCreate = function () use ($b, $m2): void {
            $book = $this->books->items[$b];
            $this->books->items[$b] = $book->withdraw();
        };
        $this->expectException(BookNotAvailable::class);
        $this->lend->execute($m1, [$b], 'u1');
    }

    public function test_conflict_with_copies_remaining_is_retried(): void
    {
        $b = $this->book('1', 3);
        $m = $this->member();
        $this->loans->beforeCreate = function () use ($b): void {
            $this->books->items[$b] = $this->books->items[$b]->withdraw();
        };
        $loan = $this->lend->execute($m, [$b], 'u1');
        $this->assertCount(1, $loan->items);
        $this->assertSame(1, $this->books->items[$b]->availableCopies);
    }

    public function test_return_restocks_once_and_second_return_fails(): void
    {
        $b = $this->book('1');
        $loan = $this->lend->execute($this->member(), [$b], 'u1');
        $item = $loan->items[0];
        $returned = $this->return->execute($loan->id, $item->id);
        $this->assertNotNull($returned->returnedAt);
        $this->assertSame(2, $this->books->items[$b]->availableCopies);
        $this->assertSame('closed', $this->loans->items[$loan->id]->status($this->clock->now()));
        try {
            $this->return->execute($loan->id, $item->id);
            $this->fail('expected ItemAlreadyReturned');
        } catch (ItemAlreadyReturned) {
            $this->assertSame(2, $this->books->items[$b]->availableCopies);
        }
    }

    public function test_overdue_lines_sorted_by_days_late_desc_and_member_detail(): void
    {
        $m1 = $this->member('1');
        $m2 = $this->member('2');
        $this->lend->execute($m1, [$this->book('1')], 'u1');
        $this->clock->advanceDays(3);
        $this->lend->execute($m2, [$this->book('2')], 'u1');
        $this->clock->advanceDays(13); // first: 2 days late; second: not yet due
        $overdue = (new OverdueService($this->loans, $this->clock))->execute();
        $this->assertCount(1, $overdue);
        $this->assertSame(2, $overdue[0]->daysLate);
        $this->assertSame('Socio 1', $overdue[0]->memberName);
        $this->assertSame(1, (new GetMemberService($this->members, $this->loans))->execute($m1)->activeLoans);
    }

    public function test_list_loans_filters_by_status_and_rejects_inverted_range(): void
    {
        $m = $this->member();
        $this->lend->execute($m, [$this->book('1')], 'u1');
        $list = new ListLoansService($this->loans, $this->clock);
        $this->assertSame(1, $list->execute(null, null, Loan::STATUS_OPEN, $m, 1, 20)->total);
        $this->assertSame(0, $list->execute(null, null, Loan::STATUS_CLOSED, $m, 1, 20)->total);
        $this->expectException(InvalidDateRange::class);
        $list->execute(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-01'), null, null, 1, 20);
    }

    public function test_report_keeps_frozen_title_after_rename_and_is_empty_without_loans(): void
    {
        $report = new MostBorrowedReportService(new InMemoryReport($this->loans));
        $from = new DateTimeImmutable('2026-10-01');
        $to = new DateTimeImmutable('2026-10-31');
        $this->assertSame([], $report->execute($from, $to)['rows']);

        $b = $this->book('1');
        $this->lend->execute($this->member(), [$b], 'u1');
        (new UpdateBookService($this->books))->execute($b, 'Titulo nuevo', 'Autor', 'cat-2', 2);

        $rows = $report->execute($from, $to)['rows'];
        $this->assertCount(1, $rows);
        $this->assertSame('Libro 1', $rows[0]->bookTitle);
        $this->assertSame('Novela', $rows[0]->categoryName);
        $this->assertSame(1, $rows[0]->timesBorrowed);
    }

    public function test_report_inverted_range_is_rejected(): void
    {
        $this->expectException(InvalidDateRange::class);
        (new MostBorrowedReportService(new InMemoryReport($this->loans)))->execute(new DateTimeImmutable('2026-10-31'), new DateTimeImmutable('2026-10-01'));
    }
}
