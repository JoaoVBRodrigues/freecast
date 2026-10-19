# Safe mocks: no PHP bootstrap, database connection, migration or seed executes.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$scratch = Join-Path $project ('storage/app/testing/script-safety-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $scratch | Out-Null
Copy-Item -LiteralPath (Join-Path $project 'scripts/Run-Part4ATests.ps1') -Destination $scratch
$marker = Join-Path $scratch 'calls.txt'
$setup = @'
$part4Root = $PSScriptRoot
$env:DB_DATABASE = 'freecast_test'
$env:FREECAST_PRIVATE_ROOT = Join-Path $part4Root 'storage/app/testing/browser-4a-private'
'@
foreach ($scenario in @('environment-failure', 'native-preflight-failure', 'native-prepare-failure')) {
    if (Test-Path -LiteralPath $marker) { Remove-Item -LiteralPath $marker }
    $environment = if ($scenario -eq 'environment-failure') { "throw 'Mock preparation failure'" } else { $setup }
    Set-Content -LiteralPath (Join-Path $scratch 'Use-Part4ATestEnvironment.ps1') -Value $environment
    $mock = @'
function global:php {
    $action = $args[-1]
    Add-Content -LiteralPath (Join-Path $PSScriptRoot 'calls.txt') -Value $action
    $global:LASTEXITCODE = if ($action -eq 'assert' -and 'SCENARIO' -eq 'native-prepare-failure') { 0 } else { 29 }
}
& (Join-Path $PSScriptRoot 'Run-Part4ATests.ps1') -Action Prepare
'@
    Set-Content -LiteralPath (Join-Path $scratch 'child.ps1') -Value $mock.Replace('SCENARIO', $scenario)
    $child = Start-Process -FilePath powershell.exe -WindowStyle Hidden -Wait -PassThru -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ('"' + (Join-Path $scratch 'child.ps1') + '"')) -RedirectStandardOutput (Join-Path $scratch ($scenario + '.out.log')) -RedirectStandardError (Join-Path $scratch ($scenario + '.err.log'))
    if ($child.ExitCode -eq 0) { throw "Child failure not propagated: $scenario" }
    $calls = if (Test-Path -LiteralPath $marker) { @(Get-Content -LiteralPath $marker) } else { @() }
    $expected = switch ($scenario) { 'environment-failure' { 0 }; 'native-preflight-failure' { 1 }; 'native-prepare-failure' { 2 } }
    if ($calls.Count -ne $expected) { throw "Unexpected executable calls: $scenario" }
    Write-Output "PASS ${scenario}: child nonzero, expected calls only ($expected)."
}
