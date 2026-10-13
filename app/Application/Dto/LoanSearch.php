<?php

declare(strict_types=1);

namespace App\Application\Dto;

use DateTimeImmutable;

final readonly class LoanSearch
{
    public function __construct(
        public ?DateTimeImmutable $fromInclusive,
        public ?DateTimeImmutable $toExclusive,
        public ?string $status,
        public ?string $memberId,
        public int $page,
        public int $size,
    ) {
    }
}
