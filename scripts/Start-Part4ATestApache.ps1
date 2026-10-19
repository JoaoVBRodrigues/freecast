$ErrorActionPreference = 'Stop'
if ($env:DB_DATABASE -ne 'freecast_test') { throw 'Test database is required.' }
$part4Apache = Join-Path $part4Root 'storage/app/testing/apache-4a'
New-Item -ItemType Directory -Path $part4Apache -Force | Out-Null
$part4Short = (& $env:ComSpec /d /c ('for %I in ("' + $part4Root + '") do @echo %~sI') | Select-Object -Last 1).Trim().Replace('\', '/')
if ($part4Short -match '[^\x00-\x7F]') { throw 'Apache requires an ASCII short path.' }
$part4Config = Join-Path $part4Apache 'httpd.conf'
@"
ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8014
ServerName 127.0.0.1:8014
PidFile "$part4Short/storage/app/testing/apache-4a/httpd.pid"
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule authz_core_module "C:/xampp/apache/modules/mod_authz_core.so"
LoadModule authz_host_module "C:/xampp/apache/modules/mod_authz_host.so"
LoadModule dir_module "C:/xampp/apache/modules/mod_dir.so"
LoadModule mime_module "C:/xampp/apache/modules/mod_mime.so"
LoadModule rewrite_module "C:/xampp/apache/modules/mod_rewrite.so"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
<FilesMatch "\.php$">
 SetHandler application/x-httpd-php
</FilesMatch>
<Directory />
 AllowOverride None
 Require all denied
</Directory>
DocumentRoot "$part4Short/public"
<Directory "$part4Short/public">
 Options FollowSymLinks
 AllowOverride All
 Require all granted
</Directory>
DirectoryIndex index.php
AccessFileName .htaccess
ErrorLog "$part4Short/storage/app/testing/apache-4a/error.log"
LogLevel warn
"@ | Set-Content -Encoding ASCII -LiteralPath $part4Config
& 'C:/xampp/apache/bin/httpd.exe' -f "$part4Short/storage/app/testing/apache-4a/httpd.conf" -DFREECAST_4A_TEST
if ($LASTEXITCODE -ne 0) { throw 'Isolated Apache failed.' }
