<?php

declare(strict_types=1);

namespace App\Adapters\System;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Real UTC clock. CLOCK_OFFSET_DAYS (default 0) shifts "now" by whole days; it exists only so the demo
 * seeder can create loans in the past (e.g. -20) and then run with 0 to get overdue lines.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly int $offsetDays = 0)
    {
    }

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->offsetDays === 0 ? $now : $now->modify(sprintf('%+d days', $this->offsetDays));
    }
}
