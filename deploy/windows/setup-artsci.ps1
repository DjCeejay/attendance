# ==============================================================================
# ARTSCI Attendance - Application Initialization on Windows
# Run this script inside the project root (e.g., C:\artsci-attendance) as Administrator:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\deploy\windows\setup-artsci.ps1
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Attendance - Windows Production Setup" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$appRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
if (-not (Test-Path "$appRoot\artisan")) {
    $appRoot = Get-Location
}

Set-Location $appRoot
Write-Host "Application Directory: $appRoot" -ForegroundColor Yellow

# 1. Environment file check
if (-not (Test-Path ".env")) {
    Write-Host "[1/6] .env file not found. Copying from deploy\windows\.env.office.example..." -ForegroundColor Yellow
    Copy-Item "deploy\windows\.env.office.example" ".env"
    Write-Host "  ✓ Created .env file. Please review your database credentials!" -ForegroundColor Green
} else {
    Write-Host "[1/6] Existing .env file detected." -ForegroundColor Green
}

# 2. Key Generation
Write-Host "[2/6] Verifying Application Key..." -ForegroundColor Yellow
$appKey = Select-String -Path ".env" -Pattern "^APP_KEY=(.+)$"
if (-not $appKey -or $appKey.Matches[0].Groups[1].Value.Trim() -eq "") {
    php artisan key:generate --force
    Write-Host "  ✓ Generated new APP_KEY." -ForegroundColor Green
} else {
    Write-Host "  ✓ Valid APP_KEY already configured." -ForegroundColor Green
}

# 3. Directory Permissions (ICACLS)
Write-Host "[3/6] Setting Storage & Cache Directory Permissions..." -ForegroundColor Yellow
$dirsToPermit = @("storage", "bootstrap\cache")

foreach ($dir in $dirsToPermit) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    # Grant permissions securely: Full control for SYSTEM & Administrators, Modify for service accounts
    icacls "$appRoot\$dir" /grant "SYSTEM:(OI)(CI)F" /T /Q | Out-Null
    icacls "$appRoot\$dir" /grant "Administrators:(OI)(CI)F" /T /Q | Out-Null
    icacls "$appRoot\$dir" /grant "*S-1-5-20:(OI)(CI)M" /T /Q | Out-Null # NETWORK SERVICE
    icacls "$appRoot\$dir" /grant "IIS_IUSRS:(OI)(CI)M" /T /Q 2>$null | Out-Null # IIS_IUSRS if IIS is used
    Write-Host "  ✓ Permissions applied to $dir" -ForegroundColor Green
}

# 4. Database Migrations
Write-Host "[4/6] Executing Database Migrations..." -ForegroundColor Yellow
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERROR] Database migration failed! Check database service and credentials in .env." -ForegroundColor Red
    exit 1
}
Write-Host "  ✓ Migrations executed successfully." -ForegroundColor Green

# 5. Production Optimization & Cache
Write-Host "[5/6] Optimizing Laravel Cache & Route Maps..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache
Write-Host "  ✓ Configuration, routes, and views compiled for production." -ForegroundColor Green

# 6. Database Integrity Verification
Write-Host "[6/6] Verifying Database Health..." -ForegroundColor Yellow
php artisan db:verify-integrity

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Office Server setup complete!" -ForegroundColor Cyan
Write-Host " Next: Set up Caddy/IIS and register Windows Scheduled Tasks." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
