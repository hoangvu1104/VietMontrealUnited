param(
    [string]$SftpHost = $env:SFTP_HOST,
    [string]$SftpUser = $env:SFTP_USER,
    [int]$Port = 22,
    [string]$PublicRoot = $env:SFTP_PUBLIC_ROOT,
    [string]$IdentityFile = $env:SFTP_IDENTITY_FILE,
    [string]$KnownHostsFile = $env:SFTP_KNOWN_HOSTS_FILE,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$manifest = Get-Content (Join-Path $PSScriptRoot 'deploy-manifest.json') -Raw | ConvertFrom-Json

function Quote-Sftp([string]$Value) {
    if ($Value -match '[\r\n"\\]' -or $Value.Contains('`')) { throw 'Unsupported SFTP path characters.' }
    return '"' + $Value + '"'
}

if ($DryRun) {
    if (!$PublicRoot) { $PublicRoot = '/home/CPANEL_USER/public_html/vmu' }
} else {
    if ($SftpHost -notmatch '^[a-zA-Z0-9][a-zA-Z0-9.-]*$') { throw 'Set SFTP_HOST to the hosting IP or hostname.' }
    if ($SftpUser -notmatch '^[a-zA-Z0-9_][a-zA-Z0-9_.-]*$') { throw 'Set SFTP_USER to the cPanel SSH username.' }
    foreach ($file in @($IdentityFile, $KnownHostsFile)) {
        if (!$file -or !(Test-Path -LiteralPath $file -PathType Leaf)) { throw 'SSH identity and verified known_hosts files are required.' }
    }
}
if ($Port -lt 1 -or $Port -gt 65535) { throw 'Invalid port.' }
foreach ($remoteRoot in @($PublicRoot)) {
    if (!$remoteRoot -or $remoteRoot -notmatch '^/[^\s]+$' -or $remoteRoot -match '(^|/)\.\.?(/|$)' -or $remoteRoot -eq '/') {
        throw 'Specify absolute remote roots without spaces or parent traversal.'
    }
    $null = Quote-Sftp $remoteRoot
}
$PublicRoot = $PublicRoot.TrimEnd('/')

$commands = [System.Collections.Generic.List[string]]::new()
$directories = [System.Collections.Generic.HashSet[string]]::new()
$count = 0
# Validate the existing destination before transferring files.

$commands.Add('cd ' + (Quote-Sftp $PublicRoot))
foreach ($kind in @('public')) {
    $destinationRoot = $PublicRoot
    # The destination must already exist; a wrong path must fail before upload.
    $commands.Add('cd ' + (Quote-Sftp $destinationRoot))
    foreach ($relative in $manifest.$kind) {
        if ($relative -notmatch '^[a-zA-Z0-9_./-]+$' -or $relative -match '(^|/)\.\.?(/|$)' -or $relative.StartsWith('/')) {
            throw "Invalid manifest path: $relative"
        }
        if ($relative -match '^(storage/|uploads/)' -and $relative -ne 'uploads/.htaccess') { continue }
        if ($relative -match '^config/.*\.local\.php$') { throw 'Production configuration cannot be deployed.' }
        $sourceRelative = $relative
        $source = Join-Path $root $sourceRelative
        # Dotfiles such as .htaccess are hidden on Linux runners.
        $item = Get-Item -LiteralPath $source -Force
        if ($item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw "Invalid source: $relative" }
        $parent = $destinationRoot
        $parts = $relative.Split('/')
        for ($i = 0; $i -lt $parts.Length - 1; $i++) {
            $parent += '/' + $parts[$i]
            if ($directories.Add($parent)) {
                # Existing directories are expected. A failed mkdir is followed by a strict cd check.
                $commands.Add('-mkdir ' + (Quote-Sftp $parent))
                $commands.Add('cd ' + (Quote-Sftp $parent))
            }
        }
        $destination = $destinationRoot + '/' + $relative
        $commands.Add('put ' + (Quote-Sftp ($source.Replace('\', '/'))) + ' ' + (Quote-Sftp $destination))
        $count++
    }
}
if ($DryRun) {
    $commands | Write-Output
    Write-Output "Dry run: $count allowlisted files; no connection or upload."
    exit 0
}

$batch = Join-Path ([IO.Path]::GetTempPath()) ('vmu-sftp-' + [guid]::NewGuid().ToString('N') + '.txt')
try {
    [IO.File]::WriteAllLines($batch, $commands, [Text.UTF8Encoding]::new($false))
    & sftp -b $batch -P $Port -i $IdentityFile -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$KnownHostsFile" -o ConnectTimeout=30 "${SftpUser}@${SftpHost}"
    if ($LASTEXITCODE -ne 0) { throw 'SFTP failed. Some files may have been uploaded; inspect the log and rerun after fixing the cause.' }
    Write-Output "Deployed $count allowlisted files."
} finally {
    Remove-Item -LiteralPath $batch -Force -ErrorAction SilentlyContinue
}



