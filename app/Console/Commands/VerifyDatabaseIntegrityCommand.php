<?php

namespace App\Console\Commands;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceCredential;
use App\Models\AttendanceNetwork;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\PayrollArchive;
use App\Models\SalaryDeduction;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDatabaseIntegrityCommand extends Command
{
    protected $signature = 'db:verify-integrity {--json : Output report in JSON format for comparison diffing}';
    protected $description = 'Verify database record counts, foreign key integrity, and table statistics for Railway to Windows migration.';

    public function handle(): int
    {
        $this->info("=== ARTSCI Database Integrity & Audit Report ===");
        $this->line("Database Connection: " . config('database.default'));
        $this->line("Timestamp: " . now()->toIso8601String());
        $this->newLine();

        $stats = [
            'users_total' => User::count(),
            'users_approved' => User::where('status', 'approved')->count(),
            'users_pending' => User::where('status', 'pending')->count(),
            'attendance_credentials_total' => AttendanceCredential::count(),
            'attendance_credentials_active' => AttendanceCredential::where('is_active', true)->count(),
            'attendance_networks_total' => AttendanceNetwork::count(),
            'attendance_settings_total' => AttendanceSetting::count(),
            'attendance_records_total' => AttendanceRecord::count(),
            'attendance_records_checked_in' => AttendanceRecord::whereNotNull('check_in_at')->count(),
            'attendance_records_checked_out' => AttendanceRecord::whereNotNull('check_out_at')->count(),
            'attendance_audit_logs_total' => AttendanceAuditLog::count(),
            'shifts_total' => Shift::count(),
            'staff_profiles_total' => StaffProfile::count(),
            'salary_deductions_total' => SalaryDeduction::count(),
            'salary_deductions_active' => SalaryDeduction::where('status', 'active')->count(),
            'payroll_archives_total' => PayrollArchive::count(),
        ];

        // Integrity Checks
        $issues = [];

        // 1. Orphaned attendance records
        $orphanedRecords = AttendanceRecord::whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('users')->whereColumn('users.id', 'attendance_records.user_id');
        })->count();
        if ($orphanedRecords > 0) {
            $issues[] = "Found {$orphanedRecords} orphaned attendance_records without a valid user!";
        }

        // 2. Orphaned credentials
        $orphanedCredentials = AttendanceCredential::whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('users')->whereColumn('users.id', 'attendance_credentials.user_id');
        })->count();
        if ($orphanedCredentials > 0) {
            $issues[] = "Found {$orphanedCredentials} orphaned attendance_credentials without a valid user!";
        }

        // 3. Duplicate attendance on same date
        $duplicateAttendance = AttendanceRecord::select('user_id', 'attendance_date', DB::raw('count(*) as cnt'))
            ->groupBy('user_id', 'attendance_date')
            ->having('cnt', '>', 1)
            ->count();
        if ($duplicateAttendance > 0) {
            $issues[] = "Found {$duplicateAttendance} duplicate attendance records on identical dates!";
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'stats' => $stats,
                'integrity_issues' => $issues,
                'status' => empty($issues) ? 'healthy' : 'issues_found',
            ], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $headers = ['Metric / Entity', 'Count / Value'];
        $rows = [];
        foreach ($stats as $key => $val) {
            $rows[] = [ucwords(str_replace('_', ' ', $key)), (string) $val];
        }

        $this->table($headers, $rows);
        $this->newLine();

        if (empty($issues)) {
            $this->info("✓ Integrity Check PASSED: No orphaned records or schema inconsistencies detected.");
        } else {
            $this->error("✗ Integrity Check FAILED with " . count($issues) . " issue(s):");
            foreach ($issues as $issue) {
                $this->error("  - " . $issue);
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
