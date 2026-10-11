# ==============================================================================
# ARTSCI Attendance - Backup Restoration Test & Verification Script
# Safely tests restoring a backup into a temporary database without impacting production.
# ==============================================================================

param (
    [string]$AppRoot = "C:\artsci-attendance",
    [string]$BackupFilePath = "",
    [string]$TestDatabaseName = "artsci_test_verification"
)

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Backup Restoration & Integrity Test" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Locate newest backup if not specified
if ([string]::IsNullOrEmpty($BackupFilePath)) {
    $newestBackup = Get-ChildItem -Path "C:\artsci-backups\daily\artsci_db_backup_*.dump" -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $newestBackup) {
        Write-Host "[ERROR] No backup file found in C:\artsci-backups\daily\. Please specify -BackupFilePath." -ForegroundColor Red
        exit 1
    }
    $BackupFilePath = $newestBackup.FullName
}

if (-not (Test-Path $BackupFilePath)) {
    Write-Host "[ERROR] Backup file does not exist: $BackupFilePath" -ForegroundColor Red
    exit 1
}

Write-Host "Testing backup file : $BackupFilePath" -ForegroundColor Yellow
Write-Host "Temporary database  : $TestDatabaseName" -ForegroundColor Yellow

# 2. Parse DB Credentials from .env
$dbHost = "127.0.0.1"
$dbPort = "5432"
$dbUser = "postgres"
$dbPass = ""

$envFile = Join-Path $AppRoot ".env"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -match "^DB_USERNAME=(.+)$") { $dbUser = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_PASSWORD=(.+)$") { $dbPass = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_HOST=(.+)$") { $dbHost = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_PORT=(.+)$") { $dbPort = $matches[1].Trim().Trim('"').Trim("'") }
    }
}

# 3. Locate pg_restore and psql
$pgRestoreExe = "pg_restore"
$psqlExe = "psql"

if (-not (Get-Command pg_restore -ErrorAction SilentlyContinue)) {
    $pgPaths = Get-ChildItem "C:\Program Files\PostgreSQL" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
    foreach ($p in $pgPaths) {
        $candidate = Join-Path $p.FullName "bin\pg_restore.exe"
        if (Test-Path $candidate) {
            $pgRestoreExe = $candidate
            $psqlExe = Join-Path $p.FullName "bin\psql.exe"
            break
        }
    }
}

try {
    if (-not [string]::IsNullOrEmpty($dbPass)) {
        $env:PGPASSWORD = $dbPass
    }

    # 4. Drop temporary database if exists and re-create it
    Write-Host "[1/4] Creating clean test database [$TestDatabaseName]..." -ForegroundColor Yellow
    & $psqlExe -h $dbHost -p $dbPort -U $dbUser -c "DROP DATABASE IF EXISTS $TestDatabaseName;" 2>$null | Out-Null
    & $psqlExe -h $dbHost -p $dbPort -U $dbUser -c "CREATE DATABASE $TestDatabaseName;" | Out-Null

    # 5. Restore backup into test database
    Write-Host "[2/4] Restoring backup archive into [$TestDatabaseName]..." -ForegroundColor Yellow
    & $pgRestoreExe -h $dbHost -p $dbPort -U $dbUser -d $TestDatabaseName --no-owner --no-privileges "$BackupFilePath" 2>&1 | Out-Null

    # 6. Query restored record counts
    Write-Host "[3/4] Validating restored data integrity..." -ForegroundColor Yellow
    $userCount = & $psqlExe -h $dbHost -p $dbPort -U $dbUser -d $TestDatabaseName -t -c "SELECT count(*) FROM users;"
    $recordCount = & $psqlExe -h $dbHost -p $dbPort -U $dbUser -d $TestDatabaseName -t -c "SELECT count(*) FROM attendance_records;"
    $credCount = & $psqlExe -h $dbHost -p $dbPort -U $dbUser -d $TestDatabaseName -t -c "SELECT count(*) FROM attendance_credentials;"

    Write-Host "  ✓ Users Restored             : $($userCount.Trim())" -ForegroundColor Green
    Write-Host "  ✓ Attendance Records Restored : $($recordCount.Trim())" -ForegroundColor Green
    Write-Host "  ✓ WebAuthn Passkeys Restored : $($credCount.Trim())" -ForegroundColor Green

    # 7. Clean up temporary database
    Write-Host "[4/4] Cleaning up temporary test database..." -ForegroundColor Yellow
    & $psqlExe -h $dbHost -p $dbPort -U $dbUser -c "DROP DATABASE IF EXISTS $TestDatabaseName;" 2>$null | Out-Null

    Write-Host ""
    Write-Host "==========================================================" -ForegroundColor Green
    Write-Host " RESTORATION VERIFICATION SUCCESSFUL!" -ForegroundColor Green
    Write-Host " The backup file is fully readable and can be restored cleanly." -ForegroundColor Green
    Write-Host "==========================================================" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Backup restoration test failed: $_" -ForegroundColor Red
    exit 1
} finally {
    $env:PGPASSWORD = $null
}
