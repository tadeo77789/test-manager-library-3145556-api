<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Dto\BookChange;
use App\Application\Dto\IssuedToken;
use App\Application\Dto\LoanSearch;
use App\Application\Dto\OverdueLine;
use App\Application\Dto\Page;
use App\Application\Dto\ReportRow;
use App\Application\Dto\StoredCover;
use App\Application\Dto\TokenClaims;
use App\Application\Errors\ConcurrentUpdate;
use App\Application\Ports\BookRepository;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Domain\Book;
use App\Domain\Category;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Errors\IsbnTaken;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\ValueObject\DateRange;
use DateTimeImmutable;

final class SequenceIds implements IdGenerator
{
    private int $n = 0;

    public function next(): string
    {
        return 'id-' . (++$this->n);
    }
}

final class FakeHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return 'hash(' . $plain . ')';
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash === $this->hash($plain);
    }
}

final class FakeTokens implements TokenIssuer
{
    public function issue(User $user): IssuedToken
    {
        return new IssuedToken('token-' . $user->id, 28800, $user->role);
    }

    public function verify(string $token): ?TokenClaims
    {
        return null;
    }
}

final class InMemoryUsers implements UserRepository
{
    /** @var array<string, User> */
    public array $byName = [];

    public function add(User $user): void
    {
        if (isset($this->byName[$user->username])) {
            throw new UsernameTaken();
        }
        $this->byName[$user->username] = $user;
    }

    public function findByUsername(string $normalizedUsername): ?User
    {
        return $this->byName[$normalizedUsername] ?? null;
    }
}

final class InMemoryMembers implements MemberRepository
{
    /** @var array<string, Member> */
    public array $items = [];

    public function add(Member $member): void
    {
        foreach ($this->items as $m) {
            if ($m->documentNumber->value === $member->documentNumber->value) {
                throw new DocumentTaken();
            }
        }
        $this->items[$member->id] = $member;
    }

    public function findById(string $id): ?Member
    {
        return $this->items[$id] ?? null;
    }

    public function search(?string $q, int $page, int $size): Page
    {
        $all = array_values(array_filter($this->items, static fn (Member $m): bool => $q === null
            || stripos($m->fullName, $q) !== false || stripos($m->documentNumber->value, $q) !== false));

        return new Page(array_slice($all, ($page - 1) * $size, $size), $page, $size, count($all));
    }
}

final class InMemoryBooks implements BookRepository
{
    /** @var array<string, Book> */
    public array $items = [];
    public bool $openLoans = false;

    /** @var list<Category> */
    public array $categories = [];

    public function __construct()
    {
        $this->categories = [new Category('cat-1', 'Novela'), new Category('cat-2', 'Ciencia')];
    }

    public function categories(): array
    {
        return $this->categories;
    }

    public function findCategory(string $id): ?Category
    {
        foreach ($this->categories as $c) {
            if ($c->id === $id) {
                return $c;
            }
        }
        return null;
    }

    public function add(Book $book): void
    {
        foreach ($this->items as $b) {
            if ($b->isActive() && $b->isbn->value === $book->isbn->value) {
                throw new IsbnTaken();
            }
        }
        $this->items[$book->id] = $book;
    }

    public function save(Book $book): void
    {
        $this->items[$book->id] = $book;
    }

    public function findActiveById(string $id): ?Book
    {
        $b = $this->items[$id] ?? null;
        return $b !== null && $b->isActive() ? $b : null;
    }

    public function findActiveByIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (($b = $this->findActiveById($id)) !== null) {
                $out[$id] = $b;
            }
        }
        return $out;
    }

    public function search(?string $q, ?string $categoryId, int $page, int $size): Page
    {
        $all = array_filter($this->items, static fn (Book $b): bool => $b->isActive()
            && ($categoryId === null || $b->category->id === $categoryId)
            && ($q === null || stripos($b->title, $q) !== false || stripos($b->author, $q) !== false));
        usort($all, static fn (Book $a, Book $b): int => strcmp($a->title, $b->title));

        return new Page(array_slice($all, ($page - 1) * $size, $size), $page, $size, count($all));
    }

    public function hasOpenLoans(string $bookId): bool
    {
        return $this->openLoans;
    }
}

final class InMemoryLoans implements LoanRepository
{
    /** @var array<string, Loan> */
    public array $items = [];
    /** Optional hook: runs inside create() before the optimistic check, to simulate a concurrent writer. */
    public ?\Closure $beforeCreate = null;

