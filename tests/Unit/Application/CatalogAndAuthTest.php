<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Services\CreateBookService;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookCoverService;
use App\Application\Services\GetBookService;
use App\Application\Services\LoginService;
use App\Application\Services\RemoveBookCoverService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\SetBookCoverService;
use App\Application\Services\UpdateBookService;
use App\Domain\Errors\BookHasOpenLoans;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Errors\InvalidCredentials;
use App\Domain\Errors\IsbnTaken;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Errors\ValidationFailed;
use App\Domain\User;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeClock;
use Tests\Support\FakeHasher;
use Tests\Support\FakeTokens;
use Tests\Support\InMemoryBooks;
use Tests\Support\InMemoryCovers;
use Tests\Support\InMemoryMembers;
use Tests\Support\InMemoryUsers;
use Tests\Support\SequenceIds;

final class CatalogAndAuthTest extends TestCase
{
    private InMemoryUsers $users;
    private InMemoryBooks $books;
    private SequenceIds $ids;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
        $this->books = new InMemoryBooks();
        $this->ids = new SequenceIds();
    }

    public function test_create_librarian_normalizes_name_and_never_stores_plain_password(): void
    {
        $svc = new CreateLibrarianService($this->users, new FakeHasher(), $this->ids);
        $user = $svc->execute('Ana ', 'secret123', 'librarian');
        $this->assertSame('ana', $user->username);
        $this->assertSame('hash(secret123)', $this->users->byName['ana']->passwordHash);
        $this->expectException(UsernameTaken::class);
        $svc->execute('ANA', 'secret123', 'librarian');
    }

    public function test_create_librarian_rejects_admin_role_and_short_password(): void
    {
        $svc = new CreateLibrarianService($this->users, new FakeHasher(), $this->ids);
        try {
            $svc->execute('x', 'secret123', 'admin');
            $this->fail('expected ValidationFailed');
        } catch (ValidationFailed) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(ValidationFailed::class);
        $svc->execute('x', 'short', 'librarian');
    }

    public function test_login_success_and_uniform_failure(): void
    {
        $hasher = new FakeHasher();
        $this->users->add(User::createAdmin('u1', 'admin', $hasher->hash('pw-admin-1')));
        $login = new LoginService($this->users, $hasher, new FakeTokens());
        $token = $login->execute(' ADMIN ', 'pw-admin-1');
        $this->assertSame(28800, $token->expiresIn);
        $this->assertSame('admin', $token->role);

        foreach ([['admin', 'wrong'], ['nobody', 'pw-admin-1']] as [$u, $p]) {
            try {
                $login->execute($u, $p);
                $this->fail('expected InvalidCredentials');
            } catch (InvalidCredentials $e) {
                $this->assertSame('Credenciales inválidas.', $e->getMessage());
            }
        }
    }

    public function test_create_book_and_isbn_taken_and_unknown_category(): void
    {
        $svc = new CreateBookService($this->books, $this->ids);
        $book = $svc->execute('Título', 'Autor', '9780307474728', 'cat-1', 3);
        $this->assertSame(3, $book->availableCopies);
        try {
            $svc->execute('Otro', 'Autor', '9780307474728', 'cat-1', 1);
            $this->fail('expected IsbnTaken');
        } catch (IsbnTaken) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(ValidationFailed::class);
        $svc->execute('Otro', 'Autor', '9780307474729', 'nope', 1);
    }

    public function test_search_filters_by_text_and_category_and_hides_deleted(): void
    {
        $create = new CreateBookService($this->books, $this->ids);
        $create->execute('Cien años', 'García', '9780000000001', 'cat-1', 1);
        $a = $create->execute('Cosmos', 'Sagan', '9780000000002', 'cat-2', 1);
        $search = new SearchBooksService($this->books);
        $this->assertSame(1, $search->execute('sagan', null, 1, 20)->total);
        $this->assertSame(1, $search->execute(null, 'cat-1', 1, 20)->total);
        (new DeleteBookService($this->books, FakeClock::at('2026-10-14T00:00:00Z')))->execute($a->id);
        $this->assertSame(1, $search->execute(null, null, 1, 20)->total);
        $this->expectException(NotFound::class);
        (new GetBookService($this->books))->execute($a->id);
    }

    public function test_delete_refused_with_open_loans(): void
    {
        $b = (new CreateBookService($this->books, $this->ids))->execute('T', 'A', '9780000000001', 'cat-1', 1);
        $this->books->openLoans = true;
        $this->expectException(BookHasOpenLoans::class);
        (new DeleteBookService($this->books, FakeClock::at('2026-10-14T00:00:00Z')))->execute($b->id);
    }

    public function test_update_book_shifts_available(): void
    {
        $b = (new CreateBookService($this->books, $this->ids))->execute('T', 'A', '9780000000001', 'cat-1', 2);
        $u = (new UpdateBookService($this->books))->execute($b->id, 'T2', 'A2', 'cat-2', 5);
        $this->assertSame(5, $u->availableCopies);
        $this->assertSame('Ciencia', $u->category->name);
    }

    public function test_cover_lifecycle_key_is_null_without_cover_and_binary_removed(): void
    {
        $covers = new InMemoryCovers();
        $b = (new CreateBookService($this->books, $this->ids))->execute('T', 'A', '9780000000001', 'cat-1', 2);
        $this->assertNull($b->coverKey);
        $set = (new SetBookCoverService($this->books, $covers))->execute($b->id, 'PNGBYTES');
        $this->assertNotNull($set->coverKey);
        $this->assertSame('PNGBYTES', (new GetBookCoverService($this->books, $covers))->execute($b->id)->bytes);
        (new RemoveBookCoverService($this->books, $covers))->execute($b->id);
        $this->assertNull($this->books->items[$b->id]->coverKey);
        $this->assertSame([], $covers->files);
        $this->expectException(NotFound::class);
        (new GetBookCoverService($this->books, $covers))->execute($b->id);
    }

    public function test_duplicate_member_document_is_rejected(): void
    {
        $svc = new CreateMemberService(new InMemoryMembers(), $this->ids);
        $svc->execute('Luis', '123');
        $this->expectException(DocumentTaken::class);
        $svc->execute('Otro', '123');
    }
}
