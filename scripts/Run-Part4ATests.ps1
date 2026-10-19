param([ValidateSet('Prepare', 'Build', 'Serve', 'Reverb', 'Worker', 'Browser', 'Browser4A')][string]$Action)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Use-Part4ATestEnvironment.ps1')
Set-Location $part4Root
if ($env:DB_DATABASE -ne 'freecast_test' -or $env:FREECAST_PRIVATE_ROOT -ne (Join-Path $part4Root 'storage/app/testing/browser-4a-private')) { throw 'Isolation is required.' }
& php (Join-Path $PSScriptRoot 'assert-test-environment.php')
if ($LASTEXITCODE -ne 0) { throw 'Runtime isolation check failed.' }
switch ($Action) {
    'Prepare' {
        php artisan migrate --force
        if ($LASTEXITCODE -ne 0) { throw 'Migration failed.' }
        php artisan db:seed --force
    }
    'Build' { npm.cmd run build }
    'Serve' { & (Join-Path $PSScriptRoot 'Start-Part4ATestApache.ps1') }
    'Reverb' { php artisan reverb:start --host=127.0.0.1 --port=8084 }
    'Worker' { php artisan queue:work --tries=5 --timeout=20 --sleep=1 }
    'Browser' { npx.cmd playwright test tests/Browser/characters.spec.ts tests/Browser/rooms.spec.ts tests/Browser/maps.spec.ts }
    'Browser4A' { npx.cmd playwright test tests/Browser/characters.spec.ts }
}
if ($LASTEXITCODE -ne 0) { throw "Action $Action failed." }
