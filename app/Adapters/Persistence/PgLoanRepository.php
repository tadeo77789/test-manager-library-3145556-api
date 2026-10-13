<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Application\Dto\LoanSearch;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Errors\ConcurrentUpdate;
use App\Application\Ports\LoanRepository;
use App\Domain\Book;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Loans and their lines. Concurrency (ADR-002, adapted): the book is updated with
 * "UPDATE ... WHERE id = ? AND available_copies = <read value>"; zero rows means someone else won.
 */
final class PgLoanRepository implements LoanRepository
{
    public function create(Loan $loan, array $changes): void
    {
        DB::transaction(function () use ($loan, $changes): void {
            DB::table('library.loan')->insert([
                'id' => $loan->id,
                'member_id' => $loan->memberId,
                'loaned_by_user_id' => $loan->loanedByUserId,
                'loaned_at' => Rows::fromUtc($loan->loanedAt),
            ]);
            foreach ($changes as $change) {
                $updated = DB::table('library.book')
                    ->where('id', $change->before->id)
                    ->where('available_copies', $change->before->availableCopies)
                    ->update(['available_copies' => $change->after->availableCopies]);
                if ($updated !== 1) {
                    throw new ConcurrentUpdate();
                }
            }
            foreach ($loan->items as $item) {
                DB::table('library.loan_item')->insert([
                    'id' => $item->id,
                    'loan_id' => $loan->id,
                    'book_id' => $item->bookId,
                    'book_title' => $item->bookTitle,
                    'isbn' => $item->isbn,
                    'category_name' => $item->categoryName,
                    'due_at' => Rows::fromUtc($item->dueAt),
                    'returned_at' => null,
                ]);
            }
        });
    }

    public function saveReturn(string $loanId, LoanItem $returned, Book $restocked): void
    {
        DB::transaction(function () use ($loanId, $returned, $restocked): void {
            $updated = DB::table('library.loan_item')
                ->where('id', $returned->id)
                ->where('loan_id', $loanId)
                ->whereNull('returned_at')
                ->update(['returned_at' => Rows::fromUtc($returned->returnedAt)]);
            if ($updated !== 1) {
                throw new ItemAlreadyReturned();
            }
            // Relative increment: the CHECK (available <= total) is the last barrier.
            DB::table('library.book')->where('id', $restocked->id)->update([
                'available_copies' => DB::raw('available_copies + 1'),
            ]);
        });
    }

    public function findById(string $id): ?Loan
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }
        $r = DB::table('library.loan')->where('id', $id)->first();
        if ($r === null) {
            return null;
        }

        return $this->hydrate([$r])[0];
    }

    public function search(LoanSearch $s, DateTimeImmutable $now): Page
    {
        $query = DB::table('library.loan as l');
        if ($s->fromInclusive !== null) {
            $query->where('l.loaned_at', '>=', Rows::fromUtc($s->fromInclusive));
        }
        if ($s->toExclusive !== null) {
            $query->where('l.loaned_at', '<', Rows::fromUtc($s->toExclusive));
        }
        if ($s->memberId !== null) {
            $query->where('l.member_id', preg_match('/^[0-9a-f-]{36}$/i', $s->memberId) === 1 ? $s->memberId : '00000000-0000-0000-0000-000000000000');
        }
        $nowSql = Rows::fromUtc($now);
        $unreturned = 'EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id = l.id AND i.returned_at IS NULL';
        match ($s->status) {
            Loan::STATUS_CLOSED => $query->whereRaw('NOT ' . $unreturned . ')'),
            Loan::STATUS_OVERDUE => $query->whereRaw($unreturned . ' AND i.due_at < ?)', [$nowSql]),
            Loan::STATUS_OPEN => $query->whereRaw($unreturned . ')')
                ->whereRaw('NOT ' . $unreturned . ' AND i.due_at < ?)', [$nowSql]),
            default => null,
        };
        $total = (clone $query)->count();
        $rows = $query->select('l.*')->orderByDesc('l.loaned_at')->orderBy('l.id')
            ->offset(($s->page - 1) * $s->size)->limit($s->size)->get();

        return new Page($this->hydrate($rows->all()), $s->page, $s->size, $total);
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        return DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at')
            ->count();
    }

    public function hasOverdueByMember(string $memberId, DateTimeImmutable $now): bool
    {
        return DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.member_id', $memberId)
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', Rows::fromUtc($now))
            ->exists();
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $rows = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->join('library.member as m', 'm.id', '=', 'l.member_id')
            ->whereNull('i.returned_at')
            ->where('i.due_at', '<', Rows::fromUtc($now))
            ->orderBy('i.due_at')
            ->select('i.*', 'l.id as loan_id', 'm.full_name as member_name')
            ->get();

        return array_map(static function ($r) use ($now): OverdueLine {
            $item = Rows::item($r);

            return new OverdueLine($r->loan_id, $item->id, $item->bookTitle, $r->member_name, $item->dueAt, $item->daysLate($now));
        }, $rows->all());
    }

    /**
     * @param list<\stdClass> $loanRows
     * @return list<Loan>
     */
    private function hydrate(array $loanRows): array
    {
        $ids = array_map(static fn ($r) => $r->id, $loanRows);
        $items = [];
        foreach (DB::table('library.loan_item')->whereIn('loan_id', $ids)->orderBy('book_title')->orderBy('id')->get() as $r) {
            $items[$r->loan_id][] = Rows::item($r);
        }

        return array_map(static fn ($r): Loan => new Loan(
            $r->id,
            $r->member_id,
            $r->loaned_by_user_id,
            Rows::toUtc($r->loaned_at),
            $items[$r->id] ?? [],
        ), $loanRows);
    }
}
