# Dot-source only in a disposable shell. Does not change .env or start public listeners.
$ErrorActionPreference = 'Stop'
$part4Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (Test-Path (Join-Path $part4Root 'bootstrap/cache/config.php')) { throw 'Clear configuration cache before using the isolated test environment.' }
foreach ($part4Line in Get-Content -Encoding UTF8 (Join-Path $part4Root '.env')) {
    if ($part4Line -match '^([A-Z][A-Z0-9_]*)=(.*)$') {
        $part4Name = $Matches[1]
        $part4Value = $Matches[2].Trim().Trim('"').Trim("'")
        if ($part4Value -notmatch '\$\{') { [Environment]::SetEnvironmentVariable($part4Name, $part4Value, 'Process') }
    }
}
$env:APP_ENV = 'validation'
$env:FREECAST_TEST_RUNTIME = '1'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8014'
$env:DB_URL = ''
$env:DB_CONNECTION = 'pgsql'
$env:DB_DATABASE = 'freecast_test'
$env:SESSION_CONNECTION = 'pgsql'
$env:DB_CACHE_CONNECTION = 'pgsql'
$env:DB_CACHE_LOCK_CONNECTION = 'pgsql'
$env:DB_QUEUE_CONNECTION = 'pgsql'
$env:SESSION_DRIVER = 'database'
$env:SESSION_COOKIE = 'freecast_4a_test_session'
$env:SESSION_SECURE_COOKIE = 'false'
$env:SESSION_DOMAIN = ''
$env:FREECAST_PRIVATE_ROOT = Join-Path $part4Root 'storage/app/testing/browser-4a-private'
$env:QUEUE_CONNECTION = 'database'
$env:BROADCAST_CONNECTION = 'reverb'
$env:REVERB_HOST = '127.0.0.1'
$env:REVERB_PORT = '8084'
$env:REVERB_SCHEME = 'http'
$env:REVERB_SERVER_HOST = '127.0.0.1'
$env:REVERB_SERVER_PORT = '8084'
$env:REVERB_ALLOWED_ORIGINS = '127.0.0.1'
$env:VITE_REVERB_APP_KEY = $env:REVERB_APP_KEY
$env:VITE_REVERB_HOST = '127.0.0.1'
$env:VITE_REVERB_PORT = '8084'
$env:VITE_REVERB_SCHEME = 'http'
$env:FREECAST_BASE_URL = $env:APP_URL
$env:FREECAST_TEST_OUTPUT_DIR = 'test-results/parte4a'
if ($env:DB_HOST -ne '127.0.0.1' -or $env:DB_PORT -ne '5434' -or -not $env:DB_USERNAME -or -not $env:DB_PASSWORD) { throw 'Explicit loopback PostgreSQL credentials required.' }
