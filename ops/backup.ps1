[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $Database,

    [Parameter(Mandatory = $true)]
    [string] $OutputDirectory,

    [Parameter(Mandatory = $true)]
    [string] $PrivateStoragePath,

    [string] $MySqlDumpPath = 'mysqldump',

    [string] $DefaultsFile,

    [string] $HostName = '127.0.0.1',

    [int] $Port = 3306,

    [string] $User
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $PrivateStoragePath -PathType Container)) {
    throw 'The private storage path does not exist.'
}

$resolvedPrivatePath = (Resolve-Path -LiteralPath $PrivateStoragePath).Path
$resolvedOutputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
if ($resolvedOutputRoot.StartsWith($resolvedPrivatePath, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'The backup destination must not be inside private application storage.'
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$bundle = Join-Path $resolvedOutputRoot "jca-backup-$stamp"
New-Item -ItemType Directory -Path $bundle -Force | Out-Null

$databaseDump = Join-Path $bundle 'database.sql'
$privateArchive = Join-Path $bundle 'private-storage.zip'
$dumpError = Join-Path $bundle 'database-dump.stderr.txt'

$dumpArguments = @()
if ($DefaultsFile) {
    $resolvedDefaults = (Resolve-Path -LiteralPath $DefaultsFile).Path
    $dumpArguments += "--defaults-extra-file=$resolvedDefaults"
}
$dumpArguments += @('--protocol=tcp', "--host=$HostName", "--port=$Port")
if ($User) {
    $dumpArguments += "--user=$User"
}
$dumpArguments += @(
    '--single-transaction',
    '--routines',
    '--triggers',
    '--hex-blob',
    '--set-gtid-purged=OFF',
    '--default-character-set=utf8mb4',
    $Database
)

$dump = Start-Process -FilePath $MySqlDumpPath -ArgumentList $dumpArguments -Wait -PassThru -NoNewWindow -RedirectStandardOutput $databaseDump -RedirectStandardError $dumpError
if ($dump.ExitCode -ne 0 -or -not (Test-Path -LiteralPath $databaseDump) -or (Get-Item -LiteralPath $databaseDump).Length -eq 0) {
    throw 'The database backup failed. Review the protected local stderr file.'
}
Remove-Item -LiteralPath $dumpError -Force

$privateItems = Get-ChildItem -LiteralPath $resolvedPrivatePath -Force
if ($privateItems.Count -eq 0) {
    $emptyMarker = Join-Path $bundle '.private-storage-empty'
    New-Item -ItemType File -Path $emptyMarker | Out-Null
    Compress-Archive -LiteralPath $emptyMarker -DestinationPath $privateArchive -CompressionLevel Optimal
    Remove-Item -LiteralPath $emptyMarker -Force
} else {
    Compress-Archive -Path (Join-Path $resolvedPrivatePath '*') -DestinationPath $privateArchive -CompressionLevel Optimal
}

$manifest = @($databaseDump, $privateArchive) | ForEach-Object {
    $hash = Get-FileHash -LiteralPath $_ -Algorithm SHA256
    "{0}  {1}" -f $hash.Hash.ToLowerInvariant(), [System.IO.Path]::GetFileName($_)
}
$manifest | Set-Content -LiteralPath (Join-Path $bundle 'manifest.sha256.txt') -Encoding UTF8

@{
    created_at = (Get-Date).ToUniversalTime().ToString('o')
    database = $Database
    includes_private_storage = $true
    encrypted_by_script = $false
} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $bundle 'metadata.json') -Encoding UTF8

Write-Output $bundle
