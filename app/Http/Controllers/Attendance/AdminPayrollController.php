<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\PayrollArchive;
use App\Models\SalaryDeduction;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPayrollController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * Admin Payroll Overview (/admin/payroll)
     */
    public function index(Request $request)
    {
        $selectedPeriod = $request->query('pay_period', PayrollService::currentPayPeriod());

        $staffUsers = User::whereIn('role', ['artsci_staff', 'afc_staff'])
            ->with(['staffProfile.shift'])
            ->orderBy('name')
            ->get();

        $staffPayrollData = [];
        $grandBaseSalary = 0;
        $grandTotalDeductions = 0;
        $grandNetSalary = 0;

        foreach ($staffUsers as $staff) {
            $summary = $this->payrollService->calculateMonthlyBalance($staff, $selectedPeriod);
            $staffPayrollData[] = [
                'user'    => $staff,
                'summary' => $summary,
            ];
            $grandBaseSalary += $summary['base_salary'];
            $grandTotalDeductions += $summary['total_deductions'];
            $grandNetSalary += $summary['net_salary'];
        }

        $recentDeductions = SalaryDeduction::where('pay_period', $selectedPeriod)
            ->with(['user', 'waivedBy', 'attendanceRecord'])
            ->latest()
            ->paginate(20);

        $shifts = Shift::all();
        $archives = PayrollArchive::select('pay_period')->distinct()->orderBy('pay_period', 'desc')->get();

        return view('admin.payroll.index', compact(
            'selectedPeriod',
            'staffPayrollData',
            'grandBaseSalary',
            'grandTotalDeductions',
            'grandNetSalary',
            'recentDeductions',
            'shifts',
            'archives'
        ));
    }

    /**
     * Update Staff Profile (Salary, Shift, Off-Days)
     */
    public function updateStaffProfile(Request $request, User $user)
    {
        $validated = $request->validate([
            'base_salary'            => ['required', 'numeric', 'min:0'],
            'shift_id'               => ['nullable', 'exists:shifts,id'],
            'custom_resumption_time' => ['nullable', 'date_format:H:i'],
            'off_days'               => ['nullable', 'array'],
            'off_days.*'             => ['integer', 'between:0,6'],
            'grace_period_minutes'   => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        $profile = $user->getOrCreateStaffProfile();
        $profile->update([
            'base_salary'            => $validated['base_salary'],
            'shift_id'               => $validated['shift_id'] ?? null,
            'custom_resumption_time' => $validated['custom_resumption_time'] ?? null,
            'off_days'               => $validated['off_days'] ?? [],
            'grace_period_minutes'   => $validated['grace_period_minutes'],
        ]);

        return redirect()->back()->with('success', "Payroll profile updated for {$user->name}.");
    }

    /**
     * Waive a ₦500 lateness deduction
     */
    public function waiveDeduction(Request $request, SalaryDeduction $deduction)
    {
        $request->validate([
            'waiver_reason' => ['required', 'string', 'max:500'],
        ]);

        $admin = Auth::user();
        $this->payrollService->waivePenalty($deduction, $admin, $request->input('waiver_reason'));

        return redirect()->back()->with('success', "₦500 penalty for {$deduction->user->name} has been waived.");
    }

    /**
     * Execute Monthly Payroll Reset
     */
    public function executeReset(Request $request)
    {
        $request->validate([
            'pay_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $admin = Auth::user();
        $payPeriod = $request->input('pay_period');

        $result = $this->payrollService->executeMonthlyPayrollReset($admin, $payPeriod);

        return redirect()->back()->with('success', "Monthly payroll reset completed for {$payPeriod}. {$result['archived']} staff records archived.");
    }

    /**
     * Shift Template Management (/admin/shifts)
     */
    public function shiftsIndex()
    {
        $shifts = Shift::withCount('staffProfiles')->get();
        return view('admin.shifts.index', compact('shifts'));
    }

    public function storeShift(Request $request)
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'resumption_time' => ['required', 'date_format:H:i'],
            'closing_time'    => ['nullable', 'date_format:H:i'],
            'department'      => ['required', 'in:acf,artsci,all'],
            'description'     => ['nullable', 'string', 'max:500'],
        ]);

        Shift::create($validated);

        return redirect()->back()->with('success', "Shift '{$validated['name']}' created successfully.");
    }

    public function updateShift(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'resumption_time' => ['required', 'date_format:H:i'],
            'closing_time'    => ['nullable', 'date_format:H:i'],
            'department'      => ['required', 'in:acf,artsci,all'],
            'description'     => ['nullable', 'string', 'max:500'],
        ]);

        $shift->update($validated);

        return redirect()->back()->with('success', "Shift '{$shift->name}' updated successfully.");
    }
}
