<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\ReportRow;
use App\Domain\ValueObject\DateRange;

interface ReportReader
{
    /**
     * One aggregated query over the frozen loan_item values (ADR-004).
     *
     * @return list<ReportRow>
     */
    public function mostBorrowed(DateRange $range): array;
}
