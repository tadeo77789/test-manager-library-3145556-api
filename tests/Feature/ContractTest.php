<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Ports\Clock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\IntegrationTestCase;
use Tests\Support\FakeClock;

/** T-16 / T-17: walks the routes and error codes of api-contract.md against real PostgreSQL. */
final class ContractTest extends IntegrationTestCase
{
    private const NOVELA = '11111111-1111-4111-8111-111111111111';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private FakeClock $clock;
    private string $admin;
    private string $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = FakeClock::at('2026-10-14T14:15:00Z');
        $this->app->instance(Clock::class, $this->clock);
        $this->artisan('library:create-admin')->assertSuccessful();
        $this->admin = $this->login('admin', 'admin-password-1');
        $this->postJson('/api/v1/users', ['username' => 'Ana ', 'password' => 'ana-password-1', 'role' => 'librarian'], $this->h($this->admin))->assertCreated();
        $this->librarian = $this->login('ana', 'ana-password-1');
    }


    private function h(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function login(string $u, string $p): string
    {
        return $this->postJson('/api/v1/auth/login', ['username' => $u, 'password' => $p])->assertOk()->json('access_token');
    }

    private function assertError(\Illuminate\Testing\TestResponse $r, int $status, string $code): void
    {
        $r->assertStatus($status)->assertJsonStructure(['code', 'message', 'details'])->assertJsonPath('code', $code);
        $this->assertIsArray($r->json('details'));
        $this->assertStringNotContainsString('vendor', $r->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
    }

    private function book(string $n, int $copies = 2): string
    {
        return $this->postJson('/api/v1/books', [
            'title' => 'Libro ' . $n, 'author' => 'Autor', 'isbn' => '978000000000' . $n, 'category_id' => self::NOVELA, 'total_copies' => $copies,
        ], $this->h($this->admin))->assertCreated()->json('id');
    }

    private function member(string $doc): string
    {
        return $this->postJson('/api/v1/members', ['full_name' => 'Socio ' . $doc, 'document_number' => $doc], $this->h($this->librarian))->assertCreated()->json('id');
    }

    public function test_admin_bootstrap_is_idempotent_and_login_response_shape(): void
    {
        $this->artisan('library:create-admin')->assertSuccessful();
        $this->assertSame(2, DB::table('library.user')->count());
        $r = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'admin-password-1'])->assertOk();
        $r->assertJsonPath('token_type', 'Bearer')->assertJsonPath('expires_in', 28800)->assertJsonPath('role', 'admin');
        $this->assertStringNotContainsString('password', $r->getContent());
    }

    public function test_login_failures_are_uniform_401(): void
    {
        $a = $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'bad']);
        $b = $this->postJson('/api/v1/auth/login', ['username' => 'ghost', 'password' => 'bad']);
        $this->assertError($a, 401, 'INVALID_CREDENTIALS');
        $this->assertSame($a->json('message'), $b->json('message'));
        $this->assertError($this->postJson('/api/v1/auth/login', []), 422, 'VALIDATION_ERROR');
    }

    public function test_token_and_role_enforcement(): void
    {
        $this->assertError($this->getJson('/api/v1/books'), 401, 'UNAUTHENTICATED');
        $this->assertError($this->getJson('/api/v1/books', $this->h('garbage')), 401, 'UNAUTHENTICATED');
        $this->assertError($this->postJson('/api/v1/books', [], $this->h($this->librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertError($this->postJson('/api/v1/users', [], $this->h($this->librarian)), 403, 'FORBIDDEN_ROLE');
        $this->assertError($this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->h($this->librarian)), 403, 'FORBIDDEN_ROLE');
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_users_rules(): void
    {
        $h = $this->h($this->admin);
        $this->assertError($this->postJson('/api/v1/users', ['username' => 'ANA', 'password' => 'ana-password-1', 'role' => 'librarian'], $h), 409, 'USERNAME_TAKEN');
        $this->assertError($this->postJson('/api/v1/users', ['username' => 'root', 'password' => 'ana-password-1', 'role' => 'admin'], $h), 422, 'VALIDATION_ERROR');
        $r = $this->postJson('/api/v1/users', ['username' => 'luis', 'password' => 'luis-password-1', 'role' => 'librarian'], $h)->assertCreated();
        $this->assertSame(['id', 'username', 'role'], array_keys($r->json()));
    }

    public function test_books_crud_search_pagination_and_errors(): void
    {
        $h = $this->h($this->admin);
        $id = $this->book('1', 4);
        $this->assertError($this->postJson('/api/v1/books', ['title' => 'x', 'author' => 'y', 'isbn' => '9780000000001', 'category_id' => self::NOVELA, 'total_copies' => 1], $h), 409, 'ISBN_TAKEN');
        $this->assertError($this->postJson('/api/v1/books', ['title' => 'x', 'author' => 'y', 'isbn' => '123', 'category_id' => self::NOVELA, 'total_copies' => 1], $h), 422, 'VALIDATION_ERROR');
        $this->assertError($this->postJson('/api/v1/books', ['title' => 'x', 'author' => 'y', 'isbn' => '9780000000099', 'category_id' => self::NOVELA, 'total_copies' => 0], $h), 422, 'VALIDATION_ERROR');

        $this->getJson("/api/v1/books/$id", $this->h($this->librarian))->assertOk()
            ->assertJsonPath('available_copies', 4)->assertJsonPath('category.name', 'Novela')->assertJsonPath('cover_key', null);
        $this->putJson("/api/v1/books/$id", ['title' => 'Nuevo', 'author' => 'A', 'category_id' => self::NOVELA, 'total_copies' => 6], $h)
            ->assertOk()->assertJsonPath('available_copies', 6);

        $list = $this->getJson('/api/v1/books?q=nuev&page=1&size=10', $this->h($this->librarian))->assertOk();
        $this->assertSame(['items', 'page', 'size', 'total'], array_keys($list->json()));
        $this->assertSame(1, $list->json('total'));
        $this->assertError($this->getJson('/api/v1/books?size=51', $h), 422, 'VALIDATION_ERROR');
        $this->assertCount(5, $this->getJson('/api/v1/categories', $h)->assertOk()->json());

        $this->deleteJson("/api/v1/books/$id", [], $h)->assertNoContent();
        $this->assertError($this->getJson("/api/v1/books/$id", $h), 404, 'NOT_FOUND');
        $this->assertError($this->getJson('/api/v1/books/not-a-uuid', $h), 404, 'NOT_FOUND');
    }

    public function test_delete_book_with_open_loan_is_409(): void
    {
        $b = $this->book('1');
        $m = $this->member('10');
        $this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => [['book_id' => $b]]], $this->h($this->librarian))->assertCreated();
        $this->assertError($this->deleteJson("/api/v1/books/$b", [], $this->h($this->admin)), 409, 'BOOK_HAS_OPEN_LOANS');
    }

    public function test_members(): void
    {
        $h = $this->h($this->librarian);
        $id = $this->member('55');
        $this->assertError($this->postJson('/api/v1/members', ['full_name' => 'Otro', 'document_number' => '55'], $h), 409, 'DOCUMENT_TAKEN');
        $this->assertError($this->postJson('/api/v1/members', ['full_name' => '', 'document_number' => '56'], $h), 422, 'VALIDATION_ERROR');
        $this->getJson("/api/v1/members/$id", $h)->assertOk()->assertJsonPath('active_loans', 0)->assertJsonPath('document_number', '55');
        $this->assertSame(1, $this->getJson('/api/v1/members?q=55', $h)->assertOk()->json('total'));
        $this->assertError($this->getJson('/api/v1/members/' . self::NOVELA, $h), 404, 'NOT_FOUND');
    }

    public function test_full_loan_flow_with_all_business_errors(): void
    {
        $h = $this->h($this->librarian);
        [$b1, $b2, $b3, $b4] = [$this->book('1'), $this->book('2'), $this->book('3'), $this->book('4', 1)];
        $m = $this->member('100');

        $created = $this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => [['book_id' => $b1], ['book_id' => $b2]]], $h)
            ->assertCreated()->assertJsonPath('status', 'open')->assertJsonPath('items.0.due_at', '2026-10-28T14:15:00Z');
        $loanId = $created->json('id');
        $itemId = $created->json('items.0.id');

        $this->assertError($this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => [['book_id' => $b3], ['book_id' => $b4]]], $h), 422, 'MEMBER_LIMIT_EXCEEDED');
        $this->assertError($this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => [['book_id' => $b3], ['book_id' => $b3]]], $h), 422, 'DUPLICATE_BOOK_IN_LOAN');
        $this->assertError($this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => []], $h), 422, 'VALIDATION_ERROR');

        $other = $this->member('200');
        $this->postJson('/api/v1/loans', ['member_id' => $other, 'items' => [['book_id' => $b4]]], $h)->assertCreated();
        $third = $this->member('300');
        $this->assertError($this->postJson('/api/v1/loans', ['member_id' => $third, 'items' => [['book_id' => $b4]]], $h), 409, 'BOOK_NOT_AVAILABLE');
        $this->assertSame(1, DB::table('library.book')->where('id', $b4)->value('available_copies') === 0 ? 1 : 0);

        $this->getJson("/api/v1/loans/$loanId", $h)->assertOk()->assertJsonPath('status', 'open');
        $this->assertSame(2, $this->getJson("/api/v1/loans?member_id=$m&status=open", $h)->assertOk()->json('items.0.items') !== null ? 2 : 0);
        $this->assertError($this->getJson('/api/v1/loans?from=2026-10-10&to=2026-10-01', $h), 422, 'INVALID_DATE_RANGE');
        $this->assertError($this->getJson('/api/v1/loans?status=weird', $h), 422, 'VALIDATION_ERROR');

        // overdue
        $this->clock->advanceDays(16);
        $this->assertError($this->getJson('/api/v1/loans', $h), 401, 'UNAUTHENTICATED'); // 8h token expired
        $h = $this->h($this->login('ana', 'ana-password-1'));
        $this->assertError($this->postJson('/api/v1/loans', ['member_id' => $m, 'items' => [['book_id' => $b3]]], $h), 409, 'MEMBER_HAS_OVERDUE');
        $od = $this->getJson('/api/v1/loans/overdue', $h)->assertOk();
        $this->assertGreaterThanOrEqual(2, $od->json('total'));
        $this->assertSame(['loan_id', 'item_id', 'book_title', 'member_name', 'due_at', 'days_late'], array_keys($od->json('items.0')));
        $this->assertSame(2, $od->json('items.0.days_late'));
        $this->getJson("/api/v1/loans/$loanId", $h)->assertJsonPath('status', 'overdue');

        // return
        $ret = $this->postJson("/api/v1/loans/$loanId/items/$itemId/return", [], $h)->assertOk();
        $this->assertNotNull($ret->json('returned_at'));
        $this->assertError($this->postJson("/api/v1/loans/$loanId/items/$itemId/return", [], $h), 409, 'ITEM_ALREADY_RETURNED');
        $this->assertSame(2, (int) DB::table('library.book')->where('id', $b1)->value('available_copies'));
        $this->assertError($this->postJson("/api/v1/loans/$loanId/items/" . self::NOVELA . '/return', [], $h), 404, 'NOT_FOUND');
        $this->assertError($this->getJson('/api/v1/loans/' . self::NOVELA, $h), 404, 'NOT_FOUND');
    }

    public function test_report_contract(): void
    {
        $b = $this->book('1', 3);
        $this->postJson('/api/v1/loans', ['member_id' => $this->member('1'), 'items' => [['book_id' => $b]]], $this->h($this->librarian))->assertCreated();
        $this->putJson("/api/v1/books/$b", ['title' => 'Renombrado', 'author' => 'A', 'category_id' => '22222222-2222-4222-8222-222222222222', 'total_copies' => 3], $this->h($this->admin))->assertOk();

        $r = $this->getJson('/api/v1/reports/most-borrowed?from=2026-10-01&to=2026-10-31', $this->h($this->admin))->assertOk();
        $r->assertJsonPath('from', '2026-10-01')->assertJsonPath('to', '2026-10-31')
            ->assertJsonPath('rows.0.book_title', 'Libro 1')->assertJsonPath('rows.0.category_name', 'Novela')->assertJsonPath('rows.0.times_borrowed', 1);
        $this->assertArrayNotHasKey('member_id', $r->json('rows.0'));
        $this->assertError($this->getJson('/api/v1/reports/most-borrowed?from=2026-10-31&to=2026-10-01', $this->h($this->admin)), 422, 'INVALID_DATE_RANGE');
        $this->assertError($this->getJson('/api/v1/reports/most-borrowed', $this->h($this->admin)), 422, 'VALIDATION_ERROR');
        $this->getJson('/api/v1/reports/most-borrowed?from=2027-01-01&to=2027-01-31', $this->h($this->admin))->assertOk()->assertJsonPath('rows', []);
    }

    public function test_cover_upload_download_and_remove(): void
    {
        $h = $this->h($this->admin);
        $id = $this->book('1');
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(self::PNG));
        $file = new UploadedFile($path, 'cover.png', 'image/png', null, true);
        $key = $this->call('PUT', "/api/v1/books/$id/cover", [], [], ['file' => $file], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->admin, 'HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->json('cover_key');
        $this->assertNotNull($key);
        $get = $this->get("/api/v1/books/$id/cover", $this->h($this->librarian))->assertOk();
        $this->assertSame('image/png', $get->headers->get('Content-Type'));

        // a text file renamed .png is rejected by content
        $bad = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($bad, 'not an image at all');
        $r = $this->call('PUT', "/api/v1/books/$id/cover", [], [], ['file' => new UploadedFile($bad, 'fake.png', 'image/png', null, true)], ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->admin, 'HTTP_ACCEPT' => 'application/json']);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertError($this->putJson("/api/v1/books/$id/cover", [], $h), 422, 'VALIDATION_ERROR');

        $this->deleteJson("/api/v1/books/$id/cover", [], $h)->assertNoContent();
        $this->assertError($this->getJson("/api/v1/books/$id/cover", $h), 404, 'NOT_FOUND');
        $this->assertNull($this->getJson("/api/v1/books/$id", $h)->json('cover_key'));
    }

    public function test_cors_preflight_and_headers(): void
    {
        $r = $this->call('OPTIONS', '/api/v1/books', [], [], [], ['HTTP_ORIGIN' => 'http://localhost:5173']);
        $r->assertStatus(204);
        $this->assertSame('http://localhost:5173', $r->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('Authorization', $r->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_unknown_route_and_method_use_single_body(): void
    {
        $this->assertError($this->getJson('/api/v1/nope'), 404, 'NOT_FOUND');
        $this->deleteJson('/api/v1/health')->assertStatus(405)->assertJsonStructure(['code', 'message', 'details']);
    }
}
