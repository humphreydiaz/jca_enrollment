param(
    [string] $OutputDirectory
)

$ErrorActionPreference = 'Stop'

$repositoryRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$deploymentRoot = [System.IO.Path]::GetFullPath((Join-Path $repositoryRoot 'storage\app\deployment'))

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = $deploymentRoot
}

$resolvedOutput = [System.IO.Path]::GetFullPath($OutputDirectory)
if (-not $resolvedOutput.StartsWith($deploymentRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "OutputDirectory must stay inside $deploymentRoot"
}

$manifest = Join-Path $repositoryRoot 'public\build\manifest.json'
if (-not (Test-Path -LiteralPath $manifest -PathType Leaf)) {
    throw 'The production frontend build is missing. Run npm run build first.'
}

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path $resolvedOutput "jcsgo-hostinger-$timestamp"
$archive = "$staging.zip"

New-Item -ItemType Directory -Force -Path $staging | Out-Null

$sourceDirectories = @('app', 'config', 'database', 'public', 'resources', 'routes')
foreach ($directory in $sourceDirectories) {
    Copy-Item -LiteralPath (Join-Path $repositoryRoot $directory) -Destination (Join-Path $staging $directory) -Recurse
}

# Local databases (database/database.sqlite) and Vite's dev-server marker must never reach the server.
Get-ChildItem -LiteralPath (Join-Path $staging 'database') -Filter '*.sqlite*' -File -ErrorAction SilentlyContinue | Remove-Item -Force
Remove-Item -LiteralPath (Join-Path $staging 'public\hot') -Force -ErrorAction SilentlyContinue

New-Item -ItemType Directory -Force -Path (Join-Path $staging 'bootstrap\cache') | Out-Null
Copy-Item -LiteralPath (Join-Path $repositoryRoot 'bootstrap\app.php') -Destination (Join-Path $staging 'bootstrap\app.php')
Copy-Item -LiteralPath (Join-Path $repositoryRoot 'bootstrap\providers.php') -Destination (Join-Path $staging 'bootstrap\providers.php')

$storageDirectories = @(
    'storage\app\private\documents',
    'storage\app\private\reports',
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)
foreach ($directory in $storageDirectories) {
    New-Item -ItemType Directory -Force -Path (Join-Path $staging $directory) | Out-Null
}

$sourceFiles = @('artisan', 'composer.json', 'composer.lock', '.env.production.example')
foreach ($file in $sourceFiles) {
    Copy-Item -LiteralPath (Join-Path $repositoryRoot $file) -Destination (Join-Path $staging $file)
}

Copy-Item -LiteralPath (Join-Path $repositoryRoot 'deploy\hostinger\.htaccess') -Destination (Join-Path $staging '.htaccess')

# Windows PowerShell 5.1's Compress-Archive stores "app\Models\User.php"; Linux unzip on Hostinger needs
# "app/Models/User.php". Write every entry with forward slashes, plus entries for empty folders.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive -Force }
$zip = [System.IO.Compression.ZipFile]::Open($archive, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stagingPrefix = $staging.TrimEnd('\') + '\'
    foreach ($item in Get-ChildItem -LiteralPath $staging -Recurse -Force) {
        $entryName = $item.FullName.Substring($stagingPrefix.Length).Replace('\', '/')
        if ($item.PSIsContainer) {
            if (-not (Get-ChildItem -LiteralPath $item.FullName -Force | Select-Object -First 1)) {
                [void] $zip.CreateEntry($entryName + '/')
            }
        } else {
            [void] [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $item.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        }
    }
} finally {
    $zip.Dispose()
}

Write-Output $archive
