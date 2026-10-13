<?php

declare(strict_types=1);

namespace App\Application\Dto;

final readonly class ReportRow
{
    public function __construct(public string $bookId, public string $bookTitle, public string $categoryName, public int $timesBorrowed)
    {
    }
}
