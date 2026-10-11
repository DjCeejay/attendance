# ==============================================================================
# ARTSCI Attendance - Windows Office Server Prerequisites & Hardware Audit
# Run this script in an Administrative PowerShell session:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\install-prerequisites.ps1
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Windows Office Server - Prerequisites & Audit" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Administrator Privilege Check
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Please right-click PowerShell and select 'Run as Administrator'." -ForegroundColor Yellow
    exit 1
}

# 2. Hardware & OS Audit
Write-Host "[1/6] Running Hardware & Operating System Assessment..." -ForegroundColor Yellow
$os = Get-CimInstance Win32_OperatingSystem
$cs = Get-CimInstance Win32_ComputerSystem
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1

$totalRamGB = [math]::Round($cs.TotalPhysicalMemory / 1GB, 2)
$systemDrive = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
$freeDiskGB = [math]::Round($systemDrive.FreeSpace / 1GB, 2)

Write-Host "  Operating System : $($os.Caption) ($($os.OSArchitecture))" -ForegroundColor Green
Write-Host "  Processor        : $($cpu.Name) ($($cpu.NumberOfCores) Cores, $($cpu.NumberOfLogicalProcessors) Threads)" -ForegroundColor Green
Write-Host "  Installed RAM    : $totalRamGB GB" -ForegroundColor Green
Write-Host "  Free Disk (C:)   : $freeDiskGB GB" -ForegroundColor Green

# Hardware Evaluation Checklist
if ($totalRamGB -lt 4.0) {
    Write-Host "  [ALERT] RAM is under 4 GB. The office server requires at least 4 GB (8 GB+ recommended)." -ForegroundColor Red
} elseif ($totalRamGB -lt 8.0) {
    Write-Host "  [WARNING] RAM is $totalRamGB GB. 8 GB+ is recommended for comfortable operation of Windows + PostgreSQL + PHP." -ForegroundColor Yellow
} else {
    Write-Host "  [OK] RAM is sufficient for production office server operation." -ForegroundColor Green
}

if ($freeDiskGB -lt 15.0) {
    Write-Host "  [ALERT] Low disk space! Only $freeDiskGB GB available. Free up at least 20 GB for system logs and backups." -ForegroundColor Red
} else {
    Write-Host "  [OK] Sufficient disk space available on drive C:." -ForegroundColor Green
}

# 3. Network Adapters Audit
Write-Host ""
Write-Host "[2/6] Auditing Network Adapters & IP Configuration..." -ForegroundColor Yellow
$adapters = Get-NetAdapter | Where-Object { $_.Status -eq "Up" }

foreach ($adapter in $adapters) {
    $ipConfig = Get-NetIPAddress -InterfaceIndex $adapter.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue
    $ipAddress = if ($ipConfig) { $ipConfig.IPAddress } else { "No IPv4 assigned" }
    Write-Host "  Adapter: $($adapter.Name) ($($adapter.InterfaceDescription))" -ForegroundColor Cyan
    Write-Host "    MAC Address : $($adapter.MacAddress)" -ForegroundColor Gray
    Write-Host "    Current IP  : $ipAddress" -ForegroundColor Gray
    Write-Host "    Link Speed  : $($adapter.LinkSpeed)" -ForegroundColor Gray
}

# 4. Disable Windows Sleep & Standby
Write-Host ""
Write-Host "[3/6] Configuring Power Plan (Keep Server Always Powered On)..." -ForegroundColor Yellow
try {
    # Set AC (plugged in) standby and hibernate timeouts to 0 (Never sleep)
    powercfg -change -standby-timeout-ac 0
    powercfg -change -hibernate-timeout-ac 0
    powercfg -change -disk-timeout-ac 0
    Write-Host "  [OK] Disabled automatic Sleep and Hibernation when plugged in." -ForegroundColor Green
} catch {
    Write-Host "  [WARNING] Could not update power settings automatically: $_" -ForegroundColor Yellow
}

# 5. Check PHP 8.3 & Required Extensions
Write-Host ""
Write-Host "[4/6] Verifying PHP 8.3 Installation & Extensions..." -ForegroundColor Yellow
$phpCmd = Get-Command php -ErrorAction SilentlyContinue

if ($phpCmd) {
    $phpVersion = php -r "echo PHP_VERSION;"
    Write-Host "  PHP Version Found: $phpVersion" -ForegroundColor Green

    $requiredExtensions = @('curl', 'fileinfo', 'mbstring', 'openssl', 'pdo_pgsql', 'sockets')
    $missingExtensions = @()

    foreach ($ext in $requiredExtensions) {
        $loaded = php -r "echo extension_loaded('$ext') ? '1' : '0';"
        if ($loaded -eq '1') {
            Write-Host "    ✓ Extension [$ext] is loaded" -ForegroundColor Green
        } else {
            Write-Host "    ✗ Extension [$ext] is MISSING" -ForegroundColor Red
            $missingExtensions += $ext
        }
    }

    if ($missingExtensions.Count -gt 0) {
        Write-Host "  [ACTION REQUIRED] Enable missing extensions in your php.ini file: $($missingExtensions -join ', ')" -ForegroundColor Yellow
    }
} else {
    Write-Host "  [ACTION REQUIRED] PHP is not installed or not in PATH." -ForegroundColor Red
    Write-Host "  Download PHP 8.3 for Windows (Non-Thread Safe x64) from: https://windows.php.net/download/" -ForegroundColor Yellow
    Write-Host "  Extract to C:\tools\php83 and add to system PATH." -ForegroundColor Yellow
}

# 6. Check PostgreSQL Installation
Write-Host ""
Write-Host "[5/6] Verifying PostgreSQL Database Installation..." -ForegroundColor Yellow
$psqlCmd = Get-Command psql -ErrorAction SilentlyContinue
$pgDumpCmd = Get-Command pg_dump -ErrorAction SilentlyContinue

if ($psqlCmd -and $pgDumpCmd) {
    Write-Host "  ✓ PostgreSQL client tools (psql, pg_dump) found in PATH." -ForegroundColor Green
} else {
    Write-Host "  [ACTION REQUIRED] PostgreSQL tools not found in PATH." -ForegroundColor Yellow
    Write-Host "  Install PostgreSQL 16 or 17 from https://www.postgresql.org/download/windows/" -ForegroundColor Yellow
    Write-Host "  Default path: C:\Program Files\PostgreSQL\16\bin" -ForegroundColor Yellow
}

# 7. Check Web Server (Caddy or IIS)
Write-Host ""
Write-Host "[6/6] Verifying Web Server (Caddy or IIS)..." -ForegroundColor Yellow
$caddyCmd = Get-Command caddy -ErrorAction SilentlyContinue
$iisService = Get-Service -Name W3SVC -ErrorAction SilentlyContinue

if ($caddyCmd) {
    Write-Host "  ✓ Caddy Web Server found in PATH." -ForegroundColor Green
} elseif ($iisService) {
    Write-Host "  ✓ Microsoft IIS is installed on this system." -ForegroundColor Green
} else {
    Write-Host "  [ACTION REQUIRED] Neither Caddy nor IIS was detected." -ForegroundColor Yellow
    Write-Host "  Recommended: Download Caddy for Windows (https://caddyserver.com/download) and place caddy.exe in C:\tools\caddy" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Assessment Complete!" -ForegroundColor Cyan
Write-Host " Review any [ACTION REQUIRED] items above before proceeding." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
