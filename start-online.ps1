param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

function Resolve-PhpExe {
    function CanRunPhp {
        param([string]$ExePath)
        try {
            if (-not (Safe-TestPath -PathToCheck $ExePath)) {
                return $false
            }
            & $ExePath -v *> $null
            return $LASTEXITCODE -eq 0
        } catch {
            return $false
        }
    }

    if ($env:PHP_EXE -and (Test-Path -LiteralPath $env:PHP_EXE)) {
        $resolvedEnvPhp = (Resolve-Path -LiteralPath $env:PHP_EXE).Path
        if (CanRunPhp -ExePath $resolvedEnvPhp) {
            return $resolvedEnvPhp
        }
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
        if (Safe-TestPath -PathToCheck $candidate) {
            $resolvedCandidate = (Resolve-Path -LiteralPath $candidate).Path
            if (CanRunPhp -ExePath $resolvedCandidate) {
                return $resolvedCandidate
            }
        }
    }

    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd -and $phpCmd.Source -and (CanRunPhp -ExePath $phpCmd.Source)) {
        return $phpCmd.Source
    }

    throw "PHP executable not found. Install PHP 8+ or set PHP_EXE to your php.exe path."
}

function Safe-TestPath {
    param([string]$PathToCheck)
    try {
        return Test-Path -LiteralPath $PathToCheck
    } catch {
        return $false
    }
}

function Get-ListeningPids {
    param([int]$CheckPort)

    $pids = @()
    if (Get-Command Get-NetTCPConnection -ErrorAction SilentlyContinue) {
        $pids = @(Get-NetTCPConnection -LocalPort $CheckPort -State Listen -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess -Unique)
    }

    if (-not $pids -or $pids.Count -eq 0) {
        $matches = @(netstat -ano | Select-String (":$CheckPort\s+.*LISTENING"))
        foreach ($line in $matches) {
            $parts = ($line.Line -split "\s+") | Where-Object { $_ -ne "" }
            if ($parts.Count -gt 0) {
                $last = $parts[$parts.Count - 1]
                if ($last -match "^\d+$") {
                    $pids += [int]$last
                }
            }
        }
        $pids = @($pids | Select-Object -Unique)
    }

    return $pids
}

$phpExe = Resolve-PhpExe
$phpDir = Split-Path -Parent $phpExe
$extDir = Join-Path $phpDir "ext"

$phpArgs = @()
if (Safe-TestPath -PathToCheck $extDir) {
    $phpArgs += "-n"
    $phpArgs += "-d"
    $phpArgs += "extension_dir=$extDir"

    $neededExtensions = @("pdo_sqlite", "sqlite3", "gd", "mbstring")
    foreach ($ext in $neededExtensions) {
        $dllPath = Join-Path $extDir ("php_{0}.dll" -f $ext)
        if (Safe-TestPath -PathToCheck $dllPath) {
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

$existingPids = @(Get-ListeningPids -CheckPort $Port)
foreach ($procId in $existingPids) {
    $proc = Get-Process -Id $procId -ErrorAction SilentlyContinue
    if ($proc -and $proc.ProcessName -ieq "php") {
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
        Write-Host ("Stopped existing PHP server on port {0} (PID {1})." -f $Port, $procId)
    } else {
        $owner = if ($proc) { $proc.ProcessName } else { "PID $procId" }
        throw ("Port {0} is already used by {1}. Stop it or start with -Port <another-port>." -f $Port, $owner)
    }
}

Write-Host ("Using PHP: {0}" -f $phpExe)
Write-Host "Running database migration..."
& $phpExe @phpArgs "database/migrate.php"
if ($LASTEXITCODE -ne 0) {
    throw "Database migration failed."
}

$lanIp = (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -First 1 -ExpandProperty IPAddress)

Write-Host ("Starting ONLINE server at http://0.0.0.0:{0}" -f $Port)
Write-Host ("Open locally: http://localhost:{0}" -f $Port)
if ($lanIp) {
    Write-Host ("Open on your network: http://{0}:{1}" -f $lanIp, $Port)
}
Write-Host "Press Ctrl+C to stop."
& $phpExe @phpArgs "-S" ("0.0.0.0:{0}" -f $Port)
