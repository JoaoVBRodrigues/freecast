<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Runs before every test, including any upload, rather than relying on test order.
        if (config('database.default') !== 'pgsql' || \Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'freecast_test') {
            throw new \RuntimeException('Tests require PostgreSQL freecast_test.');
        }
        $root = str_replace('\\', '/', config('filesystems.disks.local.root'));
        if (! str_contains($root, 'storage/app/testing/')) {
            throw new \RuntimeException('Tests require an isolated private storage root.');
        }
    }
}
