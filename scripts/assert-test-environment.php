<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = Illuminate\Support\Facades\DB::connection()->selectOne('select current_database() as name')->name;
$root = str_replace('\\', '/', config('filesystems.disks.local.root'));
if (config('database.default') !== 'pgsql' || $database !== 'freecast_test' || ! str_ends_with($root, '/storage/app/testing/browser-4a-private')) {
    throw new RuntimeException('Browser runtime must use freecast_test and isolated private storage.');
}
echo "Isolation confirmed: PostgreSQL freecast_test; storage/app/testing/browser-4a-private\n";
