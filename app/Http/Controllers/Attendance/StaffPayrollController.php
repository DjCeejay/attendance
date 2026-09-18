<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffPayrollController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedPeriod = $request->query('pay_period', PayrollService::currentPayPeriod());

        $balance = $this->payrollService->calculateMonthlyBalance($user, $selectedPeriod);

        // Fetch past archived periods
        $archives = $user->payrollArchives()->orderBy('pay_period', 'desc')->get();

        return view('staff.payroll.index', compact('user', 'selectedPeriod', 'balance', 'archives'));
    }
}
