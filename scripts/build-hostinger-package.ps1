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

Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $archive -CompressionLevel Optimal

Write-Output $archive
