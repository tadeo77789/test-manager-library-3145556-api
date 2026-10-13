<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Application\Dto\ReportRow;
use App\Application\Ports\ReportReader;
use App\Domain\ValueObject\DateRange;
use Illuminate\Support\Facades\DB;

/** ADR-004: a single aggregate query over the frozen loan_item values; never reads the live catalogue. */
final class PgReportReader implements ReportReader
{
    public function mostBorrowed(DateRange $range): array
    {
        $rows = DB::table('library.loan_item as i')
            ->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', Rows::fromUtc($range->from))
            ->where('l.loaned_at', '<', Rows::fromUtc($range->toExclusive))
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('i.book_title')
            ->orderBy('i.category_name')
            ->selectRaw('i.book_id, i.book_title, i.category_name, COUNT(*) AS times_borrowed')
            ->get();

        return array_map(
            static fn ($r): ReportRow => new ReportRow($r->book_id, $r->book_title, $r->category_name, (int) $r->times_borrowed),
            $rows->all(),
        );
    }
}
