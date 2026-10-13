<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Application\Dto\Page;
use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Errors\IsbnTaken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgBookRepository implements BookRepository
{
    public function categories(): array
    {
        return array_map(
            static fn ($r): Category => new Category($r->id, $r->name),
            DB::table('library.category')->orderBy('name')->get()->all(),
        );
    }

    public function findCategory(string $id): ?Category
    {
        if (! $this->isUuid($id)) {
            return null;
        }
        $r = DB::table('library.category')->where('id', $id)->first();

        return $r === null ? null : new Category($r->id, $r->name);
    }

    public function add(Book $book): void
    {
        try {
            DB::table('library.book')->insert($this->columns($book) + ['id' => $book->id]);
        } catch (QueryException $e) {
            if (Rows::isUniqueViolation($e, 'ux_book_isbn_active')) {
                throw new IsbnTaken();
            }
            throw $e;
        }
    }

    public function save(Book $book): void
    {
        $cols = $this->columns($book);
        unset($cols['isbn'], $cols['available_copies']);
        // The available count shifts by the change of total, relative to the stored row (safe under concurrency).
        DB::table('library.book')->where('id', $book->id)->update($cols + [
            'available_copies' => DB::raw('available_copies + (' . (int) $book->totalCopies . ' - total_copies)'),
        ]);
    }

    public function findActiveById(string $id): ?Book
    {
        if (! $this->isUuid($id)) {
            return null;
        }
        $r = $this->base()->where('b.id', $id)->whereNull('b.deleted_at')->first();

        return $r === null ? null : Rows::book($r);
    }

    public function findActiveByIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, fn ($i): bool => is_string($i) && $this->isUuid($i)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->base()->whereIn('b.id', $ids)->whereNull('b.deleted_at')->get() as $r) {
            $out[$r->id] = Rows::book($r);
        }

        return $out;
    }

    public function search(?string $q, ?string $categoryId, int $page, int $size): Page
    {
        $query = $this->base()->whereNull('b.deleted_at');
        if ($categoryId !== null) {
            $query->where('b.category_id', $this->isUuid($categoryId) ? $categoryId : '00000000-0000-0000-0000-000000000000');
        }
        if ($q !== null) {
            $like = Rows::likeEscape($q);
            $query->where(static fn ($w) => $w->where('b.title', 'ilike', $like)->orWhere('b.author', 'ilike', $like));
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('b.title')->orderBy('b.id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page(array_map([Rows::class, 'book'], $rows->all()), $page, $size, $total);
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return DB::table('library.loan_item')->where('book_id', $bookId)->whereNull('returned_at')->exists();
    }

    private function base(): \Illuminate\Database\Query\Builder
    {
        return DB::table('library.book as b')
            ->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->select('b.*', 'c.name as category_name');
    }

    /** @return array<string, mixed> */
    private function columns(Book $b): array
    {
        return [
            'title' => $b->title,
            'author' => $b->author,
            'isbn' => $b->isbn->value,
            'category_id' => $b->category->id,
            'total_copies' => $b->totalCopies,
            'available_copies' => $b->availableCopies,
            'cover_key' => $b->coverKey,
            'deleted_at' => Rows::fromUtc($b->deletedAt),
        ];
    }

    private function isUuid(string $v): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) === 1;
    }
}
