<?php

declare(strict_types=1);

namespace App\Adapters\Http;

use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Domain\Book;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Member;
use DateTimeImmutable;
use DateTimeZone;

/** Domain -> JSON shapes of api-contract.md (snake_case; never password or password_hash). */
final class Presenter
{
    public static function date(?DateTimeImmutable $d): ?string
    {
        return $d?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @return array<string, mixed> */
    public static function book(Book $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'author' => $b->author,
            'isbn' => $b->isbn->value,
            'category' => ['id' => $b->category->id, 'name' => $b->category->name],
            'total_copies' => $b->totalCopies,
            'available_copies' => $b->availableCopies,
            'cover_key' => $b->coverKey,
        ];
    }

    /** @return array<string, mixed> */
    public static function member(Member $m): array
    {
        return ['id' => $m->id, 'full_name' => $m->fullName, 'document_number' => $m->documentNumber->value];
    }

    /** @return array<string, mixed> */
    public static function item(LoanItem $i): array
    {
        return [
            'id' => $i->id,
            'book_id' => $i->bookId,
            'book_title' => $i->bookTitle,
            'isbn' => $i->isbn,
            'category_name' => $i->categoryName,
            'due_at' => self::date($i->dueAt),
            'returned_at' => self::date($i->returnedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function loan(Loan $l, DateTimeImmutable $now): array
    {
        return [
            'id' => $l->id,
            'member_id' => $l->memberId,
            'loaned_at' => self::date($l->loanedAt),
            'status' => $l->status($now),
            'items' => array_map([self::class, 'item'], $l->items),
        ];
    }

    /** @return array<string, mixed> */
    public static function overdue(OverdueLine $o): array
    {
        return [
            'loan_id' => $o->loanId,
            'item_id' => $o->itemId,
            'book_title' => $o->bookTitle,
            'member_name' => $o->memberName,
            'due_at' => self::date($o->dueAt),
            'days_late' => $o->daysLate,
        ];
    }

    /** @return array<string, mixed> */
    public static function page(Page $p, callable $map): array
    {
        return ['items' => array_map($map, $p->items), 'page' => $p->page, 'size' => $p->size, 'total' => $p->total];
    }
}
