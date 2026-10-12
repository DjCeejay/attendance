# ==============================================================================
# ARTSCI Attendance - Register Windows Scheduled Tasks
# Configured for: Windows 10 Office Server (IIS port 8080, Cloudflare Tunnel)
# Project Path: C:\ARTSCI\Attendance
# PHP Path:     C:\PHP85\php.exe
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

$appRoot = "C:\ARTSCI\Attendance"
$phpExe = "C:\PHP85\php.exe"

# 1. Resolve Application Directory
if (-not (Test-Path "$appRoot\artisan")) {
    $scriptParent = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    if (Test-Path "$scriptParent\artisan") {
        $appRoot = $scriptParent
    } else {
        $appRoot = Get-Location
    }
}

# 2. Resolve PHP Executable
if (-not (Test-Path $phpExe)) {
    $phpCmd = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCmd) {
        $phpExe = $phpCmd.Source
    } else {
        $possiblePaths = @("C:\PHP85\php.exe", "C:\PHP\php.exe", "C:\tools\php85\php.exe", "C:\tools\php83\php.exe")
        foreach ($p in $possiblePaths) {
            if (Test-Path $p) {
                $phpExe = $p
                break
            }
        }
    }
}

Write-Host "Application Root : $appRoot" -ForegroundColor Green
Write-Host "Using PHP Binary : $phpExe" -ForegroundColor Green
Write-Host ""

$systemPrincipal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$standardSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

# ------------------------------------------------------------------------------
# 1. Register Task: ARTSCI-Laravel-Scheduler (Runs every 1 minute)
# ------------------------------------------------------------------------------
$schedulerTaskName = "ARTSCI-Laravel-Scheduler"
Write-Host "[1/4] Registering $schedulerTaskName (Runs every 1 minute)..." -ForegroundColor Yellow

Unregister-ScheduledTask -TaskName $schedulerTaskName -Confirm:$false -ErrorAction SilentlyContinue

$schedulerAction = New-ScheduledTaskAction `
    -Execute $phpExe `
    -Argument "artisan schedule:run" `
    -WorkingDirectory $appRoot

$schedulerTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask `
    -TaskName $schedulerTaskName `
    -Action $schedulerAction `
    -Trigger $schedulerTrigger `
    -Principal $systemPrincipal `
    -Settings $standardSettings `
    -Description "Executes Laravel artisan schedule:run every minute for attendance checkout and scheduler jobs." | Out-Null

Write-Host "  [OK] Task [$schedulerTaskName] registered." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 2. Register Task: ARTSCI-Office-IP-Updater (Runs every 5 minutes + on Network Change)
# ------------------------------------------------------------------------------
$ipUpdaterTaskName = "ARTSCI-Office-IP-Updater"
Write-Host "[2/4] Registering $ipUpdaterTaskName (Runs every 5 minutes + on Network Change)..." -ForegroundColor Yellow

Unregister-ScheduledTask -TaskName $ipUpdaterTaskName -Confirm:$false -ErrorAction SilentlyContinue

$ipUpdaterScript = "$appRoot\deploy\windows\update-office-ip.ps1"
$ipUpdaterAction = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$ipUpdaterScript`" -AppRoot `"$appRoot`" -PhpExe `"$phpExe`"" `
    -WorkingDirectory $appRoot

$ipUpdaterTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5)

$ipUpdaterSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask `
    -TaskName $ipUpdaterTaskName `
    -Action $ipUpdaterAction `
    -Trigger $ipUpdaterTrigger `
    -Principal $systemPrincipal `
    -Settings $ipUpdaterSettings `
    -Description "Detects office public IP changes and automatically updates approved attendance network in database (every 5 min and on network connection change)." | Out-Null

# Ensure Microsoft-Windows-NetworkProfile/Operational event log channel is enabled
try {
    $netLog = Get-WinEvent -ListLog "Microsoft-Windows-NetworkProfile/Operational" -ErrorAction SilentlyContinue
    if ($netLog -and -not $netLog.IsEnabled) {
        $netLog.IsEnabled = $true
        $netLog.SaveChanges()
        Write-Host "  [OK] Enabled Microsoft-Windows-NetworkProfile/Operational event log channel." -ForegroundColor Green
    }
} catch {
    # Non-fatal if restricted or already enabled
}

