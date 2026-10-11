# ARTSCI Attendance — Safe Database & Data Migration Guide (Railway to Windows)

This document outlines the exact, non-destructive procedure for migrating live data from Railway's PostgreSQL database to the local PostgreSQL database on the Windows Office Server.

---

## 1. Principles of Migration Safety

1. **Non-Destructive**: Never reset, drop, or alter the production Railway database.
2. **Parallel Operation**: The Railway deployment remains live and operational during all testing phases until final verification is complete.
3. **Engine Parity**: Both Railway and the Windows office server run PostgreSQL. Native `pg_dump` and `pg_restore` preserve all column types, UUIDs, timestamps, JSON payloads, bcrypt password hashes, and auto-increment sequences.
4. **No Plaintext Secrets**: Passwords and credentials must never be committed to Git.

---

## 2. Step-by-Step Data Migration Procedure

### Phase A: Pre-Migration Health Check on Railway
Before dumping, run the verification command to get baseline metrics:

```bash
php artisan db:verify-integrity
```
*Record or screenshot the output table.*

---

### Phase B: Consistent Database Export from Railway
Export the PostgreSQL database using `pg_dump`:

```bash
# Export using standard pg_dump command (using Railway environment variables or connection string)
pg_dump "postgresql://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}" \
  --format=custom \
  --no-owner \
  --no-privileges \
  --file="artsci_railway_export_$(date +%Y%m%d_%H%M%S).dump"
```

*Note: The custom format (`-F c`) is compressed and supports selective table restore and sequence handling.*

To compute a cryptographic checksum for integrity verification:
```bash
sha256sum artsci_railway_export_*.dump
```

---

### Phase C: Prepare Local PostgreSQL on Windows
On the Windows Office Server:
1. Open PowerShell or Command Prompt.
2. Connect to the local PostgreSQL instance:
   ```powershell
   psql -h 127.0.0.1 -U postgres
   ```
3. Create the database and user:
   ```sql
   CREATE DATABASE artsci_attendance;
   \q
   ```

---

### Phase D: Import Data into Windows PostgreSQL
Copy the `.dump` file to the Windows computer (e.g. `C:\artsci-backups\artsci_railway_export.dump`).

Verify the SHA256 checksum on Windows:
```powershell
Get-FileHash -Path "C:\artsci-backups\artsci_railway_export.dump" -Algorithm SHA256
```

Restore into the local database:
```powershell
pg_restore -h 127.0.0.1 -p 5432 -U postgres -d artsci_attendance --clean --if-exists --no-owner --no-privileges "C:\artsci-backups\artsci_railway_export.dump"
```

---

### Phase E: Post-Restore Verification on Windows Server
Navigate to `C:\artsci-attendance` on the Windows machine and run:

```powershell
php artisan db:verify-integrity
```

Compare the table counts and record statistics against Phase A:
- `users_total` must match exactly.
- `attendance_credentials_total` must match exactly.
- `attendance_records_total` must match exactly.
- `salary_deductions_total` must match exactly.
- `payroll_archives_total` must match exactly.
- Zero orphaned records or duplicate attendance days.

---

### Phase F: Final Cutover Synchronization
On cutover day (e.g. after office closing at 18:00):
1. Take a final `pg_dump` from Railway.
2. Restore to Windows PostgreSQL.
3. Run `php artisan db:verify-integrity` on Windows.
4. Update the office DNS record `staff-portal.artsci.com.ng` to point to the office server IP (`192.168.0.100`).
5. Staff phones connecting the next morning seamlessly check in to the local office server.

---

## 3. Rollback Procedure

If any unexpected blocker occurs on the Windows server:
1. **Immediate Rollback**:
   - The Railway deployment has never been altered and remains running.
   - Simply revert the DNS record for `staff-portal.artsci.com.ng` to point back to Railway.
2. **Data Reconciliation**:
   - If any test check-ins occurred on Windows, export them with `pg_dump` and reconcile if needed.
   - Staff immediately resume attendance on Railway with zero downtime.
