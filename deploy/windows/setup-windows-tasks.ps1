# ==============================================================================
# ARTSCI Attendance - Register Windows Scheduled Tasks
# Run as Administrator:
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\setup-windows-tasks.ps1
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " ARTSCI Windows Office Server - Task Scheduler Setup" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] Must be run as Administrator!" -ForegroundColor Red
    exit 1
}

$appRoot = "C:\artsci-attendance"
$phpExe = "C:\tools\php83\php.exe"

# Detect PHP executable if not at default path
if (-not (Test-Path $phpExe)) {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $phpExe = $phpCmd.Source
    } else {
        Write-Host "[WARNING] php.exe not found at $phpExe. Please update the path before running." -ForegroundColor Yellow
    }
}

Write-Host "Using PHP Binary : $phpExe" -ForegroundColor Green
Write-Host "Application Root : $appRoot" -ForegroundColor Green
Write-Host ""

# ------------------------------------------------------------------------------
# 1. Register Task: ARTSCI-Laravel-Scheduler (Runs every 1 minute)
# ------------------------------------------------------------------------------
$schedulerTaskName = "ARTSCI-Laravel-Scheduler"
Write-Host "[1/2] Registering $schedulerTaskName (Runs every 1 minute)..." -ForegroundColor Yellow

# Unregister if already exists
Unregister-ScheduledTask -TaskName $schedulerTaskName -Confirm:$false -ErrorAction SilentlyContinue

$schedulerAction = New-ScheduledTaskAction `
    -Execute $phpExe `
    -Argument "artisan schedule:run" `
    -WorkingDirectory $appRoot

# Trigger to run every 1 minute indefinitely
$schedulerTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)

$schedulerPrincipal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

$schedulerSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

Register-ScheduledTask `
    -TaskName $schedulerTaskName `
    -Action $schedulerAction `
    -Trigger $schedulerTrigger `
    -Principal $schedulerPrincipal `
    -Settings $schedulerSettings `
    -Description "Executes Laravel artisan schedule:run every minute for automated attendance checkout and background tasks." | Out-Null

Write-Host "  ✓ Task [$schedulerTaskName] successfully registered." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 2. Register Task: ARTSCI-Database-Backup (Runs Daily at 02:00 AM)
# ------------------------------------------------------------------------------
$backupTaskName = "ARTSCI-Database-Backup"
Write-Host "[2/2] Registering $backupTaskName (Runs daily at 02:00 AM)..." -ForegroundColor Yellow

Unregister-ScheduledTask -TaskName $backupTaskName -Confirm:$false -ErrorAction SilentlyContinue

$backupScript = "$appRoot\deploy\windows\backup-database.ps1"
$backupAction = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$backupScript`"" `
    -WorkingDirectory $appRoot

$backupTrigger = New-ScheduledTaskTrigger -Daily -At "02:00 AM"

$backupPrincipal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

$backupSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask `
    -TaskName $backupTaskName `
    -Action $backupAction `
    -Trigger $backupTrigger `
    -Principal $backupPrincipal `
    -Settings $backupSettings `
    -Description "Executes daily PostgreSQL database backup and retention policy." | Out-Null

Write-Host "  ✓ Task [$backupTaskName] successfully registered." -ForegroundColor Green

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Windows Scheduled Tasks are active and ready!" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
