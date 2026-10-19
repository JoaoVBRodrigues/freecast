# Dot-source in the shell used to start Freecast. No global php.ini changes.
param([string]$PhpExecutable = 'php')
$ErrorActionPreference = 'Stop'
$freecastPhpRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$env:PHP_PATH = (Get-Command $PhpExecutable -ErrorAction Stop).Source
& $PhpExecutable (Join-Path $PSScriptRoot 'check-image-runtime.php') --quiet | Out-Null
if ($LASTEXITCODE -ne 0) {
    $freecastPhpBinary = (Get-Command $PhpExecutable -ErrorAction Stop).Source
    $freecastGdDll = Join-Path (Split-Path $freecastPhpBinary) 'ext/php_gd.dll'
    if (-not (Test-Path -LiteralPath $freecastGdDll)) { throw 'Install PHP GD with PNG/WebP support for this PHP runtime before importing maps.' }
    $freecastScanRoot = Join-Path $freecastPhpRoot 'storage/app/runtime/php-gd'
    New-Item -ItemType Directory -Path $freecastScanRoot -Force | Out-Null
    # ASCII 8.3 path also works for the isolated Windows Apache runtime.
    $freecastScanOutput = & $env:ComSpec /d /c ('for %I in ("' + $freecastScanRoot + '") do @echo %~sI')
    if ($LASTEXITCODE -ne 0 -or -not $freecastScanOutput) { throw 'PHP scan directory resolution failed.' }
    $freecastScanRoot = ($freecastScanOutput | Select-Object -Last 1).Trim()
    if ($freecastScanRoot -match '[^\x00-\x7F]') { throw 'PHP scan directory needs an ASCII-compatible path.' }
    Set-Content -LiteralPath (Join-Path $freecastScanRoot 'freecast-gd.ini') -Encoding ASCII -Value ('extension="' + $freecastGdDll.Replace('\','/') + '"')
    $env:PHP_INI_SCAN_DIR = if ($env:PHP_INI_SCAN_DIR) { $env:PHP_INI_SCAN_DIR + ';' + $freecastScanRoot } else { $freecastScanRoot }
}
& $PhpExecutable (Join-Path $PSScriptRoot 'check-image-runtime.php')
if ($LASTEXITCODE -ne 0) { throw 'Freecast PHP image decoder preflight failed.' }
