param(
    [switch]$SkipLocalBuild
)

$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stateRoot = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) 'Freecast\pilot'
$stateFile = Join-Path $stateRoot 'processes.json'
$configCachePath = Join-Path $projectRoot 'bootstrap\cache\config.php'
$logsRoot = Join-Path $stateRoot 'logs'

if (-not (Test-Path -LiteralPath $stateFile)) {
    Write-Host "No Freecast pilot process registry exists at $stateFile."
    return
}

$manifest = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
if ([IO.Path]::GetFullPath($manifest.projectRoot) -ne $projectRoot) {
    throw 'The process registry belongs to a different project root; no processes were stopped.'
}

$allProcesses = @(Get-CimInstance Win32_Process)
$apacheRecords = @($manifest.processes | Where-Object { $_.role -eq 'apache' })

foreach ($record in @($manifest.processes | Where-Object { $_.role -ne 'apache' } | Sort-Object processId -Descending)) {
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($record.processId)" -ErrorAction SilentlyContinue
    if (-not $process) {
        continue
    }

    $executablePath = [string]$process.ExecutablePath
    $commandLine = [string]$process.CommandLine
    $startTime = (Get-Process -Id $record.processId -ErrorAction SilentlyContinue).StartTime
    $sameTime = $false
    if ($startTime) {
        $expectedTime = [DateTime]::Parse($record.startTimeUtc).ToLocalTime()
        $sameTime = [Math]::Abs(($startTime - $expectedTime).TotalSeconds) -lt 5
    }

    if ($executablePath -ne $record.executablePath -or -not $sameTime -or $commandLine -notlike "*$($record.commandMarker)*") {
        Write-Warning "PID $($record.processId) no longer matches the recorded $($record.role) process; it was left untouched."
        continue
    }

    Stop-Process -Id $record.processId -Force
}

foreach ($record in $apacheRecords) {
    $descendants = [System.Collections.Generic.Queue[int]]::new()
    $childRecords = @()
    $descendants.Enqueue([int]$record.processId)
    $parentIds = [System.Collections.Generic.HashSet[int]]::new()
    [void]$parentIds.Add([int]$record.processId)

    while ($descendants.Count -gt 0) {
        $parentId = $descendants.Dequeue()
        foreach ($child in $allProcesses | Where-Object { $_.ParentProcessId -eq $parentId }) {
            if ($child.ExecutablePath -eq $record.executablePath -and
                $child.CommandLine -like "*$($record.commandMarker)*" -and
                $parentIds.Add([int]$child.ProcessId)) {
                $childRecords += $child
                $descendants.Enqueue([int]$child.ProcessId)
            }
        }
    }

    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($record.processId)" -ErrorAction SilentlyContinue
    $startTime = (Get-Process -Id $record.processId -ErrorAction SilentlyContinue).StartTime
    $sameTime = $false
    if ($startTime) {
        $expectedTime = [DateTime]::Parse($record.startTimeUtc).ToLocalTime()
        $sameTime = [Math]::Abs(($startTime - $expectedTime).TotalSeconds) -lt 5
    }

    if ($process -and $process.ExecutablePath -eq $record.executablePath -and
        $sameTime -and $process.CommandLine -like "*$($record.commandMarker)*") {
        Stop-Process -Id $record.processId -Force
    } elseif ($process) {
        Write-Warning "Apache PID $($record.processId) no longer matches the recorded Freecast process; it was left untouched."
        continue
    }

    Start-Sleep -Milliseconds 500
    foreach ($childRecord in $childRecords) {
        $childId = [int]$childRecord.ProcessId
        $child = Get-CimInstance Win32_Process -Filter "ProcessId = $childId" -ErrorAction SilentlyContinue
        if ($child -and $child.ExecutablePath -eq $record.executablePath -and
            $child.CommandLine -like "*$($record.commandMarker)*" -and
            $child.CreationDate -eq $childRecord.CreationDate) {
            Stop-Process -Id $childId -Force
        }
    }
}

Start-Sleep -Seconds 2
$remaining = @()
foreach ($record in $manifest.processes) {
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($record.processId)" -ErrorAction SilentlyContinue
    if ($process -and $process.ExecutablePath -eq $record.executablePath -and
        $process.CommandLine -like "*$($record.commandMarker)*") {
        $remaining += $record
    }
}
if ($remaining.Count -gt 0) {
    throw 'At least one recorded Freecast process is still running or a PID could not be verified. The process registry was retained; inspect it before retrying.'
}

if ($manifest.configCacheWasPresent -and $manifest.configCacheBackup -and
    (Test-Path -LiteralPath $manifest.configCacheBackup)) {
    if (-not (Test-Path -LiteralPath $configCachePath)) {
        Copy-Item -LiteralPath $manifest.configCacheBackup -Destination $configCachePath
        Write-Host 'Restored the configuration cache that existed before the pilot.'
    } else {
        Write-Warning 'A config cache now exists; it was left untouched rather than overwriting a later user change.'
    }
} elseif (-not $manifest.configCacheWasPresent -and (Test-Path -LiteralPath $configCachePath)) {
    Write-Warning 'A config cache was created after pilot startup; it was left untouched.'
}

if (-not $SkipLocalBuild) {
    if (Test-Path -LiteralPath (Join-Path $projectRoot 'public\hot')) {
        Write-Warning 'public\hot appeared after pilot startup; skipped the local asset rebuild to avoid changing the active Vite setup.'
    } else {
        Push-Location $projectRoot
        try {
            foreach ($name in @('VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME')) {
                [Environment]::SetEnvironmentVariable($name, $null, 'Process')
            }

            & npm.cmd run build
            if ($LASTEXITCODE -ne 0) {
                throw 'Could not rebuild browser assets from the current local .env settings.'
            }
        } finally {
            Pop-Location
        }
    }
}

if ($manifest.configCacheBackup -and (Test-Path -LiteralPath $manifest.configCacheBackup)) {
    Remove-Item -LiteralPath $manifest.configCacheBackup -Force
}
Remove-Item -LiteralPath $stateFile -Force
Write-Host "Freecast public access has stopped. Logs remain under $logsRoot."
Write-Host 'The project .env was not changed. Local assets were rebuilt from its current settings.'
