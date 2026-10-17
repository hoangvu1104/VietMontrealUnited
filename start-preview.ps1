$ErrorActionPreference='Stop'
$projectPath=$PSScriptRoot
$previewConfig=Get-Content -LiteralPath (Join-Path $projectPath 'var/local-preview.json') -Raw | ConvertFrom-Json
if (-not (Get-NetTCPConnection -LocalPort 3312 -State Listen -ErrorAction SilentlyContinue)) {
    $dbConfig=Join-Path $projectPath 'var/local-preview-db/my.ini'
    Start-Process -FilePath 'C:/xampp/mysql/bin/mysqld.exe' -ArgumentList "--defaults-file=$dbConfig",'--bind-address=127.0.0.1','--console' -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectPath 'var/preview-mysql.stdout.log') -RedirectStandardError (Join-Path $projectPath 'var/preview-mysql.stderr.log')
    for ($attempt=0; $attempt -lt 20; $attempt++) {
        if (Get-NetTCPConnection -LocalPort 3312 -State Listen -ErrorAction SilentlyContinue) { break }
        Start-Sleep -Milliseconds 250
    }
}
if (-not (Get-NetTCPConnection -LocalPort 8780 -State Listen -ErrorAction SilentlyContinue)) {
    $env:DB_HOST=$previewConfig.host
    $env:DB_PORT=$previewConfig.port
    $env:DB_NAME=$previewConfig.database
    $env:DB_USER=$previewConfig.username
    $env:DB_PASSWORD=$previewConfig.password
    Start-Process -FilePath (Get-Command php -ErrorAction Stop).Source -ArgumentList '-S','127.0.0.1:8780','-t','public' -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectPath 'var/preview.stdout.log') -RedirectStandardError (Join-Path $projectPath 'var/preview.stderr.log')
}
$response=Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8780/index.php?page=players' -TimeoutSec 10
if ($response.StatusCode -ne 200 -or $response.Content -notmatch 'Steven Ha') { throw 'Preview verification failed. Check var/preview.stderr.log.' }
Write-Host 'Local preview: http://127.0.0.1:8780/'
Write-Host 'Accounts: var/local-preview-accounts.txt'

