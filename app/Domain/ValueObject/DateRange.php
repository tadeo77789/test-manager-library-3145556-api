<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Errors\InvalidDateRange;
use DateTimeImmutable;

/** Date range with both ends inclusive; exposes an exclusive upper bound (start of the day after "to"). */
final readonly class DateRange
{
    private function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $toExclusive,
        public string $fromDate,
        public string $toDate,
    ) {
    }

    public static function of(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        $f = $from->setTime(0, 0);
        $t = $to->setTime(0, 0);
        if ($t < $f) {
            throw new InvalidDateRange();
        }
        return new self($f, $t->modify('+1 day'), $f->format('Y-m-d'), $t->format('Y-m-d'));
    }
}
