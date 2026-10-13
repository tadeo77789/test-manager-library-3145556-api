<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Errors\BookNotAvailable;
use App\Domain\Errors\ValidationFailed;
use App\Domain\ValueObject\Isbn;
use App\Domain\ValueObject\Quantity;
use DateTimeImmutable;

/** Immutable aggregate: every operation returns a new Book. Invariant: 0 <= available <= total, total >= 1. */
final readonly class Book
{
    public function __construct(
        public string $id,
        public string $title,
        public string $author,
        public Isbn $isbn,
        public Category $category,
        public int $totalCopies,
        public int $availableCopies,
        public ?string $coverKey = null,
        public ?DateTimeImmutable $deletedAt = null,
    ) {
        if ($totalCopies < 1) {
            throw new ValidationFailed('El total de ejemplares debe ser al menos 1.', [['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']]);
        }
        if ($availableCopies < 0 || $availableCopies > $totalCopies) {
            throw new \DomainException('Available copies out of range (0..total).');
        }
    }

    public static function create(string $id, string $title, string $author, Isbn $isbn, Category $category, Quantity $total): self
    {
        return new self($id, self::cleanTitle($title), self::cleanAuthor($author), $isbn, $category, $total->value, $total->value);
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    public function withdraw(): self
    {
        if ($this->availableCopies < 1) {
            throw new BookNotAvailable(
                sprintf('No quedan ejemplares disponibles de «%s».', $this->title),
                [['field' => 'book_id', 'issue' => 'sin ejemplares']],
            );
        }
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category, $this->totalCopies, $this->availableCopies - 1, $this->coverKey, $this->deletedAt);
    }

    public function restock(): self
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new \DomainException('Cannot restock above total copies.');
        }
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category, $this->totalCopies, $this->availableCopies + 1, $this->coverKey, $this->deletedAt);
    }

    /** Replaces title, author, category and total; available shifts by the same difference and never goes below 0. */
    public function edit(string $title, string $author, Category $category, Quantity $total): self
    {
        $available = $this->availableCopies + ($total->value - $this->totalCopies);
        if ($available < 0) {
            throw new ValidationFailed('El nuevo total deja ejemplares disponibles negativos.', [['field' => 'total_copies', 'issue' => 'menor que los ejemplares prestados']]);
        }
        return new self($this->id, self::cleanTitle($title), self::cleanAuthor($author), $this->isbn, $category, $total->value, $available, $this->coverKey, $this->deletedAt);
    }

    public function markDeleted(DateTimeImmutable $now): self
    {
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category, $this->totalCopies, $this->availableCopies, $this->coverKey, $now);
    }

    /** A null or empty key means "no cover": it is always stored as null, never as an empty string. */
    public function withCoverKey(?string $coverKey): self
    {
        $key = ($coverKey === null || $coverKey === '') ? null : $coverKey;
        return new self($this->id, $this->title, $this->author, $this->isbn, $this->category, $this->totalCopies, $this->availableCopies, $key, $this->deletedAt);
    }

    private static function cleanTitle(string $title): string
    {
        $t = trim($title);
        if ($t === '' || mb_strlen($t) > 250) {
            throw new ValidationFailed('El título es obligatorio (máximo 250 caracteres).', [['field' => 'title', 'issue' => 'obligatorio, máximo 250 caracteres']]);
        }
        return $t;
    }

    private static function cleanAuthor(string $author): string
    {
        $a = trim($author);
        if ($a === '' || mb_strlen($a) > 200) {
            throw new ValidationFailed('El autor es obligatorio (máximo 200 caracteres).', [['field' => 'author', 'issue' => 'obligatorio, máximo 200 caracteres']]);
        }
        return $a;
    }
}