# Attach Event Trigger for Network Changes (Microsoft-Windows-NetworkProfile/Operational 10000, 10001, 10002)
$eventTriggerAttached = $false
try {
    # Method A: COM Schedule.Service (Native Task Scheduler 2.0 API)
    $taskService = New-Object -ComObject("Schedule.Service")
    $taskService.Connect()
    $rootFolder = $taskService.GetFolder("\")
    $comTask = $rootFolder.GetTask($ipUpdaterTaskName)
    $comDefinition = $comTask.Definition
    $comTrigger = $comDefinition.Triggers.Create(0) # 0 = TASK_TRIGGER_EVENT
    $comTrigger.Subscription = '<QueryList><Query Id="0" Path="Microsoft-Windows-NetworkProfile/Operational"><Select Path="Microsoft-Windows-NetworkProfile/Operational">*[System[(EventID=10000 or EventID=10001 or EventID=10002)]]</Select></Query></QueryList>'
    $comTrigger.Enabled = $true
    $comTrigger.Delay = "PT3S" # 3-second delay to let DHCP and IP stack settle
    $rootFolder.RegisterTaskDefinition($ipUpdaterTaskName, $comDefinition, 6, "SYSTEM", $null, 5) | Out-Null
    $eventTriggerAttached = $true
    Write-Host "  [OK] Attached network-change event trigger (EventID 10000, 10001, 10002) via COM." -ForegroundColor Green
} catch {
    Write-Host "  [NOTICE] COM trigger registration returned: $($_.Exception.Message). Trying XML registration fallback..." -ForegroundColor Yellow
}

if (-not $eventTriggerAttached) {
    try {
        # Method B: XML manipulation via Export-ScheduledTask
        [xml]$taskXml = Export-ScheduledTask -TaskName $ipUpdaterTaskName
        $ns = New-Object System.Xml.XmlNamespaceManager($taskXml.NameTable)
        $ns.AddNamespace("t", "http://schemas.microsoft.com/windows/2004/02/mit/task")

        $triggersNode = $taskXml.SelectSingleNode("//t:Triggers", $ns)
        if ($triggersNode) {
            $existingEvent = $triggersNode.SelectSingleNode("t:EventTrigger", $ns)
            if (-not $existingEvent) {
                $eventElem = $taskXml.CreateElement("EventTrigger", "http://schemas.microsoft.com/windows/2004/02/mit/task")

                $enabledElem = $taskXml.CreateElement("Enabled", "http://schemas.microsoft.com/windows/2004/02/mit/task")
                $enabledElem.InnerText = "true"
                $eventElem.AppendChild($enabledElem) | Out-Null

                $subElem = $taskXml.CreateElement("Subscription", "http://schemas.microsoft.com/windows/2004/02/mit/task")
                $subElem.InnerText = '<QueryList><Query Id="0" Path="Microsoft-Windows-NetworkProfile/Operational"><Select Path="Microsoft-Windows-NetworkProfile/Operational">*[System[(EventID=10000 or EventID=10001 or EventID=10002)]]</Select></Query></QueryList>'
                $eventElem.AppendChild($subElem) | Out-Null

                $delayElem = $taskXml.CreateElement("Delay", "http://schemas.microsoft.com/windows/2004/02/mit/task")
                $delayElem.InnerText = "PT3S"
                $eventElem.AppendChild($delayElem) | Out-Null

                $triggersNode.AppendChild($eventElem) | Out-Null

                Register-ScheduledTask -TaskName $ipUpdaterTaskName -Xml $taskXml.OuterXml -Force | Out-Null
                $eventTriggerAttached = $true
                Write-Host "  [OK] Attached network-change event trigger via XML definition." -ForegroundColor Green
            }
        }
    } catch {
        Write-Host "  [WARNING] Could not attach network event trigger via XML: $($_.Exception.Message)." -ForegroundColor Yellow
        Write-Host "  (The 5-minute periodic schedule trigger remains active)." -ForegroundColor Yellow
    }
}

Write-Host "  [OK] Task [$ipUpdaterTaskName] registered (5-min schedule + network-change event trigger)." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 3. Register Task: ARTSCI-GitHub-AutoDeploy (Runs every 3 minutes)
# ------------------------------------------------------------------------------
$autoDeployTaskName = "ARTSCI-GitHub-AutoDeploy"
Write-Host "[3/4] Registering $autoDeployTaskName (Runs every 3 minutes)..." -ForegroundColor Yellow

Unregister-ScheduledTask -TaskName $autoDeployTaskName -Confirm:$false -ErrorAction SilentlyContinue

$autoDeployScript = "$appRoot\deploy\windows\auto-deploy-github.ps1"
$autoDeployAction = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$autoDeployScript`" -AppRoot `"$appRoot`" -PhpExe `"$phpExe`"" `
    -WorkingDirectory $appRoot

$autoDeployTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 3)

Register-ScheduledTask `
    -TaskName $autoDeployTaskName `
    -Action $autoDeployAction `
    -Trigger $autoDeployTrigger `
    -Principal $systemPrincipal `
    -Settings $standardSettings `
    -Description "Periodically checks GitHub main branch and deploys new commits automatically." | Out-Null

Write-Host "  [OK] Task [$autoDeployTaskName] registered." -ForegroundColor Green

# ------------------------------------------------------------------------------
# 4. Register Task: ARTSCI-Database-Backup (Runs Daily at 02:00 AM)
# ------------------------------------------------------------------------------
$backupTaskName = "ARTSCI-Database-Backup"
Write-Host "[4/4] Registering $backupTaskName (Runs daily at 02:00 AM)..." -ForegroundColor Yellow

Unregister-ScheduledTask -TaskName $backupTaskName -Confirm:$false -ErrorAction SilentlyContinue

$backupScript = "$appRoot\deploy\windows\backup-database.ps1"
$backupAction = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$backupScript`" -AppRoot `"$appRoot`"" `
    -WorkingDirectory $appRoot

$backupTrigger = New-ScheduledTaskTrigger -Daily -At "02:00 AM"

$backupSettings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask `
    -TaskName $backupTaskName `
    -Action $backupAction `
    -Trigger $backupTrigger `
    -Principal $systemPrincipal `
    -Settings $backupSettings `
    -Description "Executes daily PostgreSQL database backup and retention policy." | Out-Null

Write-Host "  [OK] Task [$backupTaskName] registered." -ForegroundColor Green

Write-Host ""
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " All 4 Windows Scheduled Tasks are registered and active!" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
