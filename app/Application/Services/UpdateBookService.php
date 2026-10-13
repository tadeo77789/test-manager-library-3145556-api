<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\BookRepository;
use App\Domain\Book;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\Quantity;

/** HU-03 replace: title, author, category and total (available shifts by the same difference). */
final class UpdateBookService
{
    public function __construct(private readonly BookRepository $books)
    {
    }

    public function execute(string $id, string $title, string $author, string $categoryId, int $totalCopies): Book
    {
        $book = $this->books->findActiveById($id) ?? throw new NotFound('El libro no existe.');
        $category = $this->books->findCategory($categoryId)
            ?? throw new ValidationFailed('La categoría no existe.', [['field' => 'category_id', 'issue' => 'no existe']]);
        $updated = $book->edit($title, $author, $category, Quantity::of($totalCopies));
        $this->books->save($updated);

        return $updated;
    }
}
