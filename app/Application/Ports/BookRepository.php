<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\Page;
use App\Domain\Book;
use App\Domain\Category;

interface BookRepository
{
    /** @return list<Category> ordered by name */
    public function categories(): array;

    public function findCategory(string $id): ?Category;

    /** @throws \App\Domain\Errors\IsbnTaken */
    public function add(Book $book): void;

    /** Persists title, author, category, totals, cover_key and deleted_at (lending goes through LoanRepository). */
    public function save(Book $book): void;

    /** Active books only (deleted_at IS NULL). */
    public function findActiveById(string $id): ?Book;

    /**
     * @param list<string> $ids
     * @return array<string, Book> active books keyed by id
     */
    public function findActiveByIds(array $ids): array;

    /** @return Page<Book> */
    public function search(?string $q, ?string $categoryId, int $page, int $size): Page;

    public function hasOpenLoans(string $bookId): bool;
}
