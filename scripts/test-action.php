<?php

// This process both verifies and performs the action; no separate preflight gap.
require __DIR__.'/../vendor/autoload.php';
if (getenv('FREECAST_TEST_RUNTIME') !== '1' || getenv('APP_ENV') !== 'validation'
    || getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_DATABASE') !== 'freecast_test'
    || ! getenv('FREECAST_PRIVATE_ROOT')) {
    throw new RuntimeException('Explicit isolated environment is missing. No action performed.');
}
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
App\Support\TestEnvironmentGuard::assert();
switch ($argv[1] ?? '') {
    case 'prepare':
        $exit = $kernel->call('migrate', ['--force' => true]);
        echo $kernel->output();
        if ($exit !== 0) { exit($exit); }
        App\Support\TestEnvironmentGuard::assert();
        $exit = $kernel->call('db:seed', ['--class' => Database\Seeders\TestFixtureSeeder::class, '--force' => true]);
        echo $kernel->output();
        exit($exit);
    case 'assert':
        echo "Isolation verified in PHP: PostgreSQL freecast_test, exclusive test storage, validation runtime.\n";
        break;
    default:
        throw new RuntimeException('Unknown test action.');
}
