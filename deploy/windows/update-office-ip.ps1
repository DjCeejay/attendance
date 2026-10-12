# ==============================================================================
# ARTSCI Attendance - Automatic Office Public IP Updater for Windows
# Location: C:\ARTSCI\Attendance\deploy\windows\update-office-ip.ps1
# Runs every 5 minutes and immediately on Network State Changes via Task Scheduler.
# ==============================================================================

param (
    [string]$AppRoot = "C:\ARTSCI\Attendance",
    [string]$PhpExe = "C:\PHP85\php.exe",
    [switch]$Force
)

# 1. Detect project directory dynamically if run from inside repository
if (-not (Test-Path "$AppRoot\artisan")) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path "$scriptParent\artisan") {
        $AppRoot = $scriptParent
    } else {
        $AppRoot = Get-Location
    }
}

# 2. Detect PHP executable if not at default C:\PHP85\php.exe
if (-not (Test-Path $PhpExe)) {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $PhpExe = $phpCmd.Source
    } else {
        $possiblePaths = @("C:\PHP85\php.exe", "C:\PHP\php.exe", "C:\tools\php85\php.exe", "C:\tools\php83\php.exe")
        foreach ($p in $possiblePaths) {
            if (Test-Path $p) {
                $PhpExe = $p
                break
            }
        }
    }
}

$logFile = Join-Path $AppRoot "storage\logs\office-ip-updater.log"
$lockFile = Join-Path $AppRoot "storage\framework\update-office-ip.lock"
$lastSuccessFile = Join-Path $AppRoot "storage\framework\update-office-ip-lastsuccess.txt"

function Write-IpLog {
    param ([string]$Message, [string]$Level = "INFO")
    $logLine = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [$Level] $Message"
    Write-Host $logLine
    $logDir = Split-Path $logFile -Parent
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    Add-Content -Path $logFile -Value $logLine -ErrorAction SilentlyContinue
}

# 3. Verify Prerequisites
if (-not (Test-Path $PhpExe)) {
    Write-IpLog "PHP executable not found at '$PhpExe'. Cannot run attendance:update-office-ip." "ERROR"
    exit 1
}

if (-not (Test-Path "$AppRoot\artisan")) {
    Write-IpLog "Laravel artisan not found at '$AppRoot\artisan'. Exiting." "ERROR"
    exit 1
}

# 4. Mutex / Concurrency Lock (Prevent overlapping updater executions)
$lockDir = Split-Path $lockFile -Parent
if (-not (Test-Path $lockDir)) {
    New-Item -ItemType Directory -Path $lockDir -Force | Out-Null
}

if (Test-Path $lockFile) {
    $lockAge = (Get-Date) - (Get-Item $lockFile).LastWriteTime
    if ($lockAge.TotalMinutes -lt 3) {
        Write-IpLog "Another instance is actively running (lock age: $([math]::Round($lockAge.TotalSeconds, 1))s). Skipping overlapping execution." "INFO"
        exit 0
    }
    Write-IpLog "Found stale lock file ($([math]::Round($lockAge.TotalMinutes, 1)) min old). Overriding stale lock." "WARNING"
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}

# 5. Debounce Check (Avoid spamming external IP APIs on rapid network events)
if (-not $Force -and (Test-Path $lastSuccessFile)) {
    $lastSuccessAge = (Get-Date) - (Get-Item $lastSuccessFile).LastWriteTime
    if ($lastSuccessAge.TotalSeconds -lt 15) {
        Write-IpLog "Office public IP was successfully checked recently ($([math]::Round($lastSuccessAge.TotalSeconds, 1))s ago). Debouncing execution." "INFO"
        exit 0
    }
}

# Acquire lockfile
Set-Content -Path $lockFile -Value "PID: $PID; Started: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"

try {
    # Brief stabilization pause (2s) to allow Windows adapter, DHCP, and DNS to settle after link state change
    Start-Sleep -Seconds 2

    Set-Location $AppRoot
    $output = & $PhpExe artisan attendance:update-office-ip 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -eq 0) {
        Set-Content -Path $lastSuccessFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" -ErrorAction SilentlyContinue
        Write-IpLog "IP check succeeded: $($output -join ' | ')" "INFO"
        exit 0
    } else {
        Write-IpLog "IP check exited with code ${exitCode}: $($output -join ' | ')" "WARNING"
        exit $exitCode
    }
} catch {
    Write-IpLog "Exception occurred while executing update: $_" "ERROR"
    exit 1
} finally {
    # Always remove the lockfile upon completion or error
    Remove-Item $lockFile -Force -ErrorAction SilentlyContinue
}
