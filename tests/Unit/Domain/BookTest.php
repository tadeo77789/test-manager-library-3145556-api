<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    private function book(int $total = 2): Book
    {
        return Book::create('b1', '  Cien años de soledad ', 'García Márquez', Isbn::fromString('9780307474728'), new Category('c1', 'Novela'), Quantity::of($total));
    }

    public function test_create_trims_title_and_starts_fully_available(): void
    {
        $b = $this->book(4);
        $this->assertSame('Cien años de soledad', $b->title);
        $this->assertSame(4, $b->availableCopies);
        $this->assertNull($b->coverKey);
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $b = $this->book(1)->withdraw();
        $this->assertSame(0, $b->availableCopies);
        $this->expectException(BookNotAvailable::class);
        $b->withdraw();
    }

    public function test_restock_never_exceeds_total(): void
    {
        $b = $this->book(1);
        $this->expectException(\DomainException::class);
        $b->restock();
    }

    public function test_withdraw_then_restock_round_trip_and_immutability(): void
    {
        $b = $this->book(2);
        $w = $b->withdraw();
        $this->assertSame(2, $b->availableCopies);
        $this->assertSame(2, $w->restock()->availableCopies);
    }

    public function test_edit_shifts_available_by_same_difference(): void
    {
        $b = $this->book(3)->withdraw()->withdraw();
        $e = $b->edit('T', 'A', new Category('c2', 'Ciencia'), Quantity::of(5));
        $this->assertSame(5, $e->totalCopies);
        $this->assertSame(3, $e->availableCopies);
    }

    public function test_edit_cannot_leave_negative_available(): void
    {
        $b = $this->book(3)->withdraw()->withdraw();
        $this->expectException(ValidationFailed::class);
        $b->edit('T', 'A', new Category('c1', 'Novela'), Quantity::of(1));
    }

    public function test_soft_delete_and_empty_cover_becomes_null(): void
    {
        $b = $this->book()->withCoverKey('abc.png');
        $this->assertSame('abc.png', $b->coverKey);
        $this->assertNull($b->withCoverKey('')->coverKey);
        $d = $b->markDeleted(new DateTimeImmutable('2026-10-13'));
        $this->assertFalse($d->isActive());
        $this->assertTrue($b->isActive());
    }
}
