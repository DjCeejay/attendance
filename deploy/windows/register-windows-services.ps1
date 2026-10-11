# ==============================================================================
# ARTSCI Attendance - Register Windows Services (Caddy & PHP FastCGI)
# Run as Administrator in PowerShell:
#   .\register-windows-services.ps1
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Windows Office Server - Service Registration" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] Must be run as Administrator!" -ForegroundColor Red
    exit 1
}

$appRoot = "C:\artsci-attendance"
$phpDir = "C:\tools\php83"
$caddyDir = "C:\tools\caddy"
$nssmExe = "C:\tools\nssm\nssm.exe"

# Check NSSM availability
if (-not (Test-Path $nssmExe)) {
    Write-Host "[INFO] NSSM (Non-Sucking Service Manager) not found at $nssmExe." -ForegroundColor Yellow
    Write-Host "You can download NSSM from https://nssm.cc/download and place nssm.exe in C:\tools\nssm\" -ForegroundColor Yellow
    Write-Host "Alternatively, you can run Caddy and PHP FastCGI using the provided start batch script." -ForegroundColor Yellow
} else {
    # 1. Register PHP FastCGI Service
    Write-Host "[1/2] Registering PHP-FastCGI Windows Service..." -ForegroundColor Yellow
    & $nssmExe stop "ARTSCI-PHP-FastCGI" 2>$null | Out-Null
    & $nssmExe remove "ARTSCI-PHP-FastCGI" confirm 2>$null | Out-Null

    & $nssmExe install "ARTSCI-PHP-FastCGI" "$phpDir\php-cgi.exe" "-b 127.0.0.1:9000"
    & $nssmExe set "ARTSCI-PHP-FastCGI" AppDirectory "$appRoot"
    & $nssmExe set "ARTSCI-PHP-FastCGI" AppEnvironmentExtra "PHP_FCGI_MAX_REQUESTS=1000"
    & $nssmExe set "ARTSCI-PHP-FastCGI" Start SERVICE_AUTO_START
    & $nssmExe set "ARTSCI-PHP-FastCGI" AppRestartDelay 2000
    & $nssmExe start "ARTSCI-PHP-FastCGI"
    Write-Host "  ✓ ARTSCI-PHP-FastCGI service installed and started." -ForegroundColor Green

    # 2. Register Caddy Web Server Service
    Write-Host "[2/2] Registering Caddy Web Server Windows Service..." -ForegroundColor Yellow
    & $nssmExe stop "ARTSCI-Caddy" 2>$null | Out-Null
    & $nssmExe remove "ARTSCI-Caddy" confirm 2>$null | Out-Null

    & $nssmExe install "ARTSCI-Caddy" "$caddyDir\caddy.exe" "run --config `"$appRoot\deploy\windows\Caddyfile`""
    & $nssmExe set "ARTSCI-Caddy" AppDirectory "$caddyDir"
    & $nssmExe set "ARTSCI-Caddy" Start SERVICE_AUTO_START
    & $nssmExe set "ARTSCI-Caddy" AppRestartDelay 3000
    & $nssmExe start "ARTSCI-Caddy"
    Write-Host "  ✓ ARTSCI-Caddy service installed and started." -ForegroundColor Green
}

Write-Host ""
Write-Host "Services configured to start automatically on Windows boot." -ForegroundColor Cyan
