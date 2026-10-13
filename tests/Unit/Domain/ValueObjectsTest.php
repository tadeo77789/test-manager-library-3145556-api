<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Errors\InvalidDateRange;
use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\DateRange;
use App\Domain\ValueObject\DocumentNumber;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_isbn_accepts_13_digits(): void
    {
        $this->assertSame('9780307474728', Isbn::fromString(' 9780307474728 ')->value);
    }

    #[DataProvider('badIsbns')]
    public function test_isbn_rejects_invalid(string $raw): void
    {
        $this->expectException(ValidationFailed::class);
        Isbn::fromString($raw);
    }

    public static function badIsbns(): array
    {
        return [['978030747472'], ['97803074747289'], ['97803074747AB'], ['']];
    }

    public function test_quantity_must_be_positive(): void
    {
        $this->assertSame(3, Quantity::of(3)->value);
        $this->expectException(ValidationFailed::class);
        Quantity::of(0);
    }

    public function test_document_number_is_trimmed_and_required(): void
    {
        $this->assertSame('123', DocumentNumber::fromString(' 123 ')->value);
        $this->expectException(ValidationFailed::class);
        DocumentNumber::fromString('  ');
    }

    public function test_date_range_end_may_equal_start_and_is_inclusive(): void
    {
        $r = DateRange::of(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2026-10-01'));
        $this->assertSame('2026-10-02', $r->toExclusive->format('Y-m-d'));
    }

    public function test_date_range_end_before_start_fails(): void
    {
        $this->expectException(InvalidDateRange::class);
        DateRange::of(new DateTimeImmutable('2026-10-02'), new DateTimeImmutable('2026-10-01'));
    }
}
