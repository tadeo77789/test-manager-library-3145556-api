<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\LoanSearch;
use App\Application\Dto\Page;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Domain\Errors\ValidationFailed;
use App\Domain\Loan;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;

/** HU-08: list by date range, computed status and member, newest first. */
final class ListLoansService
{
    public function __construct(private readonly LoanRepository $loans, private readonly Clock $clock)
    {
    }

    /** @return Page<Loan> */
    public function execute(?DateTimeImmutable $from, ?DateTimeImmutable $to, ?string $status, ?string $memberId, int $page, int $size): Page
    {
        if ($status !== null && ! in_array($status, [Loan::STATUS_OPEN, Loan::STATUS_OVERDUE, Loan::STATUS_CLOSED], true)) {
            throw new ValidationFailed('Estado inválido.', [['field' => 'status', 'issue' => 'debe ser open, overdue o closed']]);
        }
        $fromInclusive = null;
        $toExclusive = null;
        if ($from !== null && $to !== null) {
            $range = DateRange::of($from, $to);
            $fromInclusive = $range->from;
            $toExclusive = $range->toExclusive;
        } elseif ($from !== null) {
            $fromInclusive = $from->setTime(0, 0);
        } elseif ($to !== null) {
            $toExclusive = $to->setTime(0, 0)->modify('+1 day');
        }

        return $this->loans->search(new LoanSearch($fromInclusive, $toExclusive, $status, $memberId, $page, $size), $this->clock->now());
    }
}
