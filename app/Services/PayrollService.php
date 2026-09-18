<?php

namespace App\Services;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\PayrollArchive;
use App\Models\SalaryDeduction;
use App\Models\User;
use Carbon\Carbon;

class PayrollService
{
    /**
     * Current pay period in YYYY-MM format.
     */
    public static function currentPayPeriod(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Evaluate whether a checked-in attendance record incurs a ₦500 lateness deduction.
     * Creates a SalaryDeduction record idempotently (one deduction per attendance record).
     */
    public function evaluateAndApplyLatenessPenalty(AttendanceRecord $record, User $user): ?SalaryDeduction
    {
        // If already has a deduction for this record, do not duplicate
        if ($record->deduction()->exists()) {
            return null;
        }

        if ($record->status !== 'late') {
            return null;
        }

        // Get staff profile (to check off-days)
        $profile = $user->staffProfile;
        if ($profile && $profile->isOffDay($record->attendance_date)) {
            return null; // Off-day: no penalty
        }

        $penaltyAmount = (float) AttendanceSetting::get('lateness_penalty_amount', 500);
        $payPeriod = Carbon::parse($record->attendance_date)->format('Y-m');

        $deduction = SalaryDeduction::create([
            'user_id'              => $user->id,
            'attendance_record_id' => $record->id,
            'pay_period'           => $payPeriod,
            'amount'               => $penaltyAmount,
            'deduction_type'       => 'lateness',
            'reason'               => 'Late resumption on ' . Carbon::parse($record->attendance_date)->format('D, M j, Y'),
            'status'               => 'active',
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'lateness_deduction',
            actor: $user,
            affectedUser: $user,
            record: $record,
            newValues: [
                'deduction_id' => $deduction->id,
                'amount'       => $penaltyAmount,
                'pay_period'   => $payPeriod,
            ],
            reason: "₦" . number_format($penaltyAmount) . " lateness deduction applied for late check-in on " . $record->attendance_date
        );

        return $deduction;
    }

    /**
     * Calculate a staff member's payroll balance for a given pay period.
     */
    public function calculateMonthlyBalance(User $user, string $payPeriod): array
    {
        $profile    = $user->staffProfile;
        $baseSalary = $profile ? (float) $profile->base_salary : 0.0;

        $deductions = $user->salaryDeductions()->where('pay_period', $payPeriod)->get();

        $totalActive = $deductions->where('status', 'active')->sum('amount');
        $totalWaived = $deductions->where('status', 'waived')->sum('amount');
        $lateCount   = $deductions->where('deduction_type', 'lateness')->where('status', 'active')->count();
        $netSalary   = max(0, $baseSalary - $totalActive);

        return [
            'base_salary'      => $baseSalary,
            'total_deductions' => $totalActive,
            'total_waived'     => $totalWaived,
            'net_salary'       => $netSalary,
            'late_count'       => $lateCount,
            'deductions'       => $deductions,
        ];
    }

    /**
     * Waive a ₦500 lateness penalty. Audits the action.
     */
    public function waivePenalty(SalaryDeduction $deduction, User $admin, string $reason): SalaryDeduction
    {
        $deduction->update([
            'status'        => 'waived',
            'waived_by'     => $admin->id,
            'waived_at'     => now(),
            'waiver_reason' => $reason,
        ]);

        AttendanceAuditLog::logEvent(
            eventType: 'deduction_waived',
            actor: $admin,
            affectedUser: $deduction->user,
            newValues: [
                'deduction_id' => $deduction->id,
                'amount'       => $deduction->amount,
                'waiver_reason' => $reason,
            ],
            reason: "₦" . number_format($deduction->amount) . " lateness deduction waived by admin. Reason: {$reason}"
        );

        return $deduction->fresh();
    }

    /**
     * Archive current pay period payroll for all staff and reset active deductions.
     */
    public function executeMonthlyPayrollReset(User $admin, string $payPeriod): array
    {
        $staffUsers = User::whereIn('role', ['artsci_staff', 'afc_staff'])
            ->where('status', 'approved')
            ->with(['staffProfile', 'salaryDeductions'])
            ->get();

        $archived = 0;

        foreach ($staffUsers as $user) {
            $balance = $this->calculateMonthlyBalance($user, $payPeriod);

            // Archive payroll snapshot (upsert so re-running is safe)
            PayrollArchive::updateOrCreate(
                ['user_id' => $user->id, 'pay_period' => $payPeriod],
                [
                    'base_salary'      => $balance['base_salary'],
                    'total_deductions' => $balance['total_deductions'],
                    'net_salary'       => $balance['net_salary'],
                    'late_count'       => $balance['late_count'],
                    'reset_by'         => $admin->id,
                    'reset_at'         => now(),
                ]
            );

            $archived++;
        }

        AttendanceAuditLog::logEvent(
            eventType: 'payroll_reset',
            actor: $admin,
            affectedUser: null,
            newValues: ['pay_period' => $payPeriod, 'staff_archived' => $archived],
            reason: "Monthly payroll reset executed by admin for pay period {$payPeriod}. {$archived} staff records archived."
        );

        return [
            'archived'    => $archived,
            'pay_period'  => $payPeriod,
        ];
    }
}
