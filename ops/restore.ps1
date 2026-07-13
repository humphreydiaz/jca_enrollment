[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $BundleDirectory,

    [Parameter(Mandatory = $true)]
    [string] $Database,

    [Parameter(Mandatory = $true)]
    [string] $RestorePrivatePath,

    [Parameter(Mandatory = $true)]
    [switch] $ConfirmIsolatedEmptyTarget,

    [string] $MySqlPath = 'mysql',

    [string] $DefaultsFile,

    [string] $HostName = '127.0.0.1',

    [int] $Port = 3306,

    [string] $User
)

$ErrorActionPreference = 'Stop'

if (-not $ConfirmIsolatedEmptyTarget) {
    throw 'Restore is allowed only after explicitly confirming an isolated empty target.'
}

$bundle = (Resolve-Path -LiteralPath $BundleDirectory).Path
$databaseDump = Join-Path $bundle 'database.sql'
$privateArchive = Join-Path $bundle 'private-storage.zip'
$manifestPath = Join-Path $bundle 'manifest.sha256.txt'

foreach ($required in @($databaseDump, $privateArchive, $manifestPath)) {
    if (-not (Test-Path -LiteralPath $required -PathType Leaf)) {
        throw 'The backup bundle is incomplete.'
    }
}

$manifestLines = Get-Content -LiteralPath $manifestPath
foreach ($file in @($databaseDump, $privateArchive)) {
    $name = [System.IO.Path]::GetFileName($file)
    $expectedLine = $manifestLines | Where-Object { $_ -match "\s\s$([regex]::Escape($name))$" }
    if (-not $expectedLine) {
        throw 'The backup manifest is missing a required file.'
    }
    $expected = ($expectedLine -split '\s\s')[0]
    $actual = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $expected) {
        throw 'Backup checksum verification failed.'
    }
}

if (Test-Path -LiteralPath $RestorePrivatePath) {
    if ((Get-ChildItem -LiteralPath $RestorePrivatePath -Force).Count -gt 0) {
        throw 'The private-storage restore target must be empty.'
    }
} else {
    New-Item -ItemType Directory -Path $RestorePrivatePath -Force | Out-Null
}

$mysqlArguments = @()
if ($DefaultsFile) {
    $resolvedDefaults = (Resolve-Path -LiteralPath $DefaultsFile).Path
    $mysqlArguments += "--defaults-extra-file=$resolvedDefaults"
}
$mysqlArguments += @('--protocol=tcp', "--host=$HostName", "--port=$Port")
if ($User) {
    $mysqlArguments += "--user=$User"
}
$mysqlArguments += @('--batch', '--skip-column-names', '--execute=SELECT/**/COUNT(*)FROM/**/information_schema.tables/**/WHERE/**/table_schema=DATABASE();', $Database)
$tableCountFile = Join-Path ([System.IO.Path]::GetTempPath()) "jca-restore-table-count-$([guid]::NewGuid().ToString('N')).txt"
$tableCheck = Start-Process -FilePath $MySqlPath -ArgumentList $mysqlArguments -Wait -PassThru -NoNewWindow -RedirectStandardOutput $tableCountFile
if ($tableCheck.ExitCode -ne 0) {
    throw 'The target database could not be checked.'
}
$tableCount = [int] ((Get-Content -LiteralPath $tableCountFile -Raw).Trim())
Remove-Item -LiteralPath $tableCountFile -Force
if ($tableCount -ne 0) {
    throw 'The target database must be empty before restore.'
}

$restoreArguments = @()
if ($DefaultsFile) {
    $restoreArguments += "--defaults-extra-file=$resolvedDefaults"
}
$restoreArguments += @('--protocol=tcp', "--host=$HostName", "--port=$Port")
if ($User) {
    $restoreArguments += "--user=$User"
}
$restoreArguments += @('--default-character-set=utf8mb4', $Database)
$restore = Start-Process -FilePath $MySqlPath -ArgumentList $restoreArguments -Wait -PassThru -NoNewWindow -RedirectStandardInput $databaseDump
if ($restore.ExitCode -ne 0) {
    throw 'Database restore failed.'
}

Expand-Archive -LiteralPath $privateArchive -DestinationPath $RestorePrivatePath

Write-Output 'Restore completed; run the documented application and reconciliation checks before any cutover.'
