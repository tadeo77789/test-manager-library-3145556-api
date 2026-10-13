<?php

declare(strict_types=1);

namespace App\Application\Dto;

use DateTimeImmutable;

final readonly class OverdueLine
{
    public function __construct(
        public string $loanId,
        public string $itemId,
        public string $bookTitle,
        public string $memberName,
        public DateTimeImmutable $dueAt,
        public int $daysLate,
    ) {
    }
}
