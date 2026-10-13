<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FakeClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $iso): self
    {
        return new self(new DateTimeImmutable($iso, new DateTimeZone('UTC')));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceDays(int $days): void
    {
        $this->now = $this->now->modify(($days >= 0 ? '+' : '') . $days . ' days');
    }
}
