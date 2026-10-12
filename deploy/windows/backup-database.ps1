# ==============================================================================
# ARTSCI Attendance - Automated Database Backup & Multi-Drive Retention
# Runs daily via Windows Task Scheduler.
# ==============================================================================

param (
    [string]$AppRoot = "C:\ARTSCI\Attendance",
    [string]$PrimaryBackupDir = "C:\artsci-backups\daily",
    [string]$SecondaryBackupDir = "D:\artsci-backups\daily", # External USB or Secondary Drive
    [int]$RetentionDays = 30
)

# 1. Validate Application Directory: strictly require artisan and .env, never fall back to Get-Location
if (-not (Test-Path "$AppRoot\artisan")) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path "$scriptParent\artisan") {
        $AppRoot = $scriptParent
    } else {
        Write-Error "CRITICAL: Application root invalid. 'artisan' not found at '$AppRoot\artisan'. Backup aborted."
        exit 1
    }
}

$envFile = Join-Path $AppRoot ".env"
if (-not (Test-Path $envFile)) {
    Write-Error "CRITICAL: Environment configuration missing. '.env' not found at '$envFile'. Backup aborted."
    exit 1
}

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

# 2. Ensure Primary Backup Directory Exists
if (-not (Test-Path $PrimaryBackupDir)) {
    New-Item -ItemType Directory -Path $PrimaryBackupDir -Force | Out-Null
}

# 3. Parse Database Connection Settings strictly from .env (no silent fallbacks)
$dbHost = ""
$dbPort = ""
$dbName = ""
$dbUser = ""
$dbPass = ""

Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -match "^DB_DATABASE=(.+)$") { $dbName = $matches[1].Trim().Trim('"').Trim("'") }
    if ($line -match "^DB_USERNAME=(.+)$") { $dbUser = $matches[1].Trim().Trim('"').Trim("'") }
    if ($line -match "^DB_PASSWORD=(.*)$") { $dbPass = $matches[1].Trim().Trim('"').Trim("'") }
    if ($line -match "^DB_HOST=(.+)$") { $dbHost = $matches[1].Trim().Trim('"').Trim("'") }
    if ($line -match "^DB_PORT=(.+)$") { $dbPort = $matches[1].Trim().Trim('"').Trim("'") }
}

# Require complete database configuration; never silently use fallbacks
if ([string]::IsNullOrWhiteSpace($dbName)) {
    Write-BackupLog "CRITICAL: DB_DATABASE is missing or empty in '$envFile'! Backup aborted." "ERROR"
    exit 1
}
if ([string]::IsNullOrWhiteSpace($dbHost)) {
    Write-BackupLog "CRITICAL: DB_HOST is missing or empty in '$envFile'! Backup aborted." "ERROR"
    exit 1
}
if ([string]::IsNullOrWhiteSpace($dbPort)) {
    Write-BackupLog "CRITICAL: DB_PORT is missing or empty in '$envFile'! Backup aborted." "ERROR"
    exit 1
}
if ([string]::IsNullOrWhiteSpace($dbUser)) {
    Write-BackupLog "CRITICAL: DB_USERNAME is missing or empty in '$envFile'! Backup aborted." "ERROR"
    exit 1
}

# 4. Locate pg_dump and pg_restore executables
$pgDumpExe = "pg_dump"
$pgRestoreExe = "pg_restore"
$pgDumpCmd = Get-Command pg_dump -ErrorAction SilentlyContinue

if (-not $pgDumpCmd) {
    # Search common PostgreSQL installation directories
    $pgPaths = Get-ChildItem "C:\Program Files\PostgreSQL" -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending
    foreach ($p in $pgPaths) {
        $candidateDump = Join-Path $p.FullName "bin\pg_dump.exe"
        if (Test-Path $candidateDump) {
            $pgDumpExe = $candidateDump
            $candidateRestore = Join-Path $p.FullName "bin\pg_restore.exe"
            if (Test-Path $candidateRestore) {
                $pgRestoreExe = $candidateRestore
            }
            break
        }
    }
} else {
    $pgRestoreCmd = Get-Command pg_restore -ErrorAction SilentlyContinue
    if (-not $pgRestoreCmd) {
        $pgDumpPath = $pgDumpCmd.Source
        if ($pgDumpPath) {
            $candidateRestore = Join-Path (Split-Path $pgDumpPath -Parent) "pg_restore.exe"
            if (Test-Path $candidateRestore) {
                $pgRestoreExe = $candidateRestore
            }
        }
    }
}

if (-not (Get-Command $pgDumpExe -ErrorAction SilentlyContinue) -and -not (Test-Path $pgDumpExe)) {
    Write-BackupLog "pg_dump executable not found in PATH or standard Program Files locations! Backup aborted." "ERROR"
    exit 1
}

# 5. Execute pg_dump with PGPASSWORD set securely in memory
$backupFileName = "artsci_db_backup_$timestamp.dump"
$backupFilePath = Join-Path $PrimaryBackupDir $backupFileName

Write-BackupLog "Executing pg_dump for database [$dbName] on host [$dbHost:$dbPort]..."

try {
    if (-not [string]::IsNullOrEmpty($dbPass)) {
        $env:PGPASSWORD = $dbPass
    }

    # Custom format (-F c) produces a compressed, verified archive with blob support.
    # Do NOT pipe to Out-Null here — piping resets $LASTEXITCODE to 0 in PowerShell.
    # Redirect stderr to stdout so errors appear in the log, and capture exit code immediately.
    & $pgDumpExe -h $dbHost -p $dbPort -U $dbUser -F c -b -f $backupFilePath $dbName 2>&1
    $pgDumpExitCode = $LASTEXITCODE
} finally {
    # Clear password from environment variable immediately
    $env:PGPASSWORD = $null
}

if ($pgDumpExitCode -ne 0 -or -not (Test-Path $backupFilePath)) {
    Write-BackupLog "pg_dump failed with exit code $pgDumpExitCode!" "ERROR"
    exit 1
}

$fileItem = Get-Item $backupFilePath
if ($fileItem.Length -eq 0) {
    Write-BackupLog "pg_dump produced an empty 0-byte file! Backup failed." "ERROR"
    Remove-Item $backupFilePath -Force
    exit 1
}

# 6. Verify Archive Integrity using pg_restore --list
Write-BackupLog "Verifying archive integrity with pg_restore --list..."
$verifyOutput = & $pgRestoreExe --list "$backupFilePath" 2>&1
$verifyExitCode = $LASTEXITCODE

if ($verifyExitCode -ne 0 -or -not $verifyOutput) {
    Write-BackupLog "Archive verification FAILED with exit code $verifyExitCode! Corrupt or unreadable archive: $($verifyOutput -join ' ')" "ERROR"
    Remove-Item $backupFilePath -Force -ErrorAction SilentlyContinue
    exit 1
}

$fileSizeMB = [math]::Round($fileItem.Length / 1MB, 2)
Write-BackupLog "Database dumped and verified successfully ($fileSizeMB MB, valid TOC) to $backupFilePath"

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
