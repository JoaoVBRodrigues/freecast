# Real PHP child, fake Laravel bootstrap/guard/kernel. Never connects to a DB.
$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$scratch = Join-Path $project ('storage/app/testing/php-action-safety-' + [guid]::NewGuid().ToString('N'))
foreach ($directory in @('scripts', 'vendor', 'bootstrap')) { New-Item -ItemType Directory -Path (Join-Path $scratch $directory) -Force | Out-Null }
Copy-Item -LiteralPath (Join-Path $project 'scripts/test-action.php') -Destination (Join-Path $scratch 'scripts/test-action.php')
@'
<?php
namespace App\Support;
class TestEnvironmentGuard {
    public static function assert(): void {
        file_put_contents(getenv('ACTION_SAFETY_CALLS'), "guard\n", FILE_APPEND);
        if (getenv('ACTION_SAFETY_SCENARIO') === 'guard') throw new \RuntimeException('Mock guard failure');
    }
}
'@ | Set-Content -Encoding ASCII -LiteralPath (Join-Path $scratch 'vendor/autoload.php')
@'
<?php
return new class {
    public function make(string $name): object { return new class {
        public function bootstrap(): void {
            // Models Laravel's console renderer returning normally on exceptions.
            set_exception_handler(static function (Throwable $e) { echo "Mock console renderer\n"; });
            file_put_contents(getenv('ACTION_SAFETY_CALLS'), "bootstrap\n", FILE_APPEND);
            if (getenv('ACTION_SAFETY_SCENARIO') === 'bootstrap') throw new RuntimeException('Mock bootstrap failure');
        }
        public function call(string $command, array $arguments): int {
            file_put_contents(getenv('ACTION_SAFETY_CALLS'), $command."\n", FILE_APPEND);
            if ($command === 'migrate' && getenv('ACTION_SAFETY_SCENARIO') === 'migration') return 29;
            return $command === 'db:seed' ? 31 : 0;
        }
        public function output(): string { return ''; }
    }; }
};
'@ | Set-Content -Encoding ASCII -LiteralPath (Join-Path $scratch 'bootstrap/app.php')
$names = @('FREECAST_TEST_RUNTIME', 'APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'FREECAST_PRIVATE_ROOT', 'ACTION_SAFETY_CALLS', 'ACTION_SAFETY_SCENARIO')
$previous = @{}
foreach ($name in $names) { $previous[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
    $env:FREECAST_TEST_RUNTIME = '1'
    $env:APP_ENV = 'validation'
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_DATABASE = 'freecast_test'
    $env:FREECAST_PRIVATE_ROOT = Join-Path $scratch 'private'
    $php = (Get-Command php -ErrorAction Stop).Source
    foreach ($scenario in @('bootstrap', 'guard', 'migration', 'seed')) {
        $env:ACTION_SAFETY_SCENARIO = $scenario
        $env:ACTION_SAFETY_CALLS = Join-Path $scratch ($scenario + '.calls')
        $child = Start-Process -FilePath $php -WindowStyle Hidden -Wait -PassThru -ArgumentList @(('"' + (Join-Path $scratch 'scripts/test-action.php') + '"'), 'prepare') -RedirectStandardOutput (Join-Path $scratch ($scenario + '.out')) -RedirectStandardError (Join-Path $scratch ($scenario + '.err'))
        $expectedExit = switch ($scenario) { 'bootstrap' { 1 }; 'guard' { 1 }; 'migration' { 29 }; 'seed' { 31 } }
        if ($child.ExitCode -ne $expectedExit) { throw "PHP failure exit was not propagated: $scenario" }
        $expectedCalls = switch ($scenario) { 'bootstrap' { 'bootstrap' }; 'guard' { 'bootstrap,guard' }; 'migration' { 'bootstrap,guard,migrate' }; 'seed' { 'bootstrap,guard,migrate,guard,db:seed' } }
        if ((@(Get-Content -LiteralPath $env:ACTION_SAFETY_CALLS) -join ',') -ne $expectedCalls) { throw "Unexpected later action: $scenario" }
        Write-Output "PASS PHP ${scenario}: exit $expectedExit, no subsequent action."
    }
} finally {
    foreach ($name in $names) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
}
