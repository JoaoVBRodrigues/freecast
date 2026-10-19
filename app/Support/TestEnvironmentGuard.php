<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TestEnvironmentGuard
{
    public static function assert(): void
    {
        $connection = DB::connection();
        $root = str_replace('\\', '/', config('filesystems.disks.local.root', ''));
        $allowed = [storage_path('app/testing/browser-4a-private'), storage_path('app/testing/phpunit-private')];
        $allowed = array_map(fn ($path) => str_replace('\\', '/', $path), $allowed);
        if (! str_contains($root, ':') && ! str_starts_with($root, '/')) {
            $root = str_replace('\\', '/', base_path($root));
        }
        if (config('database.default') !== 'pgsql'
            || $connection->getDriverName() !== 'pgsql'
            || $connection->selectOne('select current_database() as name')->name !== 'freecast_test'
            || ! in_array($root, $allowed, true)
            || (is_dir($root) && strcasecmp(str_replace('\\', '/', realpath($root)), $root) !== 0)) {
            throw new RuntimeException('Refusing test operation: PostgreSQL freecast_test and exclusive test storage are required.');
        }
    }
}
