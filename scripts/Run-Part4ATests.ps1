param([Parameter(Mandatory=$true)][ValidateSet('Prepare', 'Build', 'Serve', 'Reverb', 'Worker', 'Browser', 'Browser4A', 'Browser4B', 'CrossMap4B', 'Regression4B', 'Realtime')][string]$Action)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Use-Part4ATestEnvironment.ps1')
Set-Location $part4Root
if ($env:DB_DATABASE -ne 'freecast_test' -or $env:FREECAST_PRIVATE_ROOT -ne (Join-Path $part4Root 'storage/app/testing/browser-4a-private')) { throw 'Isolation is required.' }
& php (Join-Path $PSScriptRoot 'test-action.php') assert
if ($LASTEXITCODE -ne 0) { throw 'Runtime isolation check failed.' }
switch ($Action) {
    'Prepare' {
        & php (Join-Path $PSScriptRoot 'test-action.php') prepare
    }
    'Build' { npm.cmd run build }
    'Serve' { & (Join-Path $PSScriptRoot 'Start-Part4ATestApache.ps1') }
    'Reverb' { php artisan reverb:start --host=127.0.0.1 --port=8084 }
    'Worker' { php artisan queue:work --tries=5 --timeout=20 --sleep=1 }
    'Browser' { npx.cmd playwright test tests/Browser/characters.spec.ts tests/Browser/rooms.spec.ts tests/Browser/maps.spec.ts }
    'Browser4B' { $env:FREECAST_TEST_OUTPUT_DIR = 'test-results/parte4b'; npx.cmd playwright test tests/Browser/map-tools.spec.ts }
    'Regression4B' { $env:FREECAST_TEST_OUTPUT_DIR = 'test-results/parte4b-regression'; npx.cmd playwright test tests/Browser/characters.spec.ts tests/Browser/maps.spec.ts }
    'CrossMap4B' { $env:FREECAST_TEST_OUTPUT_DIR = 'test-results/parte4b-cross-map'; npx.cmd playwright test tests/Browser/map-tools.spec.ts --grep cross-map }
    'Browser4A' { npx.cmd playwright test tests/Browser/characters.spec.ts }
    'Realtime' { npx.cmd playwright test tests/Browser/realtime.spec.ts }
}
if ($LASTEXITCODE -ne 0) { throw "Action $Action failed." }
