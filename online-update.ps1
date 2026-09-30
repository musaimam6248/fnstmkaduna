param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

function Resolve-PhpExe {
    if ($env:PHP_EXE -and (Test-Path -LiteralPath $env:PHP_EXE)) {
        return (Resolve-Path -LiteralPath $env:PHP_EXE).Path
    }

    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd -and $phpCmd.Source -and (Test-Path -LiteralPath $phpCmd.Source)) {
        return $phpCmd.Source
    }

    $candidates = @(
        (Join-Path $projectRoot "php\php.exe"),
        (Join-Path $projectRoot "xampp\php\php.exe"),
        (Join-Path $projectRoot "xampp\php\windowsXamppPhp\php.exe"),
        (Join-Path $env:USERPROFILE "OneDrive\Desktop\school1\xampp\php\windowsXamppPhp\php.exe"),
        (Join-Path $env:USERPROFILE "Desktop\school1\xampp\php\windowsXamppPhp\php.exe"),
        "C:\xampp\php\php.exe",
        "C:\xamppp\php\php.exe",
        "C:\php\php.exe"
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    throw "PHP executable not found. Install PHP 8+ or set PHP_EXE to your php.exe path."
}

$phpExe = Resolve-PhpExe
$phpDir = Split-Path -Parent $phpExe
$extDir = Join-Path $phpDir "ext"

$phpArgs = @()
if (Test-Path -LiteralPath $extDir) {
    $phpArgs += "-n"
    $phpArgs += "-d"
    $phpArgs += "extension_dir=$extDir"

    $neededExtensions = @("pdo_sqlite", "sqlite3", "gd", "mbstring")
    foreach ($ext in $neededExtensions) {
        $dllPath = Join-Path $extDir ("php_{0}.dll" -f $ext)
        if (Test-Path -LiteralPath $dllPath) {
            $phpArgs += "-d"
            $phpArgs += "extension=$ext"
        }
    }
}

$moduleList = & $phpExe @phpArgs -m
if ($LASTEXITCODE -ne 0) {
    throw "Failed to run PHP CLI using: $phpExe"
}

if (-not ($moduleList -match "^pdo_sqlite$")) {
    throw "Missing required extension: pdo_sqlite. Update PHP config or use a PHP build with SQLite enabled."
}

Write-Host "Running ONLINE UPDATE (database migration + runtime checks)..."
Write-Host ("Using PHP: {0}" -f $phpExe)
& $phpExe @phpArgs "database/migrate.php"
if ($LASTEXITCODE -ne 0) {
    throw "Database migration failed."
}

Write-Host "ONLINE UPDATE completed successfully."
Write-Host "Next: run .\start-online.ps1 to start the online server."