    public function __construct(private readonly InMemoryBooks $books, private readonly InMemoryMembers $members)
    {
    }

    public function create(Loan $loan, array $changes): void
    {
        if ($this->beforeCreate !== null) {
            ($this->beforeCreate)();
            $this->beforeCreate = null;
        }
        foreach ($changes as $change) {
            /** @var BookChange $change */
            $stored = $this->books->items[$change->before->id];
            if ($stored->availableCopies !== $change->before->availableCopies) {
                throw new ConcurrentUpdate();
            }
        }
        foreach ($changes as $change) {
            $this->books->items[$change->after->id] = $change->after;
        }
        $this->items[$loan->id] = $loan;
    }

    public function saveReturn(string $loanId, LoanItem $returned, Book $restocked): void
    {
        $loan = $this->items[$loanId];
        if ($loan->item($returned->id)?->isReturned()) {
            throw new ItemAlreadyReturned();
        }
        $items = array_map(static fn (LoanItem $i): LoanItem => $i->id === $returned->id ? $returned : $i, $loan->items);
        $this->items[$loanId] = new Loan($loan->id, $loan->memberId, $loan->loanedByUserId, $loan->loanedAt, array_values($items));
        $this->books->items[$restocked->id] = $restocked;
    }

    public function findById(string $id): ?Loan
    {
        return $this->items[$id] ?? null;
    }

    public function search(LoanSearch $search, DateTimeImmutable $now): Page
    {
        $all = array_values(array_filter($this->items, static fn (Loan $l): bool => ($search->memberId === null || $l->memberId === $search->memberId)
            && ($search->status === null || $l->status($now) === $search->status)
            && ($search->fromInclusive === null || $l->loanedAt >= $search->fromInclusive)
            && ($search->toExclusive === null || $l->loanedAt < $search->toExclusive)));

        return new Page(array_slice($all, ($search->page - 1) * $search->size, $search->size), $search->page, $search->size, count($all));
    }

    public function countUnreturnedByMember(string $memberId): int
    {
        $n = 0;
        foreach ($this->items as $l) {
            if ($l->memberId === $memberId) {
                $n += count(array_filter($l->items, static fn (LoanItem $i): bool => ! $i->isReturned()));
            }
        }
        return $n;
    }

    public function hasOverdueByMember(string $memberId, DateTimeImmutable $now): bool
    {
        foreach ($this->items as $l) {
            if ($l->memberId === $memberId && $l->status($now) === Loan::STATUS_OVERDUE) {
                return true;
            }
        }
        return false;
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        $out = [];
        foreach ($this->items as $l) {
            foreach ($l->items as $i) {
                if ($i->isOverdue($now)) {
                    $out[] = new OverdueLine($l->id, $i->id, $i->bookTitle, $this->members->items[$l->memberId]->fullName, $i->dueAt, $i->daysLate($now));
                }
            }
        }
        return $out;
    }
}

final class InMemoryReport implements ReportReader
{
    public function __construct(private readonly InMemoryLoans $loans)
    {
    }

    public function mostBorrowed(DateRange $range): array
    {
        $acc = [];
        foreach ($this->loans->items as $l) {
            if ($l->loanedAt < $range->from || $l->loanedAt >= $range->toExclusive) {
                continue;
            }
            foreach ($l->items as $i) {
                $key = $i->bookId . '|' . $i->bookTitle . '|' . $i->categoryName;
                $acc[$key] = new ReportRow($i->bookId, $i->bookTitle, $i->categoryName, ($acc[$key]->timesBorrowed ?? 0) + 1);
            }
        }
        $rows = array_values($acc);
        usort($rows, static fn (ReportRow $a, ReportRow $b): int => [$b->timesBorrowed, $a->bookTitle] <=> [$a->timesBorrowed, $b->bookTitle]);

        return $rows;
    }
}

final class InMemoryCovers implements CoverStorage
{
    /** @var array<string, string> */
    public array $files = [];

    public function store(string $bytes): string
    {
        $key = 'cover-' . (count($this->files) + 1);
        $this->files[$key] = $bytes;
        return $key;
    }

    public function read(string $key): ?StoredCover
    {
        return isset($this->files[$key]) ? new StoredCover($this->files[$key], 'image/png') : null;
    }

    public function delete(string $key): void
    {
        unset($this->files[$key]);
    }
}
