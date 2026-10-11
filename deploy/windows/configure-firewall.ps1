# ==============================================================================
# ARTSCI Attendance - Windows Firewall LAN Security Configuration
# Run this script in an Administrative PowerShell session:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\configure-firewall.ps1
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Windows Office Server - Firewall Security Setup" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] Must be run as Administrator!" -ForegroundColor Red
    exit 1
}

# Define authorized office subnets
# Office Wi-Fi A (Tenda/ZTE): 192.168.0.0/24, 192.168.1.0/24
# Office Wi-Fi B (Airtel):     192.168.8.0/24
$allowedSubnets = @("192.168.0.0/24", "192.168.1.0/24", "192.168.8.0/24", "127.0.0.1")

Write-Host "Configuring Windows Defender Firewall rules..." -ForegroundColor Yellow
Write-Host "Allowed Remote Subnets: $($allowedSubnets -join ', ')" -ForegroundColor Gray

# 1. Remove any legacy or stale rules
Remove-NetFirewallRule -DisplayName "ARTSCI Attendance Web (HTTP 80)" -ErrorAction SilentlyContinue
Remove-NetFirewallRule -DisplayName "ARTSCI Attendance Secure (HTTPS 443)" -ErrorAction SilentlyContinue
Remove-NetFirewallRule -DisplayName "ARTSCI PostgreSQL Local Only" -ErrorAction SilentlyContinue

# 2. Inbound HTTP Port 80 (Redirect to HTTPS)
New-NetFirewallRule -DisplayName "ARTSCI Attendance Web (HTTP 80)" `
    -Direction Inbound `
    -Action Allow `
    -Protocol TCP `
    -LocalPort 80 `
    -RemoteAddress $allowedSubnets `
    -Profile Private, Domain `
    -Description "Allows office staff phones to connect to ARTSCI Attendance HTTP for HTTPS redirection." | Out-Null

Write-Host "  ✓ Inbound HTTP (Port 80) restricted to office LAN subnets." -ForegroundColor Green

# 3. Inbound HTTPS Port 443 (Primary Secure Web & PWA Port)
New-NetFirewallRule -DisplayName "ARTSCI Attendance Secure (HTTPS 443)" `
    -Direction Inbound `
    -Action Allow `
    -Protocol TCP `
    -LocalPort 443 `
    -RemoteAddress $allowedSubnets `
    -Profile Private, Domain `
    -Description "Allows office staff phones to connect to ARTSCI Attendance PWA over HTTPS." | Out-Null

Write-Host "  ✓ Inbound HTTPS (Port 443) restricted to office LAN subnets." -ForegroundColor Green

# 4. Explicitly block external access to PostgreSQL (Port 5432)
New-NetFirewallRule -DisplayName "ARTSCI PostgreSQL Local Only" `
    -Direction Inbound `
    -Action Block `
    -Protocol TCP `
    -LocalPort 5432 `
    -RemoteAddress "0.0.0.0-127.0.0.0", "127.0.0.2-255.255.255.255" `
    -Profile Any `
    -Description "Blocks all non-localhost access to internal PostgreSQL database." | Out-Null

Write-Host "  ✓ Inbound PostgreSQL (Port 5432) blocked from external and remote access." -ForegroundColor Green

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Firewall Configuration Complete!" -ForegroundColor Cyan
Write-Host " Web ports 80/443 are only accessible from office subnets." -ForegroundColor Cyan
Write-Host " Public internet inbound connections are blocked." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
