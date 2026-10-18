param(
    [switch]$UseTestDatabase
)

$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stateRoot = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) 'Freecast\pilot'
$toolsRoot = Join-Path $stateRoot 'tools'
$logsRoot = Join-Path $stateRoot 'logs'
$apacheRoot = Join-Path $stateRoot 'apache'
$stateFile = Join-Path $stateRoot 'processes.json'
$configCachePath = Join-Path $projectRoot 'bootstrap\cache\config.php'
$configCacheBackup = Join-Path $stateRoot 'config-cache.before-pilot.php'
$caddyFile = Join-Path $PSScriptRoot 'Caddyfile.Pilot'
$publicRoot = Join-Path $projectRoot 'public'
$phpPath = 'C:\xampp\php\php.exe'
$apachePath = 'C:\xampp\apache\bin\httpd.exe'
$apacheServerRoot = 'C:\xampp\apache'
$phpRoot = 'C:\xampp\php'
$portMap = @(8000, 8082, 8091)
$environmentNames = @(
    'APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'SESSION_COOKIE', 'SESSION_DOMAIN',
    'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE', 'SESSION_HTTP_ONLY', 'DB_CONNECTION',
    'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'DB_URL', 'SESSION_CONNECTION', 'DB_CACHE_CONNECTION', 'DB_CACHE_LOCK_CONNECTION',
    'DB_QUEUE_CONNECTION',
    'BROADCAST_CONNECTION', 'QUEUE_CONNECTION', 'REVERB_HOST', 'REVERB_PORT',
    'REVERB_SCHEME', 'REVERB_SERVER_HOST', 'REVERB_SERVER_PORT',
    'REVERB_ALLOWED_ORIGINS', 'REVERB_APP_ID', 'REVERB_APP_KEY', 'REVERB_APP_SECRET',
    'VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST',
    'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME', 'PILOT_HOST', 'PATH'
)
$originalEnvironment = @{}
$changedEnvironment = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)

function Set-PilotEnvironment {
    param([string]$Name, [AllowEmptyString()][string]$Value)

    if (-not $changedEnvironment.Contains($Name)) {
        $originalEnvironment[$Name] = [Environment]::GetEnvironmentVariable($Name, 'Process')
        [void]$changedEnvironment.Add($Name)
    }

    [Environment]::SetEnvironmentVariable($Name, $Value, 'Process')
}

function Restore-PilotEnvironment {
    foreach ($name in $changedEnvironment) {
        [Environment]::SetEnvironmentVariable($name, $originalEnvironment[$name], 'Process')
    }
}

function Save-PilotState {
    $manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $stateFile -Encoding UTF8
}

function Add-PilotProcess {
    param(
        [string]$Role,
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$WorkingDirectory,
        [string]$StdoutPath,
        [string]$StderrPath,
        [string]$CommandMarker
    )

    $process = Start-Process `
        -FilePath $FilePath `
        -ArgumentList $Arguments `
        -WorkingDirectory $WorkingDirectory `
        -RedirectStandardOutput $StdoutPath `
        -RedirectStandardError $StderrPath `
        -PassThru

    Start-Sleep -Milliseconds 700
    $process.Refresh()
    if ($process.HasExited) {
        throw "$Role exited during startup. See its log in $logsRoot."
    }

    $record = [ordered]@{
        role = $Role
        processId = $process.Id
        executablePath = $FilePath
        startTimeUtc = $process.StartTime.ToUniversalTime().ToString('o')
        commandMarker = $CommandMarker
    }
    $manifest.processes += $record
    Save-PilotState

    return $process
}

function Get-DotEnvValue {
    param([string]$Name)

    $matches = @(Get-Content -LiteralPath (Join-Path $projectRoot '.env') |
        Where-Object { $_ -match ('^' + [regex]::Escape($Name) + '=') })
    if ($matches.Count -ne 1) {
        throw "Expected exactly one $Name entry in .env."
    }

    $value = $matches[0].Substring($Name.Length + 1).Trim()
    if ($value.Length -ge 2 -and $value.StartsWith('"') -and $value.EndsWith('"')) {
        $value = $value.Substring(1, $value.Length - 2)
    } elseif ($value.Length -ge 2 -and $value.StartsWith("'") -and $value.EndsWith("'")) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    return $value
}

