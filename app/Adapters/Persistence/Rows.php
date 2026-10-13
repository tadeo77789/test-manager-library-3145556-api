<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Domain\Book;
use App\Domain\Category;
use App\Domain\LoanItem;
use App\Domain\ValueObject\Isbn;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use stdClass;

/** Row <-> domain mapping helpers shared by the PostgreSQL adapters. */
final class Rows
{
    public static function toUtc(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    public static function fromUtc(?DateTimeImmutable $value): ?string
    {
        return $value?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    /** Row must carry the book columns plus category_name. */
    public static function book(stdClass $r): Book
    {
        return new Book(
            $r->id,
            $r->title,
            $r->author,
            Isbn::fromString(trim($r->isbn)),
            new Category($r->category_id, $r->category_name),
            (int) $r->total_copies,
            (int) $r->available_copies,
            $r->cover_key,
            self::toUtc($r->deleted_at),
        );
    }

    public static function item(stdClass $r): LoanItem
    {
        return new LoanItem(
            $r->id,
            $r->book_id,
            $r->book_title,
            trim($r->isbn),
            $r->category_name,
            self::toUtc($r->due_at),
            self::toUtc($r->returned_at),
        );
    }

    public static function isUniqueViolation(QueryException $e, string $constraint): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505' && str_contains($e->getMessage(), $constraint);
    }

    public static function likeEscape(string $text): string
    {
        return '%' . addcslashes($text, '\\%_') . '%';
    }
}
