# ==============================================================================
# ARTSCI Attendance - Automated Database Backup & Multi-Drive Retention
# Runs daily via Windows Task Scheduler.
# ==============================================================================

param (
    [string]$AppRoot = "C:\artsci-attendance",
    [string]$PrimaryBackupDir = "C:\artsci-backups\daily",
    [string]$SecondaryBackupDir = "D:\artsci-backups\daily", # External USB or Secondary Drive
    [int]$RetentionDays = 30
)

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$logFile = Join-Path $AppRoot "storage\logs\backup.log"

function Write-BackupLog {
    param ([string]$Message, [string]$Level = "INFO")
    $logLine = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [$Level] $Message"
    Write-Host $logLine
    $logDir = Split-Path $logFile -Parent
    if (-not (Test-Path $logDir)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    }
    Add-Content -Path $logFile -Value $logLine -ErrorAction SilentlyContinue
}

Write-BackupLog "Starting ARTSCI Database Backup job..."

# 1. Ensure Primary Backup Directory Exists
if (-not (Test-Path $PrimaryBackupDir)) {
    New-Item -ItemType Directory -Path $PrimaryBackupDir -Force | Out-Null
}

# 2. Parse Database Connection Settings from .env
$dbHost = "127.0.0.1"
$dbPort = "5432"
$dbName = "artsci_attendance"
$dbUser = "postgres"
$dbPass = ""

$envFile = Join-Path $AppRoot ".env"
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -match "^DB_DATABASE=(.+)$") { $dbName = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_USERNAME=(.+)$") { $dbUser = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_PASSWORD=(.+)$") { $dbPass = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_HOST=(.+)$") { $dbHost = $matches[1].Trim().Trim('"').Trim("'") }
        if ($line -match "^DB_PORT=(.+)$") { $dbPort = $matches[1].Trim().Trim('"').Trim("'") }
    }
}

# 3. Locate pg_dump executable
$pgDumpExe = "pg_dump"
$pgDumpCmd = Get-Command pg_dump -ErrorAction SilentlyContinue

if (-not $pgDumpCmd) {
    # Search common PostgreSQL installation directories
    $pgPaths = Get-ChildItem "C:\Program Files\PostgreSQL" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
    foreach ($p in $pgPaths) {
        $candidate = Join-Path $p.FullName "bin\pg_dump.exe"
        if (Test-Path $candidate) {
            $pgDumpExe = $candidate
            break
        }
    }
}

if (-not (Get-Command $pgDumpExe -ErrorAction SilentlyContinue) -and -not (Test-Path $pgDumpExe)) {
    Write-BackupLog "pg_dump executable not found in PATH or standard Program Files locations! Backup aborted." "ERROR"
    exit 1
}

# 4. Execute pg_dump with PGPASSWORD set securely in memory
$backupFileName = "artsci_db_backup_$timestamp.dump"
$backupFilePath = Join-Path $PrimaryBackupDir $backupFileName

Write-BackupLog "Executing pg_dump for database [$dbName] on host [$dbHost:$dbPort]..."

try {
    if (-not [string]::IsNullOrEmpty($dbPass)) {
        $env:PGPASSWORD = $dbPass
    }

    # Custom format (-F c) produces a compressed, verified archive with blob support
    & $pgDumpExe -h $dbHost -p $dbPort -U $dbUser -F c -b -f $backupFilePath $dbName 2>&1 | Out-Null
} finally {
    # Clear password from environment variable immediately
    $env:PGPASSWORD = $null
}

if ($LASTEXITCODE -ne 0 -or -not (Test-Path $backupFilePath)) {
    Write-BackupLog "pg_dump failed with exit code $LASTEXITCODE!" "ERROR"
    exit 1
}

$fileItem = Get-Item $backupFilePath
if ($fileItem.Length -eq 0) {
    Write-BackupLog "pg_dump produced an empty 0-byte file! Backup failed." "ERROR"
    Remove-Item $backupFilePath -Force
    exit 1
}

$fileSizeMB = [math]::Round($fileItem.Length / 1MB, 2)
Write-BackupLog "Database dumped successfully ($fileSizeMB MB) to $backupFilePath"

# 5. Secondary Backup Copy (Off-Disk Resilience)
if (Test-Path $SecondaryBackupDir) {
    Copy-Item $backupFilePath -Destination $SecondaryBackupDir -Force
    Write-BackupLog "Successfully replicated backup to secondary storage: $SecondaryBackupDir"
} else {
    Write-BackupLog "Secondary backup destination [$SecondaryBackupDir] is NOT reachable. WARNING: Backups reside only on primary disk!" "WARNING"
}

# 6. Retention Policy - Purge Backups Older Than $RetentionDays
$cutoffDate = (Get-Date).AddDays(-$RetentionDays)
Write-BackupLog "Purging backups older than $RetentionDays days (Cutoff: $($cutoffDate.ToString('yyyy-MM-dd')))..."

Get-ChildItem -Path $PrimaryBackupDir -Filter "artsci_db_backup_*.dump" | Where-Object { $_.LastWriteTime -lt $cutoffDate } | ForEach-Object {
    Write-BackupLog "Removing expired primary backup: $($_.Name)"
    Remove-Item $_.FullName -Force
}

if (Test-Path $SecondaryBackupDir) {
    Get-ChildItem -Path $SecondaryBackupDir -Filter "artsci_db_backup_*.dump" | Where-Object { $_.LastWriteTime -lt $cutoffDate } | ForEach-Object {
        Write-BackupLog "Removing expired secondary backup: $($_.Name)"
        Remove-Item $_.FullName -Force
    }
}

Write-BackupLog "Backup & Retention Job Finished Successfully."
exit 0
