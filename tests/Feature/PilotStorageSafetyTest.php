<?php

namespace Tests\Feature;

use Tests\TestCase;

class PilotStorageSafetyTest extends TestCase
{
    public function test_test_pilot_refuses_development_storage_before_account_queries(): void
    {
        // Uses the already guarded test connection; no DB/storage writes or tunnel.
        $this->app->detectEnvironment(fn () => 'production');
        $host = 'synthetic.trycloudflare.com';
        config([
            'app.debug' => false,
            'app.url' => 'https://'.$host,
            'database.connections.pgsql.url' => null,
            'session.cookie' => 'freecast_pilot_session',
            'session.secure' => true,
            'session.domain' => null,
            'session.connection' => 'pgsql',
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'pgsql',
            'cache.default' => 'array',
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.app_id' => 'synthetic',
            'broadcasting.connections.reverb.key' => 'synthetic',
            'broadcasting.connections.reverb.secret' => 'synthetic',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8082,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'reverb.servers.reverb.host' => '127.0.0.1',
            'reverb.servers.reverb.port' => 8082,
            'reverb.apps.apps.0.allowed_origins' => [$host],
            'filesystems.disks.local.root' => storage_path('app/private'),
        ]);

        $this->artisan('pilot:preflight', ['--test-database' => true])
            ->expectsOutputToContain('Test pilot requires effective PostgreSQL freecast_test and exclusive test storage.')
            ->assertExitCode(1);
    }
}