function Get-VerifiedTools {
    $architecture = [System.Runtime.InteropServices.RuntimeInformation]::OSArchitecture
    if ($architecture -ne [System.Runtime.InteropServices.Architecture]::X64) {
        throw "The official pilot binaries are currently provisioned for Windows x64; detected $architecture."
    }

    New-Item -ItemType Directory -Path $toolsRoot -Force | Out-Null
    $cloudflaredPath = Join-Path $toolsRoot 'cloudflared.exe'
    $caddyPath = Join-Path $toolsRoot 'caddy.exe'
    $headers = @{ 'User-Agent' = 'FreecastPilotSetup' }

    if (-not (Test-Path -LiteralPath $cloudflaredPath)) {
        $release = Invoke-RestMethod -Uri 'https://api.github.com/repos/cloudflare/cloudflared/releases/latest' -Headers $headers
        $asset = $release.assets | Where-Object { $_.name -eq 'cloudflared-windows-amd64.exe' } | Select-Object -First 1
        if (-not $asset) {
            throw 'The official Cloudflare release does not include a Windows x64 executable.'
        }

        & curl.exe --fail --location --silent --show-error $asset.browser_download_url --output $cloudflaredPath
        if ($LASTEXITCODE -ne 0) {
            throw 'Downloading the official Cloudflare executable failed.'
        }
    }

    $signature = Get-AuthenticodeSignature -FilePath $cloudflaredPath
    if ($signature.Status -ne 'Valid' -or $signature.SignerCertificate.Subject -notmatch 'Cloudflare') {
        throw 'Cloudflared did not pass Authenticode verification for Cloudflare, Inc.'
    }

    if (-not (Test-Path -LiteralPath $caddyPath)) {
        $release = Invoke-RestMethod -Uri 'https://api.github.com/repos/caddyserver/caddy/releases/latest' -Headers $headers
        $version = $release.tag_name.TrimStart('v')
        $archiveName = "caddy_${version}_windows_amd64.zip"
        $archive = $release.assets | Where-Object { $_.name -eq $archiveName } | Select-Object -First 1
        $checksumName = "caddy_${version}_checksums.txt"
        $checksums = $release.assets | Where-Object { $_.name -eq $checksumName } | Select-Object -First 1
        if (-not $archive -or -not $checksums) {
            throw 'The official Caddy Windows x64 archive or checksum manifest is unavailable.'
        }

        $archivePath = Join-Path $toolsRoot $archiveName
        $checksumPath = Join-Path $toolsRoot $checksumName
        & curl.exe --fail --location --silent --show-error $checksums.browser_download_url --output $checksumPath
        if ($LASTEXITCODE -ne 0) {
            throw 'Downloading the official Caddy checksum manifest failed.'
        }
        & curl.exe --fail --location --silent --show-error $archive.browser_download_url --output $archivePath
        if ($LASTEXITCODE -ne 0) {
            throw 'Downloading the official Caddy archive failed.'
        }

        $checksumLine = Get-Content -LiteralPath $checksumPath |
            Where-Object { $_ -match ('\s\*?' + [regex]::Escape($archiveName) + '$') } |
            Select-Object -First 1
        if (-not $checksumLine) {
            throw 'The official Caddy checksum manifest has no entry for the Windows x64 archive.'
        }
        $expectedHash = ($checksumLine -split '\s+')[0].ToLowerInvariant()
        $hashAlgorithm = if ($expectedHash.Length -eq 128) { 'SHA512' } else { 'SHA256' }
        $actualHash = (Get-FileHash -LiteralPath $archivePath -Algorithm $hashAlgorithm).Hash.ToLowerInvariant()
        if ($actualHash -ne $expectedHash) {
            throw 'The downloaded Caddy archive does not match the official release checksum.'
        }

        Expand-Archive -LiteralPath $archivePath -DestinationPath $toolsRoot -Force
        Remove-Item -LiteralPath $archivePath, $checksumPath -Force
    }

    if (-not (Test-Path -LiteralPath $caddyPath)) {
        throw 'The verified Caddy executable is missing.'
    }

    $cloudflaredVersion = (& $cloudflaredPath --version 2>&1 | Select-Object -First 1)
    $caddyVersion = (& $caddyPath version 2>&1 | Select-Object -First 1)
    Write-Host "Verified cloudflared: $cloudflaredVersion"
    Write-Host "Verified Caddy: $caddyVersion"

    return @{
        Cloudflared = $cloudflaredPath
        Caddy = $caddyPath
    }
}

