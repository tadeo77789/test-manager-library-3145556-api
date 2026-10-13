<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\IntegrationTestCase;

final class SchemaTest extends IntegrationTestCase
{
    private const CAT = '11111111-1111-4111-8111-111111111111';

    public function test_six_tables_without_defaults(): void
    {
        $tables = DB::select("SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema = 'library' ORDER BY 1");
        $this->assertSame(['book', 'category', 'loan', 'loan_item', 'member', 'user'], array_column($tables, 'table_name'));
        $this->assertSame([], DB::select("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = 'library' AND column_default IS NOT NULL"));
        $this->assertSame([], DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema = 'library' AND column_name IN ('created_at','updated_at','status','days_late')"));
    }

    public function test_all_timestamps_are_timestamptz(): void
    {
        $rows = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'library' AND column_name IN ('loaned_at','due_at','returned_at','deleted_at')");
        $this->assertCount(4, $rows);
        foreach ($rows as $r) {
            $this->assertSame('timestamp with time zone', $r->data_type, $r->column_name);
        }
    }

    public function test_five_categories_with_fixed_ids(): void
    {
        $rows = DB::select('SELECT id, name FROM library.category ORDER BY id');
        $this->assertCount(5, $rows);
        $this->assertSame(self::CAT, $rows[0]->id);
        $this->assertSame('Novela', $rows[0]->name);
    }

    public function test_named_constraints_and_partial_isbn_index_exist(): void
    {
        $names = array_column(DB::select("SELECT conname FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'library'"), 'conname');
        foreach (['ck_book_available_range', 'ck_book_total_positive', 'ck_user_role'] as $ck) {
            $this->assertContains($ck, $names);
        }
        $idx = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE schemaname='library' AND indexname='ux_book_isbn_active'");
        $this->assertStringContainsString('WHERE (deleted_at IS NULL)', $idx->indexdef);
    }

    public function test_fk_delete_rules(): void
    {
        $rows = DB::select("SELECT conname, confdeltype FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname='library' AND contype='f'");
        $map = array_column($rows, 'confdeltype', 'conname');
        $this->assertSame('c', $map['fk_loan_item_loan']);
        foreach (['fk_book_category', 'fk_loan_member', 'fk_loan_user', 'fk_loan_item_book'] as $fk) {
            $this->assertSame('r', $map[$fk], $fk);
        }
    }

    public function test_available_range_check_rejects_violation(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('ck_book_available_range');
        DB::insert("INSERT INTO library.book (id,title,author,isbn,category_id,total_copies,available_copies) VALUES (gen_random_uuid(),'t','a','9780307474728',?,1,2)", [self::CAT]);
    }

    public function test_total_positive_check_rejects_violation(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('ck_book_total_positive');
        DB::insert("INSERT INTO library.book (id,title,author,isbn,category_id,total_copies,available_copies) VALUES (gen_random_uuid(),'t','a','9780307474728',?,0,0)", [self::CAT]);
    }

    public function test_role_check_rejects_violation(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('ck_user_role');
        DB::insert('INSERT INTO library."user" (id,username,password_hash,role) VALUES (gen_random_uuid(),\'x\',\'h\',\'root\')');
    }
}
