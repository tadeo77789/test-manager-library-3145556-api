<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\ReportRow;
use App\Application\Ports\ReportReader;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;

/** HU-10: no socios, no bibliotecarios; groups by frozen book and category name. */
final class MostBorrowedReportService
{
    public function __construct(private readonly ReportReader $reader)
    {
    }

    /** @return array{range: DateRange, rows: list<ReportRow>} */
    public function execute(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $range = DateRange::of($from, $to);

        return ['range' => $range, 'rows' => $this->reader->mostBorrowed($range)];
    }
}
