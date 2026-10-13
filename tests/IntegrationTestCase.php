<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Base for tests that need the real PostgreSQL schema (migrated once per process, truncated per test). */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$migrated) {
            DB::unprepared('DROP SCHEMA IF EXISTS library CASCADE');
            DB::unprepared('DROP TABLE IF EXISTS public.migrations; DROP TABLE IF EXISTS library.migrations');
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }
        DB::unprepared('TRUNCATE library.loan_item, library.loan, library.book, library.member, library."user" CASCADE');
    }
}
