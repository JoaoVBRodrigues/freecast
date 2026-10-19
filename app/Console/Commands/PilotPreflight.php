<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PilotPreflight extends Command
{
    protected $signature = 'pilot:preflight {--test-database : Permit the isolated freecast_test database for browser validation}';

    protected $description = 'Validate the security-critical runtime settings before starting a public Quick Tunnel';

    public function handle(): int
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        $pgsql = config('database.connections.pgsql');
        $database = (string) $pgsql['database'];
        $sessionConnection = config('session.connection') ?: config('database.default');
        $queueConnection = config('queue.connections.database.connection') ?: config('database.default');
        $cacheConnection = config('cache.stores.database.connection') ?: config('database.default');
        $cacheLockConnection = config('cache.stores.database.lock_connection') ?: $cacheConnection;
        $isTestDatabase = $this->option('test-database');

        $requirements = [
            ['Application environment must be production.', app()->environment('production')],
            ['APP_DEBUG must be false.', config('app.debug') === false],
            ['APP_URL must use HTTPS and include a host.', parse_url($url, PHP_URL_SCHEME) === 'https' && is_string($host) && $host !== ''],
            ['The default database connection must be PostgreSQL.', config('database.default') === 'pgsql'],
            ['PostgreSQL must use the Freecast loopback service on port 5434.',
                $pgsql['host'] === '127.0.0.1'
                && (int) $pgsql['port'] === 5434
                && in_array($pgsql['url'], [null, ''], true)],
            ['PostgreSQL credentials must be configured.',
                is_string($pgsql['username']) && $pgsql['username'] !== ''
                && is_string($pgsql['password']) && $pgsql['password'] !== ''],
            ['Database-backed sessions must use PostgreSQL.',
                config('session.driver') !== 'database' || $sessionConnection === 'pgsql'],
            ['The queue must use the database driver.', config('queue.default') === 'database'],
            ['Database-backed queue and cache connections must use PostgreSQL.',
                ($queueConnection === 'pgsql')
                && (config('cache.default') !== 'database'
                    || ($cacheConnection === 'pgsql' && $cacheLockConnection === 'pgsql'))],
            ['The Reverb broadcaster must be enabled.', config('broadcasting.default') === 'reverb'],
            ['The Reverb app ID, key, and secret must be configured.',
                is_string(config('broadcasting.connections.reverb.app_id'))
                && config('broadcasting.connections.reverb.app_id') !== ''
                && is_string(config('broadcasting.connections.reverb.key'))
                && config('broadcasting.connections.reverb.key') !== ''
                && is_string(config('broadcasting.connections.reverb.secret'))
                && config('broadcasting.connections.reverb.secret') !== ''],
            ['Laravel must publish to Reverb over loopback HTTP on port 8082.',
                config('broadcasting.connections.reverb.options.host') === '127.0.0.1'
                && (int) config('broadcasting.connections.reverb.options.port') === 8082
                && config('broadcasting.connections.reverb.options.scheme') === 'http'],
            ['Reverb must listen only on loopback port 8082.',
                config('reverb.servers.reverb.host') === '127.0.0.1'
                && (int) config('reverb.servers.reverb.port') === 8082],
            ['The Reverb origin allowlist must contain only the current Quick Tunnel hostname.',
                is_string($host)
                && config('reverb.apps.apps.0.allowed_origins') === [$host]],
            ['The session cookie must be unique to the pilot and secure.',
                config('session.cookie') === 'freecast_pilot_session'
                && config('session.secure') === true
                && config('session.domain') === null],
            ['APP_KEY must be configured.', is_string(config('app.key')) && config('app.key') !== ''],
            ['A development/test database may only be selected explicitly for test validation.',
                $isTestDatabase ? $database === 'freecast_test' : $database !== 'freecast_test'],
        ];

        foreach ($requirements as [$message, $passed]) {
            if (! $passed) {
                $this->error($message);

                return self::FAILURE;
            }
        }

        if ($isTestDatabase) {
            try {
                \App\Support\TestEnvironmentGuard::assert();
            } catch (\RuntimeException) {
                $this->error('Test pilot requires effective PostgreSQL freecast_test and exclusive test storage.');

                return self::FAILURE;
            }
        }

        try {
            DB::connection($sessionConnection)->getPdo();
            DB::connection($queueConnection)->getPdo();
            if (config('cache.default') === 'database') {
                DB::connection($cacheConnection)->getPdo();
                DB::connection($cacheLockConnection)->getPdo();
            }
        } catch (\PDOException $exception) {
            $this->error('A configured PostgreSQL persistence connection is unavailable.');

            report($exception);

            return self::FAILURE;
        }

        $demoAccounts = User::query()
            ->whereIn('email', [
                'mestre@freecast.test',
                'jogador@freecast.test',
                'convidada@freecast.test',
            ])
            ->get(['password']);

        foreach ($demoAccounts as $account) {
            if (Hash::check('Mesa2026!', $account->password)) {
                $this->error('A seeded demonstration account still accepts its bundled password. Change its password before exposing the application.');

                return self::FAILURE;
            }
        }

        $this->info("Pilot preflight passed for PostgreSQL database '{$database}'.");

        return self::SUCCESS;
    }
}
