param(
    [ValidateSet('down', 'up', 'status')]
    [string] $Action = 'down',

    [ValidateSet('docker', 'local')]
    [string] $Target = 'docker',

    [string] $ComposeFile,
    [string] $Render = 'errors::503',
    [int] $Retry = 60,
    [string] $Secret = $env:MAINTENANCE_SECRET,
    [string] $AppDir
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $PSCommandPath
$projectRoot = Split-Path -Parent $scriptDir

if (-not $ComposeFile) {
    $ComposeFile = Join-Path $projectRoot 'docker-compose.yml'
}

if (-not [System.IO.Path]::IsPathRooted($ComposeFile)) {
    $ComposeFile = Join-Path $projectRoot $ComposeFile
}

if (-not $AppDir) {
    if ($env:LEGACY_APP_DIR) {
        $AppDir = $env:LEGACY_APP_DIR
    } else {
        $AppDir = $projectRoot
    }
}

function Invoke-Artisan {
    param([string[]] $ArtisanArgs)

    if ($Target -eq 'docker') {
        & docker compose -f $ComposeFile exec -T app php artisan @ArtisanArgs
        if ($LASTEXITCODE -ne 0) {
            throw "php artisan failed with exit code $LASTEXITCODE"
        }
        return
    }

    Push-Location $AppDir
    try {
        & php artisan @ArtisanArgs
        if ($LASTEXITCODE -ne 0) {
            throw "php artisan failed with exit code $LASTEXITCODE"
        }
    } finally {
        Pop-Location
    }
}

function Get-MaintenanceStatus {
    if ($Target -eq 'docker') {
        & docker compose -f $ComposeFile exec -T app sh -lc 'if test -f storage/framework/down; then echo down; else echo up; fi'
        if ($LASTEXITCODE -ne 0) {
            throw "maintenance status check failed with exit code $LASTEXITCODE"
        }
        return
    }

    if (Test-Path (Join-Path $AppDir 'storage/framework/down')) {
        'down'
    } else {
        'up'
    }
}

switch ($Action) {
    'down' {
        $downArgs = @('down', "--render=$Render", "--retry=$Retry")
        if ($Secret) {
            $downArgs += "--secret=$Secret"
        }
        Invoke-Artisan -ArtisanArgs $downArgs
    }
    'up' {
        Invoke-Artisan -ArtisanArgs @('up')
    }
    'status' {
        Get-MaintenanceStatus
    }
}
