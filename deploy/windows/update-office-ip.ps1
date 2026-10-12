# ==============================================================================
# ARTSCI Attendance - Automatic Office Public IP Updater for Windows
# Location: C:\ARTSCI\Attendance\deploy\windows\update-office-ip.ps1
# Runs every 5 minutes via Windows Task Scheduler.
# ==============================================================================

param (
    [string]$AppRoot = "C:\ARTSCI\Attendance",
    [string]$PhpExe = "C:\PHP85\php.exe"
)

# Detect project directory dynamically if run from inside repository
if (-not (Test-Path "$AppRoot\artisan")) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path "$scriptParent\artisan") {
        $AppRoot = $scriptParent
    } else {
        $AppRoot = Get-Location
    }
}

# Detect PHP executable if not at default C:\PHP85\php.exe
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

if (-not (Test-Path $PhpExe)) {
    Write-IpLog "PHP executable not found at '$PhpExe'. Cannot run attendance:update-office-ip." "ERROR"
    exit 1
}

if (-not (Test-Path "$AppRoot\artisan")) {
    Write-IpLog "Laravel artisan not found at '$AppRoot\artisan'. Exiting." "ERROR"
    exit 1
}

# Execute Laravel artisan command
try {
    Set-Location $AppRoot
    $output = & $PhpExe artisan attendance:update-office-ip 2>&1
    $exitCode = $LASTEXITCODE

    if ($exitCode -eq 0) {
        Write-IpLog "IP check succeeded: $($output -join ' | ')" "INFO"
        exit 0
    } else {
        Write-IpLog "IP check exited with code $exitCode: $($output -join ' | ')" "WARNING"
        exit $exitCode
    }
} catch {
    Write-IpLog "Exception occurred while executing update: $_" "ERROR"
    exit 1
}
