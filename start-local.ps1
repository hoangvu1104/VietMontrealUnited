$ErrorActionPreference = 'Stop'
$projectPath = $PSScriptRoot
$siteUrl = 'http://127.0.0.1:8765/'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$logPath = Join-Path $projectPath 'var'
New-Item -ItemType Directory -Force -Path $logPath | Out-Null

# Reuse existing services; do not start a second process on occupied ports.
if (-not (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue)) {
    $mysqlPath = 'C:\xampp\mysql\bin\mysqld.exe'
    if (-not (Test-Path -LiteralPath $mysqlPath)) {
        throw 'Please start MySQL, then run this script again.'
    }
    Start-Process -FilePath $mysqlPath -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini','--standalone' -WindowStyle Hidden
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) { break }
        Start-Sleep -Milliseconds 500
    }
    if (-not (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue)) {
        throw 'MySQL did not start. Check the XAMPP MySQL log.'
    }
}

if (-not (Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath $phpPath -ArgumentList '-S','127.0.0.1:8765','-t','public' -WorkingDirectory $projectPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logPath 'php-local.stdout.log') -RedirectStandardError (Join-Path $logPath 'php-local.stderr.log')
}

for ($attempt = 0; $attempt -lt 15; $attempt++) {
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $siteUrl -TimeoutSec 3
        if ($response.StatusCode -eq 200 -and $response.Content -match 'Club overview' -and $response.Content -notmatch 'Fatal error|Warning:|Uncaught') {
            Write-Host "Website is running: $siteUrl"
            exit 0
        }
    } catch { }
    Start-Sleep -Milliseconds 500
}
throw 'Website did not start correctly. Check var/php-local.stderr.log and the database configuration.'
