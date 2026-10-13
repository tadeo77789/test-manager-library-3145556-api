<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\OverdueLine;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;

/** HU-09: unreturned lines past due, most days late first. */
final class OverdueService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    /** @return list<OverdueLine> */
    public function execute(): array
    {
        $lines = $this->loans->overdueLines($this->clock->now());
        usort($lines, static fn (OverdueLine $a, OverdueLine $b): int => [$b->daysLate, $a->bookTitle] <=> [$a->daysLate, $b->bookTitle]);

        return $lines;
    }
}
