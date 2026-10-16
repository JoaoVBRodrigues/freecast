<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresTestDatabaseTest extends TestCase
{
    public function test_the_test_suite_uses_its_dedicated_postgresql_database(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('freecast_test', DB::connection()->getDatabaseName());
    }
}
