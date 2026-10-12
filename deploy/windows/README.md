# ARTSCI Attendance — Windows 10 Office Server Automation Suite

This directory contains the deployment automation, security scripts, service definitions, IP updater, and automatic GitHub deployment for the ARTSCI Attendance Windows 10 office server.

---

## Server Configuration Summary

* **Project Location**: `C:\ARTSCI\Attendance`
* **PHP Executable**: `C:\PHP85\php.exe` (PHP 8.5)
* **Web Server**: Microsoft IIS 10 on port `8080`
* **Public Domain**: `https://staff-portal.artsci.com.ng`
* **Public Access**: Cloudflare Tunnel (`cloudflared` -> `localhost:8080`)
* **Database**: Local PostgreSQL 18, port `5433`, database `artsci_attendance_import`
* **GitHub Repository**: Connected to `main` branch with auto-deployment

---

## Automation Scripts Overview

| File | Schedule | Purpose |
| :--- | :--- | :--- |
| [`update-office-ip.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/update-office-ip.ps1) | Every 5 minutes | **Automatic Office Public IP Updater**: Automatically queries public IP services (`api.ipify.org`, `icanhazip.com`, etc.) and updates the approved office network in PostgreSQL whenever the router's public IP changes. |
| [`auto-deploy-github.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/auto-deploy-github.ps1) | Every 3 minutes | **Automatic GitHub Deployment**: Periodically fetches `main` from GitHub, takes a safety DB backup, updates composer/migrations/caches, performs a post-deploy health check (`/up`), and auto-rolls back if an issue occurs. |
| [`setup-windows-tasks.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-windows-tasks.ps1) | One-time Setup | Registers all 4 scheduled tasks in Windows Task Scheduler under `SYSTEM`: Laravel Scheduler (1m), IP Updater (5m), GitHub Auto-Deploy (3m), DB Backup (daily 02:00). |
| [`backup-database.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/backup-database.ps1) | Daily 02:00 AM | Automated PostgreSQL `pg_dump -F c` backup with 30-day retention pruning and off-disk replication to secondary storage (`D:\artsci-backups`). |
| [`verify-backup-restore.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/verify-backup-restore.ps1) | Manual / Test | Restores backup into an isolated test database (`artsci_test_verification`) and verifies record counts without touching production data. |
| [`install-prerequisites.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/install-prerequisites.ps1) | One-time Setup | Hardware audit (RAM, disk, CPU), network adapter check, and sleep/hibernate disabling via `powercfg`. |
| [`setup-artsci.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-artsci.ps1) | One-time Setup | Initial app setup, least-privilege ICACLS permissions on `storage`, and production cache warming. |
| [`configure-firewall.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/configure-firewall.ps1) | One-time Setup | Configures Windows Defender Firewall to allow LAN subnets and block external access to PostgreSQL. |
| [`.env.office.example`](file:///home/codecps/Desktop/attendance/deploy/windows/.env.office.example) | Template | Production `.env` template configured for PostgreSQL port 5433, database `artsci_attendance_import`, and `hybrid` verification mode. |

---

## Task 1: How the Automatic Office Public IP Updater Works

1. **Detection**: Every 5 minutes, `update-office-ip.ps1` runs `php artisan attendance:update-office-ip`.
2. **Redundant Providers**: The `PublicIpService` queries a redundant pool of public IP resolvers (`api.ipify.org`, `icanhazip.com`, `checkip.amazonaws.com`, `ifconfig.me`, `ipinfo.io`) with strict validation rejecting private/loopback addresses.
3. **Smart Database Update**:
   - If the public IP matches the currently approved IP, it updates timestamps without creating audit noise.
   - If the router was assigned a new public IP (e.g. after a router restart or power outage), it automatically updates the designated `AttendanceNetwork` record (`<new_ip>/32`) and logs the change in `attendance_audit_logs`.
4. **Cloudflare Tunnel Visitor Matching**:
   - Staff phones connect to `https://staff-portal.artsci.com.ng`.
   - Cloudflare identifies the connecting IP of staff on office Wi-Fi as the office's public IP.
   - Cloudflare forwards this in `CF-Connecting-IP`.
   - Laravel validates that the visitor IP matches the office public IP, approving check-in!
   - Staff at home or on mobile data have different public IPs and are blocked (403 Forbidden).
5. **No Router Access Required**: Does not require router logins, port forwarding, or DDNS credentials.

---

## Task 2: How GitHub Auto-Deployment Works

1. **Scheduled Polling**: Every 3 minutes, `auto-deploy-github.ps1` runs in the background.
2. **Concurrency Locking**: Uses `storage\framework\deploy.lock` to prevent overlapping deployments.
3. **Change Detection**: Runs `git fetch origin main` and compares local `HEAD` vs `origin/main`. If there are no new commits, it exits immediately.
4. **Safety Backup**: If new commits are detected, it executes `backup-database.ps1` to take a snapshot before applying changes.
5. **Fast-Forward Merge**: Runs `git merge origin/main --ff-only` to ensure server `.env`, local database, and server secrets are never overwritten.
6. **Composer & Migrations**:
   - If `composer.json` or `composer.lock` changed, runs `composer install --no-dev --optimize-autoloader`.
   - Runs safe pending migrations via `php artisan migrate --force`. (Never runs `migrate:fresh`).
7. **Cache Refresh**: Compiles production configs, routes, and views (`config:cache`, `route:cache`, `view:cache`).
8. **Health Check & Auto-Rollback**:
   - Sends a request to `http://127.0.0.1:8080/up`.
   - If HTTP 200 is received, deployment succeeds.
   - If the health check fails, it automatically rolls back `git checkout` to the previous commit and restores caches, keeping the website online.

---

## One-Time Server Setup Instructions

Open PowerShell as **Administrator** on the Windows 10 server:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass

# 1. Register all scheduled tasks (Scheduler, IP Updater, Auto-Deploy, Daily Backup)
cd C:\ARTSCI\Attendance
.\deploy\windows\setup-windows-tasks.ps1
```

To configure GitHub authentication for automatic pulling:
* **Option A (SSH Deploy Key - Recommended)**:
  Generate an SSH key: `ssh-keygen -t ed25519 -C "artsci-office-server"`
  Add the public key to GitHub repository -> **Settings** -> **Deploy Keys** (read-only).
  Set git remote: `git remote set-url origin git@github.com:DjCeejay/attendance.git`
* **Option B (GitHub Personal Access Token)**:
  Generate a fine-grained or classic GitHub PAT with `Contents: Read-only`.
  Configure Git Credential Manager or set: `git remote set-url origin https://<TOKEN>@github.com/DjCeejay/attendance.git`
