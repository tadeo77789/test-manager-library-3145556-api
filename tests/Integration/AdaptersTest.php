<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Adapters\Persistence\PgBookRepository;
use App\Adapters\Persistence\PgLoanRepository;
use App\Adapters\Persistence\PgMemberRepository;
use App\Adapters\Persistence\PgReportReader;
use App\Adapters\Persistence\PgUserRepository;
use App\Adapters\System\UuidGenerator;
use App\Application\Dto\BookChange;
use App\Application\Errors\ConcurrentUpdate;
use App\Application\Services\CreateBookService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\LendBooksService;
use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateBookService;
use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Errors\IsbnTaken;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Loan;
use App\Domain\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Tests\IntegrationTestCase;
use Tests\Support\FakeClock;

/** T-12 / T-13: repositories against the real PostgreSQL schema. */
final class AdaptersTest extends IntegrationTestCase
{
    private const NOVELA = '11111111-1111-4111-8111-111111111111';
    private const CIENCIA = '22222222-2222-4222-8222-222222222222';

    private FakeClock $clock;
    private PgBookRepository $books;
    private PgMemberRepository $members;
    private PgLoanRepository $loans;
    private UuidGenerator $ids;
    private string $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = FakeClock::at('2026-10-14T14:15:00Z');
        $this->books = new PgBookRepository();
        $this->members = new PgMemberRepository();
        $this->loans = new PgLoanRepository();
        $this->ids = new UuidGenerator();
        $this->userId = $this->ids->next();
        (new PgUserRepository())->add(User::createAdmin($this->userId, 'admin', 'hash'));
    }

    private function lend(): LendBooksService
    {
        return new LendBooksService($this->members, $this->books, $this->loans, $this->clock, $this->ids);
    }

    private function book(string $isbnSuffix, int $copies = 2, string $title = 'Libro'): string
    {
        return (new CreateBookService($this->books, $this->ids))->execute($title, 'Autor', '97800000000' . $isbnSuffix, self::NOVELA, $copies)->id;
    }

    private function member(string $doc = '100'): string
    {
        return (new CreateMemberService($this->members, $this->ids))->execute('Socio ' . $doc, $doc)->id;
    }

    public function test_unique_violations_are_translated_to_domain_errors(): void
    {
        $this->book('01');
        try {
            $this->book('01');
            $this->fail('expected IsbnTaken');
        } catch (IsbnTaken) {
            $this->addToAssertionCount(1);
        }
        $this->member('7');
        try {
            $this->member('7');
            $this->fail('expected DocumentTaken');
        } catch (DocumentTaken) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(UsernameTaken::class);
        (new PgUserRepository())->add(User::createAdmin($this->ids->next(), 'admin', 'x'));
    }

    public function test_isbn_can_be_reused_after_soft_delete(): void
    {
        $id = $this->book('02');
        $book = $this->books->findActiveById($id);
        $this->books->save($book->markDeleted($this->clock->now()));
        $this->assertNull($this->books->findActiveById($id));
        $this->book('02');
        $this->addToAssertionCount(1);
    }

    public function test_search_filters_and_paginates(): void
    {
        $this->book('03', 1, 'Cien años de soledad');
        $this->book('04', 1, 'Cosmos');
        $page = $this->books->search('COSM', null, 1, 20);
        $this->assertSame(1, $page->total);
        $this->assertSame('Cosmos', $page->items[0]->title);
        $this->assertSame(2, $this->books->search(null, self::NOVELA, 1, 1)->total);
        $this->assertCount(1, $this->books->search(null, null, 1, 1)->items);
        $this->assertSame(0, $this->books->search('100%', null, 1, 20)->total);
    }

    public function test_lend_return_roundtrip_and_status_filters(): void
    {
        $b = $this->book('05', 1);
        $m = $this->member();
        $loan = $this->lend()->execute($m, [$b], $this->userId);
        $this->assertSame(0, $this->books->findActiveById($b)->availableCopies);
        $stored = $this->loans->findById($loan->id);
        $this->assertSame('open', $stored->status($this->clock->now()));
        $this->assertSame(1, $this->loans->countUnreturnedByMember($m));

        $later = $this->clock->now()->modify('+15 days');
        $this->assertSame('overdue', $stored->status($later));
        $this->assertTrue($this->loans->hasOverdueByMember($m, $later));
        $this->assertSame(1, $this->loans->search(new \App\Application\Dto\LoanSearch(null, null, 'overdue', $m, 1, 20), $later)->total);
        $this->assertSame(0, $this->loans->search(new \App\Application\Dto\LoanSearch(null, null, 'open', $m, 1, 20), $later)->total);
        $lines = $this->loans->overdueLines($later);
        $this->assertCount(1, $lines);
        $this->assertSame('Socio 100', $lines[0]->memberName);

        (new ReturnItemService($this->loans, $this->books, $this->clock))->execute($loan->id, $loan->items[0]->id);
        $this->assertSame(1, $this->books->findActiveById($b)->availableCopies);
        $this->assertSame(1, $this->loans->search(new \App\Application\Dto\LoanSearch(null, null, 'closed', null, 1, 20), $later)->total);
        $this->expectException(ItemAlreadyReturned::class);
        (new ReturnItemService($this->loans, $this->books, $this->clock))->execute($loan->id, $loan->items[0]->id);
    }

    public function test_last_copy_two_loans_one_wins_one_conflicts_rnf2(): void
    {
        $b = $this->book('06', 1);
        $m1 = $this->member('1');
        $m2 = $this->member('2');
        // Both librarians read the book with 1 copy available.
        $stale = $this->books->findActiveById($b);
        $loanA = Loan::open($this->ids->next(), $m1, $this->userId, $this->clock->now());
        $addedA = $loanA->addItem($stale, $this->ids->next());
        $loanB = Loan::open($this->ids->next(), $m2, $this->userId, $this->clock->now());
        $addedB = $loanB->addItem($stale, $this->ids->next());

        $this->loans->create($addedA->loan, [new BookChange($stale, $addedA->book)]);
        try {
            $this->loans->create($addedB->loan, [new BookChange($stale, $addedB->book)]);
            $this->fail('second loan must conflict');
        } catch (ConcurrentUpdate) {
            $this->assertSame(1, DB::table('library.loan')->count());
            $this->assertSame(0, $this->books->findActiveById($b)->availableCopies);
        }
        // Through the service the loser sees the business error.
        $this->expectException(BookNotAvailable::class);
        $this->lend()->execute($m2, [$b], $this->userId);
    }

    public function test_failed_lend_leaves_no_trace(): void
    {
        $ok = $this->book('07', 2);
        $none = $this->book('08', 1);
        $other = $this->member('1');
        $this->lend()->execute($other, [$none], $this->userId);
        try {
            $this->lend()->execute($this->member('2'), [$ok, $none], $this->userId);
            $this->fail('expected BookNotAvailable');
        } catch (BookNotAvailable) {
            $this->assertSame(1, DB::table('library.loan')->count());
            $this->assertSame(2, $this->books->findActiveById($ok)->availableCopies);
        }
    }

    public function test_report_groups_by_frozen_values_after_rename_and_recategorise(): void
    {
        $b = $this->book('09', 3, 'Titulo viejo');
        $this->lend()->execute($this->member('1'), [$b], $this->userId);
        (new UpdateBookService($this->books))->execute($b, 'Titulo nuevo', 'Autor', self::CIENCIA, 3);
        $this->lend()->execute($this->member('2'), [$b], $this->userId);

        $report = new MostBorrowedReportService(new PgReportReader());
        $rows = $report->execute(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-31'))['rows'];
        $this->assertCount(2, $rows);
        $titles = array_map(static fn ($r) => $r->bookTitle . '/' . $r->categoryName, $rows);
        sort($titles);
        $this->assertSame(['Titulo nuevo/Ciencia', 'Titulo viejo/Novela'], $titles);
        $this->assertSame([], $report->execute(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-30'))['rows']);
    }

    public function test_update_book_shifts_available_by_total_difference(): void
    {
        $b = $this->book('10', 3);
        $this->lend()->execute($this->member(), [$b], $this->userId);
        $updated = (new UpdateBookService($this->books))->execute($b, 'T', 'A', self::NOVELA, 5);
        $this->assertSame(4, $updated->availableCopies);
        $this->assertSame(4, $this->books->findActiveById($b)->availableCopies);
        $this->assertSame([], (new OverdueService($this->loans, $this->clock))->execute());
    }
}