function Assert-FreeLoopbackPort {
    param([int]$Port)

    $listeners = @(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
    if ($listeners.Count -gt 0) {
        $owners = $listeners | ForEach-Object {
            $owner = Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue
            "$($_.LocalAddress):$Port (PID $($_.OwningProcess), $($owner.Name))"
        }
        throw "TCP port $Port is already in use: $($owners -join ', '). No process was stopped."
    }
}

function Get-ApacheCompatiblePath {
    param([string]$Path)

    $command = 'for %I in ("' + $Path + '") do @echo %~sI'
    $output = & $env:ComSpec /d /c $command
    if ($LASTEXITCODE -ne 0) {
        throw "Could not resolve an Apache-compatible Windows path for $Path."
    }

    $shortPath = ($output | Select-Object -Last 1).Trim()
    if ([string]::IsNullOrWhiteSpace($shortPath) -or $shortPath -match '[^\x00-\x7F]') {
        throw "Apache requires an ASCII-compatible short path for $Path; no safe path was available."
    }

    return $shortPath
}

function Write-IsolatedApacheConfig {
    $apacheConfig = Join-Path $apacheRoot 'httpd.conf'
    $apacheLog = (Get-ApacheCompatiblePath -Path (Join-Path $logsRoot 'apache-error.log')).Replace('\', '/')
    $apachePid = (Get-ApacheCompatiblePath -Path (Join-Path $apacheRoot 'httpd.pid')).Replace('\', '/')
    $serverRoot = $apacheServerRoot.Replace('\', '/')
    $documentRoot = (Get-ApacheCompatiblePath -Path $publicRoot).Replace('\', '/')
    $phpRootForward = $phpRoot.Replace('\', '/')
    $modulesRoot = (Join-Path $apacheServerRoot 'modules').Replace('\', '/')

    $config = @"
ServerRoot "$serverRoot"
Listen 127.0.0.1:8000
ServerName 127.0.0.1:8000
PidFile "$apachePid"
LoadFile "$phpRootForward/php8ts.dll"
LoadFile "$phpRootForward/libpq.dll"
LoadFile "$phpRootForward/libsqlite3.dll"
LoadModule authz_core_module "$modulesRoot/mod_authz_core.so"
LoadModule authz_host_module "$modulesRoot/mod_authz_host.so"
LoadModule dir_module "$modulesRoot/mod_dir.so"
LoadModule mime_module "$modulesRoot/mod_mime.so"
LoadModule rewrite_module "$modulesRoot/mod_rewrite.so"
LoadModule php_module "$phpRootForward/php8apache2_4.dll"
PHPIniDir "$phpRootForward"
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
<IfModule mime_module>
    AddType text/html .php
</IfModule>
<Directory />
    AllowOverride None
    Require all denied
</Directory>
DocumentRoot "$documentRoot"
<Directory "$documentRoot">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
DirectoryIndex index.php
AccessFileName .htaccess
ErrorLog "$apacheLog"
LogLevel warn
"@

    Set-Content -LiteralPath $apacheConfig -Value $config -Encoding ASCII
    return $apacheConfig
}

function Wait-ForTunnelHostname {
    param([System.Diagnostics.Process]$TunnelProcess)

    $stdout = Join-Path $logsRoot 'cloudflared.stdout.log'
    $stderr = Join-Path $logsRoot 'cloudflared.stderr.log'
    $deadline = [DateTime]::UtcNow.AddSeconds(90)
    $pattern = 'https://([a-z0-9-]+\.trycloudflare\.com)'

    while ([DateTime]::UtcNow -lt $deadline) {
        $TunnelProcess.Refresh()
        if ($TunnelProcess.HasExited) {
            throw "cloudflared exited before creating its Quick Tunnel. See $stderr."
        }

        foreach ($path in @($stdout, $stderr)) {
            if (-not (Test-Path -LiteralPath $path)) {
                continue
            }

            $content = Get-Content -LiteralPath $path -Raw
            if ([string]::IsNullOrEmpty($content)) {
                continue
            }

            $match = [regex]::Match($content, $pattern, 'IgnoreCase')
            if ($match.Success) {
                return $match.Groups[1].Value.ToLowerInvariant()
            }
        }

        Start-Sleep -Milliseconds 250
    }

    throw "No trycloudflare.com hostname appeared within 90 seconds. See $stdout and $stderr."
}

if (Test-Path -LiteralPath $stateFile) {
    throw "Pilot state already exists at $stateFile. Run scripts\Stop-Pilot.ps1 and inspect its result before starting another instance."
}

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) {
    throw 'The project .env file is required and was not found.'
}
if (-not (Test-Path -LiteralPath $phpPath) -or -not (Test-Path -LiteralPath $apachePath)) {
    throw 'This Windows host requires the existing XAMPP PHP CLI and Apache installation at C:\xampp.'
}
if (Test-Path -LiteralPath (Join-Path $publicRoot 'hot')) {
    throw 'public\hot exists. Stop the Vite development server and remove its hot marker before starting the public pilot.'
}

New-Item -ItemType Directory -Path $stateRoot, $logsRoot, $apacheRoot -Force | Out-Null
foreach ($port in $portMap) {
    Assert-FreeLoopbackPort -Port $port
}

$manifest = [ordered]@{
    schemaVersion = 1
    projectRoot = $projectRoot
    startedAtUtc = [DateTime]::UtcNow.ToString('o')
    publicUrl = $null
    database = $null
    testDatabase = [bool]$UseTestDatabase
    configCacheWasPresent = Test-Path -LiteralPath $configCachePath
    configCacheBackup = $null
    processes = @()
}
Save-PilotState

try {
    $tools = Get-VerifiedTools

    & docker compose up -d postgres
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not start the PostgreSQL service for this project.'
    }
    $containerId = (& docker compose ps -q postgres).Trim()
    if (-not $containerId) {
        throw 'The project PostgreSQL container was not found after starting its Compose service.'
    }
    $dbPort = (& docker compose port postgres 5432).Trim()
    if ($LASTEXITCODE -ne 0 -or $dbPort -ne '127.0.0.1:5434') {
        throw "PostgreSQL must remain bound to 127.0.0.1:5434 (reported: $dbPort)."
    }
    $health = ''
    $dbDeadline = [DateTime]::UtcNow.AddSeconds(90)
    while ([DateTime]::UtcNow -lt $dbDeadline) {
        $health = (& docker inspect --format '{{.State.Health.Status}}' $containerId).Trim()
        if ($health -eq 'healthy') {
            break
        }
        Start-Sleep -Seconds 2
    }
    if ($health -ne 'healthy') {
        throw 'The Freecast PostgreSQL container did not become healthy.'
    }

    $cloudflaredProcess = Add-PilotProcess `
        -Role 'cloudflared' `
        -FilePath $tools.Cloudflared `
        -Arguments @('tunnel', '--no-autoupdate', '--url', 'http://127.0.0.1:8091') `
        -WorkingDirectory $stateRoot `
        -StdoutPath (Join-Path $logsRoot 'cloudflared.stdout.log') `
        -StderrPath (Join-Path $logsRoot 'cloudflared.stderr.log') `
        -CommandMarker 'tunnel --no-autoupdate --url'

    $tunnelHost = Wait-ForTunnelHostname -TunnelProcess $cloudflaredProcess
    $publicUrl = "https://$tunnelHost"
    $manifest.publicUrl = $publicUrl
    Save-PilotState

    Set-PilotEnvironment -Name 'PILOT_HOST' -Value $tunnelHost
    $caddyOutput = Join-Path $logsRoot 'caddy.stdout.log'
    $caddyError = Join-Path $logsRoot 'caddy.stderr.log'
    & $tools.Caddy validate --config $caddyFile --adapter caddyfile
    if ($LASTEXITCODE -ne 0) {
        throw "Caddy rejected the pilot configuration. See $caddyError."
    }
    $null = Add-PilotProcess `
        -Role 'caddy' `
        -FilePath $tools.Caddy `
        -Arguments @('run', '--config', "`"$caddyFile`"", '--adapter', 'caddyfile') `
        -WorkingDirectory $projectRoot `
        -StdoutPath $caddyOutput `
        -StderrPath $caddyError `
        -CommandMarker 'Caddyfile.Pilot'

    $appKey = Get-DotEnvValue -Name 'APP_KEY'
    $dbHost = Get-DotEnvValue -Name 'DB_HOST'
    $dbPort = Get-DotEnvValue -Name 'DB_PORT'
    $dbUsername = Get-DotEnvValue -Name 'DB_USERNAME'
    $dbPassword = Get-DotEnvValue -Name 'DB_PASSWORD'
    $reverbAppId = Get-DotEnvValue -Name 'REVERB_APP_ID'
    $reverbPublicKey = Get-DotEnvValue -Name 'REVERB_APP_KEY'
    $reverbSecret = Get-DotEnvValue -Name 'REVERB_APP_SECRET'
    if ([string]::IsNullOrWhiteSpace($appKey) -or
        [string]::IsNullOrWhiteSpace($dbHost) -or
        [string]::IsNullOrWhiteSpace($dbPort) -or
        [string]::IsNullOrWhiteSpace($dbUsername) -or
        [string]::IsNullOrWhiteSpace($dbPassword) -or
        [string]::IsNullOrWhiteSpace($reverbAppId) -or
        [string]::IsNullOrWhiteSpace($reverbPublicKey) -or
        [string]::IsNullOrWhiteSpace($reverbSecret)) {
        throw 'APP_KEY, PostgreSQL connection settings, and Reverb app credentials must be set in .env.'
    }

    Set-PilotEnvironment -Name 'PATH' -Value "$phpRoot;$env:PATH"
    Set-PilotEnvironment -Name 'APP_KEY' -Value $appKey
    Set-PilotEnvironment -Name 'APP_ENV' -Value 'production'
    Set-PilotEnvironment -Name 'APP_DEBUG' -Value 'false'
    Set-PilotEnvironment -Name 'APP_URL' -Value $publicUrl
    Set-PilotEnvironment -Name 'SESSION_COOKIE' -Value 'freecast_pilot_session'
    Set-PilotEnvironment -Name 'SESSION_DOMAIN' -Value 'null'
    Set-PilotEnvironment -Name 'SESSION_SECURE_COOKIE' -Value 'true'
    Set-PilotEnvironment -Name 'SESSION_SAME_SITE' -Value 'lax'
    Set-PilotEnvironment -Name 'SESSION_HTTP_ONLY' -Value 'true'
    Set-PilotEnvironment -Name 'DB_CONNECTION' -Value 'pgsql'
    Set-PilotEnvironment -Name 'DB_HOST' -Value $dbHost
    Set-PilotEnvironment -Name 'DB_PORT' -Value $dbPort
    Set-PilotEnvironment -Name 'DB_USERNAME' -Value $dbUsername
    Set-PilotEnvironment -Name 'DB_PASSWORD' -Value $dbPassword
    Set-PilotEnvironment -Name 'DB_URL' -Value ''
    Set-PilotEnvironment -Name 'SESSION_CONNECTION' -Value 'pgsql'
    Set-PilotEnvironment -Name 'DB_CACHE_CONNECTION' -Value 'pgsql'
    Set-PilotEnvironment -Name 'DB_CACHE_LOCK_CONNECTION' -Value 'pgsql'
    Set-PilotEnvironment -Name 'DB_QUEUE_CONNECTION' -Value 'pgsql'
    Set-PilotEnvironment -Name 'BROADCAST_CONNECTION' -Value 'reverb'
    Set-PilotEnvironment -Name 'QUEUE_CONNECTION' -Value 'database'
    Set-PilotEnvironment -Name 'REVERB_HOST' -Value '127.0.0.1'
    Set-PilotEnvironment -Name 'REVERB_PORT' -Value '8082'
    Set-PilotEnvironment -Name 'REVERB_SCHEME' -Value 'http'
    Set-PilotEnvironment -Name 'REVERB_APP_ID' -Value $reverbAppId
    Set-PilotEnvironment -Name 'REVERB_APP_KEY' -Value $reverbPublicKey
    Set-PilotEnvironment -Name 'REVERB_APP_SECRET' -Value $reverbSecret
    Set-PilotEnvironment -Name 'REVERB_SERVER_HOST' -Value '127.0.0.1'
    Set-PilotEnvironment -Name 'REVERB_SERVER_PORT' -Value '8082'
    Set-PilotEnvironment -Name 'REVERB_ALLOWED_ORIGINS' -Value $tunnelHost
    Set-PilotEnvironment -Name 'VITE_REVERB_APP_KEY' -Value $reverbPublicKey
    Set-PilotEnvironment -Name 'VITE_REVERB_HOST' -Value $tunnelHost
    Set-PilotEnvironment -Name 'VITE_REVERB_PORT' -Value '443'
    Set-PilotEnvironment -Name 'VITE_REVERB_SCHEME' -Value 'https'

    if ($UseTestDatabase) {
        Set-PilotEnvironment -Name 'DB_DATABASE' -Value 'freecast_test'
    } else {
        Set-PilotEnvironment -Name 'DB_DATABASE' -Value (Get-DotEnvValue -Name 'DB_DATABASE')
    }

    if ($manifest.configCacheWasPresent) {
        Copy-Item -LiteralPath $configCachePath -Destination $configCacheBackup
        $manifest.configCacheBackup = $configCacheBackup
        Save-PilotState
    }

    Push-Location $projectRoot
    try {
        & $phpPath artisan config:clear
        if ($LASTEXITCODE -ne 0) {
            throw 'Laravel configuration cache could not be cleared for the runtime overrides.'
        }

        $preflightArguments = @('artisan', 'pilot:preflight')
        if ($UseTestDatabase) {
            $preflightArguments += '--test-database'
        }
        & $phpPath @preflightArguments
        if ($LASTEXITCODE -ne 0) {
            throw 'Pilot preflight failed. The public tunnel will not be started further.'
        }

        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) {
            throw 'Production Vite build failed.'
        }
    } finally {
        Pop-Location
    }

    $apacheConfig = Write-IsolatedApacheConfig
    & $apachePath -t -f $apacheConfig -d $apacheServerRoot
    if ($LASTEXITCODE -ne 0) {
        throw 'The isolated Apache configuration failed its syntax check.'
    }
    $null = Add-PilotProcess `
        -Role 'apache' `
        -FilePath $apachePath `
        -Arguments @('-f', "`"$apacheConfig`"", '-D', 'FOREGROUND') `
        -WorkingDirectory $apacheServerRoot `
        -StdoutPath (Join-Path $logsRoot 'apache.stdout.log') `
        -StderrPath (Join-Path $logsRoot 'apache-stderr.log') `
        -CommandMarker 'Freecast\pilot\apache\httpd.conf'

    $artisanServices = @(
        @{ Role = 'reverb'; Args = @('artisan', 'reverb:start', '--host=127.0.0.1', '--port=8082'); Marker = 'reverb:start' },
        @{ Role = 'queue'; Args = @('artisan', 'queue:work', '--tries=5', '--timeout=20', '--sleep=1'); Marker = 'artisan queue:work' },
        @{ Role = 'scheduler'; Args = @('artisan', 'schedule:work'); Marker = 'artisan schedule:work' }
    )
    foreach ($service in $artisanServices) {
        $null = Add-PilotProcess `
            -Role $service.Role `
            -FilePath $phpPath `
            -Arguments $service.Args `
            -WorkingDirectory $projectRoot `
            -StdoutPath (Join-Path $logsRoot "$($service.Role).stdout.log") `
            -StderrPath (Join-Path $logsRoot "$($service.Role).stderr.log") `
            -CommandMarker $service.Marker
    }

    $ready = $false
    $readyDeadline = [DateTime]::UtcNow.AddSeconds(60)
    while ([DateTime]::UtcNow -lt $readyDeadline) {
        $response = & curl.exe --silent --show-error --output NUL --write-out '%{http_code}' "$publicUrl/login" 2>$null
        if ($LASTEXITCODE -eq 0 -and "$response".Trim() -eq '200') {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) {
        throw "The public login endpoint did not return HTTP 200. Review logs under $logsRoot."
    }

    Save-PilotState
    Write-Host "Freecast pilot is ready: $publicUrl"
    Write-Host "Share this temporary link only with your friends. Do not share test credentials."
    Write-Host "Caddy proxy: 127.0.0.1:8091; Laravel: 127.0.0.1:8000; Reverb: 127.0.0.1:8082."
    Write-Host "Processes and logs: $stateRoot"
} catch {
    $startupError = $_
    if (Test-Path -LiteralPath $stateFile) {
        & (Join-Path $PSScriptRoot 'Stop-Pilot.ps1') -SkipLocalBuild
    }
    throw $startupError
} finally {
    Restore-PilotEnvironment
}
