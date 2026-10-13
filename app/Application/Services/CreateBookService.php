<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Application\Ports\IdGenerator;
use App\Domain\Book;
use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;

/** HU-03 create. The unique ISBN among active books is enforced by the database and surfaced as ISBN_TAKEN. */
final class CreateBookService
{
    public function __construct(private readonly BookRepository $books, private readonly IdGenerator $ids)
    {
    }

    public function execute(string $title, string $author, string $isbn, string $categoryId, int $totalCopies): Book
    {
        $category = $this->books->findCategory($categoryId)
            ?? throw new ValidationFailed('La categoría no existe.', [['field' => 'category_id', 'issue' => 'no existe']]);
        $book = Book::create($this->ids->next(), $title, $author, Isbn::fromString($isbn), $category, Quantity::of($totalCopies));
        $this->books->add($book);

        return $book;
    }
}
