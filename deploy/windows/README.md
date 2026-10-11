# ARTSCI Attendance — Windows Office Server Deployment Suite

This directory contains the deployment automation, security scripts, service definitions, and migration documentation for moving ARTSCI Attendance from Railway to an on-premise Windows 10/11 office server.

---

## Files in this Directory

| File | Type | Purpose |
| :--- | :--- | :--- |
| [`install-prerequisites.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/install-prerequisites.ps1) | PowerShell Script | Hardware assessment, CPU/RAM/Disk audit, network adapter detection, power management (sleep disable), and PHP 8.3 / PostgreSQL check. |
| [`setup-artsci.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-artsci.ps1) | PowerShell Script | Environment configuration, application key generation, Windows directory permissions (`icacls`), migrations, and production optimizations. |
| [`configure-firewall.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/configure-firewall.ps1) | PowerShell Script | Restricts Windows Defender Firewall ports 80 & 443 strictly to authorized office LAN subnets (`192.168.0.0/24`, `192.168.8.0/24`), blocks external access. |
| [`backup-database.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/backup-database.ps1) | PowerShell Script | Automated PostgreSQL database backup, 30-day retention pruning, and off-disk replication to secondary storage/USB drive. |
| [`setup-windows-tasks.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/setup-windows-tasks.ps1) | PowerShell Script | Registers Laravel Scheduler (every 1 min) and Daily Database Backup (daily at 02:00 AM) in Windows Task Scheduler. |
| [`register-windows-services.ps1`](file:///home/codecps/Desktop/attendance/deploy/windows/register-windows-services.ps1) | PowerShell Script | Registers PHP FastCGI and Caddy Web Server as native Windows background services (auto-restart on crash and boot). |
| [`run-artsci-service.bat`](file:///home/codecps/Desktop/attendance/deploy/windows/run-artsci-service.bat) | Batch Script | Lightweight foreground service launcher for testing and debugging. |
| [`Caddyfile.example`](file:///home/codecps/Desktop/attendance/deploy/windows/Caddyfile.example) | Web Server Config | Production configuration for Caddy Web Server with reverse proxy to PHP-CGI FastCGI, access logs, and HTTPS. |
| [`web.config.example`](file:///home/codecps/Desktop/attendance/deploy/windows/web.config.example) | Web Server Config | Alternative production configuration for Microsoft IIS 10 with FastCGI and URL Rewrite Module. |
| [`.env.office.example`](file:///home/codecps/Desktop/attendance/deploy/windows/.env.office.example) | Environment Template | Production environment settings for Windows Office Server (`local_lan` mode, PostgreSQL, trusted proxy loopback). |
| [`database-migration-guide.md`](file:///home/codecps/Desktop/attendance/deploy/windows/database-migration-guide.md) | Documentation | Step-by-step, zero-downtime data migration procedure from Railway to Windows with rollback plan. |
| [`office-network-setup.md`](file:///home/codecps/Desktop/attendance/deploy/windows/office-network-setup.md) | Documentation | Dual Wi-Fi A (Tenda) & B (Airtel) bridging, DHCP reservation, Split-Horizon DNS, and PWA HTTPS architecture. |

---

## Quick Start on the Windows Office Computer

1. Open PowerShell as **Administrator**.
2. Run prerequisite audit:
   ```powershell
   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
   .\deploy\windows\install-prerequisites.ps1
   ```
3. Run application setup:
   ```powershell
   .\deploy\windows\setup-artsci.ps1
   ```
4. Configure firewall:
   ```powershell
   .\deploy\windows\configure-firewall.ps1
   ```
5. Register background scheduled tasks:
   ```powershell
   .\deploy\windows\setup-windows-tasks.ps1
   ```
6. Follow [`database-migration-guide.md`](file:///home/codecps/Desktop/attendance/deploy/windows/database-migration-guide.md) to import production data.
