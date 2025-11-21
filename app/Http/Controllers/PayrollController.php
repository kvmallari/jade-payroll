<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Employee;
use App\Models\TimeLog;
use App\Models\PayScheduleSetting;
use App\Models\PaySchedule;
use App\Models\CashAdvance;
use App\Models\CashAdvancePayment;
use App\Http\Controllers\TimeLogController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display all payrolls from all periods and schedules.
     */
    public function indexAll(Request $request)
    {
        $this->authorize('view payrolls');

        // Get all payrolls with filters
        $query = Payroll::with(['creator', 'approver', 'payrollDetails.employee'])
            ->withCount('payrollDetails')
            ->orderBy('created_at', 'desc');

        // Filter by schedule
        if ($request->filled('schedule')) {
            $query->where('pay_schedule', $request->schedule);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payroll type
        if ($request->filled('type')) {
            $query->where('payroll_type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('period_start', [$request->date_from, $request->date_to]);
        }

        $payrolls = $query->paginate(10)->withQueryString();

        // Get available schedule settings for filter options
        $scheduleSettings = \App\Models\PayScheduleSetting::systemDefaults()
            ->orderBy('sort_order')
            ->get();

        return view('payrolls.index-all', compact('payrolls', 'scheduleSettings'));
    }

    /**
     * Display a listing of payrolls (original method for compatibility).
     */
    public function index(Request $request)
    {
        $this->authorize('view payrolls');

        // Handle AJAX request for pay periods
        if ($request->ajax() && $request->input('action') === 'get_periods') {
            return $this->getPayPeriods($request);
        }

        // Show all payrolls with filters
        $query = Payroll::with(['creator', 'approver', 'payrollDetails' => function ($query) {
            $query->with('employee');
        }])
            ->withCount('payrollDetails')
            ->orderBy('created_at', 'desc');

        // Filter by pay schedule
        if ($request->filled('pay_schedule')) {
            $query->where('pay_schedule', $request->pay_schedule);
        }

        // Filter by status - allow processing, approved, and paid
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'paid') {
                // Filter for payrolls that are marked as paid
                $query->where('is_paid', true);
            } elseif (in_array($status, ['processing', 'approved'])) {
                $query->where('status', $status);
                // If filtering for approved, also exclude paid ones unless specifically requested
                if ($status === 'approved') {
                    $query->where('is_paid', false);
                }
            }
        } else {
            // Default to only processing and approved payrolls (exclude drafts)
            $query->whereIn('status', ['processing', 'approved']);
        }

        // Filter by payroll type - only allow automated and manual
        if ($request->filled('type')) {
            $type = $request->type;
            if (in_array($type, ['automated', 'manual'])) {
                $query->where('payroll_type', $type);
            }
        }

        // Filter by pay period
        if ($request->filled('pay_period')) {
            $periodDates = explode('|', $request->pay_period);
            if (count($periodDates) === 2) {
                $startDate = Carbon::parse($periodDates[0]);
                $endDate = Carbon::parse($periodDates[1]);
                $query->where('period_start', $startDate)
                    ->where('period_end', $endDate);
            }
        }

        // Filter by employee name search
        if ($request->filled('name_search')) {
            $query->whereHas('payrollDetails.employee', function ($q) use ($request) {
                $searchTerm = $request->name_search;
                $q->where(DB::raw("CONCAT(first_name, ' ', middle_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('employee_number', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Paginate with configurable records per page (default 10)
        $perPage = $request->get('per_page', 10);
        $payrolls = $query->paginate($perPage)->withQueryString();

        // Calculate summary statistics for paid and approved payrolls
        $summaryQuery = Payroll::whereIn('status', ['approved'])->orWhere('is_paid', true);

        // Apply same filters for summary
        if ($request->filled('pay_schedule')) {
            $summaryQuery->where('pay_schedule', $request->pay_schedule);
        }
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'paid') {
                $summaryQuery = Payroll::where('is_paid', true);
            } elseif (in_array($status, ['processing', 'approved'])) {
                $summaryQuery = Payroll::where('status', $status);
                if ($status === 'approved') {
                    $summaryQuery->where('is_paid', false);
                }
            }
        }
        if ($request->filled('type')) {
            $type = $request->type;
            if (in_array($type, ['automated', 'manual'])) {
                $summaryQuery->where('payroll_type', $type);
            }
        }
        if ($request->filled('pay_period')) {
            $periodDates = explode('|', $request->pay_period);
            if (count($periodDates) === 2) {
                $startDate = Carbon::parse($periodDates[0]);
                $endDate = Carbon::parse($periodDates[1]);
                $summaryQuery->where('period_start', $startDate)
                    ->where('period_end', $endDate);
            }
        }

        // Calculate summary stats using accurate breakdown data for all payrolls
        $summaryPayrolls = $summaryQuery->clone()->with('snapshots', 'payrollDetails')->get();
        $totalNetPay = 0;
        $totalDeductions = 0;
        $totalGrossPay = 0;

        foreach ($summaryPayrolls as $payroll) {
            if ($payroll->snapshots->isNotEmpty()) {
                // Use snapshot data for locked/processing payrolls
                foreach ($payroll->snapshots as $snapshot) {
                    // Calculate basic pay from breakdown
                    $basicPay = 0;
                    if ($snapshot->basic_breakdown) {
                        $basicBreakdown = is_string($snapshot->basic_breakdown) ?
                            json_decode($snapshot->basic_breakdown, true) : $snapshot->basic_breakdown;
                        if (is_array($basicBreakdown)) {
                            foreach ($basicBreakdown as $type => $data) {
                                $basicPay += $data['amount'] ?? 0;
                            }
                        }
                    } else {
                        $basicPay = $snapshot->regular_pay ?? 0;
                    }

                    // Calculate pay components from breakdown data
                    $holidayPay = $this->calculateCorrectHolidayPayFromSnapshot($snapshot);
                    $restPay = $this->calculateCorrectRestPayFromSnapshot($snapshot);
                    $overtimePay = $this->calculateCorrectOvertimePayFromSnapshot($snapshot);

                    $allowances = $snapshot->allowances_total ?? 0;
                    $bonuses = $snapshot->bonuses_total ?? 0;
                    $incentives = $snapshot->incentives_total ?? 0;

                    // Calculate gross pay from components
                    $grossPay = $basicPay + $holidayPay + $restPay + $overtimePay + $allowances + $bonuses + $incentives;

                    // Calculate deductions from breakdown
                    $deductions = $this->recalculateDeductionsFromBreakdown($snapshot, $grossPay);

                    // Calculate net pay
                    $netPay = $grossPay - $deductions;

                    $totalGrossPay += $grossPay;
                    $totalDeductions += $deductions;
                    $totalNetPay += $netPay;
                }
            } else {
                // Use stored totals for draft payrolls (no snapshots yet)
                $totalGrossPay += $payroll->total_gross ?? 0;
                $totalDeductions += $payroll->total_deductions ?? 0;
                $totalNetPay += $payroll->total_net ?? 0;
            }
        }

        $summaryStats = [
            'total_net_pay' => $totalNetPay,
            'total_deductions' => $totalDeductions,
            'total_gross_pay' => $totalGrossPay,
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'payrolls' => $payrolls,
                'summaryStats' => $summaryStats,
                'html' => view('payrolls.partials.payroll-list', compact('payrolls'))->render(),
                'pagination' => view('payrolls.partials.pagination', compact('payrolls'))->render()
            ]);
        }

        return view('payrolls.index', compact('payrolls', 'summaryStats'));
    }

    /**
     * Display payslips for employees (their own approved payslips only)
     */
    public function employeePayslips(Request $request)
    {
        // Handle AJAX request for pay periods
        if ($request->ajax() && $request->input('action') === 'get_periods') {
            return $this->getPayPeriods($request);
        }

        // Get current user's employee record
        $employee = Employee::where('user_id', Auth::id())->first();

        if (!$employee) {
            abort(404, 'Employee record not found');
        }

        // Show processing, approved, and paid payrolls for the current employee
        $query = Payroll::with(['creator', 'approver', 'payrollDetails.employee.position', 'snapshots'])
            ->whereHas('payrollDetails', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->whereIn('status', ['processing', 'approved'])  // Include processing and approved
            ->withCount('payrollDetails')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'paid') {
                // Filter for payrolls that are marked as paid
                $query->where('is_paid', true);
            } elseif ($status === 'approved') {
                // Filter for approved but not paid payrolls
                $query->where('status', 'approved')->where('is_paid', false);
            } elseif ($status === 'processing') {
                // Filter for processing payrolls
                $query->where('status', 'processing');
            }
        }

        // Filter by pay period
        if ($request->filled('pay_period')) {
            $periodDates = explode('|', $request->pay_period);
            if (count($periodDates) === 2) {
                $startDate = Carbon::parse($periodDates[0]);
                $endDate = Carbon::parse($periodDates[1]);
                $query->where('period_start', $startDate)
                    ->where('period_end', $endDate);
            }
        }

        // Paginate with configurable records per page (default 10)
        $perPage = $request->get('per_page', 10);
        $payrolls = $query->paginate($perPage)->withQueryString();

        // Calculate summary statistics
        $summaryQuery = clone $query;
        $summaryPayrolls = $summaryQuery->get();

        $totalNetPay = 0;
        $totalDeductions = 0;
        $totalGrossPay = 0;

        foreach ($summaryPayrolls as $payroll) {
            if ($payroll->snapshots->isNotEmpty()) {
                foreach ($payroll->snapshots as $snapshot) {
                    // Calculate basic pay from breakdown
                    $basicPay = 0;
                    if ($snapshot->basic_breakdown) {
                        $basicBreakdown = is_string($snapshot->basic_breakdown) ?
                            json_decode($snapshot->basic_breakdown, true) : $snapshot->basic_breakdown;
                        if (is_array($basicBreakdown)) {
                            foreach ($basicBreakdown as $type => $data) {
                                $basicPay += $data['amount'] ?? 0;
                            }
                        }
                    } else {
                        $basicPay = $snapshot->regular_pay ?? 0;
                    }

                    // Calculate pay components from breakdown data
                    $holidayPay = $this->calculateCorrectHolidayPayFromSnapshot($snapshot);
                    $restPay = $this->calculateCorrectRestPayFromSnapshot($snapshot);
                    $overtimePay = $this->calculateCorrectOvertimePayFromSnapshot($snapshot);

                    $allowances = $snapshot->allowances_total ?? 0;
                    $bonuses = $snapshot->bonuses_total ?? 0;
                    $incentives = $snapshot->incentives_total ?? 0;

                    // Calculate gross pay from components
                    $grossPay = $basicPay + $holidayPay + $restPay + $overtimePay + $allowances + $bonuses + $incentives;

                    // Calculate deductions from breakdown
                    $deductions = $this->recalculateDeductionsFromBreakdown($snapshot, $grossPay);

                    // Calculate net pay
                    $netPay = $grossPay - $deductions;

                    $totalGrossPay += $grossPay;
                    $totalDeductions += $deductions;
                    $totalNetPay += $netPay;
                }
            } else {
                // Use stored totals for draft payrolls (no snapshots yet)
                $totalGrossPay += $payroll->total_gross ?? 0;
                $totalDeductions += $payroll->total_deductions ?? 0;
                $totalNetPay += $payroll->total_net ?? 0;
            }
        }

        $summaryStats = [
            'total_net_pay' => $totalNetPay,
            'total_deductions' => $totalDeductions,
            'total_gross_pay' => $totalGrossPay,
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'payrolls' => $payrolls,
                'summaryStats' => $summaryStats,
                'html' => view('payslips.partials.payroll-list', compact('payrolls'))->render(),
                'pagination' => view('payslips.partials.pagination', compact('payrolls'))->render()
            ]);
        }

        return view('payslips.index', compact('payrolls', 'summaryStats'));
    }

    /**
     * Get pay periods for AJAX request
     */
    private function getPayPeriods(Request $request)
    {
        $schedule = $request->input('schedule');

        // Check if this is being called from employee payslips (current route is payslips.index)
        if ($request->route()->getName() === 'payslips.index') {
            // For employee payslips, get periods for current employee's approved payrolls only
            $employee = Employee::where('user_id', Auth::id())->first();
            if (!$employee) {
                return response()->json(['periods' => []]);
            }

            $existingPeriods = Payroll::whereHas('payrollDetails', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
                ->whereIn('status', ['processing', 'approved'])
                ->select('period_start', 'period_end')
                ->distinct()
                ->orderBy('period_start', 'desc')
                ->limit(12)
                ->get();
        } else {
            // Original logic for payroll management
            if (!$schedule) {
                return response()->json(['periods' => []]);
            }

            // Get the schedule setting
            $scheduleSetting = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();

            if (!$scheduleSetting) {
                return response()->json(['periods' => []]);
            }

            // Get existing payroll periods from database for this schedule
            $existingPeriods = Payroll::where('pay_schedule', $schedule)
                ->whereIn('status', ['processing', 'approved'])
                ->select('period_start', 'period_end')
                ->distinct()
                ->orderBy('period_start', 'desc')
                ->limit(12) // Show last 12 periods
                ->get();
        }

        $periods = [];
        foreach ($existingPeriods as $period) {
            $label = $period->period_start->format('M j') . ' - ' . $period->period_end->format('M j, Y');
            $value = $period->period_start->format('Y-m-d') . '|' . $period->period_end->format('Y-m-d');

            $periods[] = [
                'label' => $label,
                'value' => $value
            ];
        }

        return response()->json(['periods' => $periods]);
    }

    /**
     * Generate payroll summary
     */
    public function generateSummary(Request $request)
    {
        $this->authorize('view payrolls');

        $format = $request->input('export', 'pdf');

        // Build query based on filters
        $query = Payroll::with(['payrollDetails.employee', 'snapshots'])
            ->whereIn('status', ['processing', 'approved']);

        // Apply filters
        if ($request->filled('pay_schedule')) {
            $query->where('pay_schedule', $request->pay_schedule);
        }

        if ($request->filled('status') && in_array($request->status, ['processing', 'approved'])) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type') && in_array($request->type, ['automated', 'manual'])) {
            $query->where('payroll_type', $request->type);
        }

        if ($request->filled('pay_period')) {
            $periodDates = explode('|', $request->pay_period);
            if (count($periodDates) === 2) {
                $startDate = Carbon::parse($periodDates[0]);
                $endDate = Carbon::parse($periodDates[1]);
                $query->where('period_start', $startDate)
                    ->where('period_end', $endDate);
            }
        }

        $payrolls = $query->orderBy('created_at', 'desc')->get();

        if ($format === 'excel') {
            return $this->exportPayrollSummaryExcel($payrolls);
        } else {
            return $this->exportPayrollSummaryPDF($payrolls);
        }
    }

    /**
     * Export payroll summary as Excel
     */
    private function exportPayrollSummaryExcel($payrolls)
    {
        $fileName = 'payroll_summary_' . date('Y-m-d_H-i-s') . '.csv';

        // Create CSV content with proper headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->streamDownload(function () use ($payrolls) {
            $output = fopen('php://output', 'w');

            // Initialize totals for Excel
            $totalBasicExcel = 0;
            $totalHolidayExcel = 0;
            $totalRestExcel = 0;
            $totalOvertimeExcel = 0;
            $totalAllowancesExcel = 0;
            $totalBonusesExcel = 0;
            $totalIncentivesExcel = 0;
            $totalGrossExcel = 0;
            $totalDeductionsExcel = 0;
            $totalNetExcel = 0;

            // Headers
            fputcsv($output, [
                'Payroll #',
                'Employee',
                'Period',
                'Basic Pay',
                'Holiday Pay',
                'Rest Pay',
                'Overtime Pay',
                'Allowances',
                'Bonuses',
                'Incentives',
                'Total Gross',
                'Total Deductions',
                'Total Net'
            ]);

            // Data rows - use snapshots for accurate data
            foreach ($payrolls as $payroll) {
                $snapshots = $payroll->snapshots; // Use snapshots instead of payrollDetails

                if ($snapshots->isEmpty()) {
                    // Fallback to payroll details if no snapshots
                    foreach ($payroll->payrollDetails as $detail) {
                        // Calculate correct Holiday, Rest, and Overtime pay from breakdown data
                        $correctHolidayPay = $this->calculateCorrectHolidayPay($detail, $payroll);
                        $correctRestPay = $this->calculateCorrectRestPay($detail, $payroll);
                        $correctOvertimePay = $this->calculateCorrectOvertimePay($detail, $payroll);

                        $basicPay = $detail->basic_pay ?? 0;
                        $allowances = $detail->allowances_total ?? 0;
                        $bonuses = $detail->bonuses_total ?? 0;
                        $incentives = $detail->incentives ?? 0;
                        $grossPay = $detail->gross_pay ?? 0;
                        $deductions = $detail->total_deductions ?? 0;
                        $netPay = $detail->net_pay ?? 0;

                        // Add to totals
                        $totalBasicExcel += $basicPay;
                        $totalHolidayExcel += $correctHolidayPay;
                        $totalRestExcel += $correctRestPay;
                        $totalOvertimeExcel += $correctOvertimePay;
                        $totalAllowancesExcel += $allowances;
                        $totalBonusesExcel += $bonuses;
                        $totalIncentivesExcel += $incentives;
                        $totalGrossExcel += $grossPay;
                        $totalDeductionsExcel += $deductions;
                        $totalNetExcel += $netPay;

                        fputcsv($output, [
                            $payroll->payroll_number,
                            $detail->employee->full_name,
                            $payroll->period_start->format('M j') . ' - ' . $payroll->period_end->format('M j, Y'),
                            number_format($basicPay, 2),
                            number_format($correctHolidayPay, 2),
                            number_format($correctRestPay, 2),
                            number_format($correctOvertimePay, 2),
                            number_format($allowances, 2),
                            number_format($bonuses, 2),
                            number_format($incentives, 2),
                            number_format($grossPay, 2),
                            number_format($deductions, 2),
                            number_format($netPay, 2)
                        ]);
                    }
                } else {
                    foreach ($snapshots as $snapshot) {
                        // Calculate correct Holiday, Rest, and Overtime pay from breakdown data
                        $correctHolidayPay = $this->calculateCorrectHolidayPayFromSnapshot($snapshot);
                        $correctOvertimePay = $this->calculateCorrectOvertimePayFromSnapshot($snapshot);
                        $correctRestPay = $this->calculateCorrectRestPayFromSnapshot($snapshot);

                        // Calculate basic pay from breakdown data to match payroll view
                        $basicPay = 0;
                        if ($snapshot->basic_breakdown) {
                            $basicBreakdown = is_string($snapshot->basic_breakdown) ?
                                json_decode($snapshot->basic_breakdown, true) :
                                $snapshot->basic_breakdown;
                            if (is_array($basicBreakdown)) {
                                foreach ($basicBreakdown as $type => $data) {
                                    $basicPay += $data['amount'] ?? 0;
                                }
                            }
                        } else {
                            $basicPay = $snapshot->regular_pay ?? 0;
                        }

                        $allowances = $snapshot->allowances_total ?? 0;
                        $bonuses = $snapshot->bonuses_total ?? 0;
                        $incentives = $snapshot->incentives_total ?? 0;

                        // Calculate gross pay from corrected component values
                        $grossPay = $basicPay + $correctHolidayPay + $correctRestPay + $correctOvertimePay + $allowances + $bonuses + $incentives;

                        // Recalculate deductions based on the recalculated gross pay
                        $deductions = $this->recalculateDeductionsFromBreakdown($snapshot, $grossPay);

                        // Calculate net pay from corrected values
                        $netPay = $grossPay - $deductions;

                        // Add to totals
                        $totalBasicExcel += $basicPay;
                        $totalHolidayExcel += $correctHolidayPay;
                        $totalRestExcel += $correctRestPay;
                        $totalOvertimeExcel += $correctOvertimePay;
                        $totalAllowancesExcel += $allowances;
                        $totalBonusesExcel += $bonuses;
                        $totalIncentivesExcel += $incentives;
                        $totalGrossExcel += $grossPay;
                        $totalDeductionsExcel += $deductions;
                        $totalNetExcel += $netPay;

                        fputcsv($output, [
                            $payroll->payroll_number,
                            $snapshot->employee_name,
                            $payroll->period_start->format('M j') . ' - ' . $payroll->period_end->format('M j, Y'),
                            number_format($basicPay, 2),
                            number_format($correctHolidayPay, 2),
                            number_format($correctRestPay, 2),
                            number_format($correctOvertimePay, 2),
                            number_format($allowances, 2),
                            number_format($bonuses, 2),
                            number_format($incentives, 2),
                            number_format($grossPay, 2),
                            number_format($deductions, 2),
                            number_format($netPay, 2)
                        ]);
                    }
                }
            }

            // Add totals row
            fputcsv($output, [
                'TOTAL',
                '',
                '',
                number_format($totalBasicExcel, 2),
                number_format($totalHolidayExcel, 2),
                number_format($totalRestExcel, 2),
                number_format($totalOvertimeExcel, 2),
                number_format($totalAllowancesExcel, 2),
                number_format($totalBonusesExcel, 2),
                number_format($totalIncentivesExcel, 2),
                number_format($totalGrossExcel, 2),
                number_format($totalDeductionsExcel, 2),
                number_format($totalNetExcel, 2)
            ]);

            fclose($output);
        }, $fileName, $headers);
    }

    /**
     * Export payroll summary as PDF
     */
    private function exportPayrollSummaryPDF($payrolls)
    {
        $fileName = 'payroll_summary_' . date('Y-m-d_H-i-s') . '.pdf';

        // Create HTML content for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Payroll Summary</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; color: #333; font-size: 18px; }
                .header p { margin: 5px 0; color: #666; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .text-right { text-align: right; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .currency { text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Payroll Summary Report</h1>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
                <p>Total Payrolls: ' . $payrolls->count() . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Payroll #</th>
                        <th>Employee</th>
                        <th>Period</th>
                        <th class="currency">Basic Pay</th>
                        <th class="currency">Holiday Pay</th>
                        <th class="currency">Rest Pay</th>
                        <th class="currency">Overtime</th>
                        <th class="currency">Allowances</th>
                        <th class="currency">Bonuses</th>
                        <th class="currency">Incentives</th>
                        <th class="currency">Gross Pay</th>
                        <th class="currency">Deductions</th>
                        <th class="currency">Net Pay</th>
                    </tr>
                </thead>
                <tbody>';

        $totalBasic = 0;
        $totalHoliday = 0;
        $totalRest = 0;
        $totalOvertime = 0;
        $totalAllowances = 0;
        $totalBonuses = 0;
        $totalIncentives = 0;
        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($payrolls as $payroll) {
            $snapshots = $payroll->snapshots;

            if ($snapshots->isEmpty()) {
                // Fallback to payroll details
                foreach ($payroll->payrollDetails as $detail) {
                    $basicPay = $detail->basic_pay ?? 0;
                    $holidayPay = $this->calculateCorrectHolidayPay($detail, $payroll);
                    $restPay = $this->calculateCorrectRestPay($detail, $payroll);
                    $overtimePay = $this->calculateCorrectOvertimePay($detail, $payroll);
                    $allowances = $detail->allowances_total ?? 0;
                    $bonuses = $detail->bonuses_total ?? 0;
                    $incentives = $detail->incentives ?? 0;
                    $grossPay = $detail->gross_pay ?? 0;
                    $deductions = $detail->total_deductions ?? 0;
                    $netPay = $detail->net_pay ?? 0;

                    $totalBasic += $basicPay;
                    $totalHoliday += $holidayPay;
                    $totalRest += $restPay;
                    $totalOvertime += $overtimePay;
                    $totalAllowances += $allowances;
                    $totalBonuses += $bonuses;
                    $totalIncentives += $incentives;
                    $totalGross += $grossPay;
                    $totalDeductions += $deductions;
                    $totalNet += $netPay;

                    $html .= '
                        <tr>
                            <td>' . $payroll->payroll_number . '</td>
                            <td>' . $detail->employee->full_name . '</td>
                            <td>' . $payroll->period_start->format('M j') . ' - ' . $payroll->period_end->format('M j, Y') . '</td>
                            <td class="currency">' . number_format($basicPay, 2) . '</td>
                            <td class="currency">' . number_format($holidayPay, 2) . '</td>
                            <td class="currency">' . number_format($restPay, 2) . '</td>
                            <td class="currency">' . number_format($overtimePay, 2) . '</td>
                            <td class="currency">' . number_format($allowances, 2) . '</td>
                            <td class="currency">' . number_format($bonuses, 2) . '</td>
                            <td class="currency">' . number_format($incentives, 2) . '</td>
                            <td class="currency">' . number_format($grossPay, 2) . '</td>
                            <td class="currency">' . number_format($deductions, 2) . '</td>
                            <td class="currency">' . number_format($netPay, 2) . '</td>
                        </tr>';
                }
            } else {
                foreach ($snapshots as $snapshot) {
                    // Calculate basic pay from breakdown data to match payroll view
                    $basicPay = 0;
                    if ($snapshot->basic_breakdown) {
                        $basicBreakdown = is_string($snapshot->basic_breakdown) ?
                            json_decode($snapshot->basic_breakdown, true) :
                            $snapshot->basic_breakdown;
                        if (is_array($basicBreakdown)) {
                            foreach ($basicBreakdown as $type => $data) {
                                $basicPay += $data['amount'] ?? 0;
                            }
                        }
                    } else {
                        $basicPay = $snapshot->regular_pay ?? 0;
                    }

                    $holidayPay = $this->calculateCorrectHolidayPayFromSnapshot($snapshot);
                    $restPay = $this->calculateCorrectRestPayFromSnapshot($snapshot);
                    $overtimePay = $this->calculateCorrectOvertimePayFromSnapshot($snapshot);
                    $allowances = $snapshot->allowances_total ?? 0;
                    $bonuses = $snapshot->bonuses_total ?? 0;
                    $incentives = $snapshot->incentives_total ?? 0;

                    // Calculate gross pay from corrected component values
                    $grossPay = $basicPay + $holidayPay + $restPay + $overtimePay + $allowances + $bonuses + $incentives;

                    // Recalculate deductions based on the recalculated gross pay
                    $deductions = $this->recalculateDeductionsFromBreakdown($snapshot, $grossPay);

                    // Calculate net pay from corrected values
                    $netPay = $grossPay - $deductions;

                    $totalBasic += $basicPay;
                    $totalHoliday += $holidayPay;
                    $totalRest += $restPay;
                    $totalOvertime += $overtimePay;
                    $totalAllowances += $allowances;
                    $totalBonuses += $bonuses;
                    $totalIncentives += $incentives;
                    $totalGross += $grossPay;
                    $totalDeductions += $deductions;
                    $totalNet += $netPay;

                    $html .= '
                        <tr>
                            <td>' . $payroll->payroll_number . '</td>
                            <td>' . $snapshot->employee_name . '</td>
                            <td>' . $payroll->period_start->format('M j') . ' - ' . $payroll->period_end->format('M j, Y') . '</td>
                            <td class="currency">' . number_format($basicPay, 2) . '</td>
                            <td class="currency">' . number_format($holidayPay, 2) . '</td>
                            <td class="currency">' . number_format($restPay, 2) . '</td>
                            <td class="currency">' . number_format($overtimePay, 2) . '</td>
                            <td class="currency">' . number_format($allowances, 2) . '</td>
                            <td class="currency">' . number_format($bonuses, 2) . '</td>
                            <td class="currency">' . number_format($incentives, 2) . '</td>
                            <td class="currency">' . number_format($grossPay, 2) . '</td>
                            <td class="currency">' . number_format($deductions, 2) . '</td>
                            <td class="currency">' . number_format($netPay, 2) . '</td>
                        </tr>';
                }
            }
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3"><strong>TOTAL</strong></td>
                        <td class="currency"><strong>' . number_format($totalBasic, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalHoliday, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalRest, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalOvertime, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalAllowances, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalBonuses, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalIncentives, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalGross, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalDeductions, 2) . '</strong></td>
                        <td class="currency"><strong>' . number_format($totalNet, 2) . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate proper PDF
        try {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            // Fallback to simple HTML if DomPDF is not available
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '_report.html', $fileName) . '"',
            ]);
        }
    }

    /**
     * Show the form for creating a new payroll.
     */
    public function create(Request $request)
    {
        $this->authorize('create payrolls');

        // Get the selected pay schedule filter
        $selectedSchedule = $request->input('schedule');

        // Get all payroll schedule settings (both active and inactive for display)
        $scheduleSettings = \App\Models\PayScheduleSetting::systemDefaults()
            ->orderBy('sort_order')
            ->get();

        // If no schedule is selected, show all available schedules for selection
        if (!$selectedSchedule) {
            // Calculate current periods for each schedule to display
            foreach ($scheduleSettings as $setting) {
                if ($setting->is_active) {
                    $currentPeriods = $this->getCurrentPeriodDisplayForSchedule($setting);
                    $setting->current_period_display = $currentPeriods;
                }
            }

            return view('payrolls.create', [
                'scheduleSettings' => $scheduleSettings,
                'selectedSchedule' => null,
                'availablePeriods' => [],
                'selectedPeriod' => null,
                'employees' => collect()
            ]);
        }

        // Get the specific schedule setting
        $scheduleSetting = $scheduleSettings->firstWhere('code', $selectedSchedule);
        if (!$scheduleSetting) {
            return redirect()->route('payrolls.create')
                ->withErrors(['schedule' => 'Invalid pay schedule selected.']);
        }

        // Get current month periods only for the selected schedule
        $availablePeriods = $this->getCurrentMonthPeriodsForSchedule($scheduleSetting);

        // Get selected period if provided, or auto-select current period
        $selectedPeriodId = $request->input('period');
        $selectedPeriod = null;
        $employees = collect();

        if ($selectedPeriodId) {
            $selectedPeriod = collect($availablePeriods)->firstWhere('id', $selectedPeriodId);
        } else {
            // Auto-select current period if none specified
            $selectedPeriod = collect($availablePeriods)->firstWhere('is_current', true);
            if (!$selectedPeriod && count($availablePeriods) > 0) {
                // If no current period, select the first available period
                $selectedPeriod = $availablePeriods[0];
            }
        }

        if ($selectedPeriod) {
            // Get employees for this schedule - handle different naming conventions
            $payScheduleVariations = $this->getPayScheduleVariations($selectedPeriod['pay_schedule']);

            $employees = Employee::with(['user', 'department', 'position'])
                ->where('employment_status', 'active')
                ->whereIn('pay_schedule', $payScheduleVariations)
                ->orderBy('first_name')
                ->get();
        }

        return view('payrolls.create', compact(
            'scheduleSettings',
            'selectedSchedule',
            'availablePeriods',
            'selectedPeriod',
            'employees'
        ));
    }

    /**
     * Store a newly created payroll.
     */
    public function store(Request $request)
    {
        $this->authorize('create payrolls');

        $validated = $request->validate([
            'selected_period' => 'required|string',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        // Parse the selected period data
        $periodData = json_decode(base64_decode($validated['selected_period']), true);

        if (!$periodData) {
            return back()->withErrors(['selected_period' => 'Invalid period selection.'])->withInput();
        }

        // Validate that selected employees match the pay schedule
        $selectedEmployees = Employee::whereIn('id', $validated['employee_ids'])->get();
        $invalidEmployees = $selectedEmployees->where('pay_schedule', '!=', $periodData['pay_schedule']);

        if ($invalidEmployees->count() > 0) {
            return back()->withErrors([
                'employee_ids' => 'All selected employees must have the same pay schedule as the selected period.'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Create payroll (always regular type now)
            $payroll = Payroll::create([
                'payroll_number' => Payroll::generatePayrollNumber('regular'),
                'period_start' => $periodData['period_start'],
                'period_end' => $periodData['period_end'],
                'pay_date' => $periodData['pay_date'],
                'payroll_type' => 'regular',
                'pay_schedule' => $periodData['pay_schedule'],
                'description' => 'Manual payroll for ' . $periodData['period_display'],
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $processedEmployees = 0;

            // Create payroll details for each employee
            foreach ($validated['employee_ids'] as $employeeId) {
                try {
                    $employee = Employee::find($employeeId);

                    if (!$employee) {
                        Log::warning("Employee with ID {$employeeId} not found");
                        continue;
                    }

                    // Calculate payroll details
                    $payrollDetail = $this->calculateEmployeePayroll($employee, $payroll);

                    $totalGross += $payrollDetail->gross_pay;
                    $totalDeductions += $payrollDetail->total_deductions;
                    $totalNet += $payrollDetail->net_pay;
                    $processedEmployees++;
                } catch (\Exception $e) {
                    Log::error("Failed to process employee {$employeeId}: " . $e->getMessage());
                    // Continue with other employees rather than failing the entire payroll
                    continue;
                }
            }

            if ($processedEmployees === 0) {
                throw new \Exception('No employees could be processed for payroll.');
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', "Payroll created successfully! {$processedEmployees} employees processed.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payroll: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create payroll: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Automation Payroll - Show schedule selection
     */
    public function automationIndex()
    {
        $this->authorize('view payrolls');

        // Get all active pay schedules from pay_schedules table (NOT pay_schedule_settings)
        $allSchedules = PaySchedule::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();



        // Group schedules by type for display
        $schedulesByType = $allSchedules->groupBy('type');

        // Calculate current periods and employee counts for each schedule
        foreach ($allSchedules as $schedule) {
            // Count active employees for this schedule
            $schedule->active_employees_count = \App\Models\Employee::where('pay_schedule_id', $schedule->id)
                ->where('employment_status', 'active')
                ->count();

            // Calculate current pay period for this schedule
            try {
                $schedule->next_period = $this->calculateCurrentPayPeriodForSchedule($schedule);
            } catch (\Exception $e) {
                $schedule->next_period = null;
            }

            // Calculate and format last payroll period
            try {
                $previousPeriod = $this->calculatePreviousPayPeriodForSchedule($schedule);
                if ($previousPeriod) {
                    $startDate = \Carbon\Carbon::parse($previousPeriod['start']);
                    $endDate = \Carbon\Carbon::parse($previousPeriod['end']);
                    $schedule->last_payroll_period = $startDate->format('M d') . ' - ' . $endDate->format('d, Y');
                }
            } catch (\Exception $e) {
                // If calculation fails, fallback to checking actual last payroll
                $lastPayroll = \App\Models\Payroll::where('pay_schedule', $schedule->type)
                    ->orderBy('pay_date', 'desc')
                    ->first();

                if ($lastPayroll) {
                    $schedule->last_payroll_period = \Carbon\Carbon::parse($lastPayroll->period_start)->format('M d') . ' - ' .
                        \Carbon\Carbon::parse($lastPayroll->period_end)->format('d, Y');
                }
            }
        }

        return view('payrolls.automation.index', [
            'schedulesByType' => $schedulesByType,
            'allSchedules' => $allSchedules
        ]);
    }

    /**
     * Show pay schedules for a specific frequency type
     */
    public function automationSchedules(Request $request, $frequency)
    {
        $this->authorize('view payrolls');

        // Validate frequency type
        $validFrequencies = ['daily', 'weekly', 'semi_monthly', 'monthly'];
        if (!in_array($frequency, $validFrequencies)) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay frequency selected.');
        }

        // Get active pay schedules for this frequency
        $schedules = PaySchedule::active()
            ->where('type', $frequency)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Calculate employee counts and periods for each schedule
        foreach ($schedules as $schedule) {
            // Count active employees for this schedule
            $schedule->active_employees_count = \App\Models\Employee::where('pay_schedule_id', $schedule->id)
                ->where('employment_status', 'active')
                ->count();

            // Calculate current pay period for this schedule
            try {
                $schedule->next_period = $this->calculateCurrentPayPeriodForSchedule($schedule);
            } catch (\Exception $e) {
                $schedule->next_period = null;
            }

            // Calculate and format last payroll period
            try {
                $previousPeriod = $this->calculatePreviousPayPeriodForSchedule($schedule);
                if ($previousPeriod) {
                    $startDate = \Carbon\Carbon::parse($previousPeriod['start']);
                    $endDate = \Carbon\Carbon::parse($previousPeriod['end']);

                    // Format with proper cross-month handling
                    if ($startDate->month === $endDate->month) {
                        $schedule->last_payroll_period = $startDate->format('M d') . ' - ' . $endDate->format('d, Y');
                    } else {
                        $schedule->last_payroll_period = $startDate->format('M d') . ' - ' . $endDate->format('M d, Y');
                    }
                }
            } catch (\Exception $e) {
                // If calculation fails, fallback to checking actual last payroll from database
                $lastPayroll = \App\Models\Payroll::where('pay_schedule', $schedule->type)
                    ->where('payroll_type', 'automated')
                    ->orderBy('period_end', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastPayroll) {
                    $startDate = \Carbon\Carbon::parse($lastPayroll->period_start);
                    $endDate = \Carbon\Carbon::parse($lastPayroll->period_end);

                    if ($startDate->month === $endDate->month) {
                        $schedule->last_payroll_period = $startDate->format('M d') . ' - ' . $endDate->format('d, Y');
                    } else {
                        $schedule->last_payroll_period = $startDate->format('M d') . ' - ' . $endDate->format('M d, Y');
                    }
                }
            }
        }

        return view('payrolls.automation.schedules', [
            'schedules' => $schedules,
            'frequency' => $frequency
        ]);
    }

    /**
     * Automation Payroll - List payrolls for specific schedule and period
     */
    public function automationPeriodList(Request $request, $schedule, $period)
    {
        $this->authorize('view payrolls');

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->type,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Calculate the specific period based on the period parameter
        $targetPeriod = null;
        if ($paySchedule) {
            $targetPeriod = $this->calculateSpecificPayPeriod($paySchedule, $period);
        } else {
            // For legacy schedules, fall back to current period
            $targetPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        if (!$targetPeriod) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Unable to calculate pay period.');
        }

        // Check if payrolls exist for this specific period
        $existingPayrolls = Payroll::with(['creator', 'approver', 'payrollDetails.employee'])
            ->withCount('payrollDetails')
            ->where('pay_schedule', $selectedSchedule->code)
            ->where('payroll_type', 'automated')
            ->where('period_start', $targetPeriod['start'])
            ->where('period_end', $targetPeriod['end'])
            ->orderBy('created_at', 'desc')
            ->paginate(request('per_page', 15));

        return view('payrolls.automation.list', [
            'selectedSchedule' => $selectedSchedule,
            'currentPeriod' => $targetPeriod,
            'payrolls' => $existingPayrolls,
            'scheduleCode' => $selectedSchedule->code,
            'hasPayrolls' => $existingPayrolls->count() > 0,
            'isDraft' => false,
            'period' => $period,
            'allApproved' => $existingPayrolls->every(function ($payroll) {
                return $payroll->status === 'approved';
            }),
            'backUrl' => route('payrolls.automation.schedules', ['frequency' => $selectedSchedule->code])
        ]);
    }

    /**
     * Calculate specific pay period based on period parameter (1st, 2nd, current)
     */
    private function calculateSpecificPayPeriod($paySchedule, $period)
    {
        $today = Carbon::now();

        switch ($period) {
            case 'current':
                return $this->calculateCurrentPayPeriodForSchedule($paySchedule);

            case '1st':
                if ($paySchedule->type === 'semi_monthly') {
                    // Calculate 1st period of current month
                    $periods = $paySchedule->getValidatedCutoffPeriods();
                    if (count($periods) >= 1) {
                        return $this->calculateSemiMonthlyPeriodForSchedule($today->year, $today->month, $periods[0]);
                    }
                }
                break;

            case '2nd':
                if ($paySchedule->type === 'semi_monthly') {
                    // Calculate 2nd period of current month
                    $periods = $paySchedule->getValidatedCutoffPeriods();
                    if (count($periods) >= 2) {
                        return $this->calculateSemiMonthlyPeriodForSchedule($today->year, $today->month, $periods[1]);
                    }
                }
                break;
        }

        // Fallback to current period
        return $this->calculateCurrentPayPeriodForSchedule($paySchedule);
    }

    /**
     * Show draft mode for specific period
     */
    private function showDraftModeForPeriod($selectedSchedule, $targetPeriod, $schedule, $period)
    {
        // Get employees who already have payroll records for this period
        $employeesWithPayrolls = Payroll::whereHas('payrollDetails')
            ->where('pay_schedule', $schedule)
            ->where('period_start', $targetPeriod['start'])
            ->where('period_end', $targetPeriod['end'])
            ->where('payroll_type', 'automated')
            ->with('payrollDetails')
            ->get()
            ->pluck('payrollDetails.*.employee_id')
            ->flatten()
            ->unique()
            ->toArray();

        // Get active employees for this schedule, excluding those who already have payrolls
        $employeesQuery = Employee::with(['user', 'department', 'position'])
            ->where('employment_status', 'active')
            ->whereNotIn('id', $employeesWithPayrolls);

        // Filter by pay schedule
        if (isset($selectedSchedule->id)) {
            // New PaySchedule system
            $employeesQuery->where('pay_schedule_id', $selectedSchedule->id);
        } else {
            // Legacy system - filter by pay_schedule field
            $employeesQuery->where('pay_schedule', $schedule);
        }

        $employees = $employeesQuery->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('payrolls.automation.list', [
            'selectedSchedule' => $selectedSchedule,
            'currentPeriod' => $targetPeriod,
            'employees' => $employees,
            'existingPayrolls' => collect([]), // Empty for draft mode
            'isDraftMode' => true,
            'period' => $period ?? 'current'
        ]);
    }

    /**
     * Show individual payroll with period-specific context
     */
    public function showPeriodSpecificPayroll(Request $request, $schedule, $period, $id)
    {
        $this->authorize('view payrolls');

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->type,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Calculate the specific period based on the period parameter
        $targetPeriod = null;
        if ($paySchedule) {
            $targetPeriod = $this->calculateSpecificPayPeriod($paySchedule, $period);
        } else {
            // For legacy schedules, fall back to current period
            $targetPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        if (!$targetPeriod) {
            return redirect()->route('payrolls.automation.period', ['schedule' => $schedule, 'period' => $period])
                ->with('error', 'Unable to calculate pay period.');
        }

        // First, check if this ID is a payroll ID (for saved/historical payrolls)
        $payroll = Payroll::with(['payrollDetails.employee', 'creator', 'approver'])
            ->where('id', $id)
            ->where('pay_schedule', $selectedSchedule->code)
            ->where('period_start', $targetPeriod['start'])
            ->where('period_end', $targetPeriod['end'])
            ->first();

        if ($payroll) {
            // This is a saved payroll - show it with the correct period context
            return $this->showPayrollWithPeriodContext($payroll, $schedule, $period, $targetPeriod);
        }

        // If not a payroll ID, treat it as an employee ID (for current period/draft payrolls)
        $employee = Employee::with(['timeSchedule', 'daySchedule'])->find($id);

        if (!$employee) {
            return redirect()->route('payrolls.automation.period', ['schedule' => $schedule, 'period' => $period])
                ->with('error', 'Employee or payroll not found.');
        }

        // Check if employee has payroll for this specific period
        $existingPayroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $selectedSchedule->code)
            ->where('period_start', $targetPeriod['start'])
            ->where('period_end', $targetPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if ($existingPayroll) {
            // Redirect to the payroll ID version to maintain consistency
            return redirect()->route('payrolls.automation.period.show', [
                'schedule' => $schedule,
                'period' => $period,
                'id' => $existingPayroll->id
            ]);
        }

        // No existing payroll - show draft mode for this specific period
        return $this->showDraftModeForSpecificPeriod($selectedSchedule, $employee, $targetPeriod, $schedule, $period);
    }

    /**
     * Show payroll with period context
     */
    private function showPayrollWithPeriodContext($payroll, $schedule, $period, $targetPeriod)
    {
        // Override the payroll period data with the target period to ensure consistency
        $payroll->calculated_period = $targetPeriod;
        $payroll->context_period = $period;
        $payroll->context_schedule = $schedule;

        // Override the period dates to match the target period
        $payroll->override_period_start = $targetPeriod['start'];
        $payroll->override_period_end = $targetPeriod['end'];
        $payroll->override_pay_date = $targetPeriod['pay_date'];

        return $this->showPayrollWithAdditionalData($payroll, [
            'schedule' => $schedule,
            'period' => $period,
            'targetPeriod' => $targetPeriod,
            'backUrl' => route('payrolls.automation.period', ['schedule' => $schedule, 'period' => $period])
        ]);
    }
    /**
     * Show draft mode for specific employee and period
     */
    private function showDraftModeForSpecificPeriod($selectedSchedule, $employee, $targetPeriod, $schedule, $period)
    {
        // This would show the employee's draft payroll for the specific period
        // For now, redirect back to the period list with a message
        return redirect()->route('payrolls.automation.period', ['schedule' => $schedule, 'period' => $period])
            ->with('info', 'No payroll found for this employee in the selected period.');
    }

    /**
     * Automation Payroll - Create payroll for selected schedule
     */
    public function automationCreate(Request $request)
    {
        Log::info('Automation Create called with: ' . json_encode($request->all()));

        $this->authorize('create payrolls');

        // Get the selected pay schedule (could be ID or legacy code)
        $scheduleParam = $request->input('schedule');

        Log::info('Schedule Parameter: ' . $scheduleParam);

        if (!$scheduleParam) {
            Log::warning('No schedule parameter provided');
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Please select a pay schedule.');
        }

        // Try to find by ID first (new system), then by code (legacy)
        $selectedSchedule = null;
        if (is_numeric($scheduleParam)) {
            // New system - PaySchedule model
            $selectedSchedule = PaySchedule::active()->find($scheduleParam);
            if ($selectedSchedule) {
                // Redirect to new list route with schedule ID
                return redirect()->route('payrolls.automation.list', ['schedule' => $selectedSchedule->id])
                    ->with('success', 'Viewing automated payrolls for ' . $selectedSchedule->name . '.');
            }
        } else {
            // Legacy system - PayScheduleSetting model
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $scheduleParam)
                ->first();

            if ($selectedSchedule) {
                // Redirect to legacy list route
                return redirect()->route('payrolls.automation.list', $scheduleParam)
                    ->with('success', 'Viewing draft payrolls for ' . $selectedSchedule->name . '. Click on individual employees to view details.');
            }
        }

        return redirect()->route('payrolls.automation.index')
            ->with('error', 'Invalid pay schedule selected.');
    }

    /**
     * Show list of automated payrolls for a specific schedule
     * If no payrolls exist, show draft mode. If payrolls exist, show real payrolls.
     */
    public function automationList(Request $request, $schedule)
    {
        $this->authorize('view payrolls');

        // Handle both new PaySchedule names and legacy PayScheduleSetting codes
        $selectedSchedule = null;
        $scheduleCode = null;

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        if ($paySchedule) {
            // For new schedules, use the type as the code for payroll queries (maintains compatibility)
            $scheduleCode = $paySchedule->type;
            $selectedSchedule = (object) [
                'code' => $paySchedule->type,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id,
                'route_name' => $paySchedule->name  // Use name for routes
            ];
        } else if (is_numeric($schedule)) {
            // Fallback: try by ID (new system)
            $paySchedule = PaySchedule::active()->find($schedule);
            if ($paySchedule) {
                // For new schedules, use the type as the code for payroll queries (maintains compatibility)
                $scheduleCode = $paySchedule->type;
                $selectedSchedule = (object) [
                    'code' => $paySchedule->type,
                    'name' => $paySchedule->name,
                    'type' => $paySchedule->type,
                    'id' => $paySchedule->id,
                    'route_name' => $paySchedule->name  // Use name for routes
                ];
            }
        } else {
            // Legacy system - PayScheduleSetting model
            $legacySchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
            if ($legacySchedule) {
                $scheduleCode = $legacySchedule->code;
                $selectedSchedule = $legacySchedule;
            }
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Calculate current period to filter payrolls
        if (isset($selectedSchedule->id) && $paySchedule) {
            // New PaySchedule system
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } else {
            // Legacy PayScheduleSetting system
            $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        // Check if payrolls exist for this period
        $existingPayrolls = Payroll::with(['creator', 'approver', 'payrollDetails.employee'])
            ->withCount('payrollDetails')
            ->where('pay_schedule', $scheduleCode)
            ->where('payroll_type', 'automated')
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter to only show DRAFT payrolls
        $draftPayrolls = $existingPayrolls->filter(function ($payroll) {
            return $payroll->status === 'draft';
        });

        // Always show draft mode for employees without payroll records
        // This will include dynamic draft payrolls for employees who don't have records yet
        $routeName = isset($selectedSchedule->route_name) ? $selectedSchedule->route_name : $scheduleCode;
        return $this->showDraftMode($selectedSchedule, $currentPeriod, $scheduleCode, $routeName);
    }

    /**
     * Show draft mode with dynamic calculations (not saved to DB)
     */
    private function showDraftMode($selectedSchedule, $currentPeriod, $schedule, $routeName = null)
    {
        // Get employees who already have payroll records for this period
        $employeesWithPayrolls = Payroll::whereHas('payrollDetails')
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->with('payrollDetails')
            ->get()
            ->pluck('payrollDetails.*.employee_id')
            ->flatten()
            ->unique()
            ->toArray();

        // Get active employees for this schedule, excluding those who already have payrolls
        $employeesQuery = Employee::with(['user', 'department', 'position'])
            ->where('employment_status', 'active')
            ->whereNotIn('id', $employeesWithPayrolls)
            ->orderBy('first_name');

        // Handle both new PaySchedule system (by ID) and legacy system (by type)
        if (isset($selectedSchedule->id) && is_numeric($selectedSchedule->id)) {
            // New system - filter by pay_schedule_id
            $employeesQuery->where('pay_schedule_id', $selectedSchedule->id);
        } else {
            // Legacy system - filter by pay_schedule type
            $employeesQuery->where('pay_schedule', $schedule);
        }

        // Get all employees count for calculations
        $allEmployees = $employeesQuery->get();

        if ($allEmployees->isEmpty()) {
            // If no employees are available for draft payrolls, show the page anyway
            // This could mean all employees already have payrolls or no active employees exist
            $allActiveEmployeesQuery = Employee::where('employment_status', 'active');

            // Handle both new PaySchedule system (by ID) and legacy system (by type)
            if (isset($selectedSchedule->id) && is_numeric($selectedSchedule->id)) {
                $allActiveEmployees = $allActiveEmployeesQuery->where('pay_schedule_id', $selectedSchedule->id)->count();
            } else {
                $allActiveEmployees = $allActiveEmployeesQuery->where('pay_schedule', $schedule)->count();
            }

            // Don't redirect, show the page with appropriate message

            // Show empty state with information (no active employees or all already have payrolls)
            $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                0,
                15,
                1,
                ['path' => request()->url()]
            );

            return view('payrolls.automation.list', compact(
                'selectedSchedule',
                'currentPeriod'
            ) + [
                'scheduleCode' => $routeName ?: $schedule,
                'isDraft' => true,
                'payrolls' => $mockPaginator,
                'hasPayrolls' => false,
                'allApproved' => false,
                'allEmployeesHavePayrolls' => ($allActiveEmployees > 0),
                'noActiveEmployees' => ($allActiveEmployees == 0),
                'totalActiveEmployees' => $allActiveEmployees,
                'draftTotals' => [
                    'gross' => 0,
                    'deductions' => 0,
                    'net' => 0,
                    'count' => 0
                ]
            ]);
        }

        // Calculate dynamic payroll data for each employee (not saved to DB)
        $payrollPreviews = collect();
        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($allEmployees as $employee) {
            $payrollCalculation = $this->calculateEmployeePayrollForPeriod($employee, $currentPeriod['start'], $currentPeriod['end']);

            // Create a temporary Payroll model instance (not saved to DB)
            $mockPayroll = new Payroll();
            // Use employee ID directly for draft mode
            $mockPayroll->id = $employee->id;
            $mockPayroll->payroll_number = 'DRAFT-' . $employee->employee_number;
            $mockPayroll->period_start = $currentPeriod['start'];
            $mockPayroll->period_end = $currentPeriod['end'];
            $mockPayroll->pay_date = $currentPeriod['pay_date'];
            $mockPayroll->status = 'draft';
            $mockPayroll->payroll_type = 'automated';
            $mockPayroll->total_net = $payrollCalculation['net_pay'] ?? 0;
            $mockPayroll->total_gross = $payrollCalculation['gross_pay'] ?? 0;
            $mockPayroll->total_deductions = $payrollCalculation['total_deductions'] ?? 0;
            $mockPayroll->created_at = now();

            // Set fake relationships
            $fakeCreator = (object) ['name' => 'System (Draft)', 'id' => 0];
            $mockPayroll->setRelation('creator', $fakeCreator);
            $mockPayroll->setRelation('approver', null);

            // Mock payroll details collection with single employee
            $mockPayrollDetail = (object) [
                'employee' => $employee,
                'employee_id' => $employee->id,
                'gross_pay' => $payrollCalculation['gross_pay'] ?? 0,
                'total_deductions' => $payrollCalculation['total_deductions'] ?? 0,
                'net_pay' => $payrollCalculation['net_pay'] ?? 0,
            ];
            $mockPayroll->setRelation('payrollDetails', collect([$mockPayrollDetail]));
            $mockPayroll->payroll_details_count = 1;

            $payrollPreviews->push($mockPayroll);

            $totalGross += $payrollCalculation['gross_pay'] ?? 0;
            $totalDeductions += $payrollCalculation['total_deductions'] ?? 0;
            $totalNet += $payrollCalculation['net_pay'] ?? 0;
        }

        // Get pagination parameters
        $perPage = request()->get('per_page', 10);
        $currentPage = request()->get('page', 1);

        // Create paginator for mock data
        $mockPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $payrollPreviews->forPage($currentPage, $perPage),
            $payrollPreviews->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );

        // Add query parameters to pagination links
        $mockPaginator->withQueryString();

        // Return draft payrolls that work with existing view
        return view('payrolls.automation.list', compact(
            'selectedSchedule',
            'currentPeriod'
        ) + [
            'scheduleCode' => $routeName ?: $schedule,
            'isDraft' => true,
            'payrolls' => $mockPaginator,
            'hasPayrolls' => false,
            'allApproved' => false,
            'draftTotals' => [
                'gross' => $totalGross,
                'deductions' => $totalDeductions,
                'net' => $totalNet,
                'count' => $payrollPreviews->count()
            ]
        ]);
    }

    /**
     * Auto-create individual payrolls for each employee in the period
     */
    private function autoCreatePayrollForPeriod($scheduleSetting, $period, $employees, $status = 'draft')
    {
        DB::beginTransaction();

        try {
            Log::info("Starting individual payroll creation for {$employees->count()} employees");

            $createdPayrolls = [];

            // Create individual payroll for each employee
            foreach ($employees as $employee) {
                Log::info("Creating payroll for employee: {$employee->id} - {$employee->first_name} {$employee->last_name}");

                try {
                    // Generate unique payroll number for this employee
                    $payrollNumber = $this->generatePayrollNumber($scheduleSetting->code);

                    // Calculate payroll details for this employee
                    $payrollCalculation = $this->calculateEmployeePayrollForPeriod($employee, $period['start'], $period['end']);

                    // Log processing calculation for comparison with draft
                    Log::info('Processing Payroll Calculation for Employee ' . $employee->id, $payrollCalculation);

                    // Create individual payroll for this employee
                    $payroll = Payroll::create([
                        'payroll_number' => $payrollNumber,
                        'pay_schedule' => $scheduleSetting->code,
                        'period_start' => $period['start'],
                        'period_end' => $period['end'],
                        'pay_date' => $period['pay_date'],
                        'status' => $status,
                        'payroll_type' => 'automated',
                        'created_by' => Auth::id() ?? 1,
                        'notes' => 'Automatically created payroll for ' . $employee->first_name . ' ' . $employee->last_name . ' (' . $scheduleSetting->name . ' schedule)',
                        'total_gross' => $payrollCalculation['gross_pay'] ?? 0,
                        'total_deductions' => $payrollCalculation['total_deductions'] ?? 0,
                        'total_net' => $payrollCalculation['net_pay'] ?? 0,
                    ]);

                    Log::info("Created payroll {$payrollNumber} for employee {$employee->id}");

                    // Calculate hourly rate using new method for payroll detail record
                    $calculatedHourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);

                    // Create payroll detail for this employee
                    $payrollDetail = PayrollDetail::create([
                        'payroll_id' => $payroll->id,
                        'employee_id' => $employee->id,
                        'basic_salary' => $employee->basic_salary ?? 0,
                        'daily_rate' => $employee->daily_rate ?? 0,
                        'hourly_rate' => $calculatedHourlyRate,
                        'days_worked' => $payrollCalculation['days_worked'] ?? 0,
                        'regular_hours' => $payrollCalculation['hours_worked'] ?? 0,
                        'overtime_hours' => $payrollCalculation['overtime_hours'] ?? 0,
                        'holiday_hours' => $payrollCalculation['holiday_hours'] ?? 0,
                        'rest_day_hours' => $payrollCalculation['rest_day_hours'] ?? 0,
                        'regular_pay' => $payrollCalculation['regular_pay'] ?? 0, // Use the calculated basic pay
                        'overtime_pay' => $payrollCalculation['overtime_pay'] ?? 0,
                        'holiday_pay' => $payrollCalculation['holiday_pay'] ?? 0,
                        'rest_day_pay' => $payrollCalculation['rest_day_pay'] ?? 0,
                        'allowances' => $payrollCalculation['allowances'] ?? 0,
                        'bonuses' => $payrollCalculation['bonuses'] ?? 0,
                        'incentives' => $payrollCalculation['incentives'] ?? 0,
                        'gross_pay' => $payrollCalculation['gross_pay'] ?? 0,
                        'sss_contribution' => $payrollCalculation['sss_deduction'] ?? 0,
                        'philhealth_contribution' => $payrollCalculation['philhealth_deduction'] ?? 0,
                        'pagibig_contribution' => $payrollCalculation['pagibig_deduction'] ?? 0,
                        'withholding_tax' => $payrollCalculation['tax_deduction'] ?? 0,
                        'late_deductions' => $payrollCalculation['late_deductions'] ?? 0,
                        'undertime_deductions' => $payrollCalculation['undertime_deductions'] ?? 0,
                        'cash_advance_deductions' => $payrollCalculation['cash_advance_deductions'] ?? 0,
                        'other_deductions' => $payrollCalculation['other_deductions'] ?? 0,
                        'total_deductions' => $payrollCalculation['total_deductions'] ?? 0,
                        'net_pay' => $payrollCalculation['net_pay'] ?? 0,
                        'earnings_breakdown' => json_encode([
                            'allowances' => $payrollCalculation['allowances_details'] ?? [],
                            'bonuses' => $payrollCalculation['bonuses_details'] ?? [],
                            'incentives' => $payrollCalculation['incentives_details'] ?? [],
                        ]),
                        'deduction_breakdown' => json_encode($payrollCalculation['deductions_details'] ?? []),
                    ]);

                    Log::info("Created payroll detail for employee {$employee->id}: Gross: {$payrollCalculation['gross_pay']}, Net: {$payrollCalculation['net_pay']}");

                    $createdPayrolls[] = $payroll;
                } catch (\Exception $e) {
                    Log::warning("Failed to create payroll for employee {$employee->id}: " . $e->getMessage());
                    // Continue with other employees
                }
            }

            DB::commit();

            // Create snapshots for processing payrolls
            if ($status === 'processing') {
                foreach ($createdPayrolls as $payroll) {
                    try {
                        Log::info("Creating snapshots for payroll {$payroll->id}");
                        $this->createPayrollSnapshots($payroll);
                    } catch (\Exception $e) {
                        Log::error("Failed to create snapshots for payroll {$payroll->id}: " . $e->getMessage());
                        // Don't fail the entire process, but log the error
                    }
                }
            }

            Log::info("Successfully created " . count($createdPayrolls) . " individual payrolls for {$scheduleSetting->name} schedule");

            // Return the first payroll (we'll redirect to the list instead)
            return $createdPayrolls[0] ?? null;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to auto-create payrolls: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate payroll details for an employee for a specific period
     */
    private function calculateEmployeePayrollForPeriod($employee, $periodStart, $periodEnd, $payroll = null)
    {
        // Basic salary calculation based on employee's salary and period
        $basicSalary = $employee->basic_salary ?? 0;

        // Get time logs for the payroll period
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$periodStart, $periodEnd])
            ->get();

        $hoursWorked = 0;
        $daysWorked = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $holidayHours = 0;
        $lateHours = 0;
        $undertimeHours = 0;

        // For draft payrolls, calculate dynamically using current grace periods
        // For approved payrolls, use stored values (snapshots)
        $isDraftMode = $payroll === null || $payroll->status === 'draft';

        // Calculate total hours and days worked
        foreach ($timeLogs as $timeLog) {
            if ($isDraftMode && $timeLog->time_in && $timeLog->time_out) {
                // Calculate dynamically using current grace periods and employee schedules
                $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);

                $hoursWorked += $dynamicCalculation['total_hours'];
                $regularHours += $dynamicCalculation['regular_hours'];
                $overtimeHours += $dynamicCalculation['overtime_hours'];
                $lateHours += $dynamicCalculation['late_hours'];
                $undertimeHours += $dynamicCalculation['undertime_hours'];

                // IMPORTANT: Set dynamic night differential hours on TimeLog for gross pay calculation
                $timeLog->dynamic_night_diff_regular_hours = $dynamicCalculation['night_diff_regular_hours'] ?? 0;
                $timeLog->dynamic_night_diff_overtime_hours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                $timeLog->dynamic_regular_hours = $dynamicCalculation['regular_hours'] ?? 0;
                $timeLog->dynamic_regular_overtime_hours = $dynamicCalculation['regular_overtime_hours'] ?? 0;

                if ($dynamicCalculation['total_hours'] > 0) {
                    $daysWorked++;
                }

                // Handle holiday hours
                if ($timeLog->is_holiday && $dynamicCalculation['total_hours'] > 0) {
                    $holidayHours += $dynamicCalculation['total_hours'];
                }
            } else {
                // Use stored values for approved payrolls (snapshot mode)
                $hoursWorked += $timeLog->total_hours ?? 0;
                $regularHours += $timeLog->regular_hours ?? 0;
                $overtimeHours += $timeLog->overtime_hours ?? 0;
                $lateHours += $timeLog->late_hours ?? 0;
                $undertimeHours += $timeLog->undertime_hours ?? 0;

                if (($timeLog->total_hours ?? 0) > 0) {
                    $daysWorked++;
                }

                // Handle holiday hours from stored values
                if ($timeLog->is_holiday && ($timeLog->total_hours ?? 0) > 0) {
                    $holidayHours += $timeLog->total_hours ?? 0;
                }
            }
        }

        // Calculate gross pay using rate multipliers from time logs
        $grossPayData = $this->calculateGrossPayWithRateMultipliersDetailed($employee, $basicSalary, $timeLogs, $hoursWorked, $daysWorked, $periodStart, $periodEnd);
        $grossPay = $grossPayData['total_gross'];

        // Use calculated basic pay for allowances/bonuses/incentives calculations instead of employee's basic salary
        $calculatedBasicPay = $grossPayData['basic_pay'] ?? 0;

        // Calculate allowances using dynamic settings
        $allowancesData = $this->calculateAllowances($employee, $calculatedBasicPay, $daysWorked, $hoursWorked, $periodStart, $periodEnd);
        $allowancesTotal = $allowancesData['total'];

        // Calculate bonuses using dynamic settings
        $bonusesData = $this->calculateBonuses($employee, $calculatedBasicPay, $daysWorked, $hoursWorked, $periodStart, $periodEnd);
        $bonusesTotal = $bonusesData['total'];

        // Calculate incentives using dynamic settings
        $incentivesData = $this->calculateIncentives($employee, $calculatedBasicPay, $daysWorked, $hoursWorked, $periodStart, $periodEnd);
        $incentivesTotal = $incentivesData['total'];

        // Calculate overtime pay (simplified for now)
        $overtimePay = 0; // TODO: Implement detailed overtime calculation based on time logs

        // Total gross pay including allowances, bonuses, and incentives
        $totalGrossPay = round($grossPay + $allowancesTotal + $bonusesTotal + $incentivesTotal + $overtimePay, 2);

        // Calculate late and undertime deductions based on dynamic calculations
        $lateDeductions = $this->calculateLateDeductions($employee, $lateHours);
        $undertimeDeductions = $this->calculateUndertimeDeductions($employee, $undertimeHours);

        // Calculate cash advance deductions for this period
        $cashAdvanceData = CashAdvance::calculateDeductionForPeriod($employee->id, $periodStart, $periodEnd);
        $cashAdvanceDeductions = $cashAdvanceData['total'];

        // Calculate deductions using dynamic settings
        // For SSS/government deductions, use taxable income (basic + holiday + rest + taxable allowances/bonuses)
        // The grossPay already includes basic + holiday + rest + overtime
        $taxableIncomeForDeductions = $grossPay; // This includes basic + holiday + rest + overtime

        // Detect pay frequency based on period duration using dynamic pay schedule settings
        $payFrequency = \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
            \Carbon\Carbon::parse($periodStart),
            \Carbon\Carbon::parse($periodEnd)
        );

        // $deductions = $this->calculateDeductions($employee, $totalGrossPay, $taxableIncomeForDeductions, $overtimePay, $allowancesTotal, $bonusesTotal, $payFrequency, $periodStart, $periodEnd);

        $netPay = $totalGrossPay - $lateDeductions - $undertimeDeductions - $cashAdvanceDeductions;

        return [
            'basic_salary' => $basicSalary,  // Employee's base salary
            'regular_pay' => $grossPayData['basic_pay'],      // Basic pay for regular work
            'overtime_pay' => $grossPayData['overtime_pay'],
            'holiday_pay' => $grossPayData['holiday_pay'],
            'rest_day_pay' => $grossPayData['rest_day_pay'] ?? 0,
            'allowances' => $allowancesTotal,
            'allowances_details' => $allowancesData['details'],
            'bonuses' => $bonusesTotal,
            'bonuses_details' => $bonusesData['details'],
            'incentives' => $incentivesTotal,
            'incentives_details' => $incentivesData['details'],
            'gross_pay' => $totalGrossPay,
            // 'tax_deduction' => $deductions['tax'],
            // 'sss_deduction' => $deductions['sss'],
            // 'philhealth_deduction' => $deductions['philhealth'],
            // 'pagibig_deduction' => $deductions['pagibig'],
            // 'other_deductions' => $deductions['other'],
            // 'deductions_details' => $deductions['deductions_details'],
            // 'total_deductions' => $deductions['total'] + $lateDeductions + $undertimeDeductions + $cashAdvanceDeductions,
            'net_pay' => $netPay,
            'hours_worked' => $hoursWorked,
            'days_worked' => $daysWorked,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'holiday_hours' => $holidayHours,
            'rest_day_hours' => $grossPayData['rest_day_hours'] ?? 0,
            'late_hours' => $lateHours,
            'undertime_hours' => $undertimeHours,
            'late_deductions' => $lateDeductions,
            'undertime_deductions' => $undertimeDeductions,
            'cash_advance_deductions' => $cashAdvanceDeductions,
            'cash_advance_details' => $cashAdvanceData['details'],
        ];
    }

    /**
     * Calculate gross pay based on schedule type and actual time worked
     */
    private function calculateGrossPay($employee, $basicSalary, $hoursWorked, $daysWorked, $periodStart, $periodEnd)
    {
        $paySchedule = $employee->pay_schedule;

        // If no time worked, no pay (except for manual adjustments)
        if ($hoursWorked <= 0 && $daysWorked <= 0) {
            return 0;
        }

        // Calculate based on actual time worked, not full salary
        switch ($paySchedule) {
            case 'daily':
                // For daily, basic salary is daily rate
                return $basicSalary * $daysWorked;

            case 'weekly':
                // Calculate hourly rate from weekly salary and pay based on hours worked
                $hourlyRate = $basicSalary / 40; // Assuming 40 hours per week
                return $hourlyRate * $hoursWorked;

            case 'semi_monthly':
                // Calculate hourly rate from semi-monthly salary and pay based on hours worked
                $hourlyRate = $basicSalary / 86.67; // Assuming ~86.67 hours per semi-month
                return $hourlyRate * $hoursWorked;

            case 'monthly':
                // Calculate hourly rate from monthly salary and pay based on hours worked
                $hourlyRate = $basicSalary / 173.33; // Assuming ~173.33 hours per month
                return $hourlyRate * $hoursWorked;

            default:
                // Default to hourly calculation using new method
                $hourlyRate = $this->calculateHourlyRate($employee, $basicSalary, $periodStart, $periodEnd);
                return $hourlyRate * $hoursWorked;
        }
    }

    /**
     * Calculate gross pay using rate multipliers from time logs
     */
    /**
     * Calculate gross pay with detailed breakdown by pay type
     */
    private function calculateGrossPayWithRateMultipliersDetailed($employee, $basicSalary, $timeLogs, $hoursWorked, $daysWorked, $periodStart, $periodEnd)
    {
        // If no time worked and no time logs, fallback to basic calculation
        if ($timeLogs->isEmpty()) {
            $basicPay = $this->calculateGrossPay($employee, $basicSalary, $hoursWorked, $daysWorked, $periodStart, $periodEnd);
            return [
                'total_gross' => $basicPay,
                'basic_pay' => $basicPay,
                'holiday_pay' => 0,
                'rest_day_pay' => 0,
                'overtime_pay' => 0,
                'regular_hours' => $hoursWorked,
                'overtime_hours' => 0,
                'holiday_hours' => 0,
                'rest_day_hours' => 0,
                'pay_breakdown' => [],
                'overtime_breakdown' => [],
                'holiday_breakdown' => [],
                'rest_day_breakdown' => [],
            ];
        }

        // Calculate hourly rate based on pay schedule
        $hourlyRate = $this->calculateHourlyRate($employee, $basicSalary, $periodStart, $periodEnd);

        $totalGrossPay = 0;
        $basicPay = 0;
        $holidayPay = 0;
        $restDayPay = 0;
        $overtimePay = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $holidayHours = 0;
        $restDayHours = 0;

        // Detailed breakdowns
        $payBreakdown = [];
        $overtimeBreakdown = [];
        $holidayBreakdown = [];
        $restDayBreakdown = [];

        // Process each time log with its rate multiplier (exclude incomplete records)
        foreach ($timeLogs as $timeLog) {
            // Skip incomplete records - those marked as incomplete or missing time in/out (except for suspension days)
            $isSuspensionLog = in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension']);
            if ($timeLog->remarks === 'Incomplete Time Record' || (!$isSuspensionLog && (!$timeLog->time_in || !$timeLog->time_out))) {
                continue;
            }

            if (!$isSuspensionLog && $timeLog->total_hours <= 0) {
                continue;
            }

            // CRITICAL: Skip suspension pay calculation here - let breakdown methods handle it properly
            // TimeLog->calculatePayAmount() doesn't have access to suspension settings context
            if ($isSuspensionLog) {
                continue; // Don't add suspension pay to gross pay here
            }

            // Calculate pay for this time log using rate configuration
            $payAmounts = $timeLog->calculatePayAmount($hourlyRate);
            $totalGrossPay += $payAmounts['total_amount'];

            // Add to hour totals (suspension logs already skipped above)
            $regularHours += $timeLog->regular_hours ?? 0;
            $overtimeHours += $timeLog->overtime_hours ?? 0;

            // Get rate configuration for breakdown display
            $rateConfig = $timeLog->getRateConfiguration();
            $displayName = $rateConfig ? $rateConfig->display_name : 'Regular Day';

            // Categorize pay by log type
            $logType = $timeLog->log_type;
            $regularAmount = $payAmounts['regular_amount'] ?? 0;
            $overtimeAmount = $payAmounts['overtime_amount'] ?? 0;

            // Track regular pay by category
            if ($regularAmount > 0) {
                if (!isset($payBreakdown[$displayName])) {
                    $payBreakdown[$displayName] = [
                        'hours' => 0,
                        'amount' => 0,
                        'rate' => $hourlyRate * ($rateConfig ? $rateConfig->regular_rate_multiplier : 1.0),
                    ];
                }
                $payBreakdown[$displayName]['hours'] += $timeLog->regular_hours ?? 0;
                $payBreakdown[$displayName]['amount'] += $regularAmount;
            }

            // Track overtime pay by category
            if ($overtimeAmount > 0) {
                $overtimeDisplayName = $displayName . ' OT';
                if (!isset($overtimeBreakdown[$overtimeDisplayName])) {
                    $overtimeBreakdown[$overtimeDisplayName] = [
                        'hours' => 0,
                        'amount' => 0,
                        'rate' => $hourlyRate * ($rateConfig ? $rateConfig->overtime_rate_multiplier : 1.25),
                    ];
                }
                $overtimeBreakdown[$overtimeDisplayName]['hours'] += $timeLog->overtime_hours ?? 0;
                $overtimeBreakdown[$overtimeDisplayName]['amount'] += $overtimeAmount;
            }

            // All overtime pay goes to overtime_pay regardless of day type
            $overtimePay += $overtimeAmount;

            // Categorize regular pay by day type
            if (str_contains($logType, 'holiday')) {
                $holidayPay += $regularAmount;
                $holidayHours += $timeLog->regular_hours ?? 0;

                // Track holiday breakdown
                if ($regularAmount > 0) {
                    if (!isset($holidayBreakdown[$displayName])) {
                        $holidayBreakdown[$displayName] = [
                            'hours' => 0,
                            'amount' => 0,
                            'rate' => $hourlyRate * ($rateConfig ? $rateConfig->regular_rate_multiplier : 1.0),
                        ];
                    }
                    $holidayBreakdown[$displayName]['hours'] += $timeLog->regular_hours ?? 0;
                    $holidayBreakdown[$displayName]['amount'] += $regularAmount;
                }
            } elseif (str_contains($logType, 'rest_day')) {
                // Rest day work is separate category
                $restDayPay += $regularAmount;
                $restDayHours += $timeLog->regular_hours ?? 0;

                // Track rest day breakdown
                if ($regularAmount > 0) {
                    if (!isset($restDayBreakdown[$displayName])) {
                        $restDayBreakdown[$displayName] = [
                            'hours' => 0,
                            'amount' => 0,
                            'rate' => $hourlyRate * ($rateConfig ? $rateConfig->regular_rate_multiplier : 1.0),
                        ];
                    }
                    $restDayBreakdown[$displayName]['hours'] += $timeLog->regular_hours ?? 0;
                    $restDayBreakdown[$displayName]['amount'] += $regularAmount;
                }
            } else {
                // Regular workday and other types
                $basicPay += $regularAmount;
            }
        }

        return [
            'total_gross' => $totalGrossPay,
            'basic_pay' => $basicPay,
            'holiday_pay' => $holidayPay,
            'rest_day_pay' => $restDayPay,
            'overtime_pay' => $overtimePay,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'holiday_hours' => $holidayHours,
            'rest_day_hours' => $restDayHours,
            'pay_breakdown' => $payBreakdown,
            'overtime_breakdown' => $overtimeBreakdown,
            'holiday_breakdown' => $holidayBreakdown,
            'rest_day_breakdown' => $restDayBreakdown,
        ];
    }

    private function calculateGrossPayWithRateMultipliers($employee, $basicSalary, $timeLogs, $hoursWorked, $daysWorked, $periodStart, $periodEnd)
    {
        // If no time worked and no time logs, fallback to basic calculation
        if ($timeLogs->isEmpty()) {
            return $this->calculateGrossPay($employee, $basicSalary, $hoursWorked, $daysWorked, $periodStart, $periodEnd);
        }

        // Calculate hourly rate based on pay schedule
        $hourlyRate = $this->calculateHourlyRate($employee, $basicSalary);

        $totalGrossPay = 0;
        $payBreakdown = [];

        // Process each time log with its rate multiplier (exclude incomplete records)
        foreach ($timeLogs as $timeLog) {
            // Skip incomplete records - those marked as incomplete or missing time in/out (except for suspension days)
            $isSuspensionLog = in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension']);
            if ($timeLog->remarks === 'Incomplete Time Record' || (!$isSuspensionLog && (!$timeLog->time_in || !$timeLog->time_out))) {
                continue;
            }

            if (!$isSuspensionLog && $timeLog->total_hours <= 0) {
                continue;
            }

            // CRITICAL: Skip suspension pay calculation here - let breakdown methods handle it properly
            // TimeLog->calculatePayAmount() doesn't have access to suspension settings context
            if ($isSuspensionLog) {
                continue; // Don't add suspension pay to gross pay here
            }

            // Calculate pay for this time log using rate configuration
            $payAmounts = $timeLog->calculatePayAmount($hourlyRate);

            $totalGrossPay += $payAmounts['total_amount'];

            // Store breakdown for reporting
            $logTypeName = $timeLog->getRateConfiguration()->display_name ?? $timeLog->log_type;
            if (!isset($payBreakdown[$logTypeName])) {
                $payBreakdown[$logTypeName] = [
                    'regular_hours' => 0,
                    'regular_amount' => 0,
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
                    'total_amount' => 0,
                ];
            }

            $payBreakdown[$logTypeName]['regular_hours'] += $timeLog->regular_hours;
            $payBreakdown[$logTypeName]['regular_amount'] += $payAmounts['regular_amount'];
            $payBreakdown[$logTypeName]['overtime_hours'] += $timeLog->overtime_hours;
            $payBreakdown[$logTypeName]['overtime_amount'] += $payAmounts['overtime_amount'];
            $payBreakdown[$logTypeName]['total_amount'] += $payAmounts['total_amount'];
        }

        // Store pay breakdown for later use (can be saved to payroll details)
        // Removed currentPayBreakdown property assignment as it's not defined

        return $totalGrossPay;
    }

    /**
     * Calculate hourly rate based on employee's fixed_rate and rate_type
     */
    private function calculateHourlyRate($employee, $basicSalary, $periodStart = null, $periodEnd = null)
    {
        // Use fixed_rate and rate_type if available
        if ($employee->fixed_rate && $employee->fixed_rate > 0 && $employee->rate_type) {
            // Get employee's assigned time schedule total hours for calculation
            $timeSchedule = $employee->timeSchedule;
            $dailyHours = $timeSchedule ? $timeSchedule->total_hours : 8; // Default to 8 hours if no schedule

            // For working days calculation, use provided dates or current month as default
            if (!$periodStart || !$periodEnd) {
                $currentMonth = now();
                $periodStart = $currentMonth->copy()->startOfMonth();
                $periodEnd = $currentMonth->copy()->endOfMonth();
            }

            switch ($employee->rate_type) {
                case 'hourly':
                    return $employee->fixed_rate;

                case 'daily':
                    return $employee->fixed_rate / $dailyHours;

                case 'weekly':
                    $weeklyWorkingDays = $this->getWorkingDaysForRateTypeWithPeriod($employee, 'weekly', $periodStart, $periodEnd);
                    return $employee->fixed_rate / ($dailyHours * $weeklyWorkingDays);

                case 'semi_monthly':
                    $semiMonthlyWorkingDays = $this->getWorkingDaysForRateTypeWithPeriod($employee, 'semi_monthly', $periodStart, $periodEnd);
                    return $employee->fixed_rate / ($dailyHours * $semiMonthlyWorkingDays);

                case 'monthly':
                    $monthlyWorkingDays = $this->getWorkingDaysForRateTypeWithPeriod($employee, 'monthly', $periodStart, $periodEnd);
                    return $employee->fixed_rate / ($dailyHours * $monthlyWorkingDays);

                default:
                    // If rate_type is not recognized, fall back to monthly calculation
                    $monthlyWorkingDays = $this->getWorkingDaysForRateTypeWithPeriod($employee, 'monthly', $periodStart, $periodEnd);
                    return $employee->fixed_rate / ($dailyHours * $monthlyWorkingDays);
            }
        }

        // Fallback to old calculation if fixed_rate/rate_type not available
        // If employee has an explicit hourly rate, use it
        if ($employee->hourly_rate && $employee->hourly_rate > 0) {
            return $employee->hourly_rate;
        }

        // Calculate hourly rate based on pay schedule
        // switch ($employee->pay_schedule) {
        //     case 'daily':
        //         // For daily, basic salary is already daily rate, convert to hourly
        //         return $basicSalary / 8; // Assuming 8 hours per day

        //     case 'weekly':
        //         // Convert weekly salary to hourly
        //         return $basicSalary / 40; // Assuming 40 hours per week

        //     case 'semi_monthly':
        //         // Convert semi-monthly salary to hourly
        //         return $basicSalary / 86.67; // Assuming ~86.67 hours per semi-month

        //     case 'monthly':
        //         // Convert monthly salary to hourly
        //         return $basicSalary / 173.33; // Assuming ~173.33 hours per month

        //     default:
        //         // Default calculation
        //         return $basicSalary / 173.33;
        // }
    }

    /**
     * Calculate daily rate for an employee based on their fixed_rate and rate_type
     */
    private function calculateDailyRate($employee, $basicSalary, $periodStart = null, $periodEnd = null)
    {
        // Use fixed_rate and rate_type if available
        if ($employee->fixed_rate && $employee->fixed_rate > 0 && $employee->rate_type) {
            // Get employee's assigned time schedule total hours for calculation
            $timeSchedule = $employee->timeSchedule;
            $dailyHours = $timeSchedule ? $timeSchedule->total_hours : 8; // Default to 8 hours if no schedule

            // Set default period if not provided
            if (!$periodStart || !$periodEnd) {
                $currentMonth = now();
                $periodStart = $currentMonth->copy()->startOfMonth();
                $periodEnd = $currentMonth->copy()->endOfMonth();
            }

            // Calculate working days based on rate type - each rate type has its own calculation period
            switch ($employee->rate_type) {
                case 'hourly':
                    return $employee->fixed_rate * $dailyHours;

                case 'daily':
                    return $employee->fixed_rate;

                case 'weekly':
                    // For weekly rate: calculate working days in a typical week
                    $weekStart = \Carbon\Carbon::parse($periodStart)->startOfWeek();
                    $weekEnd = \Carbon\Carbon::parse($periodStart)->endOfWeek();
                    $weeklyWorkingDays = $employee->getWorkingDaysForPeriod($weekStart, $weekEnd);
                    return $weeklyWorkingDays > 0 ? ($employee->fixed_rate / $weeklyWorkingDays) : 0;

                case 'semi_monthly':
                    // For semi-monthly rate: determine which cutoff period and count working days in that specific period
                    $payrollStartDay = \Carbon\Carbon::parse($periodStart)->day;
                    $currentMonth = \Carbon\Carbon::parse($periodStart);

                    if ($payrollStartDay <= 15) {
                        // First cutoff (1st - 15th): count working days in this period
                        $cutoffStart = $currentMonth->copy()->setDay(1);
                        $cutoffEnd = $currentMonth->copy()->setDay(15);
                    } else {
                        // Second cutoff (16th - EOD): count working days in this period  
                        $cutoffStart = $currentMonth->copy()->setDay(16);
                        $cutoffEnd = $currentMonth->copy()->endOfMonth();
                    }

                    $semiMonthlyWorkingDays = $employee->getWorkingDaysForPeriod($cutoffStart, $cutoffEnd);
                    return $semiMonthlyWorkingDays > 0 ? ($employee->fixed_rate / $semiMonthlyWorkingDays) : 0;

                case 'monthly':
                    // For monthly rate: calculate working days in the FULL month containing the payroll period
                    $monthStart = \Carbon\Carbon::parse($periodStart)->startOfMonth();
                    $monthEnd = \Carbon\Carbon::parse($periodStart)->endOfMonth();
                    $monthlyWorkingDays = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);
                    return $monthlyWorkingDays > 0 ? ($employee->fixed_rate / $monthlyWorkingDays) : 0;

                default:
                    // If rate_type is not recognized, fall back to monthly calculation
                    $monthStart = \Carbon\Carbon::parse($periodStart)->startOfMonth();
                    $monthEnd = \Carbon\Carbon::parse($periodStart)->endOfMonth();
                    $monthlyWorkingDays = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);
                    return $monthlyWorkingDays > 0 ? ($employee->fixed_rate / $monthlyWorkingDays) : 0;
            }
        }

        // Fallback to dynamic calculation based on basic salary if fixed_rate/rate_type not available
        if ($employee->daily_rate && $employee->daily_rate > 0) {
            return $employee->daily_rate;
        }

        // Calculate daily rate from basic salary using dynamic working days for full month
        if (!$periodStart || !$periodEnd) {
            $currentMonth = now();
            $periodStart = $currentMonth->copy()->startOfMonth();
            $periodEnd = $currentMonth->copy()->endOfMonth();
        }

        $monthStart = \Carbon\Carbon::parse($periodStart)->startOfMonth();
        $monthEnd = \Carbon\Carbon::parse($periodStart)->endOfMonth();
        $monthlyWorkingDays = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);
        return $monthlyWorkingDays > 0 ? ($basicSalary / $monthlyWorkingDays) : 0;
    }

    /**
     * Calculate deductions for an employee using dynamic settings
     */
    private function calculateDeductions($employee, $grossPay, $basicPay = null, $overtimePay = 0, $allowances = 0, $bonuses = 0, $payFrequency = 'semi_monthly', $periodStart = null, $periodEnd = null)
    {
        $basicPay = $basicPay ?? $grossPay;
        $deductions = [];
        $deductionDetails = [];
        $total = 0;

        // Get active deduction settings that apply to this employee's benefit status
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->where('type', 'government')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        $governmentTotal = 0;

        // Calculate government deductions (SSS, PhilHealth, Pag-IBIG) with distribution logic
        foreach ($deductionSettings as $setting) {
            if ($setting->tax_table_type !== 'withholding_tax') {
                $amount = $setting->calculateDeduction($basicPay, $overtimePay, $bonuses, $allowances, $grossPay, null, null, $employee->basic_salary, $payFrequency);

                // Apply deduction distribution logic if period dates are provided
                if ($periodStart && $periodEnd && $amount > 0) {
                    $originalAmount = $amount;
                    $amount = $setting->calculateDistributedAmount(
                        $originalAmount,
                        $periodStart,
                        $periodEnd,
                        $employee->pay_schedule ?? $payFrequency
                    );
                }

                if ($amount > 0) {
                    $deductions[$setting->code] = $amount;
                    $total += $amount;
                    $governmentTotal += $amount;

                    // Add to detailed breakdown for snapshot
                    $deductionDetails[] = [
                        'name' => $setting->name,
                        'code' => $setting->code,
                        'amount' => $amount,
                        'type' => $setting->type,
                        'calculation_type' => $setting->calculation_type,
                        'pay_basis' => $this->getPayBasisName($setting),
                        'pay_basis_amount' => $this->getPayBasisAmount($setting, $basicPay, $overtimePay, $bonuses, $allowances, $grossPay)
                    ];
                }
            }
        }

        // Calculate taxable income (gross pay minus government deductions)
        $taxableIncome = $grossPay - $governmentTotal;

        // Calculate withholding tax based on taxable income
        $taxSettings = \App\Models\DeductionTaxSetting::active()
            ->where('type', 'government')
            ->where('tax_table_type', 'withholding_tax')
            ->forBenefitStatus($employee->benefits_status)
            ->get();

        foreach ($taxSettings as $setting) {
            $amount = $setting->calculateDeduction($basicPay, $overtimePay, $bonuses, $allowances, $grossPay, $taxableIncome, null, $employee->basic_salary, $payFrequency);

            // Apply deduction distribution logic for withholding tax if period dates are provided
            if ($periodStart && $periodEnd && $amount > 0) {
                $originalAmount = $amount;
                $amount = $setting->calculateDistributedAmount(
                    $originalAmount,
                    $periodStart,
                    $periodEnd,
                    $employee->pay_schedule ?? $payFrequency
                );
            }

            if ($amount > 0) {
                $deductions[$setting->code] = $amount;
                $total += $amount;

                // Add to detailed breakdown for snapshot
                $deductionDetails[] = [
                    'name' => $setting->name,
                    'code' => $setting->code,
                    'amount' => $amount,
                    'type' => $setting->type,
                    'calculation_type' => $setting->calculation_type,
                    'pay_basis' => 'taxable_income', // Withholding tax uses taxable income
                    'pay_basis_amount' => $taxableIncome
                ];
            }
        }

        // Get other custom deductions
        $customDeductions = \App\Models\DeductionSetting::where('is_active', true)
            ->where('type', 'custom')
            ->get();

        foreach ($customDeductions as $setting) {
            $amount = $this->calculateCustomDeduction($setting, $employee, $basicPay, $grossPay);

            if ($amount > 0) {
                $deductions[$setting->code] = $amount;
                $total += $amount;

                // Add to detailed breakdown for snapshot
                $deductionDetails[] = [
                    'name' => $setting->name,
                    'code' => $setting->code,
                    'amount' => $amount,
                    'type' => $setting->type,
                    'calculation_type' => 'custom',
                    'pay_basis' => 'custom',
                    'pay_basis_amount' => $amount
                ];
            }
        }

        // Return standard structure for backward compatibility
        return [
            'sss' => $deductions['sss'] ?? 0,
            'philhealth' => $deductions['philhealth'] ?? 0,
            'pagibig' => $deductions['pagibig'] ?? 0,
            'tax' => $deductions['withholding_tax'] ?? 0,
            'other' => array_sum(array_filter($deductions, function ($key) {
                return !in_array($key, ['sss', 'philhealth', 'pagibig', 'withholding_tax']);
            }, ARRAY_FILTER_USE_KEY)),
            'total' => $total,
            'details' => $deductions,
            'deductions_details' => $deductionDetails // Add this for snapshot compatibility
        ];
    }

    /**
     * Get pay basis name for deduction setting
     */
    private function getPayBasisName($setting)
    {
        if ($setting->apply_to_basic_pay) return 'basic_pay';
        if ($setting->apply_to_gross_pay) return 'totalgross';
        if ($setting->apply_to_taxable_income) return 'taxable_income';
        if ($setting->apply_to_net_pay) return 'net_pay';
        if ($setting->apply_to_monthly_basic_salary) return 'monthly_basic_salary';

        return 'totalgross'; // default
    }

    /**
     * Get pay basis amount for deduction setting
     */
    private function getPayBasisAmount($setting, $basicPay, $overtimePay, $bonuses, $allowances, $grossPay)
    {
        if ($setting->apply_to_basic_pay) return $basicPay;
        if ($setting->apply_to_gross_pay) return $grossPay; // $grossPay here is the total gross pay from calculateDeductions
        if ($setting->apply_to_taxable_income) return $grossPay; // Will be calculated later
        if ($setting->apply_to_net_pay) return $grossPay; // Will be calculated later
        if ($setting->apply_to_monthly_basic_salary) return $grossPay; // Will be calculated later

        return $grossPay; // default
    }

    /**
     * Calculate custom deduction amount
     */
    private function calculateCustomDeduction($setting, $employee, $basicPay, $grossPay)
    {
        switch ($setting->calculation_type) {
            case 'percentage':
                return ($grossPay * $setting->rate) / 100;

            case 'fixed':
                return $setting->fixed_amount;

            case 'tiered':
                // Implement tiered calculation based on salary thresholds
                if ($setting->salary_threshold && $grossPay >= $setting->salary_threshold) {
                    return $setting->fixed_amount;
                }
                return 0;

            case 'table_based':
                // Implement table-based calculation using rate_table
                if ($setting->rate_table) {
                    foreach ($setting->rate_table as $tier) {
                        if ($grossPay >= $tier['min'] && $grossPay <= $tier['max']) {
                            if (isset($tier['rate'])) {
                                return ($grossPay * $tier['rate']) / 100;
                            } elseif (isset($tier['amount'])) {
                                return $tier['amount'];
                            }
                        }
                    }
                }
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Calculate allowances for an employee using dynamic settings
     */
    private function calculateAllowances($employee, $basicPay, $daysWorked = 0, $hoursWorked = 0, $periodStart = null, $periodEnd = null)
    {
        $total = 0;
        $details = [];

        // Get active allowance settings that apply to this employee's benefit status
        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($allowanceSettings as $setting) {
            $amount = $this->calculateAllowanceBonusAmount($setting, $employee, $basicPay, $daysWorked, $hoursWorked);

            // Apply distribution logic if amount > 0
            if ($amount > 0) {
                // Apply distribution logic using provided period dates or fallback to route
                if ($periodStart && $periodEnd) {
                    $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                        $periodStart,
                        $periodEnd
                    );

                    // Apply distribution logic using the model method
                    $amount = $setting->calculateDistributedAmount(
                        $amount,
                        $periodStart,
                        $periodEnd,
                        $employeePaySchedule
                    );
                } else {
                    // Fallback to route-based payroll context for backward compatibility
                    $payroll = request()->route('payroll'); // Get current payroll from route
                    if ($payroll && $payroll instanceof \App\Models\Payroll) {
                        $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                            $payroll->period_start,
                            $payroll->period_end
                        );

                        // Apply distribution logic using the model method
                        $amount = $setting->calculateDistributedAmount(
                            $amount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employeePaySchedule
                        );
                    }
                }

                if ($amount > 0) {
                    $details[$setting->code] = [
                        'name' => $setting->name,
                        'amount' => $amount,
                        'is_taxable' => $setting->is_taxable
                    ];
                    $total += $amount;
                }
            }
        }

        return [
            'total' => $total,
            'details' => $details
        ];
    }

    /**
     * Calculate bonuses for an employee using dynamic settings
     */
    private function calculateBonuses($employee, $basicPay, $daysWorked = 0, $hoursWorked = 0, $periodStart = null, $periodEnd = null)
    {
        $total = 0;
        $details = [];

        // Get active bonus settings that apply to this employee's benefit status
        $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'bonus')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($bonusSettings as $setting) {
            $amount = $this->calculateAllowanceBonusAmount($setting, $employee, $basicPay, $daysWorked, $hoursWorked);

            // Apply distribution logic if amount > 0
            if ($amount > 0) {
                // Apply distribution logic using provided period dates or fallback to route
                if ($periodStart && $periodEnd) {
                    $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                        $periodStart,
                        $periodEnd
                    );

                    // Apply distribution logic using the model method
                    $amount = $setting->calculateDistributedAmount(
                        $amount,
                        $periodStart,
                        $periodEnd,
                        $employeePaySchedule
                    );
                } else {
                    // Fallback to route-based payroll context for backward compatibility
                    $payroll = request()->route('payroll'); // Get current payroll from route
                    if ($payroll && $payroll instanceof \App\Models\Payroll) {
                        $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                            $payroll->period_start,
                            $payroll->period_end
                        );

                        // Apply distribution logic using the model method
                        $amount = $setting->calculateDistributedAmount(
                            $amount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employeePaySchedule
                        );
                    }
                }

                if ($amount > 0) {
                    $details[$setting->code] = [
                        'name' => $setting->name,
                        'amount' => $amount,
                        'is_taxable' => $setting->is_taxable
                    ];
                    $total += $amount;
                }
            }
        }

        return [
            'total' => $total,
            'details' => $details
        ];
    }

    /**
     * Calculate incentives for an employee using dynamic settings with perfect attendance check
     */
    private function calculateIncentives($employee, $basicPay, $daysWorked = 0, $hoursWorked = 0, $periodStart = null, $periodEnd = null)
    {
        $total = 0;
        $details = [];

        // Get active incentive settings that apply to this employee's benefit status
        $incentiveSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'incentives')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($incentiveSettings as $setting) {
            // Check if this incentive requires perfect attendance
            if ($setting->requires_perfect_attendance && $periodStart && $periodEnd) {
                // Check if employee has perfect attendance for this period
                if (!$setting->hasPerfectAttendance($employee, $periodStart, $periodEnd)) {
                    continue; // Skip this incentive if perfect attendance not met
                }
            }

            $amount = $this->calculateAllowanceBonusAmount($setting, $employee, $basicPay, $daysWorked, $hoursWorked);

            // Apply distribution logic if amount > 0
            if ($amount > 0) {
                // Apply distribution logic using period dates from current payroll context
                // We need payroll period for distribution calculation
                $payroll = request()->route('payroll'); // Get current payroll from route
                if ($payroll && $payroll instanceof \App\Models\Payroll) {
                    $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                        $payroll->period_start,
                        $payroll->period_end
                    );

                    // Apply distribution logic using the model method
                    $amount = $setting->calculateDistributedAmount(
                        $amount,
                        $payroll->period_start,
                        $payroll->period_end,
                        $employeePaySchedule
                    );
                }

                if ($amount > 0) {
                    $details[$setting->code] = [
                        'name' => $setting->name,
                        'amount' => $amount,
                        'is_taxable' => $setting->is_taxable
                    ];
                    $total += $amount;
                }
            }
        }

        return [
            'total' => $total,
            'details' => $details
        ];
    }

    /**
     * Calculate allowance/bonus amount based on setting configuration
     */
    private function calculateAllowanceBonusAmount($setting, $employee, $basicPay, $daysWorked, $hoursWorked)
    {
        $amount = 0;

        switch ($setting->calculation_type) {
            case 'percentage':
                $amount = ($basicPay * $setting->rate_percentage) / 100;
                break;

            case 'fixed_amount':
                $amount = $setting->fixed_amount;

                // Apply frequency multiplier
                if ($setting->frequency === 'daily' && $daysWorked > 0) {
                    $maxDays = $setting->max_days_per_period ?? $daysWorked;
                    $amount = $amount * min($daysWorked, $maxDays);
                }
                break;

            case 'daily_rate_multiplier':
                if ($employee->daily_rate) {
                    $amount = $employee->daily_rate * ($setting->multiplier ?? 1);

                    if ($setting->frequency === 'daily' && $daysWorked > 0) {
                        $maxDays = $setting->max_days_per_period ?? $daysWorked;
                        $amount = $amount * min($daysWorked, $maxDays);
                    }
                }
                break;

            case 'automatic':
                // Use the model's calculateAmount method for automatic calculation
                $amount = $setting->calculateAmount($basicPay, $employee->daily_rate, $daysWorked, $employee);
                break;
        }

        // Apply minimum and maximum constraints
        if ($setting->minimum_amount && $amount < $setting->minimum_amount) {
            $amount = $setting->minimum_amount;
        }

        if ($setting->maximum_amount && $amount > $setting->maximum_amount) {
            $amount = $setting->maximum_amount;
        }

        return $amount;
    }

    /**
     * Calculate simplified tax (placeholder) - kept for backward compatibility
     */
    private function calculateTax($grossPay)
    {
        // Very simplified tax calculation
        if ($grossPay <= 20833) {
            return 0; // Tax exempt
        }

        return ($grossPay - 20833) * 0.20; // 20% on excess
    }

    /**
     * Store automation payroll - now redirects to draft list instead of creating payrolls
     */
    public function automationStore(Request $request)
    {
        $this->authorize('create payrolls');

        $validated = $request->validate([
            'selected_period' => 'required|string',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        // Extract schedule from selected_period (format: "schedule_code|start_date|end_date")
        $periodParts = explode('|', $validated['selected_period']);
        $scheduleCode = $periodParts[0] ?? 'semi_monthly';

        // Redirect to draft list instead of creating payrolls
        return redirect()->route('payrolls.automation.list', $scheduleCode)
            ->with('success', 'Viewing draft payrolls. Review and process individual employees as needed.');
    }

    /**
     * Submit batch payrolls from automation preview
     */
    public function automationSubmit(Request $request, $schedule)
    {
        $this->authorize('create payrolls');

        // Find the pay schedule by name
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        if (!$paySchedule) {
            return redirect()->back()
                ->with('error', 'Pay schedule not found or inactive.');
        }

        // Get the current period for this schedule
        try {
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } catch (\Exception $e) {
            Log::error('Failed to calculate current period for batch submission', [
                'schedule' => $schedule,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()
                ->with('error', 'Unable to determine current pay period.');
        }

        // Get all active employees for this schedule
        $employees = Employee::where('pay_schedule', $paySchedule->name)
            ->whereNull('terminated_at')
            ->with(['timeSchedule', 'daySchedule'])
            ->get();

        if ($employees->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No active employees found for this pay schedule.');
        }

        DB::beginTransaction();
        try {
            $createdPayrolls = [];

            foreach ($employees as $employee) {
                // Check if employee already has a payroll for this period
                $existingPayroll = Payroll::where('employee_id', $employee->id)
                    ->where('period_start', $currentPeriod['start'])
                    ->where('period_end', $currentPeriod['end'])
                    ->first();

                if ($existingPayroll) {
                    continue; // Skip if payroll already exists
                }

                // Calculate payroll for this employee
                $payrollCalculation = $this->calculateEmployeePayrollForPeriod($employee, $currentPeriod['start'], $currentPeriod['end']);

                // Create the payroll record
                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'payroll_number' => Payroll::generatePayrollNumber('automated'),
                    'period_start' => $currentPeriod['start'],
                    'period_end' => $currentPeriod['end'],
                    'pay_date' => $currentPeriod['pay_date'],
                    'status' => 'draft',
                    'payroll_type' => 'automated',
                    'total_gross' => $payrollCalculation['gross_pay'] ?? 0,
                    'total_deductions' => $payrollCalculation['total_deductions'] ?? 0,
                    'total_net' => $payrollCalculation['net_pay'] ?? 0,
                    'created_by' => Auth::id(),
                    'pay_schedule' => $paySchedule->name,
                ]);

                // Create payroll detail
                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'basic_pay' => $payrollCalculation['basic_pay'] ?? 0,
                    'overtime_pay' => $payrollCalculation['overtime_pay'] ?? 0,
                    'holiday_pay' => $payrollCalculation['holiday_pay'] ?? 0,
                    'night_differential' => $payrollCalculation['night_differential'] ?? 0,
                    'allowances' => $payrollCalculation['allowances'] ?? 0,
                    'bonuses' => $payrollCalculation['bonuses'] ?? 0,
                    'gross_pay' => $payrollCalculation['gross_pay'] ?? 0,
                    'sss_contribution' => $payrollCalculation['sss_deduction'] ?? 0,
                    'philhealth_contribution' => $payrollCalculation['philhealth_deduction'] ?? 0,
                    'pagibig_contribution' => $payrollCalculation['pagibig_deduction'] ?? 0,
                    'withholding_tax' => $payrollCalculation['withholding_tax'] ?? 0,
                    'other_deductions' => $payrollCalculation['other_deductions'] ?? 0,
                    'total_deductions' => $payrollCalculation['total_deductions'] ?? 0,
                    'net_pay' => $payrollCalculation['net_pay'] ?? 0,
                ]);

                $createdPayrolls[] = $payroll;
            }

            DB::commit();

            if (empty($createdPayrolls)) {
                return redirect()->route('payrolls.automation.list', $schedule)
                    ->with('warning', 'All employees already have payrolls for this period.');
            }

            return redirect()->route('payrolls.automation.list', $schedule)
                ->with('success', 'Successfully created ' . count($createdPayrolls) . ' payroll(s) for processing.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create batch payrolls', [
                'schedule' => $schedule,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create payrolls: ' . $e->getMessage());
        }
    }

    // Manual payroll functionality removed

    /**
     * Process payroll creation (shared logic)
     */
    private function processPayrollCreation($validated, $type = 'regular')
    {
        // Parse the selected period data
        $periodData = json_decode(base64_decode($validated['selected_period']), true);

        if (!$periodData) {
            return back()->withErrors(['selected_period' => 'Invalid period selection.'])->withInput();
        }

        // Validate that selected employees match the pay schedule
        $selectedEmployees = Employee::whereIn('id', $validated['employee_ids'])->get();
        $invalidEmployees = $selectedEmployees->where('pay_schedule', '!=', $periodData['pay_schedule']);

        if ($invalidEmployees->count() > 0) {
            return back()->withErrors([
                'employee_ids' => 'All selected employees must have the same pay schedule as the selected period.'
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Create payroll 
            $payroll = Payroll::create([
                'payroll_number' => Payroll::generatePayrollNumber($type),
                'period_start' => $periodData['period_start'],
                'period_end' => $periodData['period_end'],
                'pay_date' => $periodData['pay_date'],
                'payroll_type' => $type,
                'pay_schedule' => $periodData['pay_schedule'],
                'description' => ucfirst($type) . ' payroll for ' . $periodData['period_display'],
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $processedEmployees = 0;

            // Create payroll details for each employee
            foreach ($validated['employee_ids'] as $employeeId) {
                try {
                    $employee = Employee::find($employeeId);

                    if (!$employee) {
                        Log::warning("Employee with ID {$employeeId} not found");
                        continue;
                    }

                    // Calculate payroll details
                    $payrollDetail = $this->calculateEmployeePayroll($employee, $payroll);

                    $totalGross += $payrollDetail->gross_pay;
                    $totalDeductions += $payrollDetail->total_deductions;
                    $totalNet += $payrollDetail->net_pay;
                    $processedEmployees++;
                } catch (\Exception $e) {
                    Log::error("Failed to process employee {$employeeId}: " . $e->getMessage());
                    continue;
                }
            }

            if ($processedEmployees === 0) {
                throw new \Exception('No employees could be processed for payroll.');
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', "Payroll created successfully! {$processedEmployees} employees processed.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payroll: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create payroll: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified payroll.
     */
    public function show($payroll)
    {
        $this->authorize('view payrolls');

        // Handle direct employee ID access - redirect to unified automation view
        if (is_numeric($payroll) && !Payroll::where('id', $payroll)->exists()) {
            // This is likely an employee ID, redirect to unified automation view
            $employee = Employee::find($payroll);
            if ($employee) {
                return redirect()->route('payrolls.automation.show', [
                    'schedule' => $employee->pay_schedule,
                    'id' => $payroll
                ]);
            }
        }

        // Handle regular payroll
        if (!($payroll instanceof Payroll)) {
            $payroll = Payroll::findOrFail($payroll);
        }

        // For automated payrolls with single employee, redirect to new URL format
        if ($payroll->payroll_type === 'automated' && $payroll->payrollDetails->count() === 1) {
            $employeeId = $payroll->payrollDetails->first()->employee_id;
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $payroll->pay_schedule,
                'id' => $employeeId
            ]);
        }

        // Auto-recalculate if needed (for draft payrolls)
        $this->autoRecalculateIfNeeded($payroll);

        $payroll->load([
            'payrollDetails.employee.user',
            'payrollDetails.employee.department',
            'payrollDetails.employee.position',
            'payrollDetails.employee.daySchedule',
            'payrollDetails.employee.timeSchedule',
            'creator',
            'approver'
        ]);

        // Get DTR data for all employees in the payroll period
        $employeeIds = $payroll->payrollDetails->pluck('employee_id');

        // Create array of all dates in the payroll period
        $startDate = \Carbon\Carbon::parse($payroll->period_start);
        $endDate = \Carbon\Carbon::parse($payroll->period_end);
        $periodDates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $periodDates[] = $date->format('Y-m-d');
        }

        // Get all time logs for this payroll period
        $timeLogs = TimeLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->orderBy('log_date')
            ->get()
            ->groupBy(['employee_id', function ($item) {
                return \Carbon\Carbon::parse($item->log_date)->format('Y-m-d');
            }]);

        // Organize DTR data by employee and date
        $dtrData = [];
        $timeBreakdowns = []; // New: detailed time breakdown by type

        foreach ($payroll->payrollDetails as $detail) {
            $employeeTimeLogs = $timeLogs->get($detail->employee_id, collect());
            $employeeDtr = [];

            // Initialize breakdown tracking
            $employeeBreakdown = [];

            foreach ($periodDates as $date) {
                $timeLog = $employeeTimeLogs->get($date, collect())->first();

                // For ALL payrolls, add dynamic calculation to time log object for DTR display
                // This ensures DTR Summary always shows the correct times with grace periods applied
                if ($timeLog && $timeLog->time_in && $timeLog->time_out && $timeLog->remarks !== 'Incomplete Time Record') {
                    $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                    $timeLog->dynamic_regular_hours = $dynamicCalculation['regular_hours'];
                    $timeLog->dynamic_overtime_hours = $dynamicCalculation['overtime_hours'];
                    $timeLog->dynamic_regular_overtime_hours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
                    $timeLog->dynamic_night_diff_overtime_hours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                    $timeLog->dynamic_total_hours = $dynamicCalculation['total_hours'];

                    // Debug logging for processing payrolls
                    if ($payroll->status === 'processing') {
                        Log::info("Setting dynamic properties for processing payroll", [
                            'employee_id' => $detail->employee_id,
                            'date' => $date,
                            'stored_regular_hours' => $timeLog->regular_hours,
                            'dynamic_regular_hours' => $timeLog->dynamic_regular_hours,
                            'time_in' => $timeLog->time_in,
                            'time_out' => $timeLog->time_out
                        ]);
                    }
                }

                $employeeDtr[$date] = $timeLog;

                // Track time breakdown by type (exclude incomplete records, but include suspension days even without time data)
                if ($timeLog && !($timeLog->remarks === 'Incomplete Time Record' || ((!$timeLog->time_in || !$timeLog->time_out) && !in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension'])))) {
                    $logType = $timeLog->log_type;
                    if (!isset($employeeBreakdown[$logType])) {
                        $employeeBreakdown[$logType] = [
                            'regular_hours' => 0,
                            'overtime_hours' => 0,
                            'regular_overtime_hours' => 0,
                            'night_diff_overtime_hours' => 0,
                            'night_diff_regular_hours' => 0, // ADD: Missing night differential regular hours
                            'total_hours' => 0,
                            'days_count' => 0,
                            'days' => 0, // NEW: Track suspension days
                            'suspension_settings' => [], // NEW: Store suspension configurations
                            'actual_time_log_hours' => 0, // NEW: For partial suspensions
                            'display_name' => '',
                            'rate_config' => null
                        ];
                    }

                    // Always calculate dynamically using current grace periods for consistency
                    // This ensures the breakdown matches what's shown in DTR and used in calculations
                    $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                    $regularHours = $dynamicCalculation['regular_hours'];
                    $overtimeHours = $dynamicCalculation['overtime_hours'];
                    $regularOvertimeHours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
                    $nightDiffOvertimeHours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                    $nightDiffRegularHours = $dynamicCalculation['night_diff_regular_hours'] ?? 0; // ADD: Extract night diff regular hours
                    $totalHours = $dynamicCalculation['total_hours'];

                    $employeeBreakdown[$logType]['regular_hours'] += $regularHours;
                    $employeeBreakdown[$logType]['overtime_hours'] += $overtimeHours;
                    $employeeBreakdown[$logType]['regular_overtime_hours'] += $regularOvertimeHours;
                    $employeeBreakdown[$logType]['night_diff_overtime_hours'] += $nightDiffOvertimeHours;
                    $employeeBreakdown[$logType]['night_diff_regular_hours'] += $nightDiffRegularHours; // ADD: Store night diff regular hours
                    $employeeBreakdown[$logType]['total_hours'] += $totalHours;
                    $employeeBreakdown[$logType]['days_count']++;

                    // NEW: Handle suspension specific data
                    if (in_array($logType, ['suspension', 'full_day_suspension', 'partial_suspension'])) {
                        $employeeBreakdown[$logType]['days']++;

                        // Get suspension settings for this date
                        $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $timeLog->log_date)
                            ->where('date_to', '>=', $timeLog->log_date)
                            ->where('status', 'active')
                            ->first();

                        if ($suspensionSetting) {
                            $employeeBreakdown[$logType]['suspension_settings'][$timeLog->log_date->format('Y-m-d')] = [
                                'is_paid' => $suspensionSetting->is_paid,
                                'pay_rule' => $suspensionSetting->pay_rule,
                                'pay_applicable_to' => $suspensionSetting->pay_applicable_to,
                                'type' => $suspensionSetting->type
                            ];

                            // For partial suspensions, track actual worked hours before suspension starts
                            if ($suspensionSetting->type === 'partial_suspension' && $totalHours > 0) {
                                $employeeBreakdown[$logType]['actual_time_log_hours'] += $totalHours;
                            }
                        }
                    }

                    // Get rate configuration for this type
                    $rateConfig = $timeLog->getRateConfiguration();
                    if ($rateConfig) {
                        $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                        $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                    }
                }
            }

            $dtrData[$detail->employee_id] = $employeeDtr;
            $timeBreakdowns[$detail->employee_id] = $employeeBreakdown;
        }

        // Determine if payroll uses dynamic calculations (needed for breakdown logic)
        $isDynamic = $payroll->isDynamic();

        // Calculate separate basic pay and holiday pay for each employee
        $payBreakdownByEmployee = [];
        foreach ($payroll->payrollDetails as $detail) {
            // For processing/approved/locked payrolls, ALWAYS use snapshot data if available
            if (in_array($payroll->status, ['processing', 'approved', 'locked'])) {
                $snapshot = $payroll->snapshots()->where('employee_id', $detail->employee_id)->first();
                if ($snapshot) {
                    // Check if detailed pay breakdown is available in settings_snapshot
                    $settingsSnapshot = is_string($snapshot->settings_snapshot)
                        ? json_decode($snapshot->settings_snapshot, true)
                        : $snapshot->settings_snapshot;

                    if (isset($settingsSnapshot['pay_breakdown'])) {
                        // Use detailed pay breakdown from snapshot
                        $payBreakdown = $settingsSnapshot['pay_breakdown'];
                        $payBreakdownByEmployee[$detail->employee_id] = [
                            'basic_pay' => $payBreakdown['basic_pay'] ?? 0,
                            'holiday_pay' => $payBreakdown['holiday_pay'] ?? 0,
                            'rest_day_pay' => $payBreakdown['rest_day_pay'] ?? 0,
                            'overtime_pay' => $payBreakdown['overtime_pay'] ?? 0,
                        ];
                    } else {
                        // Fallback to individual snapshot fields
                        $payBreakdownByEmployee[$detail->employee_id] = [
                            'basic_pay' => $snapshot->regular_pay ?? 0,
                            'holiday_pay' => $snapshot->holiday_pay ?? 0,
                            'rest_day_pay' => 0, // Not available in old snapshots
                            'overtime_pay' => $snapshot->overtime_pay ?? 0,
                        ];
                    }

                    // Log for debugging
                    Log::info("Using snapshot pay breakdown for employee {$detail->employee_id}", [
                        'basic_pay' => $payBreakdownByEmployee[$detail->employee_id]['basic_pay'],
                        'holiday_pay' => $payBreakdownByEmployee[$detail->employee_id]['holiday_pay'],
                        'rest_day_pay' => $payBreakdownByEmployee[$detail->employee_id]['rest_day_pay'],
                        'overtime_pay' => $payBreakdownByEmployee[$detail->employee_id]['overtime_pay'],
                        'snapshot_id' => $snapshot->id,
                        'payroll_status' => $payroll->status,
                        'employee_name' => $detail->employee->first_name . ' ' . $detail->employee->last_name
                    ]);

                    // CRITICAL: Force continue to skip dynamic calculation for processing payrolls
                    continue;
                }
            }

            // For draft payrolls or when no snapshot available, calculate dynamically
            $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
            $hourlyRate = $this->calculateHourlyRate($detail->employee, $detail->employee->basic_salary ?? 0);

            $basicPay = 0; // Regular workday pay only
            $holidayPay = 0; // All holiday-related pay
            $restPay = 0; // Rest day pay
            $overtimePay = 0; // Overtime pay

            foreach ($employeeBreakdown as $logType => $breakdown) {
                $rateConfig = $breakdown['rate_config'];
                if (!$rateConfig) continue;

                // Calculate pay amounts using rate multipliers
                $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;

                $regularPayAmount = $breakdown['regular_hours'] * $hourlyRate * $regularMultiplier;

                // Calculate overtime pay with night differential breakdown
                $overtimePayAmount = 0;
                $regularOvertimeHours = $breakdown['regular_overtime_hours'] ?? 0;
                $nightDiffOvertimeHours = $breakdown['night_diff_overtime_hours'] ?? 0;

                if ($regularOvertimeHours > 0 || $nightDiffOvertimeHours > 0) {
                    // Use breakdown calculation

                    // Regular overtime pay
                    if ($regularOvertimeHours > 0) {
                        $overtimePayAmount += $regularOvertimeHours * $hourlyRate * $overtimeMultiplier;
                    }

                    // Night differential overtime pay (overtime rate + night differential bonus)
                    if ($nightDiffOvertimeHours > 0) {
                        // Get night differential setting
                        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                        $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                        // Combined rate: base overtime rate + night differential bonus
                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                        $overtimePayAmount += $nightDiffOvertimeHours * $hourlyRate * $combinedMultiplier;
                    }
                } else {
                    // Fallback to simple calculation if no breakdown available
                    $overtimePayAmount = $breakdown['overtime_hours'] * $hourlyRate * $overtimeMultiplier;
                }

                if ($logType === 'regular_workday') {
                    $basicPay += $regularPayAmount; // Only regular pay to basic pay
                    $overtimePay += $overtimePayAmount; // Overtime pay separate
                } elseif ($logType === 'suspension') {
                    // Legacy suspension handling (should not happen with new logic)
                    $basicPay += $regularPayAmount; // Suspension regular pay to basic pay
                    $overtimePay += $overtimePayAmount; // Overtime pay separate
                } elseif (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                    // New suspension types using rate configurations
                    $basicPay += $regularPayAmount; // Suspension pay goes to basic pay
                    $overtimePay += $overtimePayAmount; // Overtime pay separate
                } elseif ($logType === 'rest_day') {
                    $restPay += ($regularPayAmount + $overtimePayAmount); // Rest day pay includes both
                } elseif (in_array($logType, ['special_holiday', 'regular_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                    $holidayPay += ($regularPayAmount + $overtimePayAmount); // Holiday pay includes both
                }
            }

            $payBreakdownByEmployee[$detail->employee_id] = [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_day_pay' => $restPay,
                'overtime_pay' => $overtimePay,
            ];
        }

        // Load current dynamic settings for display
        $allowanceSettings = collect();
        $bonusSettings = collect();
        $incentiveSettings = collect();
        $deductionSettings = collect();

        if ($isDynamic) {
            // Get current active settings for draft payrolls
            $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'allowance')
                ->orderBy('sort_order')
                ->get();
            $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'bonus')
                ->orderBy('sort_order')
                ->get();
            $incentiveSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'incentives')
                ->orderBy('sort_order')
                ->get();
            $deductionSettings = \App\Models\DeductionTaxSetting::active()
                ->orderBy('sort_order')
                ->get();
        } else {
            // For processing/approved payrolls, load and use snapshot data
            $snapshots = $payroll->snapshots()->get();

            if ($snapshots->isNotEmpty()) {
                // Update payroll details with snapshot values to ensure consistency
                foreach ($payroll->payrollDetails as $detail) {
                    $snapshot = $snapshots->where('employee_id', $detail->employee_id)->first();
                    if ($snapshot) {
                        // Override detail values with snapshot values for display consistency
                        $detail->basic_salary = $snapshot->basic_salary;
                        $detail->daily_rate = $snapshot->daily_rate;
                        $detail->hourly_rate = $snapshot->hourly_rate;
                        $detail->days_worked = $snapshot->days_worked;
                        $detail->regular_hours = $snapshot->regular_hours;
                        $detail->overtime_hours = $snapshot->overtime_hours;
                        $detail->holiday_hours = $snapshot->holiday_hours;
                        $detail->regular_pay = $snapshot->regular_pay;
                        $detail->overtime_pay = $snapshot->overtime_pay;
                        $detail->holiday_pay = $snapshot->holiday_pay;
                        $detail->allowances = $snapshot->allowances_total;
                        $detail->bonuses = $snapshot->bonuses_total;
                        $detail->incentives = $snapshot->incentives_total;
                        $detail->gross_pay = $snapshot->gross_pay;
                        $detail->sss_contribution = $snapshot->sss_contribution;
                        $detail->philhealth_contribution = $snapshot->philhealth_contribution;
                        $detail->pagibig_contribution = $snapshot->pagibig_contribution;
                        $detail->withholding_tax = $snapshot->withholding_tax;
                        $detail->late_deductions = $snapshot->late_deductions;
                        $detail->undertime_deductions = $snapshot->undertime_deductions;
                        $detail->cash_advance_deductions = $snapshot->cash_advance_deductions;
                        $detail->other_deductions = $snapshot->other_deductions;
                        $detail->total_deductions = $snapshot->total_deductions;
                        $detail->net_pay = $snapshot->net_pay;

                        // CRITICAL: Set breakdown data from snapshots for display consistency
                        if ($snapshot->allowances_breakdown) {
                            $detail->earnings_breakdown = json_encode([
                                'allowances' => is_string($snapshot->allowances_breakdown)
                                    ? json_decode($snapshot->allowances_breakdown, true)
                                    : $snapshot->allowances_breakdown
                            ]);
                        }

                        // CRITICAL: Ensure deduction breakdown is properly set from snapshot
                        if ($snapshot->deductions_breakdown) {
                            $deductionBreakdown = is_string($snapshot->deductions_breakdown)
                                ? json_decode($snapshot->deductions_breakdown, true)
                                : $snapshot->deductions_breakdown;

                            // Set as a property that can be accessed in the view
                            $detail->deduction_breakdown = $deductionBreakdown;

                            // Log for debugging
                            Log::info("Setting deduction breakdown for employee {$detail->employee_id}", [
                                'payroll_id' => $payroll->id,
                                'breakdown' => $deductionBreakdown
                            ]);
                        }
                    }
                }

                // Get settings from snapshot for display
                $firstSnapshot = $snapshots->first();
                if ($firstSnapshot && $firstSnapshot->settings_snapshot) {
                    $settingsSnapshot = is_string($firstSnapshot->settings_snapshot)
                        ? json_decode($firstSnapshot->settings_snapshot, true)
                        : $firstSnapshot->settings_snapshot;

                    if (isset($settingsSnapshot['allowance_settings'])) {
                        $allowanceSettings = collect($settingsSnapshot['allowance_settings']);
                    }
                    if (isset($settingsSnapshot['bonus_settings'])) {
                        $bonusSettings = collect($settingsSnapshot['bonus_settings']);
                    }
                    if (isset($settingsSnapshot['incentive_settings'])) {
                        $incentiveSettings = collect($settingsSnapshot['incentive_settings']);
                    }
                    if (isset($settingsSnapshot['deduction_settings'])) {
                        $deductionSettings = collect($settingsSnapshot['deduction_settings']);
                    }
                }

                // CRITICAL: Extract breakdown data from snapshots for hybrid display
                $basicBreakdown = [];
                $holidayBreakdown = [];
                $restBreakdown = [];
                $suspensionBreakdown = [];
                $overtimeBreakdown = [];

                foreach ($snapshots as $snapshot) {
                    if ($snapshot->basic_breakdown) {
                        $basicBreakdown = is_string($snapshot->basic_breakdown)
                            ? json_decode($snapshot->basic_breakdown, true)
                            : $snapshot->basic_breakdown;
                    }
                    if ($snapshot->holiday_breakdown) {
                        $holidayBreakdown = is_string($snapshot->holiday_breakdown)
                            ? json_decode($snapshot->holiday_breakdown, true)
                            : $snapshot->holiday_breakdown;
                    }
                    if ($snapshot->rest_breakdown) {
                        $restBreakdown = is_string($snapshot->rest_breakdown)
                            ? json_decode($snapshot->rest_breakdown, true)
                            : $snapshot->rest_breakdown;
                    }
                    if ($snapshot->suspension_breakdown) {
                        $suspensionBreakdown = is_string($snapshot->suspension_breakdown)
                            ? json_decode($snapshot->suspension_breakdown, true)
                            : $snapshot->suspension_breakdown;
                    }
                    if ($snapshot->overtime_breakdown) {
                        $overtimeBreakdown = is_string($snapshot->overtime_breakdown)
                            ? json_decode($snapshot->overtime_breakdown, true)
                            : $snapshot->overtime_breakdown;
                    }
                }
            } else {
                // No snapshots found - this shouldn't happen for processing/approved payrolls
                Log::warning("No snapshots found for non-dynamic payroll", [
                    'payroll_id' => $payroll->id,
                    'status' => $payroll->status
                ]);
            }
        }

        // Calculate totals for summary
        $totalBasicPay = array_sum(array_column($payBreakdownByEmployee, 'basic_pay'));
        $totalHolidayPay = array_sum(array_column($payBreakdownByEmployee, 'holiday_pay'));
        $totalRestDayPay = array_sum(array_column($payBreakdownByEmployee, 'rest_day_pay'));
        $totalOvertimePay = array_sum(array_column($payBreakdownByEmployee, 'overtime_pay'));

        // IMPORTANT: For locked/processing/approved payrolls, use static gross pay from snapshots
        // For draft payrolls, calculate from components
        if ($isDynamic) {
            // Draft payroll - calculate total gross from components (will be calculated in view)
            $totalGross = null; // Let view calculate from components
        } else {
            // Locked/processing/approved payroll - use static gross pay values from snapshots
            $totalGross = $payroll->payrollDetails->sum('gross_pay'); // These are set from snapshots above

            Log::info("Using static total gross for locked payroll", [
                'payroll_id' => $payroll->id,
                'payroll_status' => $payroll->status,
                'calculated_total_gross' => $totalGross,
                'payroll_total_gross_field' => $payroll->total_gross,
                'individual_gross_pays' => $payroll->payrollDetails->pluck('gross_pay', 'employee_id')->toArray()
            ]);
        }

        return view('payrolls.show', compact(
            'payroll',
            'dtrData',
            'periodDates',
            'allowanceSettings',
            'bonusSettings',
            'incentiveSettings',
            'deductionSettings',
            'isDynamic',
            'timeBreakdowns',
            'payBreakdownByEmployee',
            'totalBasicPay',
            'totalHolidayPay',
            'totalRestDayPay',
            'totalOvertimePay',
            'totalGross',
            'basicBreakdown',
            'holidayBreakdown',
            'restBreakdown',
            'suspensionBreakdown',
            'overtimeBreakdown'
        ));
    }

    /**
     * Show the payslip view for all employees in the payroll.
     */
    public function payslip(Payroll $payroll)
    {
        // Allow employees to view their own payslips, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('view payrolls');
        } else {
            // For employees, check if they have access to this payroll
            $employee = Auth::user()->employee;
            if (!$employee) {
                abort(404, 'Employee record not found');
            }

            // Check if this payroll contains the employee's data
            $hasAccess = $payroll->payrollDetails()->where('employee_id', $employee->id)->exists();
            if (!$hasAccess) {
                abort(403, 'You can only view your own payslips.');
            }
        }

        // Load necessary relationships
        $payroll->load([
            'payrollDetails.employee.user',
            'payrollDetails.employee.department',
            'payrollDetails.employee.position',
            'payrollDetails.employee.daySchedule',
            'payrollDetails.employee.timeSchedule',
            'creator',
            'approver'
        ]);

        // Apply the same snapshot logic as show method for approved/processing payrolls
        $isDynamic = $payroll->status === 'draft';

        if (!$isDynamic) {
            // For processing/approved payrolls, use snapshot data
            $snapshots = $payroll->snapshots()->get();
            if ($snapshots->isNotEmpty()) {
                // Update payroll details with snapshot values to ensure consistency
                foreach ($payroll->payrollDetails as $detail) {
                    $snapshot = $snapshots->where('employee_id', $detail->employee_id)->first();
                    if ($snapshot) {
                        // Override detail values with snapshot values
                        $detail->basic_salary = $snapshot->basic_salary;
                        $detail->daily_rate = $snapshot->daily_rate;
                        $detail->hourly_rate = $snapshot->hourly_rate;
                        $detail->days_worked = $snapshot->days_worked;
                        $detail->regular_hours = $snapshot->regular_hours;
                        $detail->overtime_hours = $snapshot->overtime_hours;
                        $detail->holiday_hours = $snapshot->holiday_hours;
                        $detail->regular_pay = $snapshot->regular_pay;
                        $detail->overtime_pay = $snapshot->overtime_pay;
                        $detail->holiday_pay = $snapshot->holiday_pay;
                        $detail->allowances = $snapshot->allowances_total;
                        $detail->bonuses = $snapshot->bonuses_total;
                        $detail->incentives = $snapshot->incentives_total;  // FIX: Add missing incentives
                        $detail->gross_pay = $snapshot->gross_pay;
                        $detail->sss_contribution = $snapshot->sss_contribution;
                        $detail->philhealth_contribution = $snapshot->philhealth_contribution;
                        $detail->pagibig_contribution = $snapshot->pagibig_contribution;
                        $detail->withholding_tax = $snapshot->withholding_tax;
                        $detail->late_deductions = $snapshot->late_deductions;
                        $detail->undertime_deductions = $snapshot->undertime_deductions;
                        $detail->cash_advance_deductions = $snapshot->cash_advance_deductions;
                        $detail->other_deductions = $snapshot->other_deductions;
                        $detail->total_deductions = $snapshot->total_deductions;
                        $detail->net_pay = $snapshot->net_pay;

                        // Set breakdown data from snapshots
                        if ($snapshot->allowances_breakdown) {
                            $detail->earnings_breakdown = json_encode([
                                'allowances' => is_string($snapshot->allowances_breakdown)
                                    ? json_decode($snapshot->allowances_breakdown, true)
                                    : $snapshot->allowances_breakdown
                            ]);
                        }

                        if ($snapshot->bonuses_breakdown) {
                            $detail->bonuses_breakdown = is_string($snapshot->bonuses_breakdown)
                                ? json_decode($snapshot->bonuses_breakdown, true)
                                : $snapshot->bonuses_breakdown;
                        }

                        if ($snapshot->deductions_breakdown) {
                            $detail->deduction_breakdown = is_string($snapshot->deductions_breakdown)
                                ? json_decode($snapshot->deductions_breakdown, true)
                                : $snapshot->deductions_breakdown;
                        }
                    }
                }
            }
        }

        // Get dynamic company data from employer_settings table
        $employerSettings = \App\Models\EmployerSetting::first();

        // Get active deduction settings for breakdown display
        $activeDeductions = \App\Models\DeductionTaxSetting::active()->orderBy('name')->get();

        $company = (object)[
            'name' => $employerSettings->registered_business_name ?? 'Payroll-System',
            'address' => $employerSettings->registered_address ?? 'Company Address, City, Province',
            'phone' => $employerSettings->landline_mobile ?? '+63 (000) 000-0000',
            'email' => $employerSettings->office_business_email ?? 'hr@company.com'
        ];

        return view('payrolls.payslip', compact('payroll', 'company', 'isDynamic', 'employerSettings', 'activeDeductions'));
    }

    /**
     * Download payslip as PDF using the same format as the payslip view
     */
    public function payslipDownload(Payroll $payroll)
    {
        // Use same authorization logic as payslip method
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('view payrolls');
        } else {
            // For employees, check if they have access to this payroll
            $employee = Auth::user()->employee;
            if (!$employee) {
                abort(404, 'Employee record not found');
            }

            // Check if this payroll contains the employee's data
            $hasAccess = $payroll->payrollDetails()->where('employee_id', $employee->id)->exists();
            if (!$hasAccess) {
                abort(403, 'You can only download your own payslips.');
            }
        }

        // Get the same data as the payslip method - we need to replicate the logic
        $payroll->load([
            'payrollDetails.employee.user',
            'payrollDetails.employee.department',
            'payrollDetails.employee.position',
            'payrollDetails.employee.daySchedule',
            'payrollDetails.employee.timeSchedule',
            'creator',
            'approver'
        ]);

        // Apply the same snapshot logic as payslip method for approved/processing payrolls
        $isDynamic = $payroll->status === 'draft';

        if (!$isDynamic) {
            // For processing/approved payrolls, use snapshot data
            $snapshots = $payroll->snapshots()->get();
            if ($snapshots->isNotEmpty()) {
                // Update payroll details with snapshot values to ensure consistency
                foreach ($payroll->payrollDetails as $detail) {
                    $snapshot = $snapshots->where('employee_id', $detail->employee_id)->first();
                    if ($snapshot) {
                        // Apply all the same snapshot overrides as the payslip method
                        $detail->basic_salary = $snapshot->basic_salary;
                        $detail->daily_rate = $snapshot->daily_rate;
                        $detail->hourly_rate = $snapshot->hourly_rate;
                        $detail->days_worked = $snapshot->days_worked;
                        $detail->regular_hours = $snapshot->regular_hours;
                        $detail->overtime_hours = $snapshot->overtime_hours;
                        $detail->holiday_hours = $snapshot->holiday_hours;
                        $detail->regular_pay = $snapshot->regular_pay;
                        $detail->overtime_pay = $snapshot->overtime_pay;
                        $detail->holiday_pay = $snapshot->holiday_pay;
                        $detail->allowances = $snapshot->allowances_total;
                        $detail->bonuses = $snapshot->bonuses_total;
                        $detail->incentives = $snapshot->incentives_total;
                        $detail->gross_pay = $snapshot->gross_pay;
                        $detail->sss_contribution = $snapshot->sss_contribution;
                        $detail->philhealth_contribution = $snapshot->philhealth_contribution;
                        $detail->pagibig_contribution = $snapshot->pagibig_contribution;
                        $detail->withholding_tax = $snapshot->withholding_tax;
                        $detail->late_deductions = $snapshot->late_deductions;
                        $detail->undertime_deductions = $snapshot->undertime_deductions;
                        $detail->cash_advance_deductions = $snapshot->cash_advance_deductions;
                        $detail->other_deductions = $snapshot->other_deductions;
                        $detail->total_deductions = $snapshot->total_deductions;
                        $detail->net_pay = $snapshot->net_pay;

                        // Set breakdown data from snapshots
                        if ($snapshot->allowances_breakdown) {
                            $detail->earnings_breakdown = json_encode([
                                'allowances' => is_string($snapshot->allowances_breakdown)
                                    ? json_decode($snapshot->allowances_breakdown, true)
                                    : $snapshot->allowances_breakdown
                            ]);
                        }

                        if ($snapshot->bonuses_breakdown) {
                            $detail->bonuses_breakdown = is_string($snapshot->bonuses_breakdown)
                                ? json_decode($snapshot->bonuses_breakdown, true)
                                : $snapshot->bonuses_breakdown;
                        }

                        if ($snapshot->deductions_breakdown) {
                            $detail->deduction_breakdown = is_string($snapshot->deductions_breakdown)
                                ? json_decode($snapshot->deductions_breakdown, true)
                                : $snapshot->deductions_breakdown;
                        }
                    }
                }
            }
        }

        // Get dynamic company data from employer_settings table
        $employerSettings = \App\Models\EmployerSetting::first();

        // Get active deduction settings for breakdown display
        $activeDeductions = \App\Models\DeductionTaxSetting::active()->orderBy('name')->get();

        $company = (object)[
            'name' => $employerSettings->registered_business_name ?? 'Payroll-System',
            'address' => $employerSettings->registered_address ?? 'Company Address, City, Province',
            'phone' => $employerSettings->landline_mobile ?? '+63 (000) 000-0000',
            'email' => $employerSettings->office_business_email ?? 'hr@company.com'
        ];

        // Create a PDF-optimized version of the payslip view
        $html = view('payrolls.payslip-pdf', compact('payroll', 'company', 'isDynamic', 'employerSettings', 'activeDeductions'))->render();

        // Generate PDF using DomPDF
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper('letter', 'portrait'); // Use letter size (8.5" x 11")

        // Generate filename based on employee and period
        $firstEmployee = $payroll->payrollDetails->first();
        if ($firstEmployee) {
            $filename = 'payslip_' . $firstEmployee->employee->employee_number . '_' .
                $payroll->period_start->format('Y-m') . '.pdf';
        } else {
            $filename = 'payslip_' . $payroll->payroll_number . '.pdf';
        }

        return $pdf->download($filename);
    }

    /**
     * Show the form for editing the specified payroll.
     */
    public function edit(Payroll $payroll)
    {
        $this->authorize('edit payrolls');

        if (!$payroll->canBeEdited()) {
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'This payroll cannot be edited.');
        }

        $payroll->load([
            'payrollDetails.employee.user',
            'payrollDetails.employee.department',
            'payrollDetails.employee.position'
        ]);

        return view('payrolls.edit', compact('payroll'));
    }

    /**
     * Update the specified payroll.
     */
    public function update(Request $request, Payroll $payroll)
    {
        $this->authorize('edit payrolls');

        if (!$payroll->canBeEdited()) {
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'This payroll cannot be edited.');
        }

        $validated = $request->validate([
            'pay_date' => 'required|date|after_or_equal:period_end',
            'description' => 'nullable|string|max:1000',
            'payroll_details' => 'required|array',
            'payroll_details.*.allowances' => 'numeric|min:0',
            'payroll_details.*.bonuses' => 'numeric|min:0',
            'payroll_details.*.other_earnings' => 'numeric|min:0',
            'payroll_details.*.other_deductions' => 'numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Update payroll basic info
            $payroll->update([
                'pay_date' => $validated['pay_date'],
                'description' => $validated['description'],
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            // Update payroll details
            foreach ($validated['payroll_details'] as $detailId => $detailData) {
                $detail = PayrollDetail::find($detailId);
                if ($detail && $detail->payroll_id == $payroll->id) {
                    $detail->update([
                        'allowances' => $detailData['allowances'] ?? 0,
                        'bonuses' => $detailData['bonuses'] ?? 0,
                        'other_earnings' => $detailData['other_earnings'] ?? 0,
                        'other_deductions' => $detailData['other_deductions'] ?? 0,
                    ]);

                    // Recalculate totals
                    $detail->gross_pay = $detail->regular_pay + $detail->overtime_pay +
                        $detail->holiday_pay + $detail->night_differential_pay +
                        $detail->allowances + $detail->bonuses + $detail->other_earnings;

                    $detail->calculateGovernmentContributions();
                    $detail->calculateWithholdingTax();
                    $detail->calculateTotalDeductions();
                    $detail->calculateNetPay();
                    $detail->save();

                    $totalGross += $detail->gross_pay;
                    $totalDeductions += $detail->total_deductions;
                    $totalNet += $detail->net_pay;
                }
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', 'Payroll updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update payroll: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified payroll.
     */
    public function destroy(Payroll $payroll)
    {
        // Temporarily disabled for testing
        // $this->authorize('delete payrolls');

        // Check if user can delete approved payrolls (temporarily allow all)
        $canDeleteApproved = true; // Auth::user()->can('delete approved payrolls');

        // If payroll is approved and user doesn't have permission to delete approved payrolls
        if ($payroll->status === 'approved' && !$canDeleteApproved) {
            return redirect()->route('payrolls.index')
                ->with('error', 'You do not have permission to delete approved payrolls.');
        }

        // If payroll is not approved, use the standard canBeEdited check (temporarily disabled)
        // if ($payroll->status !== 'approved' && !$payroll->canBeEdited()) {
        //     return redirect()->route('payrolls.index')
        //         ->with('error', 'This payroll cannot be deleted.');
        // }

        // Log the deletion for audit purposes
        Log::info('Payroll deleted', [
            'payroll_id' => $payroll->id,
            'payroll_number' => $payroll->payroll_number,
            'status' => $payroll->status,
            'deleted_by' => Auth::id(),
            'deleted_by_name' => Auth::user()->name
        ]);

        // If payroll was paid, reverse cash advance deductions
        if ($payroll->is_paid) {
            try {
                DB::beginTransaction();

                // Reverse cash advance deductions
                $this->reverseCashAdvanceDeductions($payroll);

                DB::commit();

                Log::info('Cash advance deductions reversed for deleted payroll', [
                    'payroll_id' => $payroll->id,
                    'payroll_number' => $payroll->payroll_number
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to reverse cash advance deductions for deleted payroll', [
                    'payroll_id' => $payroll->id,
                    'error' => $e->getMessage()
                ]);

                return redirect()->route('payrolls.index')
                    ->with('error', 'Failed to delete payroll: Could not reverse cash advance payments.');
            }
        }

        $payroll->delete();

        return redirect()->route('payrolls.index')
            ->with('success', 'Payroll deleted successfully!');
    }

    /**
     * Approve the specified payroll.
     */
    public function approve(Payroll $payroll)
    {
        $this->authorize('approve payrolls');

        if ($payroll->status !== 'processing') {
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'Only processing payrolls can be approved.');
        }

        DB::beginTransaction();
        try {
            $payroll->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', 'Payroll approved successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve payroll', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'Failed to approve payroll: ' . $e->getMessage());
        }
    }

    /**
     * Process the specified payroll.
     */
    public function process(Payroll $payroll)
    {
        $this->authorize('process payrolls');

        Log::info("Process method called", [
            'payroll_id' => $payroll->id,
            'current_status' => $payroll->status,
            'user_id' => Auth::id()
        ]);

        if ($payroll->status !== 'draft') {
            Log::warning("Attempted to process non-draft payroll", [
                'payroll_id' => $payroll->id,
                'status' => $payroll->status
            ]);
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'Only draft payrolls can be processed.');
        }

        DB::beginTransaction();
        try {
            Log::info("Starting payroll processing", [
                'payroll_id' => $payroll->id,
                'payroll_number' => $payroll->payroll_number,
                'employee_count' => $payroll->payrollDetails->count()
            ]);

            // Create snapshots for all payroll details (capture exact draft state)
            $this->createPayrollSnapshots($payroll);

            // Update payroll status
            $payroll->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'processing_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info("Successfully processed payroll", [
                'payroll_id' => $payroll->id,
                'new_status' => $payroll->fresh()->status,
                'snapshot_count' => $payroll->snapshots()->count()
            ]);

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', 'Payroll submitted for processing! Data has been locked as snapshots and will display the same calculations as when it was in draft mode.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process payroll', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    /**
     * Show dynamic payroll settings test page
     */
    public function testDynamic()
    {
        $this->authorize('view payrolls');

        // Get active deduction settings
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        // Get active allowance/bonus settings
        $allowanceSettings = \App\Models\AllowanceBonusSetting::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return view('payrolls.test-dynamic', compact('deductionSettings', 'allowanceSettings'));
    }

    /**
     * Calculate employee payroll details based on approved DTR records
     */
    private function calculateEmployeePayroll(Employee $employee, Payroll $payroll)
    {
        // Check if payroll is in processing/approved state and has snapshots
        if ($payroll->usesSnapshot()) {
            return $this->getEmployeePayrollFromSnapshot($employee, $payroll);
        }

        // For draft payrolls, calculate dynamically
        return $this->calculateEmployeePayrollDynamic($employee, $payroll);
    }

    /**
     * Get employee payroll data from snapshot (for processing/approved payrolls)
     */
    private function getEmployeePayrollFromSnapshot(Employee $employee, Payroll $payroll)
    {
        $snapshot = $payroll->snapshots()->where('employee_id', $employee->id)->first();

        if (!$snapshot) {
            throw new \Exception("No snapshot found for employee {$employee->employee_number} in payroll {$payroll->payroll_number}");
        }

        // Create or update PayrollDetail from snapshot data
        $payrollDetail = PayrollDetail::updateOrCreate(
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ],
            [
                'basic_salary' => $snapshot->basic_salary,
                'daily_rate' => $snapshot->daily_rate,
                'hourly_rate' => $snapshot->hourly_rate,
                'days_worked' => $snapshot->days_worked,
                'regular_hours' => $snapshot->regular_hours,
                'overtime_hours' => $snapshot->overtime_hours,
                'holiday_hours' => $snapshot->holiday_hours,
                'night_differential_hours' => $snapshot->night_differential_hours,
                'regular_pay' => $snapshot->regular_pay,
                'overtime_pay' => $snapshot->overtime_pay,
                'holiday_pay' => $snapshot->holiday_pay,
                'night_differential_pay' => $snapshot->night_differential_pay,
                'allowances' => $snapshot->allowances_total,
                'bonuses' => $snapshot->bonuses_total,
                'other_earnings' => $snapshot->other_earnings,
                'gross_pay' => $snapshot->gross_pay,
                'sss_contribution' => $snapshot->sss_contribution,
                'philhealth_contribution' => $snapshot->philhealth_contribution,
                'pagibig_contribution' => $snapshot->pagibig_contribution,
                'withholding_tax' => $snapshot->withholding_tax,
                'late_deductions' => $snapshot->late_deductions,
                'undertime_deductions' => $snapshot->undertime_deductions,
                'cash_advance_deductions' => $snapshot->cash_advance_deductions,
                'other_deductions' => $snapshot->other_deductions,
                'total_deductions' => $snapshot->total_deductions,
                'net_pay' => $snapshot->net_pay,
            ]
        );

        return $payrollDetail;
    }

    /**
     * Calculate employee payroll dynamically (for draft payrolls)
     */
    private function calculateEmployeePayrollDynamic(Employee $employee, Payroll $payroll)
    {
        // Get time logs for this payroll period
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->get();        // Initialize counters
        $daysWorked = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $holidayHours = 0;
        $nightDifferentialRegularHours = 0;
        $nightDifferentialOvertimeHours = 0;
        $lateHours = 0;
        $undertimeHours = 0;

        // Process each time log if available
        foreach ($timeLogs as $timeLog) {
            $daysWorked++;
            $regularHours += $timeLog->regular_hours ?? 0;
            $overtimeHours += $timeLog->overtime_hours ?? 0;
            $lateHours += $timeLog->late_hours ?? 0;
            $undertimeHours += $timeLog->undertime_hours ?? 0;

            // Add night differential hours
            $nightDifferentialRegularHours += $timeLog->night_diff_regular_hours ?? 0;
            $nightDifferentialOvertimeHours += $timeLog->night_diff_overtime_hours ?? 0;

            // Check if it's a holiday or rest day for premium calculations
            if ($timeLog->is_holiday) {
                $holidayHours += $timeLog->total_hours ?? 0;
            }
        }

        // Use new calculation method for hourly rate
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);

        if (!$hourlyRate) {
            // If no hourly rate can be calculated, throw error
            throw new \Exception("Employee {$employee->employee_number} has no valid rate configuration defined.");
        }

        $dailyRate = $hourlyRate * 8; // 8 hours per day

        // If no DTR records, set basic pay to zero (only pay for actual recorded hours)
        if ($timeLogs->isEmpty()) {
            $daysWorked = 0;
            $regularHours = 0; // No DTR records = no basic pay
        }

        // Calculate pay components
        $regularPay = $regularHours * $hourlyRate;
        $overtimePay = $overtimeHours * $hourlyRate * 1.25; // 25% overtime premium
        $holidayPay = $holidayHours * $hourlyRate * 2.0; // 100% holiday premium

        // Calculate night differential pay using dynamic rate
        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
        $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
        $nightDiffBonus = $nightDiffMultiplier - 1; // e.g., 1.10 - 1 = 0.10 (10% bonus)

        $nightDifferentialPay = ($nightDifferentialRegularHours + $nightDifferentialOvertimeHours) * $hourlyRate * $nightDiffBonus;
        $totalNightDifferentialHours = $nightDifferentialRegularHours + $nightDifferentialOvertimeHours;

        // Calculate late and undertime deductions
        $lateDeductions = $lateHours * $hourlyRate;
        $undertimeDeductions = $undertimeHours * $hourlyRate;

        // Calculate allowances and bonuses from settings
        $allowancesData = $this->calculateEmployeeAllowances($employee, $payroll, $regularHours, $overtimeHours, $holidayHours);
        $bonusesData = $this->calculateEmployeeBonuses($employee, $payroll, $regularHours, $overtimeHours, $holidayHours);
        $incentivesData = $this->calculateEmployeeIncentives($employee, $payroll, $regularHours, $overtimeHours, $holidayHours);

        // Calculate cash advance deductions
        $cashAdvanceDeductions = $this->calculateCashAdvanceDeductions($employee, $payroll);

        // Create or update payroll detail
        $payrollDetail = PayrollDetail::updateOrCreate(
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ],
            [
                'basic_salary' => $employee->basic_salary,
                'daily_rate' => $dailyRate,
                'hourly_rate' => $hourlyRate,
                'days_worked' => $daysWorked,
                'regular_hours' => $regularHours,
                'overtime_hours' => $overtimeHours,
                'holiday_hours' => $holidayHours,
                'night_differential_hours' => $totalNightDifferentialHours,
                'regular_pay' => $regularPay,
                'overtime_pay' => $overtimePay,
                'holiday_pay' => $holidayPay,
                'night_differential_pay' => $nightDifferentialPay,
                'allowances' => $allowancesData['total'],
                'bonuses' => $bonusesData['total'],
                'incentives' => $incentivesData['total'],
                'other_earnings' => 0,
                'late_deductions' => $lateDeductions,
                'undertime_deductions' => $undertimeDeductions,
                'cash_advance_deductions' => $cashAdvanceDeductions,
                'other_deductions' => 0,
                'earnings_breakdown' => json_encode([
                    'allowances' => $allowancesData['details'],
                    'bonuses' => $bonusesData['details'],
                    'incentives' => $incentivesData['details'],
                ]),
            ]
        );

        // Calculate gross pay
        $payrollDetail->gross_pay = $payrollDetail->regular_pay +
            $payrollDetail->overtime_pay +
            $payrollDetail->holiday_pay +
            $payrollDetail->night_differential_pay +
            $payrollDetail->allowances +
            $payrollDetail->bonuses +
            $payrollDetail->incentives +
            $payrollDetail->other_earnings;

        // Calculate deductions using the PayrollDetail model methods with employer sharing
        $payrollDetail->calculateGovernmentContributionsWithSharing();
        $payrollDetail->calculateWithholdingTax();
        $payrollDetail->calculateTotalDeductions();
        $payrollDetail->calculateNetPay();

        $payrollDetail->save();

        return $payrollDetail;
    }

    /**
     * Calculate employee allowances based on active settings
     */
    private function calculateEmployeeAllowances(Employee $employee, Payroll $payroll, $regularHours, $overtimeHours, $holidayHours)
    {
        // Get current active allowance settings that apply to this employee's benefit status
        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        $totalAllowances = 0;
        $allowanceDetails = [];

        foreach ($allowanceSettings as $setting) {
            // Check if this allowance requires perfect attendance
            if ($setting->requires_perfect_attendance) {
                // Check if employee has perfect attendance for this payroll period
                if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                    continue; // Skip this allowance if perfect attendance not met
                }
            }

            $allowanceAmount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours
            );

            if ($allowanceAmount > 0) {
                $allowanceDetails[$setting->code] = [
                    'name' => $setting->name,
                    'amount' => $allowanceAmount,
                    'is_taxable' => $setting->is_taxable
                ];
                $totalAllowances += $allowanceAmount;
            }
        }

        return [
            'total' => $totalAllowances,
            'details' => $allowanceDetails
        ];
    }

    /**
     * Calculate employee bonuses based on active settings
     */
    private function calculateEmployeeBonuses(Employee $employee, Payroll $payroll, $regularHours, $overtimeHours, $holidayHours)
    {
        // Get current active bonus settings that apply to this employee's benefit status
        $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'bonus')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        $totalBonuses = 0;
        $bonusDetails = [];

        foreach ($bonusSettings as $setting) {
            // Check if this bonus requires perfect attendance
            if ($setting->requires_perfect_attendance) {
                // Check if employee has perfect attendance for this payroll period
                if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                    continue; // Skip this bonus if perfect attendance not met
                }
            }

            $bonusAmount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours
            );

            if ($bonusAmount > 0) {
                $bonusDetails[$setting->code] = [
                    'name' => $setting->name,
                    'amount' => $bonusAmount,
                    'is_taxable' => $setting->is_taxable
                ];
                $totalBonuses += $bonusAmount;
            }
        }

        return [
            'total' => $totalBonuses,
            'details' => $bonusDetails
        ];
    }

    /**
     * Calculate employee incentives for payroll
     */
    private function calculateEmployeeIncentives(Employee $employee, Payroll $payroll, $regularHours, $overtimeHours, $holidayHours)
    {
        // Get current active incentive settings that apply to this employee's benefit status
        $incentiveSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'incentives')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        $totalIncentives = 0;
        $incentiveDetails = [];

        foreach ($incentiveSettings as $setting) {
            // Check if this incentive requires perfect attendance
            if ($setting->requires_perfect_attendance) {
                // Check if employee has perfect attendance for this payroll period
                if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                    continue; // Skip this incentive if perfect attendance not met
                }
            }

            $incentiveAmount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours
            );

            if ($incentiveAmount > 0) {
                $incentiveDetails[$setting->code] = [
                    'name' => $setting->name,
                    'amount' => $incentiveAmount,
                    'is_taxable' => $setting->is_taxable
                ];
                $totalIncentives += $incentiveAmount;
            }
        }

        return [
            'total' => $totalIncentives,
            'details' => $incentiveDetails
        ];
    }

    /**
     * Calculate individual allowance or bonus amount for payroll
     */
    private function calculateAllowanceBonusAmountForPayroll($setting, $employee, $payroll, $regularHours, $overtimeHours, $holidayHours, $breakdownData = null)
    {
        $amount = 0;

        switch ($setting->calculation_type) {
            case 'fixed_amount':
                $amount = $setting->fixed_amount ?? 0;
                break;

            case 'percentage':
                // Calculate percentage of basic salary
                $baseAmount = $employee->basic_salary ?? 0;
                $amount = $baseAmount * (($setting->rate_percentage ?? 0) / 100);
                break;

            case 'per_day':
                // Calculate based on actual days worked
                $daysWorked = $this->calculateDaysWorked($employee, $payroll);
                $amount = ($setting->fixed_amount ?? 0) * $daysWorked;
                break;

            case 'per_hour':
                // Calculate based on hours worked
                $totalHours = $regularHours;

                if ($setting->apply_to_overtime ?? false) {
                    $totalHours += $overtimeHours;
                }

                if ($setting->apply_to_holidays ?? false) {
                    $totalHours += $holidayHours;
                }

                $amount = ($setting->fixed_amount ?? 0) * $totalHours;
                break;

            case 'multiplier':
                // Calculate as multiplier of hourly rate using new method
                $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
                $amount = $hourlyRate * ($setting->multiplier ?? 0) * $regularHours;
                break;

            case 'basic_salary_multiplier':
                // Calculate as multiplier of basic salary
                $amount = ($employee->basic_salary ?? 0) * ($setting->multiplier ?? 0);
                break;

            case 'daily_rate_multiplier':
                // Calculate as multiplier of daily rate
                $dailyRate = $employee->daily_rate ?? $this->calculateDailyRate($employee, $employee->basic_salary ?? 0, $payroll->period_start, $payroll->period_end);
                $daysWorked = $this->calculateDaysWorked($employee, $payroll);
                $amount = $dailyRate * ($setting->multiplier ?? 0) * $daysWorked;
                break;

            case 'automatic':
                // Use the model's calculateAmount method for automatic calculation
                $basicPay = $employee->basic_salary ?? 0;
                $dailyRate = $employee->daily_rate ?? $this->calculateDailyRate($employee, $basicPay, $payroll->period_start, $payroll->period_end);
                $daysWorked = $this->calculateDaysWorked($employee, $payroll);
                // Pass breakdown data for 13th month pay calculation accuracy
                $amount = $setting->calculateAmount($basicPay, $dailyRate, $daysWorked, $employee, $breakdownData);

                Log::info("Automatic bonus calculation result", [
                    'setting_name' => $setting->name,
                    'employee_id' => $employee->id,
                    'calculated_amount' => $amount,
                    'has_breakdown_data' => !empty($breakdownData),
                    'frequency' => $setting->frequency,
                    'distribution_method' => $setting->distribution_method
                ]);
                break;
        }

        // Apply minimum and maximum limits (if fields still exist - this will be graceful)
        if (isset($setting->minimum_amount) && $setting->minimum_amount && $amount < $setting->minimum_amount) {
            $amount = $setting->minimum_amount;
        }

        if (isset($setting->maximum_amount) && $setting->maximum_amount && $amount > $setting->maximum_amount) {
            $amount = $setting->maximum_amount;
        }

        // Apply frequency and distribution method logic
        if ($amount > 0) {
            // Check perfect attendance requirement if enabled
            if ($setting->requires_perfect_attendance) {
                if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                    return 0; // Employee doesn't have perfect attendance, no allowance/bonus
                }
            }

            // Get employee's pay schedule or auto-detect from payroll period
            $employeePaySchedule = $employee->pay_schedule ?? \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                $payroll->period_start,
                $payroll->period_end
            );

            // Apply distribution logic using the model method
            $originalAmount = $amount;
            $amount = $setting->calculateDistributedAmount(
                $amount,
                $payroll->period_start,
                $payroll->period_end,
                $employeePaySchedule
            );

            Log::info("Distribution applied to bonus", [
                'bonus_name' => $setting->name,
                'employee_id' => $employee->id,
                'original_amount' => $originalAmount,
                'distributed_amount' => $amount,
                'pay_schedule' => $employeePaySchedule,
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end
            ]);
        }

        return round($amount, 2);
    }

    /**
     * Calculate distributed allowance/bonus amount based on frequency and distribution method
     */
    private function calculateDistributedAllowanceBonusAmount($setting, $payroll, $baseAmount)
    {
        $frequency = $setting->frequency;
        $distributionMethod = $setting->distribution_method ?? 'all_payrolls';

        if ($frequency === 'per_payroll') {
            return $baseAmount;
        }

        // Calculate total payrolls in the frequency period
        $totalPayrollsInPeriod = $this->calculatePayrollsInFrequencyPeriod($frequency, $payroll);

        switch ($distributionMethod) {
            case 'equally_distributed':
                // Divide the total amount equally across all payrolls in the period
                return round($baseAmount / $totalPayrollsInPeriod, 2);

            case 'first_payroll':
                // Give full amount only on the first payroll of the period
                if ($this->isFirstPayrollInFrequencyPeriod($frequency, $payroll)) {
                    return $baseAmount;
                } else {
                    return 0;
                }

            case 'last_payroll':
                // Give full amount only on the last payroll of the period
                if ($this->isLastPayrollInFrequencyPeriod($frequency, $payroll)) {
                    return $baseAmount;
                } else {
                    return 0;
                }

            case 'all_payrolls':
            default:
                // Give full amount on every payroll
                return $baseAmount;
        }
    }

    /**
     * Calculate number of payrolls in a frequency period
     */
    private function calculatePayrollsInFrequencyPeriod($frequency, $payroll)
    {
        switch ($frequency) {
            case 'monthly':
                // Assume semi-monthly payroll (2 payrolls per month)
                return 2;
            case 'quarterly':
                // 3 months * 2 payrolls per month
                return 6;
            case 'annually':
                // 12 months * 2 payrolls per month
                return 24;
            default:
                return 1;
        }
    }

    /**
     * Check if current payroll is the first in the frequency period
     */
    private function isFirstPayrollInFrequencyPeriod($frequency, $payroll)
    {
        $periodStart = $payroll->period_start;

        switch ($frequency) {
            case 'monthly':
                // First payroll of the month (1st-15th typically)
                return $periodStart->day <= 15;
            case 'quarterly':
                // First payroll of the quarter
                $quarterStartMonth = (ceil($periodStart->month / 3) - 1) * 3 + 1;
                return $periodStart->month == $quarterStartMonth && $periodStart->day <= 15;
            case 'annually':
                // First payroll of the year
                return $periodStart->month == 1 && $periodStart->day <= 15;
            default:
                return true;
        }
    }

    /**
     * Check if current payroll is the last in the frequency period
     */
    private function isLastPayrollInFrequencyPeriod($frequency, $payroll)
    {
        $periodEnd = $payroll->period_end;

        switch ($frequency) {
            case 'monthly':
                // Last payroll of the month (16th-end typically)
                return $periodEnd->day > 15;
            case 'quarterly':
                // Last payroll of the quarter
                $quarterEndMonth = ceil($periodEnd->month / 3) * 3;
                return $periodEnd->month == $quarterEndMonth && $periodEnd->day > 15;
            case 'annually':
                // Last payroll of the year
                return $periodEnd->month == 12 && $periodEnd->day > 15;
            default:
                return true;
        }
    }

    /**
     * Calculate actual days worked by employee in payroll period
     */
    private function calculateDaysWorked(Employee $employee, Payroll $payroll)
    {
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->where('regular_hours', '>', 0)
            ->count();

        return $timeLogs;
    }

    /**
     * Calculate number of weeks in payroll period
     */
    private function calculateWeeksInPeriod(Payroll $payroll)
    {
        $startDate = Carbon::parse($payroll->period_start);
        $endDate = Carbon::parse($payroll->period_end);

        return ceil($startDate->diffInDays($endDate) / 7);
    }

    /**
     * Calculate cash advance deductions for the employee (calculation only, no payment recording)
     */
    private function calculateCashAdvanceDeductions(Employee $employee, Payroll $payroll)
    {
        try {
            // Get active cash advances for this employee that should start deduction
            $cashAdvances = CashAdvance::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where('outstanding_balance', '>', 0)
                ->get();

            $totalDeductions = 0;

            foreach ($cashAdvances as $cashAdvance) {
                // Check if this cash advance should be deducted in this payroll period
                if (!$this->shouldDeductCashAdvance($cashAdvance, $employee, $payroll)) {
                    continue;
                }

                // Calculate deduction amount based on frequency
                $deductionAmount = $this->calculateCashAdvanceDeductionAmount($cashAdvance, $employee, $payroll);

                // Ensure we don't deduct more than outstanding balance
                $deductionAmount = min($deductionAmount, $cashAdvance->outstanding_balance);

                if ($deductionAmount > 0) {
                    $totalDeductions += $deductionAmount;

                    // Note: Payment recording is only done when payroll is marked as paid
                    // This method only calculates the deduction amount for display purposes
                }
            }

            return $totalDeductions;
        } catch (\Exception $e) {
            // If there's an error (like missing table), return 0
            Log::warning('Cash advance calculation failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a cash advance should be deducted in this payroll period
     */
    private function shouldDeductCashAdvance(CashAdvance $cashAdvance, Employee $employee, Payroll $payroll)
    {
        // Check if enough payroll periods have passed since the cash advance was approved
        if (!$this->hasReachedStartingPayrollPeriod($cashAdvance, $employee, $payroll)) {
            return false;
        }

        // For per_payroll frequency, deduct every payroll after reaching the starting period
        if ($cashAdvance->deduction_frequency === 'per_payroll') {
            return true;
        }

        // For monthly frequency, check timing based on employee pay schedule
        if ($cashAdvance->deduction_frequency === 'monthly') {
            return $this->isCorrectMonthlyPayrollForDeduction($cashAdvance, $employee, $payroll);
        }

        // Default to old behavior for backward compatibility
        if ($employee->pay_schedule === 'semi_monthly') {
            // Check if this is the last cutoff of the month
            return $payroll->pay_period_end->day >= 28 || $payroll->pay_period_end->isLastOfMonth();
        }

        return true;
    }

    /**
     * Check if enough payroll periods have passed to start deductions
     */
    private function hasReachedStartingPayrollPeriod(CashAdvance $cashAdvance, Employee $employee, Payroll $payroll)
    {
        // If no starting_payroll_period is set, use the old logic
        if (!$cashAdvance->starting_payroll_period) {
            return true; // Default to allowing deductions
        }

        // Special case: If starting_payroll_period is 1 (current), 
        // allow deduction immediately once approved
        if ($cashAdvance->starting_payroll_period == 1) {
            return true;
        }

        // Get the approval date of the cash advance
        $approvalDate = $cashAdvance->approved_date ?? $cashAdvance->requested_date;

        // Count how many payroll periods have occurred since approval for this employee
        $payrollsSinceApproval = \App\Models\Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('period_start', '>', $approvalDate)
            ->where('period_start', '<=', $payroll->period_start)
            ->count();

        // Check if we've reached the starting payroll period
        // (starting_payroll_period: 1=current, 2=next, 3=2nd next, 4=3rd next)
        // For periods > 1, we need to skip (starting_payroll_period - 1) periods
        return $payrollsSinceApproval >= ($cashAdvance->starting_payroll_period - 1);
    }
    /**
     * Check if this is the correct payroll for monthly cash advance deduction
     */
    private function isCorrectMonthlyPayrollForDeduction(CashAdvance $cashAdvance, Employee $employee, Payroll $payroll)
    {
        $payPeriodEnd = Carbon::parse($payroll->pay_period_end);
        $payPeriodStart = Carbon::parse($payroll->pay_period_start);

        // For monthly employees, there's only one payroll per month
        if ($employee->pay_schedule === 'monthly') {
            return true;
        }

        // For semi-monthly employees
        if ($employee->pay_schedule === 'semi_monthly') {
            if ($cashAdvance->monthly_deduction_timing === 'first_payroll') {
                // First cutoff: payroll that starts in first half of month (1st-15th)
                return $payPeriodStart->day <= 15;
            } else {
                // Last cutoff: payroll that ends in second half of month (16th-end)
                return $payPeriodEnd->day >= 16;
            }
        }

        // For weekly employees
        if ($employee->pay_schedule === 'weekly') {
            if ($cashAdvance->monthly_deduction_timing === 'first_payroll') {
                // First payroll of month: payroll that includes the 1st day of the month
                return $payPeriodStart->day <= 7;
            } else {
                // Last payroll of month: payroll that includes the last week of the month
                $lastDayOfMonth = $payPeriodEnd->copy()->endOfMonth();
                return $payPeriodEnd->diffInDays($lastDayOfMonth) <= 6;
            }
        }

        return true; // Default behavior
    }

    /**
     * Calculate the deduction amount for a cash advance
     */
    private function calculateCashAdvanceDeductionAmount(CashAdvance $cashAdvance, Employee $employee, Payroll $payroll)
    {
        if ($cashAdvance->deduction_frequency === 'monthly') {
            // For monthly deductions, use total amount divided by monthly installments
            $monthlyInstallments = $cashAdvance->monthly_installments ?? 1;
            return $cashAdvance->total_amount / $monthlyInstallments;
        } else {
            // For per_payroll deductions, use the regular installment amount
            return $cashAdvance->installment_amount ?? 0;
        }
    }

    /**
     * Calculate night differential hours for a time log
     */
    private function calculateNightDifferential(TimeLog $timeLog)
    {
        if (!$timeLog->time_in || !$timeLog->time_out) {
            return 0;
        }

        try {
            $nightStart = Carbon::createFromFormat('H:i', '22:00');
            $nightEnd = Carbon::createFromFormat('H:i', '06:00')->addDay();

            // Get just the time part for comparison
            $timeInStr = is_string($timeLog->time_in) ? $timeLog->time_in : $timeLog->time_in->format('H:i:s');
            $timeOutStr = is_string($timeLog->time_out) ? $timeLog->time_out : $timeLog->time_out->format('H:i:s');

            $timeIn = Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $timeInStr);
            $timeOut = Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $timeOutStr);

            // If time out is earlier than time in, it means work continued to next day
            if ($timeOut->lessThan($timeIn)) {
                $timeOut->addDay();
            }

            $nightDifferentialHours = 0;

            // Check overlap with night hours (10PM - 6AM)
            $nightStartDate = Carbon::parse($timeLog->log_date->format('Y-m-d') . ' 22:00');
            $nightEndDate = Carbon::parse($timeLog->log_date->format('Y-m-d') . ' 06:00')->addDay();

            $overlapStart = $timeIn->greaterThan($nightStartDate) ? $timeIn : $nightStartDate;
            $overlapEnd = $timeOut->lessThan($nightEndDate) ? $timeOut : $nightEndDate;

            if ($overlapStart->lessThan($overlapEnd)) {
                $nightDifferentialHours = $overlapEnd->diffInHours($overlapStart, true);
            }

            return $nightDifferentialHours;
        } catch (\Exception $e) {
            // If there's an error parsing times, return 0 night differential
            Log::warning('Error calculating night differential for time log ' . $timeLog->id . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate payroll from approved DTR records
     */
    public function generateFromDTR(Request $request)
    {
        $this->authorize('create payrolls');

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'pay_date' => 'required|date|after_or_equal:period_end',
            'payroll_type' => 'required|in:regular,special,13th_month,bonus',
            'description' => 'nullable|string|max:1000',
        ]);

        // Get employees with DTR records in the period
        $employeesWithDTR = Employee::whereHas('timeLogs', function ($query) use ($validated) {
            $query->whereBetween('log_date', [$validated['period_start'], $validated['period_end']]);
        })
            ->with(['user', 'department', 'position'])
            ->where('employment_status', 'active')
            ->get();

        if ($employeesWithDTR->isEmpty()) {
            return back()->withErrors(['error' => 'No employees with approved DTR records found for the selected period.']);
        }

        DB::beginTransaction();
        try {
            // Create payroll
            $payroll = Payroll::create([
                'payroll_number' => Payroll::generatePayrollNumber($validated['payroll_type']),
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'pay_date' => $validated['pay_date'],
                'payroll_type' => $validated['payroll_type'],
                'description' => $validated['description'] ?: 'Generated from approved DTR records',
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            // Create payroll details for each employee with approved DTR
            foreach ($employeesWithDTR as $employee) {
                $payrollDetail = $this->calculateEmployeePayroll($employee, $payroll);

                $totalGross += $payrollDetail->gross_pay;
                $totalDeductions += $payrollDetail->total_deductions;
                $totalNet += $payrollDetail->net_pay;
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', "Payroll generated successfully from DTR records! {$employeesWithDTR->count()} employees included.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate payroll from DTR: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to generate payroll from DTR: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Calculate automatic payroll period based on current date and schedule settings
     */
    private function calculateAutomaticPayrollPeriod($scheduleType = 'weekly')
    {
        $today = \Carbon\Carbon::now();

        // Get the specific schedule setting for the requested type
        $setting = \App\Models\PayrollScheduleSetting::where('pay_type', $scheduleType)->first();

        if ($setting) {
            $period = $this->calculatePeriodForSchedule($setting, $today);
            if ($period) {
                return [
                    'schedule_type' => $setting->pay_type,
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'pay_date' => $period['pay_date'],
                    'period_name' => $period['name'],
                    'cut_off_day' => $setting->cutoff_start_day,
                    'pay_day' => $setting->payday_offset_days
                ];
            }
        }

        // Fallback calculation based on schedule type
        return $this->getFallbackPeriod($scheduleType, $today);
    }

    /**
     * Get available periods for a schedule setting
     */
    private function getAvailablePeriodsForSchedule($setting)
    {
        $today = \Carbon\Carbon::now();
        $periods = [];

        switch ($setting->pay_type) {
            case 'weekly':
                // Get current week and next 2 weeks
                for ($i = 0; $i < 3; $i++) {
                    $weekStart = $today->copy()->addWeeks($i)->startOfWeek(\Carbon\Carbon::MONDAY);
                    $weekEnd = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                    $payDate = $weekEnd->copy()->addDays($setting->payday_offset_days);

                    $periods[] = [
                        'id' => $setting->pay_type . '_' . $weekStart->format('Y_m_d'),
                        'pay_schedule' => $setting->pay_type,
                        'period_display' => $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y'),
                        'pay_date_display' => $payDate->format('M d, Y'),
                        'period_start' => $weekStart->format('Y-m-d'),
                        'period_end' => $weekEnd->format('Y-m-d'),
                        'pay_date' => $payDate->format('Y-m-d'),
                        'setting_id' => $setting->id
                    ];
                }
                break;

            case 'semi_monthly':
                // Get current and next 2 semi-monthly periods
                for ($i = 0; $i < 3; $i++) {
                    $baseDate = $today->copy()->addMonths(floor($i / 2));
                    $isSecondHalf = ($i % 2 === 1);

                    if ($isSecondHalf || ($i === 0 && $today->day > 15)) {
                        // Second half of month (16th to end)
                        $periodStart = $baseDate->copy()->startOfMonth()->addDays(15); // 16th
                        $periodEnd = $baseDate->copy()->endOfMonth();
                        $payDate = $baseDate->copy()->addMonth()->startOfMonth()->addDays(4); // 5th of next month
                        $period = $periodStart->format('M j') . ' - ' . $periodEnd->format('M j, Y');
                    } else {
                        // First half of month (1st to 15th)
                        $periodStart = $baseDate->copy()->startOfMonth();
                        $periodEnd = $baseDate->copy()->startOfMonth()->addDays(14); // 15th
                        $payDate = $baseDate->copy()->startOfMonth()->addDays(19); // 20th of same month
                        $period = $periodStart->format('M j') . ' - ' . $periodEnd->format('j, Y');
                    }

                    $periods[] = [
                        'id' => $setting->pay_type . '_' . $periodStart->format('Y_m_d'),
                        'pay_schedule' => $setting->pay_type,
                        'period_display' => $period,
                        'pay_date_display' => $payDate->format('M d, Y'),
                        'period_start' => $periodStart->format('Y-m-d'),
                        'period_end' => $periodEnd->format('Y-m-d'),
                        'pay_date' => $payDate->format('Y-m-d'),
                        'setting_id' => $setting->id
                    ];
                }
                break;

            case 'monthly':
                // Get current and next 2 months
                for ($i = 0; $i < 3; $i++) {
                    $monthDate = $today->copy()->addMonths($i);
                    $periodStart = $monthDate->copy()->startOfMonth();
                    $periodEnd = $monthDate->copy()->endOfMonth();
                    $payDate = $monthDate->copy()->addMonth()->startOfMonth()->addDays(4); // 5th of next month

                    $periods[] = [
                        'id' => $setting->pay_type . '_' . $periodStart->format('Y_m'),
                        'pay_schedule' => $setting->pay_type,
                        'period_display' => $periodStart->format('M Y'),
                        'pay_date_display' => $payDate->format('M d, Y'),
                        'period_start' => $periodStart->format('Y-m-d'),
                        'period_end' => $periodEnd->format('Y-m-d'),
                        'pay_date' => $payDate->format('Y-m-d'),
                        'setting_id' => $setting->id
                    ];
                }
                break;
        }

        return $periods;
    }

    /**
     * Get variations of pay schedule naming to handle different database values
     */
    private function getPayScheduleVariations($paySchedule)
    {
        $variations = [$paySchedule]; // Include the original

        switch (strtolower($paySchedule)) {
            case 'weekly':
                $variations = ['weekly', 'Weekly', 'WEEKLY'];
                break;
            case 'semi_monthly':
                $variations = ['semi_monthly', 'Semi-monthly', 'semi-monthly', 'Semi Monthly', 'SEMI_MONTHLY', 'SEMI-MONTHLY'];
                break;
            case 'monthly':
                $variations = ['monthly', 'Monthly', 'MONTHLY'];
                break;
        }

        return array_unique($variations);
    }

    /**
     * Get current and upcoming payroll periods for a specific schedule setting
     */
    private function getCurrentMonthPeriodsForSchedule($setting)
    {
        $today = \Carbon\Carbon::now();
        $periods = [];

        switch ($setting->code) {
            case 'weekly':
                // Get weekly configuration from cutoff_periods
                $weeklyConfig = $setting->cutoff_periods[0] ?? [
                    'start_day' => 'monday',
                    'end_day' => 'friday',
                    'pay_day' => 'friday'
                ];

                $startDayNum = $this->getDayOfWeekNumber($weeklyConfig['start_day'] ?? 'monday');
                $endDayNum = $this->getDayOfWeekNumber($weeklyConfig['end_day'] ?? 'friday');

                // Find current week period
                $currentWeekStart = $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                while ($currentWeekStart->dayOfWeek !== $startDayNum) {
                    $currentWeekStart->addDay();
                    if ($currentWeekStart->gt($today)) {
                        $currentWeekStart->subWeek();
                        break;
                    }
                }

                // Generate only current period initially, then next periods if current date has passed
                for ($i = 0; $i < 4; $i++) {
                    $weekStart = $currentWeekStart->copy()->addWeeks($i);
                    $weekEnd = $weekStart->copy();

                    // Calculate end day of week
                    $daysToAdd = ($endDayNum - $startDayNum);
                    if ($daysToAdd < 0) $daysToAdd += 7; // Handle week wrap-around
                    $weekEnd->addDays($daysToAdd);

                    $isCurrent = $today->between($weekStart, $weekEnd);
                    $isPast = $today->gt($weekEnd);

                    // Show current period always, future periods only if current period has ended
                    if ($isCurrent || ($i > 0 && !$this->hasCurrentPeriod($periods))) {
                        // Calculate pay date
                        $payDayNum = $this->getDayOfWeekNumber($weeklyConfig['pay_day'] ?? 'friday');
                        $payDate = $weekStart->copy();
                        while ($payDate->dayOfWeek !== $payDayNum) {
                            $payDate->addDay();
                        }

                        // Adjust for holidays if configured
                        if ($setting->move_if_holiday || $setting->move_if_weekend) {
                            $payDate = $this->adjustDateForHolidays($setting, $payDate);
                        }

                        $periods[] = [
                            'id' => $setting->code . '_' . $weekStart->format('Y_m_d'),
                            'pay_schedule' => $setting->code,
                            'period_display' => $weekStart->format('M j') . '–' . $weekEnd->format('j, Y'),
                            'pay_date_display' => $payDate->format('M d, Y'),
                            'period_start' => $weekStart->format('Y-m-d'),
                            'period_end' => $weekEnd->format('Y-m-d'),
                            'pay_date' => $payDate->format('Y-m-d'),
                            'setting_id' => $setting->id,
                            'is_current' => $isCurrent
                        ];

                        // If this is current period, we're done
                        if ($isCurrent) break;
                    }
                }
                break;

            case 'semi_monthly':
                // Get semi-monthly configuration from cutoff_periods
                $semiConfig = is_string($setting->cutoff_periods)
                    ? json_decode($setting->cutoff_periods, true)
                    : $setting->cutoff_periods;

                if (is_array($semiConfig) && count($semiConfig) >= 2) {
                    // Determine current period
                    $currentDay = $today->day;
                    $showFirstPeriod = $currentDay <= 15;
                    $showSecondPeriod = $currentDay >= 16;

                    // First period (1st-15th) - show if we're in it or if it's future
                    if ($showFirstPeriod) {
                        $firstPeriod = $semiConfig[0];
                        $firstStart = $this->setDayOfMonth($today->copy(), $firstPeriod['start_day'] ?? 1);
                        $firstEnd = $this->setDayOfMonth($today->copy(), $firstPeriod['end_day'] ?? 15);

                        // Calculate pay date for first period
                        $payDay = $firstPeriod['pay_day'] ?? 15;
                        if ($payDay === -1 || $payDay === 'last') {
                            $firstPayDate = $today->copy()->endOfMonth();
                        } else {
                            $firstPayDate = $this->setDayOfMonth($today->copy(), $payDay);
                        }

                        if ($setting->move_if_holiday || $setting->move_if_weekend) {
                            $firstPayDate = $this->adjustDateForHolidays($setting, $firstPayDate);
                        }

                        $periods[] = [
                            'id' => $setting->code . '_' . $firstStart->format('Y_m') . '_1',
                            'pay_schedule' => $setting->code,
                            'period_display' => $firstStart->format('M j') . '–' . $firstEnd->format('j, Y'),
                            'pay_date_display' => $firstPayDate->format('M d, Y'),
                            'period_start' => $firstStart->format('Y-m-d'),
                            'period_end' => $firstEnd->format('Y-m-d'),
                            'pay_date' => $firstPayDate->format('Y-m-d'),
                            'setting_id' => $setting->id,
                            'is_current' => $today->between($firstStart, $firstEnd)
                        ];
                    }

                    // Second period (16th-end of month) - show if we're in it or if first period has passed
                    if ($showSecondPeriod) {
                        $secondPeriod = $semiConfig[1];
                        $secondStart = $this->setDayOfMonth($today->copy(), $secondPeriod['start_day'] ?? 16);

                        // Handle end day
                        $endDay = $secondPeriod['end_day'] ?? -1;
                        if ($endDay === -1 || $endDay === 'last') {
                            $secondEnd = $today->copy()->endOfMonth();
                        } else {
                            $secondEnd = $this->setDayOfMonth($today->copy(), $endDay);
                        }

                        // Calculate pay date for second period
                        $payDay = $secondPeriod['pay_day'] ?? -1;
                        if ($payDay === -1 || $payDay === 'last') {
                            $secondPayDate = $today->copy()->endOfMonth();
                        } else {
                            $secondPayDate = $this->setDayOfMonth($today->copy(), $payDay);
                        }

                        if ($setting->move_if_holiday || $setting->move_if_weekend) {
                            $secondPayDate = $this->adjustDateForHolidays($setting, $secondPayDate);
                        }

                        $periods[] = [
                            'id' => $setting->code . '_' . $secondStart->format('Y_m') . '_2',
                            'pay_schedule' => $setting->code,
                            'period_display' => $secondStart->format('M j') . '–' . $secondEnd->format('j, Y'),
                            'pay_date_display' => $secondPayDate->format('M d, Y'),
                            'period_start' => $secondStart->format('Y-m-d'),
                            'period_end' => $secondEnd->format('Y-m-d'),
                            'pay_date' => $secondPayDate->format('Y-m-d'),
                            'setting_id' => $setting->id,
                            'is_current' => $today->between($secondStart, $secondEnd)
                        ];
                    }
                }
                break;

            case 'monthly':
                // Get monthly configuration from cutoff_periods - always show current month
                $monthlyConfig = $setting->cutoff_periods[0] ?? [
                    'start_day' => 1,
                    'end_day' => 'last',
                    'pay_day' => 'last'
                ];

                // Full month period using configured start/end days
                $startDay = $monthlyConfig['start_day'] ?? 1;
                $periodStart = $this->setDayOfMonth($today->copy(), $startDay);

                $endDay = $monthlyConfig['end_day'] ?? 'last';
                if ($endDay === 'last' || $endDay === -1) {
                    $periodEnd = $today->copy()->endOfMonth();
                } else {
                    $periodEnd = $this->setDayOfMonth($today->copy(), $endDay);
                }

                // Calculate pay date
                $payDay = $monthlyConfig['pay_day'] ?? 'last';
                if ($payDay === 'last' || $payDay === -1) {
                    $payDate = $today->copy()->endOfMonth();
                } else {
                    $payDate = $this->setDayOfMonth($today->copy(), $payDay);
                }

                if ($setting->move_if_holiday || $setting->move_if_weekend) {
                    $payDate = $this->adjustDateForHolidays($setting, $payDate);
                }

                $periods[] = [
                    'id' => $setting->code . '_' . $periodStart->format('Y_m'),
                    'pay_schedule' => $setting->code,
                    'period_display' => $periodStart->format('M j') . '–' . $periodEnd->format('j, Y'),
                    'pay_date_display' => $payDate->format('M d, Y'),
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'pay_date' => $payDate->format('Y-m-d'),
                    'setting_id' => $setting->id,
                    'is_current' => $today->between($periodStart, $periodEnd)
                ];
                break;
        }

        return $periods;
    }

    /**
     * Check if periods array has a current period
     */
    private function hasCurrentPeriod($periods)
    {
        foreach ($periods as $period) {
            if (isset($period['is_current']) && $period['is_current']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get current period display for schedule selection page
     */
    private function getCurrentPeriodDisplayForSchedule($setting)
    {
        $today = \Carbon\Carbon::now();

        switch ($setting->code) {
            case 'weekly':
                // Find current week period using cutoff_periods configuration
                $weeklyConfig = $setting->cutoff_periods[0] ?? [
                    'start_day' => 'monday',
                    'end_day' => 'friday',
                    'pay_day' => 'friday'
                ];

                $startDayNum = $this->getDayOfWeekNumber($weeklyConfig['start_day'] ?? 'monday');
                $endDayNum = $this->getDayOfWeekNumber($weeklyConfig['end_day'] ?? 'friday');

                // Find the start of current week containing today
                $weekStart = $today->copy();

                // Go backward to find the correct start day
                while ($weekStart->dayOfWeek !== $startDayNum) {
                    $weekStart->subDay();
                }

                // Calculate the end day
                $weekEnd = $weekStart->copy();
                $daysToAdd = ($endDayNum - $startDayNum);
                if ($daysToAdd < 0) $daysToAdd += 7;
                $weekEnd->addDays($daysToAdd);

                // Make sure today falls within this period, if not adjust
                if (!$today->between($weekStart, $weekEnd)) {
                    if ($today->lt($weekStart)) {
                        // Move back one week
                        $weekStart->subWeek();
                        $weekEnd->subWeek();
                    } else {
                        // Move forward one week
                        $weekStart->addWeek();
                        $weekEnd->addWeek();
                    }
                }

                return $weekStart->format('M j') . '–' . $weekEnd->format('j');

            case 'semi_monthly':
                // Use cutoff_periods configuration for semi-monthly periods
                $semiConfig = $setting->cutoff_periods ?? [];
                $semiConfig = is_string($semiConfig)
                    ? json_decode($semiConfig, true)
                    : $semiConfig;

                if (is_array($semiConfig) && count($semiConfig) >= 2) {
                    $currentDay = $today->day;

                    // Check which period we're currently in based on configured cutoff dates
                    $firstPeriod = $semiConfig[0];
                    $secondPeriod = $semiConfig[1];

                    $firstStart = $firstPeriod['start_day'] ?? 1;
                    $firstEnd = $firstPeriod['end_day'] ?? 15;
                    $secondStart = $secondPeriod['start_day'] ?? 16;
                    $secondEnd = $secondPeriod['end_day'] ?? 'last';

                    // Determine if we're in first or second period
                    if ($currentDay >= $firstStart && $currentDay <= $firstEnd) {
                        // First period
                        return $today->format('M') . ' ' . $firstStart . '–' . $firstEnd;
                    } else {
                        // Second period
                        $endDisplay = ($secondEnd === 'last') ? $today->copy()->endOfMonth()->format('d') : $secondEnd;
                        return $today->format('M') . ' ' . $secondStart . '–' . $endDisplay;
                    }
                } else {
                    // Fallback to default 1-15, 16-end if no configuration
                    if ($today->day <= 15) {
                        return $today->format('M') . ' 1–15';
                    } else {
                        return $today->format('M') . ' 16–' . $today->copy()->endOfMonth()->format('j');
                    }
                }

            case 'monthly':
                // Use cutoff_periods configuration for monthly period
                $monthlyConfig = $setting->cutoff_periods[0] ?? [
                    'start_day' => 1,
                    'end_day' => 'last'
                ];

                $startDay = $monthlyConfig['start_day'] ?? 1;
                $endDay = $monthlyConfig['end_day'] ?? 'last';

                $endDisplay = ($endDay === 'last') ? $today->copy()->endOfMonth()->format('d') : $endDay;

                return $today->format('M') . ' ' . $startDay . '–' . $endDisplay;

            default:
                return 'Current Period';
        }
    }

    /**
     * Calculate pay date for weekly schedule
     */
    private function calculatePayDateForWeekly($setting, $weekEnd)
    {
        $weeklyConfig = $setting->cutoff_periods[0] ?? [
            'start_day' => 'monday',
            'end_day' => 'friday',
            'pay_day' => 'friday'
        ];

        $payDayName = $weeklyConfig['pay_day'];
        $payDayNum = $this->getDayOfWeekNumber($payDayName);

        // Find the pay day in the same week as week end
        $payDate = $weekEnd->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        while ($payDate->dayOfWeek !== $payDayNum) {
            $payDate->addDay();
        }

        // If pay day is before week end, it might be next week
        if ($payDate->lt($weekEnd->copy()->startOfWeek())) {
            $payDate->addWeek();
        }

        return $this->adjustDateForHolidays($setting, $payDate);
    }

    /**
     * Calculate pay date based on schedule setting and cutoff rules (legacy method for backward compatibility)
     */
    private function calculatePayDate($setting, $cutoffEnd, $period = null)
    {
        // Use new methods for better accuracy
        if ($setting->code === 'weekly') {
            return $this->calculatePayDateForWeekly($setting, $cutoffEnd);
        }

        // Default pay date calculation for semi-monthly and monthly
        switch ($setting->code) {
            case 'semi_monthly':
                if ($period === 'first_half') {
                    $semiConfig = $setting->semi_monthly_config;
                    $payDay = $semiConfig['first_period']['pay_day'];
                    if ($payDay === -1 || $payDay === 'last') {
                        $payDate = $cutoffEnd->copy()->endOfMonth();
                    } else {
                        $payDate = $this->setDayOfMonth($cutoffEnd->copy(), $payDay);
                    }
                } else {
                    $semiConfig = $setting->semi_monthly_config;
                    $payDay = $semiConfig['second_period']['pay_day'];
                    if ($payDay === -1 || $payDay === 'last') {
                        $payDate = $cutoffEnd->copy(); // Last day of month
                    } else {
                        $payDate = $this->setDayOfMonth($cutoffEnd->copy(), $payDay);
                    }
                }
                return $setting->adjustDateForHolidays($payDate);

            case 'monthly':
                if ($setting->monthly_pay_day === -1 || $setting->monthly_pay_day === 'last') {
                    $payDate = $cutoffEnd->copy(); // Last day of month
                } else {
                    $payDate = $this->setDayOfMonth($cutoffEnd->copy(), $setting->monthly_pay_day);
                }
                return $setting->adjustDateForHolidays($payDate);

            default:
                return $cutoffEnd->copy()->addDays($setting->payday_offset_days ?? 0);
        }
    }

    /**
     * Convert day name to day of week number (0=Sunday, 1=Monday, ... 6=Saturday) - Carbon standard
     */
    private function getDayOfWeekNumber($dayName)
    {
        $days = [
            'sunday' => 0,
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6
        ];

        return $days[strtolower($dayName)] ?? 5; // Default to Friday
    }
    private function getFallbackPeriod($scheduleType, $today)
    {
        switch ($scheduleType) {
            case 'weekly':
                $startOfWeek = $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                $endOfWeek = $today->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                return [
                    'schedule_type' => 'weekly',
                    'period_start' => $startOfWeek->format('Y-m-d'),
                    'period_end' => $endOfWeek->format('Y-m-d'),
                    'pay_date' => $endOfWeek->addDays(3)->format('Y-m-d'), // Pay on Wednesday after week ends
                    'period_name' => $startOfWeek->format('M j') . ' - ' . $endOfWeek->format('M j, Y')
                ];

            case 'semi_monthly':
                $day = $today->day;
                if ($day <= 15) {
                    $start = $today->copy()->startOfMonth();
                    $end = $today->copy()->startOfMonth()->addDays(14);
                    $payDate = $end->copy()->addDays(3);
                } else {
                    $start = $today->copy()->startOfMonth()->addDays(15);
                    $end = $today->copy()->endOfMonth();
                    $payDate = $end->copy()->addDays(3);
                }
                return [
                    'schedule_type' => 'semi_monthly',
                    'period_start' => $start->format('Y-m-d'),
                    'period_end' => $end->format('Y-m-d'),
                    'pay_date' => $payDate->format('Y-m-d'),
                    'period_name' => $start->format('M j') . ' - ' . $end->format('M j, Y')
                ];

            case 'monthly':
            default:
                $start = $today->copy()->startOfMonth();
                $end = $today->copy()->endOfMonth();
                return [
                    'schedule_type' => 'monthly',
                    'period_start' => $start->format('Y-m-d'),
                    'period_end' => $end->format('Y-m-d'),
                    'pay_date' => $end->addDays(5)->format('Y-m-d'), // Pay 5 days after month ends
                    'period_name' => $start->format('M Y')
                ];
        }
    }

    /**
     * Calculate the appropriate period for a specific schedule type
     */
    private function calculatePeriodForSchedule($setting, $currentDate)
    {
        switch ($setting->pay_type) {
            case 'weekly':
                return $this->calculateWeeklyPeriod($setting, $currentDate);
            case 'semi_monthly':
                return $this->calculateSemiMonthlyPeriod($setting, $currentDate);
            case 'monthly':
                return $this->calculateMonthlyPeriod($setting, $currentDate);
            default:
                return null;
        }
    }

    /**
     * Calculate weekly period
     */
    private function calculateWeeklyPeriod($setting, $currentDate)
    {
        // Find the current week period
        $startOfWeek = $currentDate->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $endOfWeek = $currentDate->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        $payDate = $endOfWeek->copy();
        if ($setting->payday_offset_days) {
            $payDate = $endOfWeek->copy()->addDays($setting->payday_offset_days);
        }

        return [
            'start' => $startOfWeek->format('Y-m-d'),
            'end' => $endOfWeek->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
            'name' => $startOfWeek->format('M j') . ' - ' . $endOfWeek->format('M j, Y')
        ];
    }

    /**
     * Calculate semi-monthly period
     */
    private function calculateSemiMonthlyPeriod($setting, $currentDate)
    {
        // Check if the setting has cutoff_periods configured
        if (isset($setting->cutoff_periods) && is_array($setting->cutoff_periods) && count($setting->cutoff_periods) >= 2) {
            // Use the new flexible cutoff period calculation
            $cutoffPeriods = $setting->cutoff_periods;
            $currentDay = $currentDate->day;

            // Parse cutoff periods to get numeric days
            $firstPeriodStart = $this->parseDayNumber($cutoffPeriods[0]['start_day']);
            $firstPeriodEnd = $this->parseDayNumber($cutoffPeriods[0]['end_day']);
            $secondPeriodStart = $this->parseDayNumber($cutoffPeriods[1]['start_day']);
            $secondPeriodEnd = $this->parseDayNumber($cutoffPeriods[1]['end_day']);

            $firstPeriodPayDay = $this->parseDayNumber($cutoffPeriods[0]['pay_date'] ?? $firstPeriodEnd);
            $secondPeriodPayDay = $this->parseDayNumber($cutoffPeriods[1]['pay_date'] ?? $secondPeriodEnd);

            // Check if we're in the first or second period
            $inFirstPeriod = false;
            if ($firstPeriodStart > $firstPeriodEnd) {
                // First period crosses month boundary (e.g., 21st to 5th)
                $inFirstPeriod = ($currentDay >= $firstPeriodStart || $currentDay <= $firstPeriodEnd);
            } else {
                // First period is within same month
                $inFirstPeriod = ($currentDay >= $firstPeriodStart && $currentDay <= $firstPeriodEnd);
            }

            if ($inFirstPeriod) {
                // We're in the first period
                if ($firstPeriodStart > $firstPeriodEnd) {
                    // Period crosses month boundary
                    if ($currentDay >= $firstPeriodStart) {
                        // Currently in the start month (last month)
                        $start = $currentDate->copy()->day($firstPeriodStart);
                        $end = $currentDate->copy()->addMonth()->day($firstPeriodEnd);
                        $periodName = $start->format('M d') . ' - ' . $end->format('M d');
                    } else {
                        // Currently in the end month (current month)
                        $start = $currentDate->copy()->subMonth()->day($firstPeriodStart);
                        $end = $currentDate->copy()->day($firstPeriodEnd);
                        $periodName = $start->format('M d') . ' - ' . $end->format('M d');
                    }
                } else {
                    // Period is within same month
                    $start = $currentDate->copy()->day($firstPeriodStart);
                    if ($firstPeriodEnd == 31 || $firstPeriodEnd === 'EOD' || $firstPeriodEnd === 'eod' || $firstPeriodEnd === 'EOM') {
                        $end = $currentDate->copy()->endOfMonth();
                        $periodName = $start->format('M') . ' ' . $firstPeriodStart . '-EOM';
                    } else {
                        $end = $currentDate->copy()->day($firstPeriodEnd);
                        $periodName = $start->format('M') . ' ' . $firstPeriodStart . '-' . $firstPeriodEnd;
                    }
                }
                $payDay = $firstPeriodPayDay;
            } else {
                // We're in the second period
                if ($secondPeriodStart > $secondPeriodEnd) {
                    // Period crosses month boundary
                    if ($currentDay >= $secondPeriodStart) {
                        // Currently in the start month (last month)
                        $start = $currentDate->copy()->day($secondPeriodStart);
                        $end = $currentDate->copy()->addMonth()->day($secondPeriodEnd);
                        $periodName = $start->format('M d') . ' - ' . $end->format('M d');
                    } else {
                        // Currently in the end month (current month)
                        $start = $currentDate->copy()->subMonth()->day($secondPeriodStart);
                        $end = $currentDate->copy()->day($secondPeriodEnd);
                        $periodName = $start->format('M d') . ' - ' . $end->format('M d');
                    }
                } else {
                    // Period is within same month
                    $start = $currentDate->copy()->day($secondPeriodStart);
                    if ($secondPeriodEnd == 31 || $secondPeriodEnd === 'EOD' || $secondPeriodEnd === 'eod' || $secondPeriodEnd === 'EOM') {
                        $end = $currentDate->copy()->endOfMonth();
                        $periodName = $start->format('M') . ' ' . $secondPeriodStart . '-EOM';
                    } else {
                        $end = $currentDate->copy()->day($secondPeriodEnd);
                        $periodName = $start->format('M') . ' ' . $secondPeriodStart . '-' . $secondPeriodEnd;
                    }
                }
                $payDay = $secondPeriodPayDay;
            }

            // Set pay date - always in current month
            if ($payDay == 31 || $payDay === 'EOD' || $payDay === 'eod' || $payDay === 'EOM') {
                $payDate = $currentDate->copy()->endOfMonth();
            } else {
                $payDate = $currentDate->copy()->day($payDay);
            }

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'pay_date' => $payDate->format('Y-m-d'),
                'name' => $periodName
            ];
        } else {
            // Fallback to traditional 1-15, 16-31 calculation
            $day = $currentDate->day;
            $month = $currentDate->month;
            $year = $currentDate->year;

            if ($day <= 15) {
                // First half of the month (1st to 15th)
                $start = \Carbon\Carbon::create($year, $month, 1);
                $end = \Carbon\Carbon::create($year, $month, 15);
                $periodName = $start->format('M') . ' 1-15';
            } else {
                // Second half of the month (16th to end)
                $start = \Carbon\Carbon::create($year, $month, 16);
                $end = \Carbon\Carbon::create($year, $month)->endOfMonth();
                $periodName = $start->format('M') . ' 16-' . $end->day;
            }

            $payDate = $end->copy();
            if ($setting->payday_offset_days) {
                $payDate = $end->copy()->addDays($setting->payday_offset_days);
            }

            return [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'pay_date' => $payDate->format('Y-m-d'),
                'name' => $periodName
            ];
        }
    }

    /**
     * Calculate monthly period
     */
    private function calculateMonthlyPeriod($setting, $currentDate)
    {
        $start = $currentDate->copy()->startOfMonth();
        $end = $currentDate->copy()->endOfMonth();

        $payDate = $end->copy();
        if ($setting->payday_offset_days) {
            $payDate = $end->copy()->addDays($setting->payday_offset_days);
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
            'name' => $start->format('M Y')
        ];
    }

    /**
     * Safe way to set day of month avoiding Carbon 3.x type issues
     */
    private function setDayOfMonth($carbon, $day)
    {
        if ($day === 'last' || $day === -1) {
            return $carbon->endOfMonth();
        }

        // Ensure day is integer and within valid range
        $dayInt = (int) $day;
        $dayInt = max(1, min(31, $dayInt));

        return $carbon->startOfMonth()->addDays($dayInt - 1);
    }

    /**
     * Adjust date for holidays and weekends
     */
    private function adjustDateForHolidays($setting, $date)
    {
        // If it's weekend and move_if_weekend is enabled
        if ($setting->move_if_weekend && ($date->isWeekend())) {
            if ($setting->move_direction === 'before') {
                // Move to previous Friday
                while ($date->isWeekend()) {
                    $date->subDay();
                }
            } else {
                // Move to next Monday  
                while ($date->isWeekend()) {
                    $date->addDay();
                }
            }
        }

        // Additional holiday checking could be implemented here
        // if ($setting->move_if_holiday) {
        //     // Check against holiday table and adjust
        // }

        return $date;
    }

    /**
     * Calculate the current pay period for a given schedule setting (not next)
     */
    private function calculateCurrentPayPeriod($scheduleSetting)
    {
        $today = Carbon::now();

        switch ($scheduleSetting->code) {
            case 'weekly':
                return $this->calculateCurrentWeeklyPayPeriod($scheduleSetting, $today);

            case 'semi_monthly':
                return $this->calculateCurrentSemiMonthlyPayPeriod($scheduleSetting, $today);

            case 'monthly':
                return $this->calculateCurrentMonthlyPayPeriod($scheduleSetting, $today);

            case 'daily':
                return $this->calculateCurrentDailyPayPeriod($scheduleSetting, $today);

            default:
                // Fallback to weekly if unknown
                return $this->calculateCurrentWeeklyPayPeriod($scheduleSetting, $today);
        }
    }

    /**
     * Calculate the previous pay period based on current pay period
     */
    private function calculatePreviousPayPeriod($scheduleSetting)
    {
        $currentPeriod = $this->calculateCurrentPayPeriod($scheduleSetting);
        $currentStart = Carbon::parse($currentPeriod['start']);

        switch ($scheduleSetting->code) {
            case 'weekly':
                // Go back one week
                $previousStart = $currentStart->copy()->subWeek();
                return $this->calculateCurrentWeeklyPayPeriod($scheduleSetting, $previousStart);

            case 'semi_monthly':
                // For semi-monthly, we need to handle crossing month boundaries
                $cutoffPeriods = $scheduleSetting->cutoff_periods;
                if (is_string($cutoffPeriods)) {
                    $cutoffPeriods = json_decode($cutoffPeriods, true);
                }

                $firstPeriodStart = $this->parseDayNumber($cutoffPeriods[0]['start_day'] ?? 1);

                if ($currentStart->day == $firstPeriodStart) {
                    // Current is first period, previous is second period of last month
                    $previousDate = $currentStart->copy()->subMonth()->endOfMonth();
                } else {
                    // Current is second period, previous is first period of same month
                    $previousDate = $currentStart->copy()->startOfMonth();
                }

                return $this->calculateCurrentSemiMonthlyPayPeriod($scheduleSetting, $previousDate);

            case 'monthly':
                // Go back one month
                $previousDate = $currentStart->copy()->subMonth();
                return $this->calculateCurrentMonthlyPayPeriod($scheduleSetting, $previousDate);

            case 'daily':
                // Go back one day
                $previousDate = $currentStart->copy()->subDay();
                return $this->calculateCurrentDailyPayPeriod($scheduleSetting, $previousDate);

            default:
                // Fallback to weekly
                $previousStart = $currentStart->copy()->subWeek();
                return $this->calculateCurrentWeeklyPayPeriod($scheduleSetting, $previousStart);
        }
    }

    /**
     * Calculate current weekly pay period based on settings
     */
    private function calculateCurrentWeeklyPayPeriod($scheduleSetting, $currentDate)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || !isset($cutoffPeriods[0]) || !is_array($cutoffPeriods[0])) {
            // Fallback to Monday-Friday if no settings
            $cutoffPeriods = [['start_day' => 'monday', 'end_day' => 'friday', 'pay_day' => 'friday']];
        }

        $cutoff = $cutoffPeriods[0];
        $startDay = $cutoff['start_day'];
        $endDay = $cutoff['end_day'];
        $payDay = $cutoff['pay_day'];

        // Find the current period that contains today's date
        $periodStart = $this->getWeekStartForDay($currentDate, $startDay);
        $periodEnd = $this->getWeekDayForDate($periodStart, $endDay);

        // Check if current date is within this period
        if ($currentDate->lt($periodStart)) {
            // We're before the current period, move back one week
            $periodStart = $periodStart->subWeek();
            $periodEnd = $this->getWeekDayForDate($periodStart, $endDay);
        } elseif ($currentDate->gt($periodEnd)) {
            // We're after the current period end, but before next period start
            // Check if the next period has started
            $nextPeriodStart = $periodStart->copy()->addWeek();
            if ($currentDate->gte($nextPeriodStart)) {
                // Next period has started
                $periodStart = $nextPeriodStart;
                $periodEnd = $this->getWeekDayForDate($periodStart, $endDay);
            }
            // Otherwise, stay with current period (we're in between periods)
        }

        $payDate = $this->getWeekDayForDate($periodStart, $payDay);

        // Adjust pay date if it's before period end
        if ($payDate->lt($periodEnd)) {
            $payDate = $payDate->addWeek();
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate current semi-monthly pay period based on settings
     */
    private function calculateCurrentSemiMonthlyPayPeriod($scheduleSetting, $currentDate)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || !isset($cutoffPeriods[0]) || !is_array($cutoffPeriods[0])) {
            // Fallback to 1-15 and 16-31
            $cutoffPeriods = [
                ['start_day' => 1, 'end_day' => 15, 'pay_date' => 16],
                ['start_day' => 16, 'end_day' => 31, 'pay_date' => 5]
            ];
        }

        $currentDay = $currentDate->day;

        // Parse cutoff periods to get numeric days
        $firstPeriodStart = $this->parseDayNumber($cutoffPeriods[0]['start_day']);
        $firstPeriodEnd = $this->parseDayNumber($cutoffPeriods[0]['end_day']);
        $secondPeriodStart = $this->parseDayNumber($cutoffPeriods[1]['start_day']);
        $secondPeriodEnd = $this->parseDayNumber($cutoffPeriods[1]['end_day']);

        // Determine which period we're currently in
        $firstPeriodPayDay = $this->parseDayNumber($cutoffPeriods[0]['pay_date'] ?? $firstPeriodEnd);
        $secondPeriodPayDay = $this->parseDayNumber($cutoffPeriods[1]['pay_date'] ?? $secondPeriodEnd);

        // Check if we're in the first or second period
        $inFirstPeriod = false;
        if ($firstPeriodStart > $firstPeriodEnd) {
            // First period crosses month boundary (e.g., 21st to 5th)
            $inFirstPeriod = ($currentDay >= $firstPeriodStart || $currentDay <= $firstPeriodEnd);
        } else {
            // First period is within same month
            $inFirstPeriod = ($currentDay >= $firstPeriodStart && $currentDay <= $firstPeriodEnd);
        }

        if ($inFirstPeriod) {
            // We're in the first period
            if ($firstPeriodStart > $firstPeriodEnd) {
                // Period crosses month boundary
                if ($currentDay >= $firstPeriodStart) {
                    // Currently in the start month (last month)
                    $periodStart = $currentDate->copy()->day($firstPeriodStart);
                    $periodEnd = $currentDate->copy()->addMonth()->day($firstPeriodEnd);
                } else {
                    // Currently in the end month (current month)
                    $periodStart = $currentDate->copy()->subMonth()->day($firstPeriodStart);
                    $periodEnd = $currentDate->copy()->day($firstPeriodEnd);
                }
            } else {
                // Period is within same month
                $periodStart = $currentDate->copy()->day($firstPeriodStart);
                if ($firstPeriodEnd == 31) {
                    $periodEnd = $currentDate->copy()->endOfMonth();
                } else {
                    $periodEnd = $currentDate->copy()->day($firstPeriodEnd);
                }
            }
            $payDay = $firstPeriodPayDay;
        } else {
            // We're in the second period
            if ($secondPeriodStart > $secondPeriodEnd) {
                // Period crosses month boundary
                if ($currentDay >= $secondPeriodStart) {
                    // Currently in the start month (last month)
                    $periodStart = $currentDate->copy()->day($secondPeriodStart);
                    $periodEnd = $currentDate->copy()->addMonth()->day($secondPeriodEnd);
                } else {
                    // Currently in the end month (current month)
                    $periodStart = $currentDate->copy()->subMonth()->day($secondPeriodStart);
                    $periodEnd = $currentDate->copy()->day($secondPeriodEnd);
                }
            } else {
                // Period is within same month
                $periodStart = $currentDate->copy()->day($secondPeriodStart);
                if ($secondPeriodEnd == 31) {
                    $periodEnd = $currentDate->copy()->endOfMonth();
                } else {
                    $periodEnd = $currentDate->copy()->day($secondPeriodEnd);
                }
            }
            $payDay = $secondPeriodPayDay;
        }

        // Set pay date - always in current month
        if ($payDay == 31 || $payDay === 'EOD' || $payDay === 'eod' || $payDay === 'EOM') {
            $payDate = $currentDate->copy()->endOfMonth();
        } else {
            $payDate = $currentDate->copy()->day($payDay);
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate current monthly pay period based on settings
     */
    private function calculateCurrentMonthlyPayPeriod($scheduleSetting, $currentDate)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || !isset($cutoffPeriods[0]) || !is_array($cutoffPeriods[0])) {
            // Fallback to 1-31
            $cutoffPeriods = [['start_day' => 1, 'end_day' => 31, 'pay_date' => 31]];
        }

        $cutoff = $cutoffPeriods[0];
        $startDay = $this->parseDayNumber($cutoff['start_day']);
        $endDay = $this->parseDayNumber($cutoff['end_day']);
        $payDay = $this->parseDayNumber($cutoff['pay_date'] ?? $endDay);

        $periodStart = $currentDate->copy()->startOfMonth()->day($startDay);

        if ($endDay == 31 || $endDay === 'EOD' || $endDay === 'eod' || $endDay === 'EOM') {
            $periodEnd = $currentDate->copy()->endOfMonth();
        } else {
            $periodEnd = $currentDate->copy()->startOfMonth()->day($endDay);
        }

        if ($payDay == 31 || $payDay === 'EOD' || $payDay === 'eod' || $payDay === 'EOM') {
            $payDate = $periodEnd->copy()->endOfMonth();
        } else {
            $payDate = $currentDate->copy()->startOfMonth()->day($payDay);
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate current daily pay period based on settings
     */
    private function calculateCurrentDailyPayPeriod($scheduleSetting, $currentDate)
    {
        $periodStart = $currentDate->copy();
        $periodEnd = $currentDate->copy();
        $payDate = $currentDate->copy()->addDay();

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate the current pay period for a PaySchedule model (new multiple schedules)
     */
    private function calculateCurrentPayPeriodForSchedule($paySchedule)
    {
        $today = Carbon::now();

        switch ($paySchedule->type) {
            case 'weekly':
                return $this->calculateCurrentWeeklyPayPeriodForSchedule($paySchedule, $today);
            case 'semi_monthly':
                return $this->calculateCurrentSemiMonthlyPayPeriodForSchedule($paySchedule, $today);
            case 'monthly':
                return $this->calculateCurrentMonthlyPayPeriodForSchedule($paySchedule, $today);
            default:
                throw new \Exception('Invalid schedule type: ' . $paySchedule->type);
        }
    }

    /**
     * Calculate the previous pay period for a PaySchedule model
     */
    private function calculatePreviousPayPeriodForSchedule($paySchedule)
    {
        $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        $currentStart = Carbon::parse($currentPeriod['start']);

        switch ($paySchedule->type) {
            case 'weekly':
                $previousStart = $currentStart->copy()->subWeek();
                return $this->calculateCurrentWeeklyPayPeriodForSchedule($paySchedule, $previousStart);

            case 'semi_monthly':
                // Find which cutoff period we're in and calculate previous using pay date logic
                $periods = $paySchedule->getValidatedCutoffPeriods();
                if (count($periods) >= 2) {
                    $today = Carbon::now();
                    $firstPeriod = $periods[0];
                    $secondPeriod = $periods[1];

                    // Use the same pay date cutoff logic as current period detection
                    $currentPeriodNum = $this->getCurrentPeriodByPayDateCutoff($today, $periods);

                    if ($currentPeriodNum == 1) {
                        // Current is 1st period, previous is 2nd period of previous cycle
                        $prevPeriod = $secondPeriod;
                        $previousDate = $today->copy()->subMonth();
                    } else {
                        // Current is 2nd period, previous is 1st period of current cycle
                        $prevPeriod = $firstPeriod;

                        // For 1st period, check if it's a cross-month period
                        $startDay = (int)$prevPeriod['start_day'];
                        $endDay = (int)$prevPeriod['end_day'];

                        if ($startDay > $endDay) {
                            // Cross-month period - starts in previous month
                            $previousDate = $today->copy()->subMonth();
                        } else {
                            // Same-month period - use current month
                            $previousDate = $today->copy();
                        }
                    }

                    return $this->calculateSemiMonthlyPeriodForSchedule($previousDate->year, $previousDate->month, $prevPeriod);
                }
                break;

            case 'monthly':
                $previousDate = $currentStart->copy()->subMonth();
                return $this->calculateCurrentMonthlyPayPeriodForSchedule($paySchedule, $previousDate);
        }

        return null;
    }

    /**
     * Calculate current weekly pay period for PaySchedule
     */
    private function calculateCurrentWeeklyPayPeriodForSchedule($paySchedule, $currentDate)
    {
        $periods = $paySchedule->getValidatedCutoffPeriods();
        if (empty($periods)) {
            throw new \Exception('No cutoff periods configured for schedule: ' . $paySchedule->name);
        }

        $cutoff = $periods[0];
        $startDayName = $cutoff['start_day'];
        $endDayName = $cutoff['end_day'];
        $payDayName = $cutoff['pay_day'];

        $dayMap = [
            'monday' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY
        ];

        $startDayNum = $dayMap[$startDayName];
        $endDayNum = $dayMap[$endDayName];
        $payDayNum = $dayMap[$payDayName];

        // Find the current week's start and end based on configured days
        $periodStart = $currentDate->copy()->startOfWeek($startDayNum);

        if ($endDayNum >= $startDayNum) {
            // Same week (e.g., Monday to Friday)
            $periodEnd = $periodStart->copy()->startOfWeek($startDayNum)->addDays($endDayNum - $startDayNum);
        } else {
            // Crosses weeks (e.g., Saturday to Friday)
            $periodEnd = $periodStart->copy()->startOfWeek($startDayNum)->addWeek()->addDays($endDayNum - 1);
        }

        // Calculate pay date
        if ($payDayNum >= $startDayNum && $payDayNum <= $endDayNum) {
            // Pay day is within the work week
            $payDate = $periodStart->copy()->startOfWeek($startDayNum)->addDays($payDayNum - $startDayNum);
        } else {
            // Pay day is after the work week
            $payDate = $periodEnd->copy()->addDays(1)->startOfWeek($payDayNum);
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate current semi-monthly pay period for PaySchedule
     */
    private function calculateCurrentSemiMonthlyPayPeriodForSchedule($paySchedule, $currentDate)
    {
        $periods = $paySchedule->getValidatedCutoffPeriods();
        if (count($periods) < 2) {
            throw new \Exception('Semi-monthly schedule must have 2 cutoff periods: ' . $paySchedule->name);
        }

        $currentDay = $currentDate->day;
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        // Determine which period we're in based on pay date cutoffs
        $firstPeriod = $periods[0];
        $secondPeriod = $periods[1];

        // NEW LOGIC: Use pay date cutoffs to determine current period
        $currentPeriodNumber = $this->getCurrentPeriodByPayDateCutoff($currentDate, $periods);

        if ($currentPeriodNumber === 1) {
            $period = $firstPeriod;
            $contextMonth = $this->determineCorrectMonthContext($currentDate, $period);
            $periodData = $this->calculateSemiMonthlyPeriodForSchedule($contextMonth['year'], $contextMonth['month'], $period);
            $periodData['period_number'] = 1;
            return $periodData;
        } else {
            $period = $secondPeriod;
            $contextMonth = $this->determineCorrectMonthContext($currentDate, $period);
            $periodData = $this->calculateSemiMonthlyPeriodForSchedule($contextMonth['year'], $contextMonth['month'], $period);
            $periodData['period_number'] = 2;
            return $periodData;
        }
    }

    /**
     * Determine the correct month context for cross-month periods
     * Simple rule: if we're before/on the pay date, and pay date comes after period end,
     * then the period spans from previous month
     */
    private function determineCorrectMonthContext($currentDate, $period)
    {
        $startDay = (int)$period['start_day'];
        $endDay = (int)$period['end_day'];
        $payDay = (int)$period['pay_date'];
        $currentDay = $currentDate->day;

        if ($startDay <= $endDay) {
            // Same-month period - use current month
            return ['year' => $currentDate->year, 'month' => $currentDate->month];
        } else {
            // Cross-month period (start_day > end_day)
            // The key insight: pay date position tells us about the period instance

            if ($payDay > $endDay) {
                // Pay date is after the period end day (e.g., period 21-5, pay 10)
                // This means pay happens AFTER crossing to next month
                // If we're currently <= pay day, we're in the "previous month started" instance
                if ($currentDay <= $payDay) {
                    $previous = $currentDate->copy()->subMonth();
                    return ['year' => $previous->year, 'month' => $previous->month];
                } else {
                    // After pay day, so we're in current month's instance
                    return ['year' => $currentDate->year, 'month' => $currentDate->month];
                }
            } else {
                // Pay date is within the period end portion (e.g., period 26-10, pay 15 -> pay comes after end)
                // Wait, this case doesn't make sense. Pay date should be after period end for this logic.
                // Let's use the simple rule: if current <= end, use previous month
                if ($currentDay <= $endDay) {
                    $previous = $currentDate->copy()->subMonth();
                    return ['year' => $previous->year, 'month' => $previous->month];
                } else {
                    return ['year' => $currentDate->year, 'month' => $currentDate->month];
                }
            }
        }
    }

    /**
     * Check if current date is within a semi-monthly period based on pay date cutoffs
     * NEW LOGIC: Period is determined by pay dates, not start/end days
     */
    private function isInSemiMonthlyPeriod($currentDate, $period)
    {
        // Get all periods to compare pay dates
        $paySchedule = null;
        $allPeriods = [];

        // We need access to all periods to determine cutoff logic
        // This is a bit of a hack, but we'll improve this approach

        // For now, let's use the old logic as fallback
        $startDay = $period['start_day'];
        $endDay = $period['end_day'];
        $currentDay = $currentDate->day;

        // Case 1: Period within same month (e.g., 1-15)
        if ($startDay <= $endDay) {
            return $currentDay >= $startDay && $currentDay <= $endDay;
        }

        // Case 2: Period spans months (e.g., 21-5 next month)  
        // We're in this period if:
        // - Current day is >= start day (end of current month part)
        // - OR current day is <= end day (beginning of next month part)
        return $currentDay >= $startDay || $currentDay <= $endDay;
    }

    /**
     * NEW METHOD: Determine current period based on pay date cutoffs only
     * Simple logic: current period determined by which pay date we haven't reached yet
     */
    private function getCurrentPeriodByPayDateCutoff($currentDate, $periods)
    {
        $currentDay = $currentDate->day;
        $firstPeriod = $periods[0];
        $secondPeriod = $periods[1];

        // Handle EOD and EOM values
        $firstPayDay = ($firstPeriod['pay_date'] === 'EOD' || $firstPeriod['pay_date'] === 'eod' || $firstPeriod['pay_date'] === 'EOM')
            ? $currentDate->daysInMonth : (int)$firstPeriod['pay_date'];
        $secondPayDay = ($secondPeriod['pay_date'] === 'EOD' || $secondPeriod['pay_date'] === 'eod' || $secondPeriod['pay_date'] === 'EOM')
            ? $currentDate->daysInMonth : (int)$secondPeriod['pay_date'];

        // Corrected pay date cutoff logic:
        // You are IN a period until its pay date (inclusive), then move to next period
        // Example: SEMI-2 has pay dates 10th and 25th
        // - Days 1-10: Period 1 (earning towards 10th pay, including pay day)
        // - Days 11-25: Period 2 (earning towards 25th pay, including pay day)  
        // - Days 26+: Period 1 (new cycle starts)

        if ($firstPayDay <= $secondPayDay) {
            // Normal case: first pay comes before second (e.g., 10th then 25th)
            if ($currentDay <= $firstPayDay) {
                return 1; // Up to and including first pay date - in 1st period
            } elseif ($currentDay <= $secondPayDay) {
                return 2; // Up to and including second pay date - in 2nd period  
            } else {
                return 1; // After second pay - new cycle, in 1st period
            }
        } else {
            // Cross-month case: first pay comes after second (e.g., 25th then 10th next month)
            if ($currentDay <= $secondPayDay) {
                return 1; // Up to and including second pay date - in 1st period
            } elseif ($currentDay <= $firstPayDay) {
                return 2; // Up to and including first pay date - in 2nd period
            } else {
                return 1; // After first pay - new cycle, in 1st period
            }
        }
    }

    /**
     * Helper method to check if current date falls within a period's date range
     * Enhanced to support EOD for start_day, end_day, and proper month context
     */
    private function isInPeriodDateRange($currentDate, $period)
    {
        $startDay = $period['start_day'];
        $endDay = $period['end_day'];
        $currentDay = $currentDate->day;

        // Handle EOD and EOM (End of Day/Month) values with proper month context
        if ($startDay === 'EOD' || $startDay === 'eod' || $startDay === 'EOM') {
            // For start_day EOD/EOM, use last day of current month
            $startDay = $currentDate->daysInMonth;
        } else {
            $startDay = (int)$startDay;
        }

        if ($endDay === 'EOD' || $endDay === 'eod' || $endDay === 'EOM') {
            // For end_day EOD/EOM, determine the appropriate month based on period logic
            if ($startDay <= $currentDate->daysInMonth) {
                // Period likely within same month, use current month's last day
                $endDay = $currentDate->daysInMonth;
            } else {
                // Period might span to next month, use next month's last day context
                $endDay = $currentDate->copy()->addMonth()->daysInMonth;
            }
        } else {
            $endDay = (int)$endDay;
        }

        // Case 1: Period within same month (e.g., 1-15 or 1-EOD)
        if ($startDay <= $endDay) {
            return $currentDay >= $startDay && $currentDay <= $endDay;
        }

        // Case 2: Period spans months (e.g., 21-5 next month or EOD-15)
        return $currentDay >= $startDay || $currentDay <= $endDay;
    }

    /**
     * Calculate semi-monthly period dates with proper month handling for PaySchedule
     * Enhanced EOD support for start_day, end_day, and pay_date with intelligent month detection
     */
    private function calculateSemiMonthlyPeriodForSchedule($year, $month, $period)
    {
        $startDay = $period['start_day'];
        $endDay = $period['end_day'];
        $payDay = $period['pay_date'];

        $baseDate = Carbon::create($year, $month, 1);

        // Enhanced EOD/EOM (End of Day/Month) handling with proper month context
        if ($startDay === 'EOD' || $startDay === 'eod' || $startDay === 'EOM') {
            // For start_day EOD/EOM, use last day of the base month
            $startDay = (int) $baseDate->daysInMonth;
        } else {
            $startDay = (int) $startDay;
        }

        if ($endDay === 'EOD' || $endDay === 'eod' || $endDay === 'EOM') {
            // For end_day EOD/EOM, determine appropriate month based on period flow
            if ($startDay <= $baseDate->daysInMonth) {
                // Period starts within current month, end EOD/EOM is current month's last day
                $endDay = (int) $baseDate->daysInMonth;
            } else {
                // Period spans months, end EOD/EOM is next month's last day
                $endDay = (int) $baseDate->copy()->addMonth()->daysInMonth;
            }
        } else {
            $endDay = (int) $endDay;
        }

        if ($payDay === 'EOD' || $payDay === 'eod' || $payDay === 'EOM') {
            // For pay_date EOD/EOM, determine appropriate month based on period end
            if ($startDay <= $endDay) {
                // Period within same month, pay EOD/EOM is current month's last day
                $payDay = (int) $baseDate->daysInMonth;
            } else {
                // Period spans months, pay EOD/EOM could be next month's last day
                $payDay = (int) $baseDate->copy()->addMonth()->daysInMonth;
            }
        } else {
            $payDay = (int) $payDay;
        }

        // Case 1: Period within same month (start <= end, e.g., 1 <= 15)
        if ($startDay <= $endDay) {
            $periodStart = Carbon::create($year, $month, $startDay);
            $periodEnd = Carbon::create($year, $month, $endDay);

            // For same-month periods, pay date is in the same month
            $payDate = Carbon::create($year, $month, $payDay);
        }
        // Case 2: Period spans months (start > end, e.g., 21 > 5)
        else {
            $periodStart = Carbon::create($year, $month, $startDay);
            $periodEnd = Carbon::create($year, $month)->addMonth()->day($endDay);

            // For cross-month periods, pay date is in the same month as the end date
            // The end date is in the next month, so pay date should also be in the next month
            $payDate = Carbon::create($year, $month)->addMonth()->day($payDay);
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate current monthly pay period for PaySchedule
     */
    private function calculateCurrentMonthlyPayPeriodForSchedule($paySchedule, $currentDate)
    {
        $periods = $paySchedule->getValidatedCutoffPeriods();
        if (empty($periods)) {
            throw new \Exception('No cutoff periods configured for schedule: ' . $paySchedule->name);
        }

        $cutoff = $periods[0];
        $currentMonth = $currentDate->month;
        $currentYear = $currentDate->year;

        $startDay = $cutoff['start_day'];
        $endDay = $cutoff['end_day'];
        $payDay = $cutoff['pay_date'];

        // Handle EOD/EOM (End of Day/Month) values - convert to actual last day of month
        if ($endDay === 'EOD' || $endDay === 'eod' || $endDay === 'EOM') {
            $endDay = (int) Carbon::create($currentYear, $currentMonth)->daysInMonth;
        }
        if ($payDay === 'EOD' || $payDay === 'eod' || $payDay === 'EOM') {
            $payDay = (int) Carbon::create($currentYear, $currentMonth)->daysInMonth;
        }

        // Ensure all day values are integers
        $startDay = (int) $startDay;
        $endDay = (int) $endDay;
        $payDay = (int) $payDay;

        // Handle month transitions for monthly periods
        if ($startDay <= $endDay) {
            // Period within same month (e.g., 1-30)
            $periodStart = Carbon::create($currentYear, $currentMonth, $startDay);
            $periodEnd = Carbon::create($currentYear, $currentMonth, $endDay);

            // Determine pay date month based on comparison logic
            if ($payDay > $endDay) {
                // Pay day comes after end day, so it's in the next month
                $payDate = Carbon::create($currentYear, $currentMonth)->addMonth()->day($payDay);
            } else {
                // Pay day is in the same month as start/end
                $payDate = Carbon::create($currentYear, $currentMonth, $payDay);
            }
        } else {
            // Period spans months (e.g., 21 to 20 next month)
            $periodStart = Carbon::create($currentYear, $currentMonth, $startDay);
            $periodEnd = Carbon::create($currentYear, $currentMonth)->addMonth()->day($endDay);

            // For spanning periods, pay day is typically in the end month (next month)
            if ($payDay <= $endDay) {
                // Pay day is in the same month as end day (next month from start)
                $payDate = Carbon::create($currentYear, $currentMonth)->addMonth()->day($payDay);
            } else {
                // Pay day is after end day, use same month as end
                $payDate = Carbon::create($currentYear, $currentMonth)->addMonth()->day($payDay);
            }
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate the next pay period for a given schedule setting based on actual settings
     */
    private function calculateNextPayPeriod($scheduleSetting)
    {
        $today = Carbon::now();

        switch ($scheduleSetting->code) {
            case 'weekly':
                return $this->calculateWeeklyPayPeriod($scheduleSetting, $today);

            case 'semi_monthly':
                return $this->calculateSemiMonthlyPayPeriod($scheduleSetting, $today);

            case 'monthly':
                return $this->calculateMonthlyPayPeriod($scheduleSetting, $today);

            case 'daily':
                return $this->calculateDailyPayPeriod($scheduleSetting, $today);

            default:
                // Fallback to weekly if unknown
                return $this->calculateWeeklyPayPeriod($scheduleSetting, $today);
        }
    }

    /**
     * Calculate weekly pay period based on settings
     */
    private function calculateWeeklyPayPeriod($scheduleSetting, $currentDate)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || !isset($cutoffPeriods[0]) || !is_array($cutoffPeriods[0])) {
            // Fallback to Monday-Friday if no settings
            $cutoffPeriods = [['start_day' => 'monday', 'end_day' => 'friday', 'pay_day' => 'friday']];
        }

        $cutoff = $cutoffPeriods[0];
        $startDay = $cutoff['start_day'];
        $endDay = $cutoff['end_day'];
        $payDay = $cutoff['pay_day'];


        // Get last payroll to determine next period
        $lastPayroll = \App\Models\Payroll::where('pay_schedule', 'weekly')
            ->orderBy('period_end', 'desc')
            ->first();

        if ($lastPayroll) {
            // Start from the day after the last payroll period ended
            $periodStart = Carbon::parse($lastPayroll->period_end)->addDay();
        } else {
            // No previous payroll - find the current or next period
            $periodStart = $this->getWeekStartForDay($currentDate, $startDay);

            // If we're past the end day of current week, move to next week
            $periodEnd = $this->getWeekDayForDate($periodStart, $endDay);
            if ($currentDate->gt($periodEnd)) {
                $periodStart = $periodStart->addWeek();
            }
        }

        $periodEnd = $this->getWeekDayForDate($periodStart, $endDay);
        $payDate = $this->getWeekDayForDate($periodStart, $payDay);

        // If pay day is before period end, move to next week
        if ($payDate->lt($periodEnd)) {
            $payDate = $payDate->addWeek();
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate semi-monthly pay period based on settings
     */
    private function calculateSemiMonthlyPayPeriod($scheduleSetting, $currentDate)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        // Fix: Decode JSON if string
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || !isset($cutoffPeriods[0]) || !is_array($cutoffPeriods[0])) {
            // Fallback to 1-15 and 16-31
            $cutoffPeriods = [
                ['start_day' => 1, 'end_day' => 15, 'pay_date' => 20],
                ['start_day' => 16, 'end_day' => 31, 'pay_date' => 5]
            ];
        }

        // Get last payroll to determine next period
        $lastPayroll = \App\Models\Payroll::where('pay_schedule', 'semi_monthly')
            ->orderBy('period_end', 'desc')
            ->first();

        $currentDay = $currentDate->day;

        // Parse cutoff periods to get numeric days
        $firstPeriodStart = $this->parseDayNumber($cutoffPeriods[0]['start_day']);
        $firstPeriodEnd = $this->parseDayNumber($cutoffPeriods[0]['end_day']);
        $secondPeriodStart = $this->parseDayNumber($cutoffPeriods[1]['start_day']);
        $secondPeriodEnd = $this->parseDayNumber($cutoffPeriods[1]['end_day']);

        // Determine which period we're in based on current date
        $firstPeriodPayDay = $this->parseDayNumber($cutoffPeriods[0]['pay_date'] ?? $cutoffPeriods[0]['pay_day'] ?? $firstPeriodEnd);
        $secondPeriodPayDay = $this->parseDayNumber($cutoffPeriods[1]['pay_date'] ?? $cutoffPeriods[1]['pay_day'] ?? $secondPeriodEnd);

        // Check if we're in the first or second period
        $inFirstPeriod = false;
        if ($firstPeriodStart > $firstPeriodEnd) {
            // First period crosses month boundary (e.g., 21st to 5th)
            $inFirstPeriod = ($currentDay >= $firstPeriodStart || $currentDay <= $firstPeriodEnd);
        } else {
            // First period is within same month
            $inFirstPeriod = ($currentDay >= $firstPeriodStart && $currentDay <= $firstPeriodEnd);
        }

        if ($inFirstPeriod) {
            // We're in the first period
            if ($firstPeriodStart > $firstPeriodEnd) {
                // Period crosses month boundary
                if ($currentDay >= $firstPeriodStart) {
                    // Currently in the start month (last month)
                    $periodStart = $currentDate->copy()->day($firstPeriodStart);
                    $periodEnd = $currentDate->copy()->addMonth()->day($firstPeriodEnd);
                } else {
                    // Currently in the end month (current month)
                    $periodStart = $currentDate->copy()->subMonth()->day($firstPeriodStart);
                    $periodEnd = $currentDate->copy()->day($firstPeriodEnd);
                }
            } else {
                // Period is within same month
                $periodStart = $currentDate->copy()->day($firstPeriodStart);
                if ($firstPeriodEnd == 31) {
                    $periodEnd = $currentDate->copy()->endOfMonth();
                } else {
                    $periodEnd = $currentDate->copy()->day($firstPeriodEnd);
                }
            }
            $payDay = $firstPeriodPayDay;
        } else {
            // We're in the second period
            if ($secondPeriodStart > $secondPeriodEnd) {
                // Period crosses month boundary
                if ($currentDay >= $secondPeriodStart) {
                    // Currently in the start month (last month)
                    $periodStart = $currentDate->copy()->day($secondPeriodStart);
                    $periodEnd = $currentDate->copy()->addMonth()->day($secondPeriodEnd);
                } else {
                    // Currently in the end month (current month)
                    $periodStart = $currentDate->copy()->subMonth()->day($secondPeriodStart);
                    $periodEnd = $currentDate->copy()->day($secondPeriodEnd);
                }
            } else {
                // Period is within same month
                $periodStart = $currentDate->copy()->day($secondPeriodStart);
                if ($secondPeriodEnd == 31) {
                    $periodEnd = $currentDate->copy()->endOfMonth();
                } else {
                    $periodEnd = $currentDate->copy()->day($secondPeriodEnd);
                }
            }
            $payDay = $secondPeriodPayDay;
        }

        // Set pay date - always in current month
        if ($payDay == 31) {
            $payDate = $currentDate->copy()->endOfMonth();
        } else {
            $payDate = $currentDate->copy()->day($payDay);
        }

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Parse day number from string (1st, 2nd, etc.) or simple number
     */
    private function parseDayNumber($dayString)
    {
        if (is_numeric($dayString)) {
            return (int) $dayString;
        }
        if ($dayString === '31st' || $dayString === '31') {
            return 31;
        }
        // Handle EOD and EOM special values by returning them as strings
        if ($dayString === 'EOD' || $dayString === 'eod' || $dayString === 'EOM') {
            return $dayString;
        }
        return (int) preg_replace('/[^0-9]/', '', $dayString);
    }

    /**
     * Calculate monthly pay period based on settings
     */
    private function calculateMonthlyPayPeriod($scheduleSetting, $currentDate)
    {
        // Get last payroll to determine next period
        $lastPayroll = \App\Models\Payroll::where('pay_schedule', 'monthly')
            ->orderBy('period_end', 'desc')
            ->first();

        if ($lastPayroll) {
            $periodStart = Carbon::parse($lastPayroll->period_end)->addDay();
        } else {
            $periodStart = $currentDate->copy()->startOfMonth();
        }

        $periodEnd = $periodStart->copy()->endOfMonth();
        $payDate = $periodEnd->copy()->addDays($scheduleSetting->pay_day_offset ?? 0);

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate daily pay period based on settings
     */
    private function calculateDailyPayPeriod($scheduleSetting, $currentDate)
    {
        // Get last payroll to determine next period
        $lastPayroll = \App\Models\Payroll::where('pay_schedule', 'daily')
            ->orderBy('period_end', 'desc')
            ->first();

        if ($lastPayroll) {
            $periodStart = Carbon::parse($lastPayroll->period_end)->addDay();
        } else {
            $periodStart = $currentDate->copy();
        }

        $periodEnd = $periodStart->copy();
        $payDate = $periodEnd->copy()->addDays($scheduleSetting->pay_day_offset ?? 0);

        return [
            'start' => $periodStart->format('Y-m-d'),
            'end' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
        ];
    }

    /**
     * Get the start of week for a specific day (monday, tuesday, etc.)
     */
    private function getWeekStartForDay($date, $dayName)
    {
        $dayOfWeek = $this->getDayOfWeekNumber($dayName);
        $currentDayOfWeek = $date->dayOfWeek;

        // Adjust Sunday from 0 to 7 for easier calculation
        if ($currentDayOfWeek === 0) $currentDayOfWeek = 7;

        $daysToSubtract = $currentDayOfWeek - $dayOfWeek;
        if ($daysToSubtract < 0) {
            $daysToSubtract += 7;
        }

        return $date->copy()->subDays($daysToSubtract);
    }

    /**
     * Get a specific weekday for a given week
     */
    private function getWeekDayForDate($weekStart, $dayName)
    {
        $dayOfWeek = $this->getDayOfWeekNumber($dayName);
        $startDayOfWeek = $weekStart->dayOfWeek;

        // Adjust Sunday from 0 to 7 for easier calculation
        if ($startDayOfWeek === 0) $startDayOfWeek = 7;

        $daysToAdd = $dayOfWeek - $startDayOfWeek;
        if ($daysToAdd < 0) {
            $daysToAdd += 7;
        }

        return $weekStart->copy()->addDays($daysToAdd);
    }

    /**
     * Generate a suggested payroll number
     */
    private function generatePayrollNumber($paySchedule)
    {
        // Use the model's generatePayrollNumber method for consistency
        return \App\Models\Payroll::generatePayrollNumber($paySchedule);
    }

    /**
     * Recalculate payroll based on current settings and data
     * This deletes the current payroll and recreates it fresh
     */
    public function recalculate(Payroll $payroll)
    {
        $this->authorize('edit payrolls');

        if (!$payroll->canBeEdited()) {
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', 'This payroll cannot be recalculated as it has been processed.');
        }

        try {
            DB::beginTransaction();

            Log::info('Starting payroll recalculation via delete/recreate', [
                'payroll_id' => $payroll->id,
                'payroll_number' => $payroll->payroll_number
            ]);

            // Store the original payroll data
            $originalData = [
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'pay_date' => $payroll->pay_date,
                'payroll_type' => $payroll->payroll_type,
                'pay_schedule' => $payroll->pay_schedule,
                'description' => $payroll->description,
                'created_by' => $payroll->created_by,
            ];

            // Get all employee IDs that were in the original payroll
            $employeeIds = $payroll->payrollDetails->pluck('employee_id')->toArray();

            // Delete the old payroll (this will cascade delete payroll details)
            $payroll->delete();

            // Create new payroll with the same data
            $newPayroll = Payroll::create([
                'payroll_number' => Payroll::generatePayrollNumber($originalData['payroll_type']),
                'period_start' => $originalData['period_start'],
                'period_end' => $originalData['period_end'],
                'pay_date' => $originalData['pay_date'],
                'payroll_type' => $originalData['payroll_type'],
                'pay_schedule' => $originalData['pay_schedule'],
                'description' => $originalData['description'] . ' (Recalculated)',
                'status' => 'draft',
                'created_by' => $originalData['created_by'],
            ]);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $processedEmployees = 0;

            // Recreate payroll details for each employee with current data
            foreach ($employeeIds as $employeeId) {
                try {
                    $employee = Employee::find($employeeId);

                    if (!$employee || $employee->employment_status !== 'active') {
                        Log::warning("Employee with ID {$employeeId} not found or not active, skipping");
                        continue;
                    }

                    // Calculate payroll details with current settings
                    $payrollDetail = $this->calculateEmployeePayroll($employee, $newPayroll);

                    $totalGross += $payrollDetail->gross_pay;
                    $totalDeductions += $payrollDetail->total_deductions;
                    $totalNet += $payrollDetail->net_pay;
                    $processedEmployees++;
                } catch (\Exception $e) {
                    Log::error("Failed to process employee {$employeeId} during recalculation: " . $e->getMessage());
                    continue;
                }
            }

            if ($processedEmployees === 0) {
                throw new \Exception('No employees could be processed for payroll recalculation.');
            }

            DB::commit();

            Log::info('Payroll recalculation completed', [
                'old_payroll_id' => 'deleted',
                'new_payroll_id' => $newPayroll->id,
                'new_payroll_number' => $newPayroll->payroll_number,
                'total_employees' => $processedEmployees
            ]);

            return redirect()->route('payrolls.show', $newPayroll)
                ->with('success', "Payroll has been recalculated! Created new payroll #{$newPayroll->payroll_number} with {$processedEmployees} employees processed.");
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Payroll recalculation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('payrolls.index')
                ->with('error', 'Failed to recalculate payroll: ' . $e->getMessage());
        }
    }
    /**
     * Auto-recalculate payroll when viewing if it's still in draft status
     */
    private function autoRecalculateIfNeeded(Payroll $payroll)
    {
        // Only auto-recalculate if payroll is in draft status
        if ($payroll->status !== 'draft') {
            return;
        }

        try {
            // Always perform full recalculation to reflect current data
            Log::info('Auto-recalculating payroll on view', ['payroll_id' => $payroll->id]);

            // First, recalculate all time log hours for this payroll period
            $this->recalculateTimeLogsForPayroll($payroll);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            // Recalculate each payroll detail completely with current settings
            foreach ($payroll->payrollDetails as $detail) {
                $employee = Employee::find($detail->employee_id);

                if (!$employee) continue;

                // Full recalculation using current dynamic settings
                $updatedDetail = $this->calculateEmployeePayrollDynamic($employee, $payroll);

                $totalGross += $updatedDetail->gross_pay;
                $totalDeductions += $updatedDetail->total_deductions;
                $totalNet += $updatedDetail->net_pay;
            }

            // Update payroll totals
            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);
        } catch (\Exception $e) {
            Log::warning('Auto-recalculation failed', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Recalculate time log hours for all employees in a payroll period
     */
    private function recalculateTimeLogsForPayroll(Payroll $payroll)
    {
        $employeeIds = $payroll->payrollDetails->pluck('employee_id');

        $timeLogs = TimeLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->get();

        $timeLogController = app(TimeLogController::class);

        foreach ($timeLogs as $timeLog) {
            // Skip if incomplete record
            if (!$timeLog->time_in || !$timeLog->time_out) {
                continue;
            }

            // Recalculate hours using the dynamic calculation method
            $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);

            // Update the stored values with the new calculations
            $timeLog->regular_hours = $dynamicCalculation['regular_hours'];
            $timeLog->overtime_hours = $dynamicCalculation['overtime_hours'];
            $timeLog->regular_overtime_hours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
            $timeLog->night_diff_overtime_hours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
            $timeLog->total_hours = $dynamicCalculation['total_hours'];
            $timeLog->late_hours = $dynamicCalculation['late_hours'];
            $timeLog->undertime_hours = $dynamicCalculation['undertime_hours'];
            $timeLog->save();
        }

        Log::info('Recalculated time logs for payroll', [
            'payroll_id' => $payroll->id,
            'time_logs_count' => $timeLogs->count()
        ]);
    }

    /**
     * Move payroll back to draft status (only from processing)
     */
    public function backToDraft(Payroll $payroll)
    {
        $this->authorize('edit payrolls');

        if ($payroll->status !== 'processing') {
            return back()->withErrors(['status' => 'Only processing payrolls can be moved back to draft.']);
        }

        DB::beginTransaction();
        try {
            // Delete all snapshots
            $payroll->snapshots()->delete();

            // Update payroll status
            $payroll->update([
                'status' => 'draft',
                'processing_started_at' => null,
                'processing_by' => null,
            ]);

            DB::commit();

            return redirect()->route('payrolls.show', $payroll)
                ->with('success', 'Payroll moved back to draft. Snapshots have been cleared.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to move payroll back to draft', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Failed to move payroll back to draft: ' . $e->getMessage()]);
        }
    }

    /**
     * Create snapshots for all employees in the payroll
     */
    private function createPayrollSnapshots(Payroll $payroll)
    {
        Log::info("Creating snapshots for payroll {$payroll->id}");

        // Delete existing snapshots first
        $payroll->snapshots()->delete();

        // Get all payroll details
        $payrollDetails = $payroll->payrollDetails()->with(['employee.timeSchedule', 'employee.daySchedule'])->get();

        if ($payrollDetails->isEmpty()) {
            throw new \Exception('No payroll details found to create snapshots.');
        }

        // Get employee IDs from payroll details
        $employeeIds = $payrollDetails->pluck('employee_id');

        // Create array of all dates in the payroll period
        $startDate = \Carbon\Carbon::parse($payroll->period_start);
        $endDate = \Carbon\Carbon::parse($payroll->period_end);
        $periodDates = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $periodDates[] = $date->format('Y-m-d');
        }

        // Get all time logs for this payroll period
        $timeLogs = TimeLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->orderBy('log_date')
            ->get()
            ->groupBy(['employee_id', function ($item) {
                return \Carbon\Carbon::parse($item->log_date)->format('Y-m-d');
            }]);

        // Calculate time breakdowns for all employees (to get only regular workday hours)
        $timeBreakdownsByEmployee = [];
        foreach ($employeeIds as $employeeId) {
            $employeeTimeLogs = $timeLogs->get($employeeId, collect());
            $employeeBreakdown = [];

            foreach ($periodDates as $date) {
                $timeLog = $employeeTimeLogs->get($date, collect())->first();

                // Track time breakdown by type (exclude incomplete records, but include suspension days even without time data)
                if ($timeLog && !($timeLog->remarks === 'Incomplete Time Record' || ((!$timeLog->time_in || !$timeLog->time_out) && !in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension'])))) {
                    $logType = $timeLog->log_type;
                    if (!isset($employeeBreakdown[$logType])) {
                        $employeeBreakdown[$logType] = [
                            'regular_hours' => 0,
                            'night_diff_regular_hours' => 0,
                            'overtime_hours' => 0,
                            'regular_overtime_hours' => 0,
                            'night_diff_overtime_hours' => 0,
                            'total_hours' => 0,
                            'days_count' => 0,
                            'days' => 0, // NEW: Track suspension days
                            'suspension_settings' => [], // NEW: Store suspension configurations
                            'actual_time_log_hours' => 0, // NEW: For partial suspensions
                            'display_name' => '',
                            'rate_config' => null
                        ];
                    }

                    // Calculate dynamically using current grace periods
                    $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                    $regularHours = $dynamicCalculation['regular_hours'];
                    $nightDiffRegularHours = $dynamicCalculation['night_diff_regular_hours'] ?? 0;
                    $overtimeHours = $dynamicCalculation['overtime_hours'];
                    $regularOvertimeHours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
                    $nightDiffOvertimeHours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                    $totalHours = $dynamicCalculation['total_hours'];

                    $employeeBreakdown[$logType]['regular_hours'] += $regularHours;
                    $employeeBreakdown[$logType]['night_diff_regular_hours'] += $nightDiffRegularHours;
                    $employeeBreakdown[$logType]['overtime_hours'] += $overtimeHours;
                    $employeeBreakdown[$logType]['regular_overtime_hours'] += $regularOvertimeHours;
                    $employeeBreakdown[$logType]['night_diff_overtime_hours'] += $nightDiffOvertimeHours;
                    $employeeBreakdown[$logType]['total_hours'] += $totalHours;
                    $employeeBreakdown[$logType]['days_count']++;

                    // NEW: Handle suspension specific data (same as draft mode)
                    // Support all suspension types: suspension, full_day_suspension, partial_suspension
                    if (in_array($logType, ['suspension', 'full_day_suspension', 'partial_suspension'])) {
                        $employeeBreakdown[$logType]['days']++;

                        // Get suspension settings for this date
                        $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $timeLog->log_date)
                            ->where('date_to', '>=', $timeLog->log_date)
                            ->where('status', 'active')
                            ->first();

                        if ($suspensionSetting) {
                            $employeeBreakdown[$logType]['suspension_settings'][$timeLog->log_date->format('Y-m-d')] = [
                                'is_paid' => $suspensionSetting->is_paid,
                                'pay_rule' => $suspensionSetting->pay_rule,
                                'pay_applicable_to' => $suspensionSetting->pay_applicable_to,
                                'type' => $suspensionSetting->type
                            ];

                            // For partial suspensions, track actual worked hours before suspension starts
                            if ($suspensionSetting->type === 'partial_suspension' && $totalHours > 0) {
                                $employeeBreakdown[$logType]['actual_time_log_hours'] += $totalHours;
                            }
                        }
                    }

                    // Get rate configuration for this type (same as draft payroll)
                    $rateConfig = $timeLog->getRateConfiguration();
                    if ($rateConfig) {
                        $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                        $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                    }
                }
            }

            $timeBreakdownsByEmployee[$employeeId] = $employeeBreakdown;
        }

        foreach ($payrollDetails as $detail) {
            $employee = $detail->employee;

            // For automated payrolls, calculate the earnings using THE EXACT SAME logic as draft mode display
            // This ensures 100% consistency between draft UI and snapshot data

            // Calculate using the same logic as the show method for draft payrolls
            $employeeBreakdown = $timeBreakdownsByEmployee[$employee->id] ?? [];
            $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $payroll->period_start, $payroll->period_end);

            // Log employee breakdown data for debugging
            Log::info("Employee breakdown data for employee {$employee->id}", [
                'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                'hourly_rate' => $hourlyRate,
                'breakdown_count' => count($employeeBreakdown),
                'breakdown_types' => array_keys($employeeBreakdown)
            ]);

            // Calculate Basic Pay using the same method as the dynamic view
            $calculatedBasicPay = $employee->calculateBasicPayForPeriod(
                \Carbon\Carbon::parse($payroll->period_start),
                \Carbon\Carbon::parse($payroll->period_end)
            );

            // Calculate Monthly Basic Pay using the same method as the dynamic view
            $currentMonth = \Carbon\Carbon::now();
            $currentMonthStart = $currentMonth->copy()->startOfMonth();
            $currentMonthEnd = $currentMonth->copy()->endOfMonth();
            $calculatedMonthlyBasicPay = $employee->calculateMonthlyBasicSalary($currentMonthStart, $currentMonthEnd);

            $basicPay = 0; // Regular workday pay only from time logs
            $regularWorkdayPay = 0; // Track ONLY regular workday pay (for regular_pay field)
            $holidayPay = 0; // All holiday-related pay
            $restPay = 0; // Rest day pay
            $overtimePay = 0; // Overtime pay

            // Use the new TimeLog's calculatePerMinuteAmount method for consistency with the view
            foreach ($employeeBreakdown as $logType => $breakdown) {
                $rateConfig = $breakdown['rate_config'];
                if (!$rateConfig) continue;

                // Get rate multipliers
                $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;

                // Calculate regular pay using SAME logic as breakdown methods for consistency
                $regularHours = $breakdown['regular_hours'];
                if ($regularHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $regularMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60;
                    $regularPayAmount = round($ratePerMinute * $roundedMinutes, 2);
                } else {
                    $regularPayAmount = 0;
                }

                // Calculate night differential regular hours pay separately
                $nightDiffRegularPayAmount = 0;
                $nightDiffRegularHours = $breakdown['night_diff_regular_hours'] ?? 0;
                if ($nightDiffRegularHours > 0) {
                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                    // Combined rate: regular rate + night differential bonus
                    $combinedMultiplier = $regularMultiplier + ($nightDiffMultiplier - 1);

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes  
                    $actualMinutes = $nightDiffRegularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60;
                    $nightDiffRegularPayAmount = round($ratePerMinute * $roundedMinutes, 2);
                }

                // Calculate overtime pay with night differential breakdown
                $overtimePayAmount = 0;
                $regularOvertimeHours = $breakdown['regular_overtime_hours'] ?? 0;
                $nightDiffOvertimeHours = $breakdown['night_diff_overtime_hours'] ?? 0;

                if ($regularOvertimeHours > 0 || $nightDiffOvertimeHours > 0) {
                    // Regular overtime pay - use consistent calculation
                    if ($regularOvertimeHours > 0) {
                        $actualMinutes = $regularOvertimeHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $overtimePayAmount += round($ratePerMinute * $roundedMinutes, 2);
                    }

                    // Night differential overtime pay - use consistent calculation
                    if ($nightDiffOvertimeHours > 0) {
                        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                        $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);

                        $actualMinutes = $nightDiffOvertimeHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $overtimePayAmount += round($ratePerMinute * $roundedMinutes, 2);
                    }
                } else {
                    // Fallback to simple calculation - use consistent method
                    $totalOvertimeHours = $breakdown['overtime_hours'];
                    if ($totalOvertimeHours > 0) {
                        $actualMinutes = $totalOvertimeHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $overtimePayAmount = round($ratePerMinute * $roundedMinutes, 2);
                    }
                }

                // Categorize pay by log type (same logic as view)
                if ($logType === 'regular_workday') {
                    $basicPay += $regularPayAmount + $nightDiffRegularPayAmount; // Only regular pay goes to basic
                    $regularWorkdayPay += $regularPayAmount + $nightDiffRegularPayAmount; // Track regular workday pay separately
                    $overtimePay += $overtimePayAmount; // All overtime goes to overtime column
                } elseif ($logType === 'suspension') {
                    // Legacy suspension handling (should not happen with new logic)
                    $basicPay += $regularPayAmount + $nightDiffRegularPayAmount; // Suspension regular hours pay to basic
                    // Do NOT add to regularWorkdayPay - suspension is not regular workday
                    $overtimePay += $overtimePayAmount; // All overtime goes to overtime column
                } elseif (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                    // New suspension types using rate configurations
                    $basicPay += $regularPayAmount + $nightDiffRegularPayAmount; // Suspension pay goes to basic
                    // Do NOT add to regularWorkdayPay - suspension is not regular workday
                    $overtimePay += $overtimePayAmount; // All overtime goes to overtime column
                } elseif ($logType === 'rest_day') {
                    $restPay += $regularPayAmount + $nightDiffRegularPayAmount; // Only regular hours pay to rest day pay
                    $overtimePay += $overtimePayAmount; // All overtime goes to overtime column
                } elseif (in_array($logType, ['special_holiday', 'regular_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                    $holidayPay += $regularPayAmount + $nightDiffRegularPayAmount; // Only regular hours pay to holiday pay
                    $overtimePay += $overtimePayAmount; // All overtime goes to overtime column
                }
            }

            // Do NOT round individual components to avoid rounding discrepancies
            // The TimeLog::calculatePayAmount() already handles per-minute precision
            // Additional rounding here causes the total to not match the sum of parts

            // Get deductions and other components using the existing payroll calculation method
            $payrollCalculation = $this->calculateEmployeePayrollForPeriod(
                $employee,
                $payroll->period_start,
                $payroll->period_end,
                null // Pass null to force draft mode calculations
            );

            // Get the time breakdown for this employee to extract ONLY regular workday hours
            $employeeTimeBreakdown = $timeBreakdownsByEmployee[$employee->id] ?? [];

            // Extract only regular workday hours for basic pay calculation
            $regularWorkdayHours = $employeeTimeBreakdown['regular_workday']['regular_hours'] ?? 0;
            $regularWorkdayOvertimeHours = $employeeTimeBreakdown['regular_workday']['overtime_hours'] ?? 0;

            // Calculate total hours for all day types (for verification)
            $totalRegularHours = 0;
            $totalOvertimeHours = 0;
            $totalHolidayHours = 0;

            foreach ($employeeTimeBreakdown as $logType => $breakdown) {
                $totalRegularHours += $breakdown['regular_hours'];
                $totalOvertimeHours += $breakdown['overtime_hours'];
                if (in_array($logType, ['special_holiday', 'regular_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                    $totalHolidayHours += $breakdown['regular_hours'] + $breakdown['overtime_hours'];
                }
            }

            // Create detailed breakdowns for Basic, Holiday, Rest, Suspension, and Overtime columns
            $basicBreakdown = $this->createBasicPayBreakdown($employeeTimeBreakdown, $employee, $payroll->period_start, $payroll->period_end);
            $holidayBreakdown = $this->createHolidayPayBreakdown($employeeTimeBreakdown, $employee, $payroll->period_start, $payroll->period_end);
            $restBreakdown = $this->createRestPayBreakdown($employeeTimeBreakdown, $employee, $payroll->period_start, $payroll->period_end);
            $suspensionBreakdown = $this->createSuspensionPayBreakdown($employeeTimeBreakdown, $employee, $payroll->period_start, $payroll->period_end);
            $overtimeBreakdown = $this->createOvertimePayBreakdown($employeeTimeBreakdown, $employee, $payroll->period_start, $payroll->period_end);

            // DEBUG: Log breakdown details for employee
            Log::info("Snapshot breakdown debug for employee {$employee->id}", [
                'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                'time_breakdown_keys' => array_keys($employeeTimeBreakdown),
                'basic_breakdown' => $basicBreakdown,
                'suspension_breakdown' => $suspensionBreakdown,
                'suspension_breakdown_empty' => empty($suspensionBreakdown),
                'basic_breakdown_before_merge' => $basicBreakdown
            ]);

            // CRITICAL: Merge suspension breakdown into basic breakdown for REGULAR column display
            // This ensures suspension amounts appear in REGULAR column in processing/approved payrolls
            // Same as dynamic/draft payroll behavior (source of truth)
            if (!empty($suspensionBreakdown)) {
                $basicBreakdown = array_merge($basicBreakdown, $suspensionBreakdown);
                Log::info("Merged suspension breakdown into basic breakdown for employee {$employee->id}", [
                    'basic_breakdown_after_merge' => $basicBreakdown
                ]);
            } else {
                Log::info("No suspension breakdown to merge for employee {$employee->id}");
            }

            // Use the SAME calculated amounts from the draft mode display logic
            // This ensures 100% consistency between draft and processing/locked payroll displays
            // $basicPay, $holidayPay, $restPay, $overtimePay are already calculated above using exact same logic as draft

            // Get breakdown data for allowances and bonuses (exactly as they are in draft mode)
            $allowancesBreakdown = $this->getEmployeeAllowancesBreakdown($employee, $payroll);

            // Pass the calculated breakdown data to bonuses calculation for 13th month pay accuracy
            // CRITICAL: Use the same structure as the draft view for consistency
            // IMPORTANT: Use $basicBreakdown AFTER suspension merge to include fixed amounts from suspensions
            $employeeBreakdownData = [
                'basic' => $basicBreakdown,
                'holiday' => $holidayBreakdown
            ];



            $bonusesBreakdown = $this->getEmployeeBonusesBreakdown($employee, $payroll, $employeeBreakdownData);
            $incentivesBreakdown = $this->getEmployeeIncentivesBreakdown($employee, $payroll);            // Log the calculated values for debugging
            Log::info("Snapshot calculation for employee {$employee->id}", [
                'basic_pay' => $basicPay,
                'regular_workday_pay' => $regularWorkdayPay,
                'holiday_pay' => $holidayPay,
                'overtime_pay' => $overtimePay,
                'rest_pay' => $restPay,
                'regular_workday_hours' => $regularWorkdayHours,
                'regular_workday_overtime_hours' => $regularWorkdayOvertimeHours,
                'total_regular_hours_all_types' => $totalRegularHours,
                'total_overtime_hours_all_types' => $totalOvertimeHours,
                'total_holiday_hours' => $totalHolidayHours,
                'gross_pay' => $payrollCalculation['gross_pay'] ?? 0,
            ]);

            // Get current settings snapshot
            $settingsSnapshot = $this->getCurrentSettingsSnapshot($employee);

            // Calculate total deductions exactly as they would be in draft mode
            // Use the calculated total deductions from the payroll calculation
            $totalDeductions = $payrollCalculation['total_deductions'] ?? 0;

            // Calculate totals from breakdown (reuse already calculated breakdown data to avoid duplicate calculations)
            $allowancesTotal = array_sum(array_column($allowancesBreakdown, 'amount'));
            $bonusesTotal = array_sum(array_column($bonusesBreakdown, 'amount'));
            $incentivesTotal = array_sum(array_column($incentivesBreakdown, 'amount'));
            $otherEarnings = $payrollCalculation['other_earnings'] ?? 0;

            // Use exact component sum to match the individual breakdown calculations
            // This ensures the total matches what the UI displays from individual components
            // REMOVED ROUNDING: Store exact calculated values without rounding for snapshot precision
            $grossPay = $basicPay + $holidayPay + $restPay + $overtimePay + $allowancesTotal + $bonusesTotal + $incentivesTotal + $otherEarnings;


            $netPay = $grossPay - $totalDeductions;

            // Create pay breakdown for snapshot
            $payBreakdown = [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_day_pay' => $restPay,
                'overtime_pay' => $overtimePay,
                'total_calculated' => $basicPay + $holidayPay + $restPay + $overtimePay
            ];

            // Calculate taxable income using the same logic as PayrollDetail.getTaxableIncomeAttribute()
            // This ensures consistency between dynamic and snapshot calculations
            // NOTE: Night differential amounts are already embedded in basicPay, holidayPay, and restPay
            // through the breakdown calculations (Regular Workday+ND, Holiday+ND, etc.)
            // So we don't need to add a separate night_differential_pay field
            $baseTaxableIncome = $basicPay + $holidayPay + $restPay + $overtimePay;
            $taxableIncome = $baseTaxableIncome;

            // Log base taxable income calculation
            Log::info("Base taxable income calculation for employee {$employee->id}", [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_pay' => $restPay,
                'overtime_pay' => $overtimePay,
                'base_taxable_income' => $baseTaxableIncome
            ]);

            // Add only taxable allowances and bonuses
            $allowanceSettings = \App\Models\AllowanceBonusSetting::where('type', 'allowance')
                ->where('is_active', true)
                ->get();
            $bonusSettings = \App\Models\AllowanceBonusSetting::where('type', 'bonus')
                ->where('is_active', true)
                ->get();

            $allSettings = $allowanceSettings->merge($bonusSettings);

            // Log what settings we're processing
            Log::info("Processing allowance/bonus settings for taxable income", [
                'employee_id' => $employee->id,
                'allowance_settings' => $allowanceSettings->map(function ($s) {
                    return ['code' => $s->code, 'name' => $s->name, 'is_taxable' => $s->is_taxable, 'type' => $s->type];
                }),
                'bonus_settings' => $bonusSettings->map(function ($s) {
                    return ['code' => $s->code, 'name' => $s->name, 'is_taxable' => $s->is_taxable, 'type' => $s->type];
                })
            ]);

            foreach ($allSettings as $setting) {
                // Log each setting we're processing
                Log::info("Evaluating setting for taxable income", [
                    'employee_id' => $employee->id,
                    'setting_code' => $setting->code,
                    'setting_name' => $setting->name,
                    'setting_type' => $setting->type,
                    'is_taxable' => $setting->is_taxable,
                    'calculation_type' => $setting->calculation_type,
                    'fixed_amount' => $setting->fixed_amount ?? 'N/A',
                    'frequency' => $setting->frequency ?? 'N/A'
                ]);

                // Only add if this setting is taxable
                if (!$setting->is_taxable) {
                    Log::info("Skipping non-taxable setting: {$setting->code} ({$setting->type})");
                    continue;
                }

                // Check if this allowance/bonus setting applies to this employee's benefit status
                // This ensures snapshot calculation matches dynamic calculation logic
                if (!$setting->appliesTo($employee)) {
                    Log::info("Skipping setting not applicable to employee benefit status: {$setting->code} ({$setting->type}) for employee {$employee->id} (benefit status: {$employee->benefits_status})");
                    continue;
                }

                Log::info("Processing taxable setting: {$setting->code} ({$setting->type})");

                $calculatedAmount = 0;                // Calculate the amount based on the setting type
                if ($setting->calculation_type === 'percentage') {
                    $calculatedAmount = ($basicPay * $setting->rate_percentage) / 100;
                } elseif ($setting->calculation_type === 'fixed_amount') {
                    $calculatedAmount = $setting->fixed_amount;

                    // Apply frequency-based calculation for daily allowances
                    if ($setting->frequency === 'daily') {
                        $workingDays = 0;

                        // Count working days from time breakdown
                        foreach ($employeeTimeBreakdown as $logType => $breakdown) {
                            if ($breakdown['regular_hours'] > 0) {
                                $workingDays += $breakdown['days_count'] ?? 0;
                            }
                        }

                        $maxDays = $setting->max_days_per_period ?? $workingDays;
                        $applicableDays = min($workingDays, $maxDays);

                        $calculatedAmount = $setting->fixed_amount * $applicableDays;
                    }
                } elseif ($setting->calculation_type === 'daily_rate_multiplier') {
                    $dailyRate = $employee->daily_rate ?? 0;
                    $multiplier = $setting->multiplier ?? 1;
                    $calculatedAmount = $dailyRate * $multiplier;
                }

                // Apply limits
                if ($setting->minimum_amount && $calculatedAmount < $setting->minimum_amount) {
                    $calculatedAmount = $setting->minimum_amount;
                }
                if ($setting->maximum_amount && $calculatedAmount > $setting->maximum_amount) {
                    $calculatedAmount = $setting->maximum_amount;
                }

                // Apply distribution method for taxable calculation (same as view logic)
                if ($calculatedAmount > 0) {
                    $employeePaySchedule = $employee->pay_schedule ?? 'semi_monthly';
                    $distributedAmount = $setting->calculateDistributedAmount(
                        $calculatedAmount,
                        $payroll->period_start,
                        $payroll->period_end,
                        $employeePaySchedule
                    );

                    // Add distributed taxable allowance/bonus to taxable income
                    $taxableIncome += $distributedAmount;

                    Log::info("Added taxable distributed amount", [
                        'setting_code' => $setting->code,
                        'setting_type' => $setting->type,
                        'original_amount' => $calculatedAmount,
                        'distributed_amount' => $distributedAmount,
                        'running_taxable_income' => $taxableIncome
                    ]);
                }
            }

            // Add only taxable incentives (same logic as allowances/bonuses)
            $incentiveSettings = \App\Models\AllowanceBonusSetting::where('type', 'incentives')
                ->where('is_active', true)
                ->get();

            Log::info("Processing incentive settings for taxable income", [
                'employee_id' => $employee->id,
                'incentive_settings' => $incentiveSettings->map(function ($s) {
                    return ['code' => $s->code, 'name' => $s->name, 'is_taxable' => $s->is_taxable, 'type' => $s->type];
                })
            ]);

            foreach ($incentiveSettings as $setting) {
                // Log each setting we're processing
                Log::info("Evaluating incentive setting for taxable income", [
                    'employee_id' => $employee->id,
                    'setting_code' => $setting->code,
                    'setting_name' => $setting->name,
                    'setting_type' => $setting->type,
                    'is_taxable' => $setting->is_taxable,
                    'requires_perfect_attendance' => $setting->requires_perfect_attendance ?? false
                ]);

                // Only add if this setting is taxable
                if (!$setting->is_taxable) {
                    Log::info("Skipping non-taxable incentive setting: {$setting->code}");
                    continue;
                }

                // Check if this incentive setting applies to this employee's benefit status
                if (!$setting->appliesTo($employee)) {
                    Log::info("Skipping incentive setting not applicable to employee benefit status: {$setting->code} for employee {$employee->id} (benefit status: {$employee->benefits_status})");
                    continue;
                }

                // Check perfect attendance requirement
                if ($setting->requires_perfect_attendance) {
                    if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                        Log::info("Skipping incentive setting due to imperfect attendance: {$setting->code} for employee {$employee->id}");
                        continue;
                    }
                }

                Log::info("Processing taxable incentive setting: {$setting->code}");

                $calculatedIncentiveAmount = $setting->fixed_amount ?? 0;

                // Apply distribution method for taxable calculation (same as view logic)
                if ($calculatedIncentiveAmount > 0) {
                    $employeePaySchedule = $employee->pay_schedule ?? 'semi_monthly';
                    $distributedIncentiveAmount = $setting->calculateDistributedAmount(
                        $calculatedIncentiveAmount,
                        $payroll->period_start,
                        $payroll->period_end,
                        $employeePaySchedule
                    );

                    // Add distributed taxable incentive to taxable income
                    $taxableIncome += $distributedIncentiveAmount;

                    Log::info("Added taxable distributed incentive amount", [
                        'setting_code' => $setting->code,
                        'original_amount' => $calculatedIncentiveAmount,
                        'distributed_amount' => $distributedIncentiveAmount,
                        'running_taxable_income' => $taxableIncome
                    ]);
                }
            }

            $taxableIncome = max(0, $taxableIncome);

            // ADDITIONAL DEBUG: Write to clean debug file
            $debugData = [
                'employee_id' => $employee->id,
                'taxable_income_after_max' => $taxableIncome,
                'base_taxable_income' => $baseTaxableIncome,
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_pay' => $restPay,
                'overtime_pay' => $overtimePay,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            file_put_contents(
                storage_path('logs/debug_taxable.txt'),
                "AFTER MAX CALCULATION:\n" . json_encode($debugData, JSON_PRETTY_PRINT) . "\n\n",
                FILE_APPEND | LOCK_EX
            );

            // Calculate deductions breakdown after taxable income is finalized
            // Pass the calculated taxable income and gross pay to ensure consistent deduction calculations
            $deductionsBreakdown = $this->getEmployeeDeductionsBreakdown($employee, $detail, $taxableIncome, $payroll, $grossPay);

            // Calculate employer deductions breakdown
            $employerDeductionsBreakdown = $this->getEmployerDeductionsBreakdown($employee, $detail, $taxableIncome, $payroll);

            // Log taxable income calculation for debugging
            Log::info("Final taxable income calculation for employee {$employee->id}", [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_pay' => $restPay,
                'overtime_pay' => $overtimePay,
                'base_taxable_income' => $baseTaxableIncome,
                'base_total' => $basicPay + $holidayPay + $restPay + $overtimePay,
                'gross_pay' => $grossPay,
                'final_taxable_income' => $taxableIncome,
                'allowance_settings_count' => $allowanceSettings->count(),
                'bonus_settings_count' => $bonusSettings->count(),
            ]);

            // FINAL DEBUG: Write to clean debug file just before snapshot creation
            $preSnapshotData = [
                'employee_id' => $employee->id,
                'taxable_income_variable' => $taxableIncome,
                'taxable_income_type' => gettype($taxableIncome),
                'is_null' => is_null($taxableIncome),
                'is_numeric' => is_numeric($taxableIncome),
                'value_as_string' => (string)$taxableIncome,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            file_put_contents(
                storage_path('logs/debug_taxable.txt'),
                "BEFORE SNAPSHOT CREATION:\n" . json_encode($preSnapshotData, JSON_PRETTY_PRINT) . "\n\n",
                FILE_APPEND | LOCK_EX
            );

            // Calculate totals from breakdown data (which includes proper distribution)
            $allowancesTotal = 0;
            if (is_array($allowancesBreakdown)) {
                foreach ($allowancesBreakdown as $allowance) {
                    $allowancesTotal += $allowance['amount'] ?? 0;
                }
            }

            $bonusesTotal = 0;
            if (is_array($bonusesBreakdown)) {
                foreach ($bonusesBreakdown as $bonus) {
                    $bonusesTotal += $bonus['amount'] ?? 0;
                }
            }

            $incentivesTotal = 0;
            if (is_array($incentivesBreakdown)) {
                foreach ($incentivesBreakdown as $incentive) {
                    $incentivesTotal += $incentive['amount'] ?? 0;
                }
            }

            // Create snapshot with exact draft mode calculations
            $snapshot = \App\Models\PayrollSnapshot::create([
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                'department' => $employee->department->name ?? 'N/A',
                'position' => $employee->position->title ?? 'N/A',
                'basic_salary' => $calculatedMonthlyBasicPay, // Use calculated Monthly Basic Pay (same as dynamic view)
                'daily_rate' => $employee->daily_rate ?? 0,
                'hourly_rate' => $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0),
                'days_worked' => $payrollCalculation['days_worked'] ?? 0,
                'regular_hours' => $regularWorkdayHours, // Only regular workday hours, not all regular hours
                'overtime_hours' => $regularWorkdayOvertimeHours, // Only regular workday overtime hours
                'holiday_hours' => $totalHolidayHours, // Total holiday hours (regular + overtime)
                'night_differential_hours' => $payrollCalculation['night_differential_hours'] ?? 0,
                'regular_pay' => $regularWorkdayPay, // Use only regular workday pay - no fallback to basic salary
                'overtime_pay' => $overtimePay, // Use calculated overtime pay
                'holiday_pay' => $holidayPay, // Use calculated holiday pay
                'rest_day_pay' => $restPay, // Use calculated rest day pay
                'night_differential_pay' => $payrollCalculation['night_differential_pay'] ?? 0,
                'basic_breakdown' => $basicBreakdown,
                'holiday_breakdown' => $holidayBreakdown,
                'rest_breakdown' => $restBreakdown,
                'suspension_breakdown' => $suspensionBreakdown,
                'overtime_breakdown' => $overtimeBreakdown,
                'allowances_breakdown' => $allowancesBreakdown,
                'allowances_total' => $allowancesTotal, // Use calculated total from breakdown
                'bonuses_breakdown' => $bonusesBreakdown,
                'bonuses_total' => $bonusesTotal, // Use calculated total from breakdown
                'incentives_breakdown' => $incentivesBreakdown,
                'incentives_total' => $incentivesTotal, // Use calculated total from breakdown
                'other_earnings' => $payrollCalculation['other_earnings'] ?? 0,
                'gross_pay' => $grossPay, // Use calculated gross pay
                'deductions_breakdown' => $deductionsBreakdown,
                'employer_deductions_breakdown' => $employerDeductionsBreakdown,
                'sss_contribution' => $payrollCalculation['sss_contribution'] ?? 0,
                'philhealth_contribution' => $payrollCalculation['philhealth_contribution'] ?? 0,
                'pagibig_contribution' => $payrollCalculation['pagibig_contribution'] ?? 0,
                'withholding_tax' => $payrollCalculation['withholding_tax'] ?? 0,
                'late_deductions' => 0, // Set to 0 as late/undertime are already accounted for in hours
                'undertime_deductions' => 0, // Set to 0 as late/undertime are already accounted for in hours
                'cash_advance_deductions' => $payrollCalculation['cash_advance_deductions'] ?? 0,
                'other_deductions' => $payrollCalculation['other_deductions'] ?? 0,
                'total_deductions' => $totalDeductions, // Use calculated total
                'net_pay' => $netPay, // Use calculated net pay
                'taxable_income' => $taxableIncome, // Store calculated taxable income
                'settings_snapshot' => array_merge($settingsSnapshot, ['pay_breakdown' => $payBreakdown]),
                'remarks' => 'Snapshot created at ' . now()->format('Y-m-d H:i:s') . ' - Captures exact draft calculations',
            ]);

            // DEBUG: Check what was actually stored in the database
            $storedData = [
                'snapshot_id' => $snapshot->id,
                'stored_taxable_income' => $snapshot->taxable_income,
                'stored_gross_pay' => $snapshot->gross_pay,
                'stored_net_pay' => $snapshot->net_pay,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];
            file_put_contents(
                storage_path('logs/debug_taxable.txt'),
                "AFTER SNAPSHOT CREATED:\n" . json_encode($storedData, JSON_PRETTY_PRINT) . "\n" . str_repeat('=', 50) . "\n\n",
                FILE_APPEND | LOCK_EX
            );

            // IMPORTANT: Also update the payroll_details table to match the snapshot values
            // This ensures consistency between snapshot data and payroll_details fallback
            $payrollDetail = $payroll->payrollDetails()->where('employee_id', $employee->id)->first();
            if ($payrollDetail) {
                $payrollDetail->update([
                    'regular_pay' => $basicPay, // Update with the exact snapshot value
                    'holiday_pay' => $holidayPay,
                    'overtime_pay' => $overtimePay,
                    'rest_day_pay' => $restPay,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                ]);

                Log::info("Updated payroll_details for employee {$employee->id} to match snapshot", [
                    'regular_pay' => $basicPay,
                    'holiday_pay' => $holidayPay,
                    'overtime_pay' => $overtimePay,
                    'gross_pay' => $grossPay
                ]);
            }

            Log::info("Created snapshot for employee {$employee->id} with calculated values", [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'overtime_pay' => $overtimePay,
                'rest_pay' => $restPay,
                'gross_pay' => $grossPay,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'deductions_count' => count($deductionsBreakdown),
            ]);
        }

        Log::info("Successfully created " . count($payrollDetails) . " snapshots for payroll {$payroll->id}");
    }

    /**
     * Debug method to view payroll snapshots (for testing/troubleshooting)
     */
    public function debugSnapshots(Payroll $payroll)
    {
        $this->authorize('view payrolls');

        $snapshots = $payroll->snapshots()->get();

        return response()->json([
            'payroll_id' => $payroll->id,
            'payroll_status' => $payroll->status,
            'payroll_number' => $payroll->payroll_number,
            'snapshot_count' => $snapshots->count(),
            'snapshots' => $snapshots->map(function ($snapshot) {
                return [
                    'employee_id' => $snapshot->employee_id,
                    'employee_name' => $snapshot->employee_name,
                    'gross_pay' => $snapshot->gross_pay,
                    'total_deductions' => $snapshot->total_deductions,
                    'net_pay' => $snapshot->net_pay,
                    'deductions_breakdown' => $snapshot->deductions_breakdown,
                    'deductions_breakdown_count' => is_array($snapshot->deductions_breakdown) ? count($snapshot->deductions_breakdown) : 0,
                    'created_at' => $snapshot->created_at,
                ];
            })
        ], 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Get allowances breakdown for employee
     */
    private function getEmployeeAllowancesBreakdown(Employee $employee, Payroll $payroll)
    {
        $breakdown = [];

        // Get active allowance settings that apply to this employee's benefit status
        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($allowanceSettings as $setting) {
            // Calculate hours data for this employee
            $timeLogs = TimeLog::where('employee_id', $employee->id)
                ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
                ->get();

            $regularHours = $timeLogs->sum('regular_hours') ?? 0;
            $overtimeHours = $timeLogs->sum('overtime_hours') ?? 0;
            $holidayHours = $timeLogs->sum('holiday_hours') ?? 0;

            $amount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours
            );

            if ($amount > 0) {
                $breakdown[] = [
                    'name' => $setting->name,
                    'code' => $setting->code ?? $setting->name,
                    'amount' => $amount,
                    'is_taxable' => $setting->is_taxable ?? true,
                    'calculation_type' => $setting->calculation_type,
                    'description' => $setting->description ?? ''
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get bonuses breakdown for employee
     */
    private function getEmployeeBonusesBreakdown(Employee $employee, Payroll $payroll, $breakdownData = null)
    {
        $breakdown = [];

        // Get active bonus settings that apply to this employee's benefit status
        $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'bonus')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($bonusSettings as $setting) {
            // Calculate hours data for this employee
            $timeLogs = TimeLog::where('employee_id', $employee->id)
                ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
                ->get();

            $regularHours = $timeLogs->sum('regular_hours') ?? 0;
            $overtimeHours = $timeLogs->sum('overtime_hours') ?? 0;
            $holidayHours = $timeLogs->sum('holiday_hours') ?? 0;

            $amount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours,
                $breakdownData
            );

            Log::info("Bonus calculation in getEmployeeBonusesBreakdown", [
                'bonus_name' => $setting->name,
                'employee_id' => $employee->id,
                'raw_calculated_amount' => $amount,
                'calculation_type' => $setting->calculation_type,
                'has_breakdown_data' => !empty($breakdownData)
            ]);

            if ($amount > 0) {
                $breakdown[] = [
                    'name' => $setting->name,
                    'code' => $setting->code ?? $setting->name,
                    'amount' => $amount,
                    'is_taxable' => $setting->is_taxable ?? true,
                    'calculation_type' => $setting->calculation_type,
                    'description' => $setting->description ?? ''
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get incentives breakdown for employee
     */
    private function getEmployeeIncentivesBreakdown(Employee $employee, Payroll $payroll)
    {
        $breakdown = [];

        // Get active incentive settings that apply to this employee's benefit status
        $incentiveSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'incentives')
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        foreach ($incentiveSettings as $setting) {
            // Check if this incentive requires perfect attendance
            if ($setting->requires_perfect_attendance) {
                // Check if employee has perfect attendance for this payroll period
                if (!$setting->hasPerfectAttendance($employee, $payroll->period_start, $payroll->period_end)) {
                    continue; // Skip this incentive if perfect attendance not met
                }
            }

            // Calculate hours data for this employee
            $timeLogs = TimeLog::where('employee_id', $employee->id)
                ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
                ->get();

            $regularHours = $timeLogs->sum('regular_hours') ?? 0;
            $overtimeHours = $timeLogs->sum('overtime_hours') ?? 0;
            $holidayHours = $timeLogs->sum('holiday_hours') ?? 0;

            $amount = $this->calculateAllowanceBonusAmountForPayroll(
                $setting,
                $employee,
                $payroll,
                $regularHours,
                $overtimeHours,
                $holidayHours
            );

            if ($amount > 0) {
                $breakdown[] = [
                    'name' => $setting->name,
                    'code' => $setting->code ?? $setting->name,
                    'amount' => $amount,
                    'is_taxable' => $setting->is_taxable ?? true,
                    'calculation_type' => $setting->calculation_type,
                    'description' => $setting->description ?? ''
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Get deductions breakdown for employee
     */
    private function getEmployeeDeductionsBreakdown(Employee $employee, PayrollDetail $detail, $taxableIncome = null, $payroll = null, $grossPay = null)
    {
        $breakdown = [];

        // Detect pay frequency from payroll period if payroll object is provided using dynamic pay schedule settings
        $payFrequency = 'semi_monthly'; // default
        if ($payroll) {
            $payFrequency = \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                \Carbon\Carbon::parse($payroll->period_start),
                \Carbon\Carbon::parse($payroll->period_end)
            );
        }

        // Get active deduction settings that apply to this employee's benefit status
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->forBenefitStatus($employee->benefits_status)
            ->orderBy('sort_order')
            ->get();

        if ($deductionSettings->isNotEmpty()) {
            // Use the passed taxable income if provided, otherwise calculate it from detail components
            if ($taxableIncome === null) {
                $taxableIncomeForDeductions = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
            } else {
                $taxableIncomeForDeductions = $taxableIncome;
            }

            $governmentTotal = 0;

            // First pass: Calculate government deductions (excluding withholding tax)
            foreach ($deductionSettings as $setting) {
                if ($setting->tax_table_type !== 'withholding_tax') {
                    // Calculate the pay basis amount and determine pay basis name
                    $payBasisAmount = 0;
                    $payBasisName = '';

                    // Determine pay basis based on the setting's configuration
                    if ($setting->apply_to_gross_pay) {
                        // Use the passed grossPay if provided, otherwise fall back to detail's gross_pay
                        $payBasisAmount = $grossPay ?? ($detail->gross_pay ?? 0);
                        $payBasisName = 'totalgross';
                    } elseif ($setting->apply_to_taxable_income) {
                        $payBasisAmount = $taxableIncomeForDeductions;
                        $payBasisName = 'taxableincome';
                    } elseif ($setting->apply_to_net_pay) {
                        $payBasisAmount = $detail->net_pay ?? 0;
                        $payBasisName = 'netpay';
                    } elseif ($setting->apply_to_monthly_basic_salary) {
                        $payBasisAmount = $employee->calculateMonthlyBasicSalary($payroll->period_start ?? null, $payroll->period_end ?? null); // Dynamic MBS
                        $payBasisName = 'mbs';
                    } else {
                        // Calculate component-based pay basis
                        $components = [];
                        if ($setting->apply_to_basic_pay || $setting->apply_to_regular) {
                            $payBasisAmount += ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
                            $components[] = 'Basic Pay';
                        }
                        if ($setting->apply_to_overtime) {
                            $payBasisAmount += $detail->overtime_pay ?? 0;
                            $components[] = 'Overtime';
                        }
                        if ($setting->apply_to_bonus) {
                            $payBasisAmount += $detail->bonuses ?? 0;
                            $components[] = 'Bonuses';
                        }
                        if ($setting->apply_to_allowances) {
                            $payBasisAmount += $detail->allowances ?? 0;
                            $components[] = 'Allowances';
                        }

                        $payBasisName = !empty($components) ? implode(' + ', $components) : 'Basic Pay';

                        // If no specific components selected, default to basic pay
                        if (empty($components)) {
                            $payBasisAmount = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
                        }
                    }

                    // Apply salary cap if set
                    $cappedAmount = $payBasisAmount;
                    if ($setting->salary_cap && $payBasisAmount > $setting->salary_cap) {
                        $cappedAmount = $setting->salary_cap;
                        $payBasisName .= ' (capped at ₱' . number_format($setting->salary_cap, 2) . ')';
                    }

                    // Calculate basic pay components (regular + holiday + rest) for proper deduction calculation
                    $basicPayComponents = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);

                    $amount = $setting->calculateDeduction(
                        $basicPayComponents, // Use actual basic pay components, not full taxable income
                        $detail->overtime_pay ?? 0,
                        $detail->bonuses ?? 0,
                        $detail->allowances ?? 0,
                        $grossPay ?? ($detail->gross_pay ?? 0), // Use passed grossPay if available
                        $taxableIncomeForDeductions, // Pass taxable income as the proper taxableIncome parameter
                        null, // netPay
                        $employee->calculateMonthlyBasicSalary($detail->payroll->period_start ?? null, $detail->payroll->period_end ?? null), // monthlyBasicSalary - DYNAMIC
                        $payFrequency // Add pay frequency parameter
                    );

                    // Apply deduction distribution logic
                    if ($payroll && $amount > 0) {
                        $originalAmount = $amount;
                        $amount = $setting->calculateDistributedAmount(
                            $originalAmount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employee->pay_schedule ?? $payFrequency
                        );
                    }

                    if ($amount > 0) {
                        $breakdown[] = [
                            'name' => $setting->name,
                            'code' => $setting->code ?? strtolower(str_replace(' ', '_', $setting->name)),
                            'amount' => round($amount, 2), // Round to 2 decimal places
                            'type' => $setting->type ?? 'government',
                            'calculation_type' => $setting->calculation_type,
                            'pay_basis' => $payBasisName,
                            'pay_basis_amount' => number_format($cappedAmount, 2, '.', '') // Use number_format for consistent 2 decimal precision
                        ];
                        $governmentTotal += $amount;
                    }
                }
            }

            // Second pass: Calculate withholding tax deductions
            // Withholding tax is calculated on the taxable income BEFORE government deductions
            foreach ($deductionSettings as $setting) {
                if ($setting->tax_table_type === 'withholding_tax') {
                    // Calculate basic pay components (regular + holiday + rest) for proper deduction calculation
                    $basicPayComponents = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);

                    $amount = $setting->calculateDeduction(
                        $basicPayComponents, // Use actual basic pay components, not full taxable income
                        $detail->overtime_pay ?? 0,
                        $detail->bonuses ?? 0,
                        $detail->allowances ?? 0,
                        $grossPay ?? ($detail->gross_pay ?? 0), // Use passed grossPay if available
                        $taxableIncomeForDeductions, // Use the same taxable income - withholding tax is not calculated after government deductions
                        null, // netPay
                        $employee->calculateMonthlyBasicSalary($detail->payroll->period_start ?? null, $detail->payroll->period_end ?? null), // Use dynamic MBS calculation for consistency
                        $payFrequency // Add pay frequency parameter
                    );

                    // Apply deduction distribution logic for withholding tax
                    if ($payroll && $amount > 0) {
                        $originalAmount = $amount;
                        $amount = $setting->calculateDistributedAmount(
                            $originalAmount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employee->pay_schedule ?? $payFrequency
                        );
                    }

                    if ($amount > 0) {
                        $breakdown[] = [
                            'name' => $setting->name,
                            'code' => $setting->code ?? strtolower(str_replace(' ', '_', $setting->name)),
                            'amount' => round($amount, 2), // Round to 2 decimal places
                            'type' => $setting->type ?? 'government',
                            'calculation_type' => $setting->calculation_type,
                            'pay_basis' => 'taxableincome', // Match other deductions' pay_basis format
                            'pay_basis_amount' => number_format($taxableIncomeForDeductions, 2, '.', '') // Use number_format for consistent 2 decimal precision
                        ];
                    }
                }
            }
        } else {
            // Fallback to traditional static deductions if no active settings
            // Calculate taxable income for fallback deductions
            $taxableIncomeForFallback = $taxableIncome ?? (($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0));
            $basicPayForFallback = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);

            if ($detail->sss_contribution > 0) {
                $breakdown[] = [
                    'name' => 'SSS Contribution',
                    'code' => 'sss',
                    'amount' => $detail->sss_contribution,
                    'type' => 'government',
                    'pay_basis' => 'Basic Pay',
                    'pay_basis_amount' => $basicPayForFallback
                ];
            }

            if ($detail->philhealth_contribution > 0) {
                $breakdown[] = [
                    'name' => 'PhilHealth Contribution',
                    'code' => 'philhealth',
                    'amount' => $detail->philhealth_contribution,
                    'type' => 'government',
                    'pay_basis' => 'Basic Pay',
                    'pay_basis_amount' => $basicPayForFallback
                ];
            }

            if ($detail->pagibig_contribution > 0) {
                $breakdown[] = [
                    'name' => 'Pag-IBIG Contribution',
                    'code' => 'pagibig',
                    'amount' => $detail->pagibig_contribution,
                    'type' => 'government',
                    'pay_basis' => 'Basic Pay',
                    'pay_basis_amount' => $basicPayForFallback
                ];
            }

            if ($detail->withholding_tax > 0) {
                $breakdown[] = [
                    'name' => 'Withholding Tax',
                    'code' => 'withholding_tax',
                    'amount' => $detail->withholding_tax,
                    'type' => 'tax',
                    'pay_basis' => 'Taxable Income',
                    'pay_basis_amount' => $taxableIncomeForFallback
                ];
            }
        }

        // Always include other deductions (excluding late/undertime as they're already accounted for in hours)
        if ($detail->cash_advance_deductions > 0) {
            $breakdown[] = [
                'name' => 'CA',
                'code' => 'cash_advance',
                'amount' => $detail->cash_advance_deductions,
                'type' => 'loan',
                'pay_basis' => 'Fixed Amount',
                'pay_basis_amount' => $detail->cash_advance_deductions
            ];
        }

        if ($detail->other_deductions > 0) {
            $breakdown[] = [
                'name' => 'Other Deductions',
                'code' => 'other',
                'amount' => $detail->other_deductions,
                'type' => 'other',
                'pay_basis' => 'Fixed Amount',
                'pay_basis_amount' => $detail->other_deductions
            ];
        }

        return $breakdown;
    }

    /**
     * Get employer deductions breakdown for employee
     */
    private function getEmployerDeductionsBreakdown(Employee $employee, PayrollDetail $detail, $taxableIncome = null, $payroll = null)
    {
        $breakdown = [];
        $payFrequency = $employee->pay_schedule ?? 'semi_monthly';

        // Get active deduction settings that apply to this employee's benefit status
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->forBenefitStatus($employee->benefits_status)
            ->where('share_with_employer', true) // Only get deductions that are shared with employer
            ->orderBy('sort_order')
            ->get();

        if ($deductionSettings->isNotEmpty()) {
            // Use the passed taxable income if provided, otherwise calculate it from detail components
            if ($taxableIncome === null) {
                $taxableIncomeForDeductions = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
            } else {
                $taxableIncomeForDeductions = $taxableIncome;
            }

            foreach ($deductionSettings as $setting) {
                // Calculate the pay basis amount
                $payBasisAmount = 0;
                $payBasisName = '';

                // Determine pay basis based on the setting's configuration
                if ($setting->apply_to_gross_pay) {
                    $payBasisAmount = $detail->gross_pay ?? 0;
                    $payBasisName = 'totalgross';
                } elseif ($setting->apply_to_taxable_income) {
                    $payBasisAmount = $taxableIncomeForDeductions;
                    $payBasisName = 'taxableincome';
                } elseif ($setting->apply_to_net_pay) {
                    $payBasisAmount = $detail->net_pay ?? 0;
                    $payBasisName = 'netpay';
                } elseif ($setting->apply_to_monthly_basic_salary) {
                    $payBasisAmount = $employee->calculateMonthlyBasicSalary($payroll->period_start ?? null, $payroll->period_end ?? null);
                    $payBasisName = 'mbs';
                } else {
                    // Calculate component-based pay basis
                    $components = [];
                    if ($setting->apply_to_basic_pay || $setting->apply_to_regular) {
                        $payBasisAmount += ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
                        $components[] = 'Basic Pay';
                    }
                    if ($setting->apply_to_overtime) {
                        $payBasisAmount += $detail->overtime_pay ?? 0;
                        $components[] = 'Overtime';
                    }
                    if ($setting->apply_to_bonus) {
                        $payBasisAmount += $detail->bonuses ?? 0;
                        $components[] = 'Bonuses';
                    }
                    if ($setting->apply_to_allowances) {
                        $payBasisAmount += $detail->allowances ?? 0;
                        $components[] = 'Allowances';
                    }

                    $payBasisName = !empty($components) ? implode(' + ', $components) : 'Basic Pay';

                    // If no specific components selected, default to basic pay
                    if (empty($components)) {
                        $payBasisAmount = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);
                    }
                }

                // Apply salary cap if set
                $cappedAmount = $payBasisAmount;
                if ($setting->salary_cap && $payBasisAmount > $setting->salary_cap) {
                    $cappedAmount = $setting->salary_cap;
                    $payBasisName .= ' (capped at ₱' . number_format($setting->salary_cap, 2) . ')';
                }

                // Calculate employer share based on deduction type
                $employerShare = 0;

                if ($setting->tax_table_type === 'sss') {
                    // Get SSS contribution details
                    $sssContribution = \Illuminate\Support\Facades\DB::table('sss_tax_table')
                        ->where('range_start', '<=', $cappedAmount)
                        ->where(function ($query) use ($cappedAmount) {
                            $query->where('range_end', '>=', $cappedAmount)
                                ->orWhereNull('range_end');
                        })
                        ->where('is_active', true)
                        ->first();

                    if ($sssContribution) {
                        $employerShare = (float) $sssContribution->employer_share;
                    }
                } elseif ($setting->tax_table_type === 'philhealth') {
                    // Get PhilHealth contribution details
                    $philHealthContribution = \App\Models\PhilHealthTaxTable::calculateContribution($cappedAmount);
                    if ($philHealthContribution) {
                        $employerShare = $philHealthContribution['employer_share'];
                    }
                } elseif ($setting->tax_table_type === 'pagibig') {
                    // Get Pag-IBIG contribution details
                    $pagibigContribution = \App\Models\PagibigTaxTable::calculateContribution($cappedAmount);
                    if ($pagibigContribution) {
                        $employerShare = $pagibigContribution['employer_share'];
                    }
                } else {
                    // For other deductions, use the calculateEmployerShare method
                    // Calculate basic pay components (regular + holiday + rest) for proper deduction calculation
                    $basicPayComponents = ($detail->regular_pay ?? 0) + ($detail->holiday_pay ?? 0) + ($detail->rest_day_pay ?? 0);

                    $employeeDeduction = $setting->calculateDeduction(
                        $basicPayComponents, // Use actual basic pay components, not full taxable income
                        $detail->overtime_pay ?? 0,
                        $detail->bonuses ?? 0,
                        $detail->allowances ?? 0,
                        $detail->gross_pay ?? 0,
                        $taxableIncomeForDeductions, // Pass taxable income as the proper taxableIncome parameter
                        null,
                        $employee->calculateMonthlyBasicSalary($payroll->period_start ?? null, $payroll->period_end ?? null),
                        $payFrequency
                    );
                    $employerShare = $setting->calculateEmployerShare($employeeDeduction, $cappedAmount);
                }

                if ($employerShare > 0) {
                    $breakdown[] = [
                        'name' => $setting->name,
                        'code' => $setting->code ?? strtolower(str_replace(' ', '_', $setting->name)),
                        'amount' => round($employerShare, 2),
                        'type' => $setting->type ?? 'government',
                        'calculation_type' => $setting->calculation_type,
                        'pay_basis' => $payBasisName,
                        'pay_basis_amount' => round($cappedAmount, 2)
                    ];
                }
            }
        }

        return $breakdown;
    }

    /**
     * Get current settings snapshot for employee
     */
    private function getCurrentSettingsSnapshot(Employee $employee)
    {
        return [
            'benefit_status' => $employee->benefits_status,
            'pay_schedule' => $employee->pay_schedule,
            'allowance_settings' => \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'allowance')
                ->forBenefitStatus($employee->benefits_status)
                ->select('id', 'name', 'calculation_type', 'fixed_amount', 'rate_percentage')
                ->get()
                ->toArray(),
            'bonus_settings' => \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'bonus')
                ->forBenefitStatus($employee->benefits_status)
                ->select('id', 'name', 'calculation_type', 'fixed_amount', 'rate_percentage')
                ->get()
                ->toArray(),
            'incentive_settings' => \App\Models\AllowanceBonusSetting::where('is_active', true)
                ->where('type', 'incentives')
                ->forBenefitStatus($employee->benefits_status)
                ->select('id', 'name', 'calculation_type', 'fixed_amount', 'rate_percentage')
                ->get()
                ->toArray(),
            'deduction_settings' => \App\Models\DeductionTaxSetting::active()
                ->forBenefitStatus($employee->benefits_status)
                ->select('id', 'name', 'calculation_type', 'fixed_amount', 'rate_percentage')
                ->get()
                ->toArray(),
            'captured_at' => now()->toISOString(),
        ];
    }

    /**
     * Show draft payroll for a specific employee (dynamic calculations)
     */
    public function showDraftPayroll(Request $request, $schedule, $employee)
    {
        $this->authorize('view payrolls');

        $employee = Employee::findOrFail($employee);
        $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
            ->where('code', $schedule)
            ->first();

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        $payrollCalculation = $this->calculateEmployeePayrollForPeriod($employee, $currentPeriod['start'], $currentPeriod['end']);

        // Debug logging
        Log::info('Draft Payroll Calculation for Employee ' . $employee->id, [
            'allowances' => $payrollCalculation['allowances'] ?? 'NOT SET',
            'allowances_details' => $payrollCalculation['allowances_details'] ?? 'NOT SET',
            'deductions_details' => $payrollCalculation['deductions_details'] ?? 'NOT SET',
        ]);

        // Create mock payroll for display
        $draftPayroll = new Payroll();
        $draftPayroll->id = $employee->id;
        $draftPayroll->payroll_number = 'DRAFT-' . $employee->employee_number;
        $draftPayroll->period_start = $currentPeriod['start'];
        $draftPayroll->period_end = $currentPeriod['end'];
        $draftPayroll->pay_date = $currentPeriod['pay_date'];
        $draftPayroll->status = 'draft';
        $draftPayroll->payroll_type = 'automated';
        $draftPayroll->total_gross = $payrollCalculation['gross_pay'] ?? 0;
        $draftPayroll->total_deductions = $payrollCalculation['total_deductions'] ?? 0;
        $draftPayroll->total_net = $payrollCalculation['net_pay'] ?? 0;
        $draftPayroll->created_at = now();

        // Set relationships
        $fakeCreator = (object) ['name' => 'System (Draft)', 'id' => 0];
        $draftPayroll->setRelation('creator', $fakeCreator);
        $draftPayroll->setRelation('approver', null);

        // Create mock payroll detail
        $draftPayrollDetail = new PayrollDetail();
        $draftPayrollDetail->employee_id = $employee->id;
        $draftPayrollDetail->basic_salary = $employee->basic_salary ?? 0;
        $draftPayrollDetail->daily_rate = $employee->daily_rate ?? 0;
        $draftPayrollDetail->hourly_rate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        $draftPayrollDetail->days_worked = $payrollCalculation['days_worked'] ?? 0;
        $draftPayrollDetail->regular_hours = $payrollCalculation['regular_hours'] ?? 0;
        $draftPayrollDetail->overtime_hours = $payrollCalculation['overtime_hours'] ?? 0;
        $draftPayrollDetail->holiday_hours = $payrollCalculation['holiday_hours'] ?? 0;
        $draftPayrollDetail->regular_pay = $payrollCalculation['basic_salary'] ?? 0;
        $draftPayrollDetail->overtime_pay = $payrollCalculation['overtime_pay'] ?? 0;
        $draftPayrollDetail->holiday_pay = $payrollCalculation['holiday_pay'] ?? 0;
        $draftPayrollDetail->allowances = $payrollCalculation['allowances'] ?? 0;
        $draftPayrollDetail->bonuses = $payrollCalculation['bonuses'] ?? 0;
        $draftPayrollDetail->gross_pay = $payrollCalculation['gross_pay'] ?? 0;
        $draftPayrollDetail->sss_contribution = $payrollCalculation['sss_deduction'] ?? 0;
        $draftPayrollDetail->philhealth_contribution = $payrollCalculation['philhealth_deduction'] ?? 0;
        $draftPayrollDetail->pagibig_contribution = $payrollCalculation['pagibig_deduction'] ?? 0;
        $draftPayrollDetail->withholding_tax = $payrollCalculation['tax_deduction'] ?? 0;
        $draftPayrollDetail->late_deductions = 0; // Not calculated in this method yet
        $draftPayrollDetail->undertime_deductions = 0; // Not calculated in this method yet
        $draftPayrollDetail->cash_advance_deductions = 0; // Not calculated in this method yet
        $draftPayrollDetail->other_deductions = $payrollCalculation['other_deductions'] ?? 0;
        $draftPayrollDetail->total_deductions = $payrollCalculation['total_deductions'] ?? 0;
        $draftPayrollDetail->net_pay = $payrollCalculation['net_pay'] ?? 0;

        // Set earnings and deduction breakdowns
        if (isset($payrollCalculation['allowances_details'])) {
            $draftPayrollDetail->earnings_breakdown = json_encode([
                'allowances' => $payrollCalculation['allowances_details']
            ]);
        }
        if (isset($payrollCalculation['deductions_details'])) {
            $draftPayrollDetail->deduction_breakdown = json_encode($payrollCalculation['deductions_details']);
        }

        // Set employee relationship
        $draftPayrollDetail->setRelation('employee', $employee->load(['user', 'department', 'position', 'daySchedule', 'timeSchedule']));

        // Set payroll details collection
        $draftPayroll->setRelation('payrollDetails', collect([$draftPayrollDetail]));

        // Create period dates array
        $startDate = \Carbon\Carbon::parse($currentPeriod['start']);
        $endDate = \Carbon\Carbon::parse($currentPeriod['end']);
        $periodDates = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $periodDates[] = $date->format('Y-m-d');
        }

        // Get DTR data
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$currentPeriod['start'], $currentPeriod['end']])
            ->orderBy('log_date')
            ->get()
            ->groupBy(function ($item) {
                return \Carbon\Carbon::parse($item->log_date)->format('Y-m-d');
            });

        // Build DTR data structure matching working version
        $employeeDtr = [];
        foreach ($periodDates as $date) {
            $timeLog = $timeLogs->get($date, collect())->first();
            $employeeDtr[$date] = $timeLog;
        }
        $dtrData = [$employee->id => $employeeDtr];

        // Create time breakdowns similar to regular show method
        $timeBreakdowns = [$employee->id => []];
        $employeeBreakdown = [];

        foreach ($periodDates as $date) {
            $timeLog = $timeLogs->get($date, collect())->first();
            if ($timeLog && !($timeLog->remarks === 'Incomplete Time Record' || (!$timeLog->time_in || !$timeLog->time_out))) {
                $logType = $timeLog->log_type;
                if (!isset($employeeBreakdown[$logType])) {
                    $employeeBreakdown[$logType] = [
                        'regular_hours' => 0,
                        'overtime_hours' => 0,
                        'total_hours' => 0,
                        'days_count' => 0,
                        'display_name' => '',
                        'rate_config' => null
                    ];
                }

                $employeeBreakdown[$logType]['regular_hours'] += $timeLog->regular_hours ?? 0;
                $employeeBreakdown[$logType]['overtime_hours'] += $timeLog->overtime_hours ?? 0;
                $employeeBreakdown[$logType]['total_hours'] += $timeLog->total_hours ?? 0;
                $employeeBreakdown[$logType]['days_count']++;

                // Get rate configuration for this type
                $rateConfig = $timeLog->getRateConfiguration();
                if ($rateConfig) {
                    $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                    $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                }
            }
        }

        $timeBreakdowns[$employee->id] = $employeeBreakdown;

        // Calculate pay breakdown by employee using same method as snapshot mode
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        $basicPay = 0;
        $holidayPay = 0;

        // Create detailed breakdowns using same methods as snapshot mode
        $basicBreakdown = $this->createBasicPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $holidayBreakdown = $this->createHolidayPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $restBreakdown = $this->createRestPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $overtimeBreakdown = $this->createOvertimePayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);

        // Calculate totals from breakdowns
        foreach ($basicBreakdown as $data) {
            $basicPay += $data['amount'] ?? 0;
        }

        if ($holidayBreakdown) {
            foreach ($holidayBreakdown as $data) {
                $holidayPay += $data['amount'] ?? 0;
            }
        }

        $payBreakdownByEmployee = [
            $employee->id => [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
            ]
        ];

        // Get settings
        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->orderBy('sort_order')
            ->get();
        $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'bonus')
            ->orderBy('sort_order')
            ->get();
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->orderBy('sort_order')
            ->get();

        $totalHolidayPay = $holidayPay;

        // Also create suspension breakdown for draft mode
        $suspensionBreakdown = $this->createSuspensionPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);

        return view('payrolls.show', compact(
            'draftPayroll',
            'dtrData',
            'periodDates',
            'allowanceSettings',
            'bonusSettings',
            'deductionSettings',
            'timeBreakdowns',
            'payBreakdownByEmployee',
            'totalHolidayPay',
            'basicBreakdown',
            'holidayBreakdown',
            'restBreakdown',
            'suspensionBreakdown',
            'overtimeBreakdown'
        ) + [
            'payroll' => $draftPayroll,
            'isDraft' => true,
            'isDynamic' => true,
            'schedule' => $schedule,
            'employee' => $employee->id
        ]);
    }

    /**
     * Process draft payroll (save to database)
     */
    public function processDraftPayroll(Request $request, $schedule, $employee)
    {
        $this->authorize('create payrolls');

        $employee = Employee::findOrFail($employee);
        $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
            ->where('code', $schedule)
            ->first();

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);

        // Check if payroll already exists
        $existingPayroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if ($existingPayroll) {
            return redirect()->route('payrolls.automation.processing.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('info', 'This payroll is already processed.');
        }

        try {
            // Create payroll with snapshot and "processing" status
            $employees = collect([$employee]);
            $createdPayroll = $this->autoCreatePayrollForPeriod($selectedSchedule, $currentPeriod, $employees, 'processing');

            return redirect()->route('payrolls.automation.processing.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('success', 'Payroll processed and saved to database with data snapshot.');
        } catch (\Exception $e) {
            Log::error('Failed to process draft payroll: ' . $e->getMessage());
            return redirect()->route('payrolls.automation.draft.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    /**
     * Show processing payroll (saved to database)
     */
    public function showProcessingPayroll(Request $request, $schedule, $employee)
    {
        $this->authorize('view payrolls');

        $employee = Employee::findOrFail($employee);
        $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
            ->where('code', $schedule)
            ->first();

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);

        // Find existing payroll
        $payroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if (!$payroll) {
            return redirect()->route('payrolls.automation.draft.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('info', 'No processed payroll found. Showing draft mode.');
        }

        // Call the regular show method but pass additional data for automation context
        $originalShow = $this->show($payroll);

        // Add automation-specific data to the view data
        $viewData = $originalShow->getData();
        $viewData['schedule'] = $schedule;
        $viewData['employee'] = $employee->id;

        return $originalShow->with($viewData);
    }

    /**
     * Back to draft for single employee
     */
    public function backToDraftSingle(Request $request, $schedule, $employee)
    {
        $this->authorize('delete payrolls');

        $employee = Employee::findOrFail($employee);
        $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
            ->where('code', $schedule)
            ->first();

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);

        // Find existing payroll
        $existingPayroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if (!$existingPayroll) {
            return redirect()->route('payrolls.automation.draft.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('info', 'No saved payroll found. Already in draft mode.');
        }

        if ($existingPayroll->status === 'approved') {
            return redirect()->route('payrolls.automation.processing.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('error', 'Cannot return to draft mode. This payroll is already approved.');
        }

        try {
            DB::beginTransaction();

            // Delete payroll details and payroll
            PayrollDetail::where('payroll_id', $existingPayroll->id)->delete();
            $existingPayroll->delete();

            DB::commit();

            return redirect()->route('payrolls.automation.draft.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('success', 'Successfully deleted saved payroll. Returned to draft mode.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return payroll to draft: ' . $e->getMessage());
            return redirect()->route('payrolls.automation.processing.show', [
                'schedule' => $schedule,
                'employee' => $employee->id
            ])->with('error', 'Failed to return to draft mode: ' . $e->getMessage());
        }
    }

    /**
     * Show payroll with additional data for automation context
     */
    private function showPayrollWithAdditionalData($payroll, $additionalData = [])
    {
        // Load all required relationships
        $payroll->load([
            'payrollDetails.employee.user',
            'payrollDetails.employee.department',
            'payrollDetails.employee.position',
            'creator',
            'approver'
        ]);

        // Get all the data needed for the show view (simplified version)
        $employeeIds = $payroll->payrollDetails->pluck('employee_id');

        // Use override period dates if available (for period-specific views)
        $periodStart = $payroll->override_period_start ?? $payroll->period_start;
        $periodEnd = $payroll->override_period_end ?? $payroll->period_end;
        $payDate = $payroll->override_pay_date ?? $payroll->pay_date;

        // Override the payroll properties for display in the view
        if (isset($payroll->override_period_start)) {
            $payroll->period_start = $periodStart;
            $payroll->period_end = $periodEnd;
            $payroll->pay_date = $payDate;
        }

        $startDate = \Carbon\Carbon::parse($periodStart);
        $endDate = \Carbon\Carbon::parse($periodEnd);
        $periodDates = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $periodDates[] = $date->format('Y-m-d');
        }

        $timeLogs = TimeLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$periodStart, $periodEnd])
            ->orderBy('log_date')
            ->get()
            ->groupBy(['employee_id']);

        $dtrData = [];
        foreach ($payroll->payrollDetails as $detail) {
            $employeeLogs = $timeLogs->get($detail->employee_id, collect());
            $employeeLogsByDate = $employeeLogs->groupBy(function ($item) {
                return \Carbon\Carbon::parse($item->log_date)->format('Y-m-d');
            });

            // Build DTR data structure matching automation format
            $employeeDtr = [];
            foreach ($periodDates as $date) {
                $timeLog = $employeeLogsByDate->get($date, collect())->first();
                $employeeDtr[$date] = $timeLog;
            }
            $dtrData[$detail->employee_id] = $employeeDtr;
        }

        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->orderBy('sort_order')
            ->get();
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->orderBy('sort_order')
            ->get();

        // Calculate time breakdowns and pay breakdowns like the regular show method
        $timeBreakdowns = [];
        foreach ($payroll->payrollDetails as $detail) {
            $employeeLogs = $timeLogs->get($detail->employee_id, collect());
            $employeeBreakdown = [];

            foreach ($periodDates as $date) {
                $dayLogs = $employeeLogs->where('log_date', $date);
                if ($dayLogs->isNotEmpty()) {
                    $timeLog = $dayLogs->first();
                    if ($timeLog && !($timeLog->remarks === 'Incomplete Time Record' || (!$timeLog->time_in || !$timeLog->time_out))) {
                        $logType = $timeLog->log_type;
                        if (!isset($employeeBreakdown[$logType])) {
                            $employeeBreakdown[$logType] = [
                                'regular_hours' => 0,
                                'overtime_hours' => 0,
                                'total_hours' => 0,
                                'days_count' => 0,
                                'display_name' => '',
                                'rate_config' => null
                            ];
                        }

                        $employeeBreakdown[$logType]['regular_hours'] += $timeLog->regular_hours ?? 0;
                        $employeeBreakdown[$logType]['overtime_hours'] += $timeLog->overtime_hours ?? 0;
                        $employeeBreakdown[$logType]['total_hours'] += $timeLog->total_hours ?? 0;
                        $employeeBreakdown[$logType]['days_count']++;

                        // Get rate configuration for this type
                        $rateConfig = $timeLog->getRateConfiguration();
                        if ($rateConfig) {
                            $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                            $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                        }
                    }
                }
            }

            $timeBreakdowns[$detail->employee_id] = $employeeBreakdown;
        }

        // Calculate separate basic pay and holiday pay for each employee
        $payBreakdownByEmployee = [];
        foreach ($payroll->payrollDetails as $detail) {
            // For processing/approved payrolls, use stored values from PayrollDetail
            if ($payroll->status !== 'draft') {
                $payBreakdownByEmployee[$detail->employee_id] = [
                    'basic_pay' => $detail->regular_pay ?? 0,
                    'holiday_pay' => $detail->holiday_pay ?? 0,
                    'rest_day_pay' => $detail->rest_day_pay ?? 0,
                    'overtime_pay' => $detail->overtime_pay ?? 0,
                ];
            } else {
                // For draft payrolls, calculate dynamically from time logs
                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                $hourlyRate = $this->calculateHourlyRate($detail->employee, $detail->employee->basic_salary ?? 0);

                $basicPay = 0; // Regular workday pay only
                $holidayPay = 0; // All holiday-related pay
                $restDayPay = 0; // Rest day pay
                $overtimePay = 0; // All overtime pay

                foreach ($employeeBreakdown as $logType => $breakdown) {
                    $rateConfig = $breakdown['rate_config'];
                    if (!$rateConfig) continue;

                    // Calculate pay amounts using rate multipliers
                    $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                    $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;

                    $regularPay = $breakdown['regular_hours'] * $hourlyRate * $regularMultiplier;

                    // Calculate overtime pay with night differential breakdown
                    $overtimePayAmount = 0;
                    $regularOvertimeHours = $breakdown['regular_overtime_hours'] ?? 0;
                    $nightDiffOvertimeHours = $breakdown['night_diff_overtime_hours'] ?? 0;

                    if ($regularOvertimeHours > 0 || $nightDiffOvertimeHours > 0) {
                        // Use breakdown calculation

                        // Regular overtime pay
                        if ($regularOvertimeHours > 0) {
                            $overtimePayAmount += $regularOvertimeHours * $hourlyRate * $overtimeMultiplier;
                        }

                        // Night differential overtime pay (overtime rate + night differential bonus)
                        if ($nightDiffOvertimeHours > 0) {
                            // Get night differential setting
                            $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                            $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                            // Combined rate: base overtime rate + night differential bonus
                            $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                            $overtimePayAmount += $nightDiffOvertimeHours * $hourlyRate * $combinedMultiplier;
                        }
                    } else {
                        // Fallback to simple calculation if no breakdown available
                        $overtimePayAmount = $breakdown['overtime_hours'] * $hourlyRate * $overtimeMultiplier;
                    }

                    // All overtime goes to overtime column regardless of day type
                    $overtimePay += $overtimePayAmount;

                    if ($logType === 'regular_workday') {
                        $basicPay += $regularPay; // Only regular hours pay to basic pay
                    } elseif (in_array($logType, ['special_holiday', 'regular_holiday'])) {
                        $holidayPay += $regularPay; // Only regular hours pay to holiday pay
                    } elseif (in_array($logType, ['rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                        $holidayPay += $regularPay; // Rest day holidays count as holiday pay
                    } elseif ($logType === 'rest_day') {
                        $restDayPay += $regularPay; // Only regular hours pay to rest day pay
                    }
                }

                $payBreakdownByEmployee[$detail->employee_id] = [
                    'basic_pay' => $basicPay,
                    'holiday_pay' => $holidayPay,
                    'rest_day_pay' => $restDayPay,
                    'overtime_pay' => $overtimePay,
                ];
            }
        }

        $totalHolidayPay = array_sum(array_column($payBreakdownByEmployee, 'holiday_pay'));
        $totalRestDayPay = array_sum(array_column($payBreakdownByEmployee, 'rest_day_pay'));
        $totalOvertimePay = array_sum(array_column($payBreakdownByEmployee, 'overtime_pay'));

        // CRITICAL: Extract breakdown data from snapshots for hybrid display
        $basicBreakdown = [];
        $holidayBreakdown = [];
        $restBreakdown = [];
        $suspensionBreakdown = [];
        $overtimeBreakdown = [];

        // Check if payroll has snapshots (processing/approved status)
        $snapshots = $payroll->snapshots()->get();
        if ($snapshots->isNotEmpty()) {
            // For processing/approved payrolls, use snapshot data for breakdowns
            foreach ($snapshots as $snapshot) {
                if ($snapshot->basic_breakdown) {
                    $basicBreakdown = is_string($snapshot->basic_breakdown)
                        ? json_decode($snapshot->basic_breakdown, true)
                        : $snapshot->basic_breakdown;
                }
                if ($snapshot->holiday_breakdown) {
                    $holidayBreakdown = is_string($snapshot->holiday_breakdown)
                        ? json_decode($snapshot->holiday_breakdown, true)
                        : $snapshot->holiday_breakdown;
                }
                if ($snapshot->rest_breakdown) {
                    $restBreakdown = is_string($snapshot->rest_breakdown)
                        ? json_decode($snapshot->rest_breakdown, true)
                        : $snapshot->rest_breakdown;
                }
                if ($snapshot->suspension_breakdown) {
                    $suspensionBreakdown = is_string($snapshot->suspension_breakdown)
                        ? json_decode($snapshot->suspension_breakdown, true)
                        : $snapshot->suspension_breakdown;
                }
                if ($snapshot->overtime_breakdown) {
                    $overtimeBreakdown = is_string($snapshot->overtime_breakdown)
                        ? json_decode($snapshot->overtime_breakdown, true)
                        : $snapshot->overtime_breakdown;
                }
            }

            foreach ($payroll->payrollDetails as $detail) {
                $snapshot = $snapshots->where('employee_id', $detail->employee_id)->first();
                if ($snapshot) {
                    // Set breakdown data from snapshots
                    $detail->earnings_breakdown = json_encode([
                        'allowances' => $snapshot->allowances_breakdown ?? []
                    ]);
                    $detail->deductions_breakdown = json_encode([
                        'deductions' => $snapshot->deductions_breakdown ?? []
                    ]);
                }
            }
        } else {
            // For draft payrolls, calculate allowance breakdowns dynamically
            foreach ($payroll->payrollDetails as $detail) {
                // Calculate allowances breakdown dynamically
                $allowancesData = $this->calculateAllowances($detail->employee, $detail->basic_salary, $detail->days_worked, $detail->regular_hours, $payroll->period_start, $payroll->period_end);

                $detail->earnings_breakdown = json_encode([
                    'allowances' => $allowancesData['breakdown'] ?? []
                ]);
            }
        }

        return view('payrolls.show', compact(
            'payroll',
            'dtrData',
            'periodDates',
            'allowanceSettings',
            'deductionSettings',
            'timeBreakdowns',
            'payBreakdownByEmployee',
            'totalHolidayPay',
            'totalRestDayPay',
            'totalOvertimePay',
            'basicBreakdown',
            'holidayBreakdown',
            'restBreakdown',
            'suspensionBreakdown',
            'overtimeBreakdown'
        ) + [
            'isDynamic' => false
        ] + $additionalData);
    }

    // ===== UNIFIED AUTOMATION PAYROLL METHODS =====

    /**
     * Show unified payroll view (handles draft, processing, approved statuses)
     */
    public function showUnifiedPayroll(Request $request, $schedule, $id)
    {
        $this->authorize('view payrolls');

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->type,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // First, check if this ID is a payroll ID (for saved/historical payrolls)
        $payroll = Payroll::with(['payrollDetails.employee', 'creator', 'approver'])
            ->where('id', $id)
            ->where('pay_schedule', $schedule)
            ->first();

        if ($payroll) {
            // This is a saved payroll - show historical data
            $firstDetail = $payroll->payrollDetails->first();
            if (!$firstDetail) {
                return redirect()->route('payrolls.index')
                    ->with('error', 'Payroll has no employee details.');
            }

            $employee = $firstDetail->employee;
            return $this->showSavedPayroll($payroll, $schedule, $employee->id);
        }

        // If not a payroll ID, treat it as an employee ID (for current period/draft payrolls)
        $employee = Employee::with(['timeSchedule', 'daySchedule'])->find($id);

        if (!$employee) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Employee or payroll not found.');
        }

        // Use the appropriate period calculation method based on system type
        if (isset($selectedSchedule->id) && $paySchedule) {
            // New PaySchedule system
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } else {
            // Legacy PayScheduleSetting system
            $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        // Check if a saved payroll exists for this employee and current period
        $existingPayroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if ($existingPayroll) {
            // Redirect to the payroll ID URL instead of employee ID
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $existingPayroll->id
            ]);
        } else {
            // Show draft payroll (dynamic calculations)
            return $this->showDraftPayrollUnified($schedule, $employee, $currentPeriod);
        }
    }

    /**
     * Process unified payroll (draft to processing)
     */
    public function processUnifiedPayroll(Request $request, $schedule, $id)
    {
        // \Log::info('ProcessUnifiedPayroll called', ['schedule' => $schedule, 'id' => $id, 'user' => auth()->id()]);

        // // Check if user is authenticated
        // if (!auth()->check()) {
        //     \Log::error('User not authenticated for payroll processing');
        //     return redirect()->back()->with('error', 'You must be logged in to process payrolls.');
        // }

        // // Check authorization
        // try {
        //     $this->authorize('create payrolls');
        //     \Log::info('User authorized for payroll processing');
        // } catch (\Exception $e) {
        //     \Log::error('Authorization failed for payroll processing', ['error' => $e->getMessage(), 'user' => auth()->id()]);
        //     return redirect()->back()->with('error', 'You do not have permission to process payrolls.');
        // }

        $employee = Employee::with(['timeSchedule', 'daySchedule'])->findOrFail($id);

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->name,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Use the appropriate period calculation method based on system type
        if (isset($selectedSchedule->id) && $paySchedule) {
            // New PaySchedule system
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } else {
            // Legacy PayScheduleSetting system
            $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        // Check if payroll already exists
        $existingPayroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if ($existingPayroll) {
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $employee->id
            ])->with('info', 'This payroll is already processed.');
        }

        try {
            // Create payroll with snapshots
            $employees = collect([$employee]);

            // Create a temporary schedule object with the correct schedule name for payroll creation
            $scheduleForCreation = (object) [
                'code' => $schedule,  // Use the actual schedule name from URL
                'name' => $selectedSchedule->name,
                'type' => $selectedSchedule->type,
                'id' => $selectedSchedule->id ?? null
            ];

            $createdPayroll = $this->autoCreatePayrollForPeriod($scheduleForCreation, $currentPeriod, $employees, 'processing');

            // Redirect to payroll ID URL instead of employee ID
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $createdPayroll->id
            ])->with('success', 'Payroll processed and saved to database with data snapshot.');
        } catch (\Exception $e) {
            Log::error('Failed to process unified payroll: ' . $e->getMessage());
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $employee->id
            ])->with('error', 'Failed to process payroll: ' . $e->getMessage());
        }
    }

    /**
     * Back to draft from unified payroll
     */
    public function backToUnifiedDraft(Request $request, $schedule, $id)
    {
        $this->authorize('edit payrolls');

        $employee = Employee::findOrFail($id);

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->name,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Use the appropriate period calculation method based on system type
        if (isset($selectedSchedule->id) && $paySchedule) {
            // New PaySchedule system
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } else {
            // Legacy PayScheduleSetting system
            $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        try {
            DB::beginTransaction();

            // Delete payroll and related data
            $payrolls = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
                $query->where('employee_id', $employee->id);
            })
                ->where('pay_schedule', $schedule)
                ->where('period_start', $currentPeriod['start'])
                ->where('period_end', $currentPeriod['end'])
                ->where('payroll_type', 'automated')
                ->get();

            foreach ($payrolls as $payroll) {
                // Delete snapshots
                $payroll->snapshots()->delete();
                // Delete payroll details
                $payroll->payrollDetails()->delete();
                // Delete payroll
                $payroll->delete();
            }

            DB::commit();

            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $employee->id
            ])->with('success', 'Successfully deleted saved payroll. Returned to draft mode.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return unified payroll to draft: ' . $e->getMessage());
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $employee->id
            ])->with('error', 'Failed to return to draft: ' . $e->getMessage());
        }
    }

    /**
     * Approve unified payroll
     */
    public function approveUnifiedPayroll(Request $request, $schedule, $id)
    {
        $this->authorize('approve payrolls');

        $employee = Employee::findOrFail($id);

        // Try to find by name first (new system)
        $paySchedule = PaySchedule::active()
            ->where('name', $schedule)
            ->first();

        $selectedSchedule = null;
        if ($paySchedule) {
            $selectedSchedule = (object) [
                'code' => $paySchedule->name,
                'name' => $paySchedule->name,
                'type' => $paySchedule->type,
                'id' => $paySchedule->id
            ];
        } else {
            // Legacy system fallback
            $selectedSchedule = \App\Models\PayScheduleSetting::systemDefaults()
                ->where('code', $schedule)
                ->first();
        }

        if (!$selectedSchedule) {
            return redirect()->route('payrolls.automation.index')
                ->with('error', 'Invalid pay schedule selected.');
        }

        // Use the appropriate period calculation method based on system type
        if (isset($selectedSchedule->id) && $paySchedule) {
            // New PaySchedule system
            $currentPeriod = $this->calculateCurrentPayPeriodForSchedule($paySchedule);
        } else {
            // Legacy PayScheduleSetting system
            $currentPeriod = $this->calculateCurrentPayPeriod($selectedSchedule);
        }

        // Find existing payroll
        $payroll = Payroll::whereHas('payrollDetails', function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);
        })
            ->where('pay_schedule', $schedule)
            ->where('period_start', $currentPeriod['start'])
            ->where('period_end', $currentPeriod['end'])
            ->where('payroll_type', 'automated')
            ->first();

        if (!$payroll) {
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $employee->id
            ])->with('error', 'No payroll found to approve.');
        }

        try {
            $payroll->status = 'approved';
            $payroll->approved_by = Auth::id();
            $payroll->approved_at = now();
            $payroll->save();

            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $payroll->id
            ])->with('success', 'Payroll approved successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to approve unified payroll: ' . $e->getMessage());
            return redirect()->route('payrolls.automation.show', [
                'schedule' => $schedule,
                'id' => $payroll->id ?? $employee->id
            ])->with('error', 'Failed to approve payroll: ' . $e->getMessage());
        }
    }

    /**
     * Show draft payroll for unified view
     */
    private function showDraftPayrollUnified($schedule, $employee, $currentPeriod)
    {
        $payrollCalculation = $this->calculateEmployeePayrollForPeriod($employee, $currentPeriod['start'], $currentPeriod['end']);

        // Log what we're getting
        Log::info('Draft Payroll Calculation for Employee ' . $employee->id, $payrollCalculation);

        // Create mock payroll for display
        $draftPayroll = new Payroll();
        $draftPayroll->id = $employee->id;
        $draftPayroll->payroll_number = 'DRAFT-' . $employee->employee_number;
        $draftPayroll->period_start = $currentPeriod['start'];
        $draftPayroll->period_end = $currentPeriod['end'];
        $draftPayroll->pay_date = $currentPeriod['pay_date'];
        $draftPayroll->status = 'draft';
        $draftPayroll->payroll_type = 'automated';
        $draftPayroll->total_gross = $payrollCalculation['gross_pay'] ?? 0;
        $draftPayroll->total_deductions = $payrollCalculation['total_deductions'] ?? 0;
        $draftPayroll->total_net = $payrollCalculation['net_pay'] ?? 0;
        $draftPayroll->created_at = now();

        // Set relationships
        $fakeCreator = (object) ['name' => 'System (Draft)', 'id' => 0];
        $draftPayroll->setRelation('creator', $fakeCreator);
        $draftPayroll->setRelation('approver', null);

        // Create mock payroll detail
        $draftPayrollDetail = new PayrollDetail();
        $draftPayrollDetail->employee_id = $employee->id;
        $draftPayrollDetail->basic_salary = $employee->basic_salary ?? 0;
        $draftPayrollDetail->daily_rate = $employee->daily_rate ?? 0;
        $draftPayrollDetail->hourly_rate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        $draftPayrollDetail->days_worked = $payrollCalculation['days_worked'] ?? 0;
        $draftPayrollDetail->regular_hours = $payrollCalculation['regular_hours'] ?? 0;
        $draftPayrollDetail->overtime_hours = $payrollCalculation['overtime_hours'] ?? 0;
        $draftPayrollDetail->holiday_hours = $payrollCalculation['holiday_hours'] ?? 0;
        $draftPayrollDetail->rest_day_hours = $payrollCalculation['rest_day_hours'] ?? 0;
        $draftPayrollDetail->regular_pay = $payrollCalculation['regular_pay'] ?? 0;
        $draftPayrollDetail->overtime_pay = $payrollCalculation['overtime_pay'] ?? 0;
        $draftPayrollDetail->holiday_pay = $payrollCalculation['holiday_pay'] ?? 0;
        $draftPayrollDetail->rest_day_pay = $payrollCalculation['rest_day_pay'] ?? 0;
        $draftPayrollDetail->allowances = $payrollCalculation['allowances'] ?? 0;
        $draftPayrollDetail->bonuses = $payrollCalculation['bonuses'] ?? 0;
        $draftPayrollDetail->incentives = $payrollCalculation['incentives'] ?? 0;
        $draftPayrollDetail->gross_pay = $payrollCalculation['gross_pay'] ?? 0;
        $draftPayrollDetail->sss_contribution = $payrollCalculation['sss_deduction'] ?? 0;
        $draftPayrollDetail->philhealth_contribution = $payrollCalculation['philhealth_deduction'] ?? 0;
        $draftPayrollDetail->pagibig_contribution = $payrollCalculation['pagibig_deduction'] ?? 0;
        $draftPayrollDetail->withholding_tax = $payrollCalculation['tax_deduction'] ?? 0;
        $draftPayrollDetail->late_deductions = $payrollCalculation['late_deductions'] ?? 0;
        $draftPayrollDetail->undertime_deductions = $payrollCalculation['undertime_deductions'] ?? 0;
        $draftPayrollDetail->cash_advance_deductions = $payrollCalculation['cash_advance_deductions'] ?? 0;
        $draftPayrollDetail->other_deductions = $payrollCalculation['other_deductions'] ?? 0;
        $draftPayrollDetail->total_deductions = $payrollCalculation['total_deductions'] ?? 0;
        $draftPayrollDetail->net_pay = $payrollCalculation['net_pay'] ?? 0;

        // Set earnings and deduction breakdowns
        if (isset($payrollCalculation['allowances_details'])) {
            $draftPayrollDetail->earnings_breakdown = json_encode([
                'allowances' => $payrollCalculation['allowances_details']
            ]);
        }
        if (isset($payrollCalculation['deductions_details'])) {
            $draftPayrollDetail->deduction_breakdown = json_encode($payrollCalculation['deductions_details']);
        }

        // Set employee relationship
        $draftPayrollDetail->setRelation('employee', $employee->load(['user', 'department', 'position', 'daySchedule', 'timeSchedule']));

        // Set payroll details collection
        $draftPayroll->setRelation('payrollDetails', collect([$draftPayrollDetail]));

        // Create period dates array
        $startDate = \Carbon\Carbon::parse($currentPeriod['start']);
        $endDate = \Carbon\Carbon::parse($currentPeriod['end']);
        $periodDates = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $periodDates[] = $date->format('Y-m-d');
        }

        // Get DTR data
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$currentPeriod['start'], $currentPeriod['end']])
            ->orderBy('log_date')
            ->get()
            ->groupBy(function ($item) {
                return \Carbon\Carbon::parse($item->log_date)->format('Y-m-d');
            });

        // Build DTR data structure matching working version
        $employeeDtr = [];
        foreach ($periodDates as $date) {
            $timeLog = $timeLogs->get($date, collect())->first();

            // For draft payrolls, add dynamic calculation to time log object for DTR display
            if ($timeLog && $timeLog->time_in && $timeLog->time_out && $timeLog->remarks !== 'Incomplete Time Record') {
                $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                $timeLog->dynamic_regular_hours = $dynamicCalculation['regular_hours'];
                $timeLog->dynamic_overtime_hours = $dynamicCalculation['overtime_hours'];
                $timeLog->dynamic_regular_overtime_hours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
                $timeLog->dynamic_night_diff_overtime_hours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                $timeLog->dynamic_total_hours = $dynamicCalculation['total_hours'];
            }

            $employeeDtr[$date] = $timeLog;
        }
        $dtrData = [$employee->id => $employeeDtr];

        // Create time breakdowns similar to regular show method
        $timeBreakdowns = [$employee->id => []];
        $employeeBreakdown = [];

        foreach ($periodDates as $date) {
            $timeLog = $timeLogs->get($date, collect())->first();
            // Track time breakdown by type (exclude incomplete records, but include suspension days even without time data)
            if ($timeLog && !($timeLog->remarks === 'Incomplete Time Record' || ((!$timeLog->time_in || !$timeLog->time_out) && !in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension'])))) {
                $logType = $timeLog->log_type;
                if (!isset($employeeBreakdown[$logType])) {
                    $employeeBreakdown[$logType] = [
                        'regular_hours' => 0,
                        'overtime_hours' => 0,
                        'regular_overtime_hours' => 0,
                        'night_diff_overtime_hours' => 0,
                        'night_diff_regular_hours' => 0, // ADD: Missing night differential regular hours
                        'total_hours' => 0,
                        'days_count' => 0,
                        'days' => 0, // NEW: Track suspension days
                        'suspension_settings' => [], // NEW: Store suspension configurations
                        'actual_time_log_hours' => 0, // NEW: For partial suspensions
                        'display_name' => '',
                        'rate_config' => null
                    ];
                }

                // Handle suspension records - only process actual suspension time logs WITH active settings
                if (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                    // CRITICAL: Check if there's an active suspension setting for this date
                    // Manual time log updates without settings should NOT be processed
                    $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $timeLog->log_date)
                        ->where('date_to', '>=', $timeLog->log_date)
                        ->where('status', 'active')
                        ->first();

                    // Only process if there's an active suspension setting
                    if ($suspensionSetting) {
                        // Process time logs that already have suspension log types
                        $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                        $totalHours = $dynamicCalculation['total_hours'] ?? 0;
                        $regularHours = $dynamicCalculation['regular_hours'] ?? 0;
                        $overtimeHours = $dynamicCalculation['overtime_hours'] ?? 0;

                        $employeeBreakdown[$logType]['days']++;
                        $employeeBreakdown[$logType]['days_count']++;

                        // Store the suspension setting for later use
                        $employeeBreakdown[$logType]['suspension_settings'][$timeLog->log_date->format('Y-m-d')] = [
                            'is_paid' => $suspensionSetting->is_paid,
                            'pay_rule' => $suspensionSetting->pay_rule,
                            'pay_applicable_to' => $suspensionSetting->pay_applicable_to,
                            'type' => $suspensionSetting->type
                        ];

                        // For partial suspensions, include the work hours
                        if ($logType === 'partial_suspension' && $totalHours > 0) {
                            $employeeBreakdown[$logType]['regular_hours'] += $regularHours;
                            $employeeBreakdown[$logType]['overtime_hours'] += $overtimeHours;
                            $employeeBreakdown[$logType]['total_hours'] += $totalHours;
                            $employeeBreakdown[$logType]['actual_time_log_hours'] += $totalHours;
                        }

                        // Get rate configuration for this suspension type
                        $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $logType)
                            ->where('is_active', true)
                            ->first();

                        if ($rateConfig) {
                            $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                            $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                        }
                    }
                    // If no suspension setting found, skip this time log (don't add to breakdown)
                } elseif ($logType === 'suspension') {
                    // Legacy suspension type - treat as regular work day for now
                    // or skip processing since it should use the new suspension types
                    continue;
                } else {
                    // Regular time log processing
                    // Use dynamic calculation for draft payroll
                    $dynamicCalculation = $this->calculateTimeLogHoursDynamically($timeLog);
                    $regularHours = $dynamicCalculation['regular_hours'];
                    $overtimeHours = $dynamicCalculation['overtime_hours'];
                    $regularOvertimeHours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
                    $nightDiffOvertimeHours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
                    $nightDiffRegularHours = $dynamicCalculation['night_diff_regular_hours'] ?? 0; // ADD: Extract night diff regular hours
                    $totalHours = $dynamicCalculation['total_hours'];

                    $employeeBreakdown[$logType]['regular_hours'] += $regularHours;
                    $employeeBreakdown[$logType]['overtime_hours'] += $overtimeHours;
                    $employeeBreakdown[$logType]['regular_overtime_hours'] += $regularOvertimeHours;
                    $employeeBreakdown[$logType]['night_diff_overtime_hours'] += $nightDiffOvertimeHours;
                    $employeeBreakdown[$logType]['night_diff_regular_hours'] += $nightDiffRegularHours; // ADD: Store night diff regular hours
                    $employeeBreakdown[$logType]['total_hours'] += $totalHours;
                    $employeeBreakdown[$logType]['days_count']++;

                    // Get rate configuration for this type
                    $rateConfig = $timeLog->getRateConfiguration();
                    if ($rateConfig) {
                        $employeeBreakdown[$logType]['display_name'] = $rateConfig->display_name;
                        $employeeBreakdown[$logType]['rate_config'] = $rateConfig;
                    }
                }
            }

            // NOTE: Removed automatic suspension detection based on settings
            // Suspension pay should only be calculated when there are actual time logs 
            // with suspension log types (full_day_suspension, partial_suspension)
        }

        $timeBreakdowns[$employee->id] = $employeeBreakdown;

        // Calculate pay breakdown by employee
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        $basicPay = 0;
        $holidayPay = 0;
        $restDayPay = 0;
        $overtimePay = 0;

        foreach ($employeeBreakdown as $logType => $breakdown) {
            $rateConfig = $breakdown['rate_config'];
            if (!$rateConfig) continue;

            $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
            $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;

            $regularPay = $breakdown['regular_hours'] * $hourlyRate * $regularMultiplier;

            // Calculate overtime pay with night differential breakdown
            $overtimePayAmount = 0;
            $regularOvertimeHours = $breakdown['regular_overtime_hours'] ?? 0;
            $nightDiffOvertimeHours = $breakdown['night_diff_overtime_hours'] ?? 0;

            if ($regularOvertimeHours > 0 || $nightDiffOvertimeHours > 0) {
                // Use breakdown calculation

                // Regular overtime pay
                if ($regularOvertimeHours > 0) {
                    $overtimePayAmount += $regularOvertimeHours * $hourlyRate * $overtimeMultiplier;
                }

                // Night differential overtime pay (overtime rate + night differential bonus)
                if ($nightDiffOvertimeHours > 0) {
                    // Get night differential setting
                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                    // Combined rate: base overtime rate + night differential bonus
                    $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                    $overtimePayAmount += $nightDiffOvertimeHours * $hourlyRate * $combinedMultiplier;
                }
            } else {
                // Fallback to simple calculation if no breakdown available
                $overtimePayAmount = $breakdown['overtime_hours'] * $hourlyRate * $overtimeMultiplier;
            }

            // All overtime goes to overtime column regardless of day type
            $overtimePay += $overtimePayAmount;

            if ($logType === 'regular_workday') {
                $basicPay += $regularPay; // Only regular hours pay to basic pay
            } elseif (in_array($logType, ['special_holiday', 'regular_holiday'])) {
                $holidayPay += $regularPay; // Only regular hours pay to holiday pay
            } elseif (in_array($logType, ['rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                $holidayPay += $regularPay; // Rest day holidays count as holiday pay
            } elseif ($logType === 'rest_day') {
                $restDayPay += $regularPay; // Only regular hours pay to rest day pay
            } elseif ($logType === 'suspension') {
                // Legacy suspension handling - should not happen with new logic
                $basicPay += $regularPay;
            } elseif (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                // CRITICAL: Do NOT add suspension pay here! 
                // Suspension pay must be calculated by breakdown methods with proper suspension settings
                // This was causing UNPAID suspensions to show amounts in REGULAR column
                // $basicPay += $regularPay; // REMOVED - let breakdown methods handle suspension pay
            }
        }

        $payBreakdownByEmployee = [
            $employee->id => [
                'basic_pay' => $basicPay,
                'holiday_pay' => $holidayPay,
                'rest_day_pay' => $restDayPay,
                'overtime_pay' => $overtimePay,
            ]
        ];

        // Get settings
        $allowanceSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'allowance')
            ->orderBy('sort_order')
            ->get();
        $bonusSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'bonus')
            ->orderBy('sort_order')
            ->get();
        $incentiveSettings = \App\Models\AllowanceBonusSetting::where('is_active', true)
            ->where('type', 'incentives')
            ->orderBy('sort_order')
            ->get();
        $deductionSettings = \App\Models\DeductionTaxSetting::active()
            ->orderBy('sort_order')
            ->get();

        $totalHolidayPay = $holidayPay;
        $totalRestDayPay = $restDayPay;
        $totalOvertimePay = $overtimePay;

        // Create detailed breakdowns using same methods as regular draft payroll
        $basicBreakdown = $this->createBasicPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $holidayBreakdown = $this->createHolidayPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $restBreakdown = $this->createRestPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $suspensionBreakdown = $this->createSuspensionPayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);
        $overtimeBreakdown = $this->createOvertimePayBreakdown($employeeBreakdown, $employee, $currentPeriod['start'], $currentPeriod['end']);

        return view('payrolls.show', compact(
            'draftPayroll',
            'dtrData',
            'periodDates',
            'allowanceSettings',
            'bonusSettings',
            'incentiveSettings',
            'deductionSettings',
            'timeBreakdowns',
            'payBreakdownByEmployee',
            'totalHolidayPay',
            'totalRestDayPay',
            'totalOvertimePay',
            'basicBreakdown',
            'holidayBreakdown',
            'restBreakdown',
            'suspensionBreakdown',
            'overtimeBreakdown'
        ) + [
            'payroll' => $draftPayroll,
            'isDraft' => true,
            'isDynamic' => true,
            'schedule' => $schedule,
            'employee' => $employee->id
        ]);
    }

    /**
     * Show saved payroll for unified view
     */
    private function showSavedPayroll($payroll, $schedule, $employeeId)
    {
        return $this->showPayrollWithAdditionalData($payroll, [
            'schedule' => $schedule,
            'employee' => $employeeId
        ]);
    }

    /**
     * Link existing time logs to a payroll based on period and employee
     */

    /**
     * Calculate time log hours dynamically using current grace periods and employee schedules
     * This is used for draft payrolls to get real-time calculations
     */
    private function calculateTimeLogHoursDynamically(TimeLog $timeLog)
    {
        // Use the same dynamic calculation method from TimeLogController
        $timeLogController = app(\App\Http\Controllers\TimeLogController::class);

        // Use reflection to access the private method
        $reflection = new \ReflectionClass($timeLogController);
        $method = $reflection->getMethod('calculateDynamicWorkingHours');
        $method->setAccessible(true);

        try {
            return $method->invoke($timeLogController, $timeLog);
        } catch (\Exception $e) {
            // Fallback to stored values if calculation fails
            Log::error('Dynamic time log calculation failed: ' . $e->getMessage());

            return [
                'total_hours' => $timeLog->total_hours ?? 0,
                'regular_hours' => $timeLog->regular_hours ?? 0,
                'night_diff_regular_hours' => $timeLog->night_diff_regular_hours ?? 0,
                'overtime_hours' => $timeLog->overtime_hours ?? 0,
                'regular_overtime_hours' => $timeLog->regular_overtime_hours ?? 0,
                'night_diff_overtime_hours' => $timeLog->night_diff_overtime_hours ?? 0,
                'late_hours' => $timeLog->late_hours ?? 0,
                'undertime_hours' => $timeLog->undertime_hours ?? 0,
            ];
        }
    }

    /**
     * Calculate late deductions based on late hours
     */
    private function calculateLateDeductions($employee, $lateHours)
    {
        if ($lateHours <= 0) {
            return 0;
        }

        // Calculate deduction based on hourly rate
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        return $lateHours * $hourlyRate;
    }

    /**
     * Calculate undertime deductions based on undertime hours
     */
    private function calculateUndertimeDeductions($employee, $undertimeHours)
    {
        if ($undertimeHours <= 0) {
            return 0;
        }

        // Calculate deduction based on hourly rate
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0);
        return $undertimeHours * $hourlyRate;
    }

    /**
     * Create Basic Pay breakdown for snapshot
     */
    private function createBasicPayBreakdown($timeBreakdown, $employee, $periodStart = null, $periodEnd = null)
    {
        $breakdown = [];
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $periodStart, $periodEnd);

        // Process regular workday hours first
        if (isset($timeBreakdown['regular_workday'])) {
            $regularData = $timeBreakdown['regular_workday'];
            $workdayHours = $regularData['regular_hours'];
            $workdayNightDiffHours = $regularData['night_diff_regular_hours'] ?? 0;

            // Get rate configuration for regular workday
            $rateConfig = $regularData['rate_config'] ?? null;
            $regularMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.01;

            if ($workdayHours > 0) {
                $actualMinutes = $workdayHours * 60;
                $roundedMinutes = round($actualMinutes);
                $adjustedHourlyRate = $hourlyRate * $regularMultiplier;
                $ratePerMinute = $adjustedHourlyRate / 60;
                $amount = round($ratePerMinute * $roundedMinutes, 2);

                $breakdown['Regular Workday'] = [
                    'hours' => $workdayHours,
                    'minutes' => $roundedMinutes,
                    'rate' => $hourlyRate,
                    'rate_per_minute' => $ratePerMinute,
                    'multiplier' => $regularMultiplier,
                    'amount' => $amount,
                    'description' => 'Regular Workday: ' . $roundedMinutes . 'm',
                    'workday_hours' => $workdayHours,
                    'suspension_hours' => 0
                ];
            }

            // Add night differential for workday if exists
            if ($workdayNightDiffHours > 0) {
                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                $combinedMultiplier = $regularMultiplier + ($nightDiffMultiplier - 1);

                $actualMinutes = $workdayNightDiffHours * 60;
                $roundedMinutes = round($actualMinutes);
                $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                $ratePerMinute = $adjustedHourlyRate / 60;
                $amount = round($ratePerMinute * $roundedMinutes, 2);

                $breakdown['Regular Workday+ND'] = [
                    'hours' => $workdayNightDiffHours,
                    'minutes' => $roundedMinutes,
                    'rate' => $hourlyRate,
                    'rate_per_minute' => $ratePerMinute,
                    'multiplier' => $combinedMultiplier,
                    'amount' => $amount
                ];
            }
        }

        // Suspension types should be handled by createSuspensionPayBreakdown, not here
        // Removed suspension processing from overtime breakdown method

        // Legacy suspension handling (for backwards compatibility)
        if (isset($timeBreakdown['suspension'])) {
            $suspensionData = $timeBreakdown['suspension'];
            $suspensionDays = $suspensionData['days'] ?? 0;
            $suspensionSettings = $suspensionData['suspension_settings'] ?? [];

            // IMPORTANT: Only calculate suspension pay if there are corresponding suspension settings
            // Manual suspension entries (created via bulk time logs UI) should NOT have settings and remain unpaid
            if ($suspensionDays > 0 && !empty($suspensionSettings)) {
                // Calculate daily rate
                $dailyRate = $hourlyRate * 8;

                foreach ($suspensionSettings as $date => $setting) {
                    $isPaid = $setting['is_paid'] ?? false;
                    $payRule = $setting['pay_rule'] ?? 'full';
                    $payApplicableTo = $setting['pay_applicable_to'] ?? 'all';
                    $isPartial = $setting['type'] === 'partial_suspension';

                    // Check if suspension pay applies to this employee
                    $employeeHasBenefits = $employee->benefits_status === 'with_benefits';
                    $shouldReceivePay = false;

                    if ($isPaid) {
                        if ($payApplicableTo === 'all') {
                            $shouldReceivePay = true;
                        } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                            $shouldReceivePay = true;
                        } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                            $shouldReceivePay = true;
                        }
                    }

                    if ($shouldReceivePay) {
                        // Get rate configuration for dynamic multiplier
                        $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'partial_suspension')
                            ->where('is_active', true)
                            ->first();
                        $dynamicMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.1;

                        // Calculate fixed daily rate amount
                        $multiplier = ($payRule === 'full') ? 1.0 : 0.5;
                        $amount = round($dailyRate * $multiplier, 2);

                        if ($isPartial) {
                            // PARTIAL SUSPENSION: Fixed amount + possible time log earnings WITH DYNAMIC RATE
                            $actualTimeLogHours = $suspensionData['actual_time_log_hours'] ?? 0;
                            $adjustedHourlyRate = $hourlyRate * $dynamicMultiplier;
                            $timeLogAmount = round($actualTimeLogHours * $adjustedHourlyRate, 2);
                            $totalAmount = $amount + $timeLogAmount;

                            $description = "Partial Suspension: ₱" . number_format($amount, 2) . " (fixed) + ₱" . number_format($timeLogAmount, 2) . " (worked)";

                            $breakdown['Paid Partial Suspension'] = [
                                'hours' => 0,
                                'days' => 1,
                                'rate' => $dailyRate,
                                'multiplier' => $multiplier,
                                'fixed_amount' => $amount,
                                'time_log_amount' => $timeLogAmount,
                                'amount' => $totalAmount,
                                'description' => $description,
                                'workday_hours' => 0,
                                'suspension_hours' => 0
                            ];
                        } else {
                            // FULL DAY SUSPENSION: Only fixed amount, no time log earnings
                            $description = "Paid Suspension: ₱" . number_format($amount, 2) . " (fixed daily rate, " . ($payRule === 'full' ? '100%' : '50%') . ")";

                            $breakdown['Paid Suspension'] = [
                                'hours' => 0,
                                'days' => 1,
                                'rate' => $dailyRate,
                                'multiplier' => $multiplier,
                                'amount' => $amount,
                                'description' => $description,
                                'workday_hours' => 0,
                                'suspension_hours' => 0
                            ];
                        }
                    }
                }
            }
        }

        return $breakdown;
    }

    /**
     * Create Holiday Pay breakdown for snapshot
     */
    private function createHolidayPayBreakdown($timeBreakdown, $employee, $periodStart = null, $periodEnd = null)
    {
        $breakdown = [];
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $periodStart, $periodEnd);
        $dailyRate = $hourlyRate * 8; // Calculate daily rate for fixed amount calculation

        // Get holidays in the period to check for hybrid payment settings
        $holidays = \App\Models\Holiday::whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_paid', true)
            ->where('is_active', true)
            ->get()
            ->keyBy('date');

        // Get dynamic rate configurations from database settings (same as draft payroll)
        // Order matches the expected display order: Regular Holiday first, then Special Holiday
        $holidayTypes = [
            'regular_holiday' => 'Regular Holiday',
            'special_holiday' => 'Special Holiday',
            'rest_day_regular_holiday' => 'Rest Day Regular Holiday',
            'rest_day_special_holiday' => 'Rest Day Special Holiday'
        ];

        foreach ($holidayTypes as $type => $name) {
            if (isset($timeBreakdown[$type])) {
                $data = $timeBreakdown[$type];
                $regularHours = $data['regular_hours']; // Regular hours for holiday pay
                $nightDiffRegularHours = $data['night_diff_regular_hours'] ?? 0; // Night differential hours for holiday pay

                // Get rate config from the time breakdown (same as draft calculation)
                $rateConfig = $data['rate_config'] ?? null;

                // If rate config is not available, fetch from database as fallback
                if (!$rateConfig) {
                    $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $type)
                        ->where('is_active', true)
                        ->first();
                }

                if ($rateConfig) {
                    $multiplier = $rateConfig->regular_rate_multiplier ?? 1.0;

                    // HYBRID HOLIDAY CALCULATION: Fixed amount + Time log calculation
                    $timeLogAmount = 0;
                    $fixedAmount = 0;

                    // Calculate time log amount if there are regular hours worked
                    if ($regularHours > 0) {
                        // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                        $actualMinutes = $regularHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $multiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                        $timeLogAmount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                    }

                    // Calculate fixed amount based on individual holiday settings in the period
                    $fixedAmount = 0;

                    foreach ($holidays as $holiday) {
                        if (($type === 'regular_holiday' && $holiday->type === 'regular') ||
                            ($type === 'special_holiday' && $holiday->type === 'special_non_working')
                        ) {
                            $payRule = $holiday->pay_rule ?? 'full';
                            if ($payRule === 'half') {
                                $fixedAmount += round($dailyRate * 0.5, 2);
                            } else {
                                $fixedAmount += round($dailyRate, 2);
                            }
                        }
                    }

                    // Total amount is fixed + time log (hybrid like partial suspension)
                    $totalAmount = $fixedAmount + $timeLogAmount;

                    // Always show holiday breakdown for transparency, even when unpaid (₱0.00)
                    $description = "Holiday Pay: ₱" . number_format($fixedAmount, 2) . " (fixed) + ₱" . number_format($timeLogAmount, 2) . " (worked)";

                    $breakdown[$name] = [
                        'hours' => $regularHours,
                        'minutes' => $roundedMinutes ?? 0,
                        'rate' => $hourlyRate,
                        'rate_per_minute' => $ratePerMinute ?? 0,
                        'multiplier' => $multiplier,
                        'dynamic_multiplier' => $multiplier, // Use rate config multiplier for display
                        'fixed_amount' => $fixedAmount,
                        'time_log_amount' => $timeLogAmount,
                        'amount' => $totalAmount,
                        'description' => $description
                    ];

                    // Holiday hours + Night Differential (also hybrid)
                    if ($nightDiffRegularHours > 0) {
                        // Get night differential settings for rate calculation
                        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                        $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                        // Combined rate: holiday rate + night differential bonus
                        $combinedMultiplier = $multiplier + ($nightDiffMultiplier - 1);

                        // Calculate time log amount for ND hours
                        $actualMinutes = $nightDiffRegularHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $ndTimeLogAmount = round($ratePerMinute * $roundedMinutes, 2);

                        // Fixed amount (same as regular holiday - based on daily rate)
                        $ndFixedAmount = $fixedAmount; // Use same fixed amount as regular holiday

                        // Total ND amount
                        $ndTotalAmount = $ndFixedAmount + $ndTimeLogAmount;

                        $ndDescription = "Holiday+ND Pay: ₱" . number_format($ndFixedAmount, 2) . " (fixed) + ₱" . number_format($ndTimeLogAmount, 2) . " (worked+ND)";

                        $breakdown[$name . '+ND'] = [
                            'hours' => $nightDiffRegularHours,
                            'minutes' => $roundedMinutes,
                            'rate' => $hourlyRate,
                            'rate_per_minute' => $ratePerMinute,
                            'multiplier' => $combinedMultiplier,
                            'dynamic_multiplier' => $combinedMultiplier, // Use combined multiplier for display
                            'fixed_amount' => $ndFixedAmount,
                            'time_log_amount' => $ndTimeLogAmount,
                            'amount' => $ndTotalAmount,
                            'description' => $ndDescription
                        ];
                    }
                } else {
                    // Ultimate fallback to hardcoded multipliers if no config found
                    $fallbackMultipliers = [
                        'special_holiday' => 1.3,
                        'regular_holiday' => 2.0,
                        'rest_day_special_holiday' => 1.5,
                        'rest_day_regular_holiday' => 2.6
                    ];
                    $multiplier = $fallbackMultipliers[$type] ?? 1.0;

                    // HYBRID HOLIDAY CALCULATION (Fallback): Fixed amount + Time log calculation
                    $timeLogAmount = 0;
                    $fixedAmount = 0;

                    // Calculate time log amount if there are regular hours worked
                    if ($regularHours > 0) {
                        $actualMinutes = $regularHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $multiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $timeLogAmount = round($ratePerMinute * $roundedMinutes, 2);
                    }

                    // Calculate fixed amount based on individual holiday settings (fallback)
                    $fixedAmount = 0;

                    foreach ($holidays as $holiday) {
                        if (($type === 'regular_holiday' && $holiday->type === 'regular') ||
                            ($type === 'special_holiday' && $holiday->type === 'special_non_working')
                        ) {
                            $payRule = $holiday->pay_rule ?? 'full';
                            if ($payRule === 'half') {
                                $fixedAmount += round($dailyRate * 0.5, 2);
                            } else {
                                $fixedAmount += round($dailyRate, 2);
                            }
                        }
                    }

                    $totalAmount = $fixedAmount + $timeLogAmount;

                    if ($totalAmount > 0) {
                        $description = "Holiday Pay: ₱" . number_format($fixedAmount, 2) . " (fixed) + ₱" . number_format($timeLogAmount, 2) . " (worked)";

                        $breakdown[$name] = [
                            'hours' => $regularHours,
                            'rate' => number_format($hourlyRate, 2),
                            'multiplier' => $multiplier,
                            'fixed_amount' => $fixedAmount,
                            'time_log_amount' => $timeLogAmount,
                            'amount' => $totalAmount,
                            'description' => $description
                        ];
                    }

                    // Holiday hours + Night Differential (Fallback - also hybrid)
                    if ($nightDiffRegularHours > 0) {
                        // Combined rate: holiday rate + night differential bonus (10%)
                        $combinedMultiplier = $multiplier + 0.10;

                        // Calculate time log amount for ND hours
                        $actualMinutes = $nightDiffRegularHours * 60;
                        $roundedMinutes = round($actualMinutes);
                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                        $ratePerMinute = $adjustedHourlyRate / 60;
                        $ndTimeLogAmount = round($ratePerMinute * $roundedMinutes, 2);

                        // Fixed amount (same as regular holiday)
                        $ndFixedAmount = $fixedAmount;
                        $ndTotalAmount = $ndFixedAmount + $ndTimeLogAmount;

                        $ndDescription = "Holiday+ND Pay: ₱" . number_format($ndFixedAmount, 2) . " (fixed) + ₱" . number_format($ndTimeLogAmount, 2) . " (worked+ND)";

                        $breakdown[$name . '+ND'] = [
                            'hours' => $nightDiffRegularHours,
                            'rate' => number_format($hourlyRate, 2),
                            'multiplier' => $combinedMultiplier,
                            'fixed_amount' => $ndFixedAmount,
                            'time_log_amount' => $ndTimeLogAmount,
                            'amount' => $ndTotalAmount,
                            'description' => $ndDescription
                        ];
                    }
                }
            }
        }

        // ADDITIONAL LOGIC: Process paid holidays that have time log records with is_holiday=true but no time worked
        // Check if employee has holiday time log records during this period
        $holidayTimeLogs = \App\Models\TimeLog::where('employee_id', $employee->id)
            ->where('is_holiday', true)
            ->whereBetween('log_date', [$periodStart, $periodEnd])
            ->get();

        if ($holidayTimeLogs->isNotEmpty()) {
            // Group time logs by holiday type based on the actual holiday dates
            $holidayLogsByType = [];

            foreach ($holidayTimeLogs as $timeLog) {
                // Find which holiday this time log corresponds to
                $matchingHoliday = null;
                foreach ($holidays as $holiday) {
                    $holidayDate = is_string($holiday->date) ? $holiday->date : $holiday->date->format('Y-m-d');
                    $logDate = is_string($timeLog->log_date) ? $timeLog->log_date : $timeLog->log_date->format('Y-m-d');
                    if ($holidayDate === $logDate) {
                        $matchingHoliday = $holiday;
                        break;
                    }
                }

                if ($matchingHoliday) {
                    $holidayType = $matchingHoliday->type === 'regular' ? 'regular_holiday' : 'special_holiday';
                    $holidayName = $matchingHoliday->type === 'regular' ? 'Regular Holiday' : 'Special Holiday';

                    if (!isset($holidayLogsByType[$holidayName])) {
                        $holidayLogsByType[$holidayName] = [
                            'type' => $holidayType,
                            'logs' => [],
                            'holidays' => []
                        ];
                    }

                    $holidayLogsByType[$holidayName]['logs'][] = $timeLog;
                    $holidayLogsByType[$holidayName]['holidays'][] = $matchingHoliday;
                }
            }

            // Process each holiday type that has time log records
            foreach ($holidayLogsByType as $holidayName => $data) {
                // Only process if this holiday type doesn't already exist in breakdown (from time worked)
                if (!isset($breakdown[$holidayName])) {
                    // Check if employee is eligible for this holiday pay
                    $employeeHasBenefits = $employee->benefits_status === 'with_benefits';
                    $sampleHoliday = $data['holidays'][0]; // Get first holiday for settings
                    $payApplicableTo = $sampleHoliday->pay_applicable_to ?? 'all';
                    $shouldReceivePay = false;

                    if ($payApplicableTo === 'all') {
                        $shouldReceivePay = true;
                    } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                        $shouldReceivePay = true;
                    } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                        $shouldReceivePay = true;
                    }

                    if ($shouldReceivePay) {
                        // Get rate config for this holiday type
                        $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $data['type'])
                            ->where('is_active', true)
                            ->first();

                        $multiplier = 1.0;
                        if ($rateConfig) {
                            $multiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                        } else {
                            // Fallback multipliers
                            $fallbackMultipliers = [
                                'special_holiday' => 1.3,
                                'regular_holiday' => 2.0
                            ];
                            $multiplier = $fallbackMultipliers[$data['type']] ?? 1.0;
                        }

                        // Calculate fixed amount by checking each holiday's individual pay rule
                        $fixedAmount = 0;
                        $processedDates = [];

                        foreach ($data['holidays'] as $holiday) {
                            $holidayDate = is_string($holiday->date) ? $holiday->date : $holiday->date->format('Y-m-d');

                            // Only process each unique date once
                            if (!in_array($holidayDate, $processedDates)) {
                                $processedDates[] = $holidayDate;

                                $payRule = $holiday->pay_rule ?? 'full';
                                if ($payRule === 'half') {
                                    $fixedAmount += round($dailyRate * 0.5, 2);
                                } else {
                                    $fixedAmount += round($dailyRate, 2);
                                }
                            }
                        }
                        $timeLogAmount = 0; // No actual time worked (covered by time breakdown)
                        $totalAmount = $fixedAmount + $timeLogAmount;

                        // Always show breakdown if there's a fixed amount
                        if ($fixedAmount > 0) {
                            $description = "Holiday Pay: ₱" . number_format($fixedAmount, 2) . " (fixed) + ₱" . number_format($timeLogAmount, 2) . " (worked)";

                            $breakdown[$holidayName] = [
                                'hours' => 0, // No time worked
                                'minutes' => 0, // No time worked
                                'rate' => $hourlyRate,
                                'rate_per_minute' => 0,
                                'multiplier' => $multiplier,
                                'dynamic_multiplier' => $multiplier,
                                'fixed_amount' => $fixedAmount,
                                'time_log_amount' => $timeLogAmount,
                                'amount' => $totalAmount,
                                'description' => $description
                            ];
                        }
                    }
                }
            }
        }

        return $breakdown;
    }

    /**
     * Create Suspension Pay breakdown for snapshot
     */
    private function createSuspensionPayBreakdown($timeBreakdown, $employee, $periodStart = null, $periodEnd = null)
    {
        $breakdown = [];
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $periodStart, $periodEnd);
        $dailyRate = $hourlyRate * 8; // Calculate daily rate for fixed amount calculation

        // Process suspension types (full_day_suspension, partial_suspension, suspension)
        $suspensionTypes = ['full_day_suspension', 'partial_suspension', 'suspension'];

        foreach ($suspensionTypes as $type) {
            if (isset($timeBreakdown[$type])) {
                $suspensionData = $timeBreakdown[$type];
                $suspensionDays = $suspensionData['days'] ?? 0;
                $suspensionSettings = $suspensionData['suspension_settings'] ?? [];
                $actualTimeLogHours = $suspensionData['actual_time_log_hours'] ?? 0;

                if ($suspensionDays > 0) {
                    // Only create breakdown entries when there are active suspension settings
                    // Manual time log updates without settings should NOT generate breakdowns

                    if ($type === 'full_day_suspension') {
                        // Initialize accumulated totals for multiple days
                        $totalFixedAmount = 0;
                        $totalTimeLogAmount = 0;
                        $totalDays = 0;
                        $totalHours = 0;
                        $totalMinutesSum = 0;
                        $avgMultiplier = 0;
                        $avgDynamicMultiplier = 0;
                        $dailyRate = $hourlyRate * 8; // Calculate daily rate once

                        // Only calculate payment if there are active suspension settings
                        if (!empty($suspensionSettings)) {
                            foreach ($suspensionSettings as $date => $setting) {
                                $isPaid = $setting['is_paid'] ?? false;
                                $payRule = $setting['pay_rule'] ?? 'full';
                                $payApplicableTo = $setting['pay_applicable_to'] ?? 'all';
                                $settingType = $setting['type'] ?? '';

                                // Only process full day suspension settings
                                $isFullDay = $settingType === 'full_day_suspension';

                                // Skip if this is not a full day suspension setting
                                if (!$isFullDay) {
                                    continue;
                                }

                                // Check if suspension pay applies to this employee
                                $employeeHasBenefits = $employee->benefits_status === 'with_benefits';
                                $shouldReceivePay = false;

                                if ($isPaid) {
                                    if ($payApplicableTo === 'all') {
                                        $shouldReceivePay = true;
                                    } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                        $shouldReceivePay = true;
                                    } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                        $shouldReceivePay = true;
                                    }
                                }

                                if ($shouldReceivePay) {
                                    // Get rate configuration for dynamic multiplier first
                                    // Map the time breakdown type to the proper PayrollRateConfiguration type_name
                                    $rateConfigTypeName = $type === 'partial_suspension' ? 'partial_suspension' : ($type === 'full_day_suspension' ? 'full_day_suspension' : $type);

                                    $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $rateConfigTypeName)
                                        ->where('is_active', true)
                                        ->first();

                                    $dynamicMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.0;

                                    // Calculate fixed daily rate amount per day
                                    $multiplier = ($payRule === 'full') ? 1.0 : 0.5;
                                    $dailyFixedAmount = round($dailyRate * $multiplier, 2);

                                    // FULL DAY SUSPENSION: Fixed amount + possible time log earnings (if any)
                                    $actualTimeLogHours = $suspensionData['actual_time_log_hours'] ?? 0;
                                    $adjustedHourlyRate = $hourlyRate * $dynamicMultiplier;
                                    $dayTimeLogAmount = round($actualTimeLogHours * $adjustedHourlyRate, 2);

                                    // For full day suspension with no time logs, only fixed amount applies
                                    if ($actualTimeLogHours == 0) {
                                        $dayTimeLogAmount = 0;
                                    }

                                    // Accumulate totals across multiple days
                                    $totalFixedAmount += $dailyFixedAmount;
                                    $totalTimeLogAmount += $dayTimeLogAmount;
                                    $totalDays++;
                                    $totalHours += $actualTimeLogHours;
                                    $totalMinutesSum += round($actualTimeLogHours * 60);
                                    $avgMultiplier = $multiplier; // Use the same across all days (should be consistent)
                                    $avgDynamicMultiplier = $dynamicMultiplier; // Use the same across all days
                                }
                            }

                            // CRITICAL: Only create breakdown entry if there's actual payment across all days
                            // UNPAID suspensions should NOT appear in breakdown at all
                            $grandTotal = $totalFixedAmount + $totalTimeLogAmount;
                            if ($grandTotal > 0) {
                                $breakdown['Full Suspension'] = [
                                    'hours' => $totalHours,
                                    'days' => $totalDays, // Proper count of suspension days
                                    'minutes' => $totalMinutesSum, // Add minutes for display
                                    'rate' => $dailyRate,
                                    'multiplier' => $avgMultiplier, // Average multiplier across all days
                                    'dynamic_multiplier' => $avgDynamicMultiplier, // Average dynamic multiplier across all days
                                    'fixed_amount' => $totalFixedAmount, // Total fixed amount across all days
                                    'time_log_amount' => $totalTimeLogAmount, // Total time log amount across all days
                                    'amount' => $grandTotal, // Total amount across all days
                                    'workday_hours' => 0,
                                    'suspension_hours' => 0
                                ];
                            }
                        }
                    }

                    if ($type === 'partial_suspension') {
                        // Initialize accumulated totals for multiple days
                        $totalFixedAmount = 0;
                        $totalTimeLogAmount = 0;
                        $totalDays = 0;
                        $totalHours = 0;
                        $totalMinutesSum = 0;
                        $avgMultiplier = 0;
                        $avgDynamicMultiplier = 0;

                        // Calculate payment if there are suspension settings OR if there are actual time logs for partial suspension
                        if (!empty($suspensionSettings) || ($actualTimeLogHours > 0 && $type === 'partial_suspension')) {
                            // Calculate daily rate
                            $dailyRate = $hourlyRate * 8;

                            // Process suspension settings if they exist
                            if (!empty($suspensionSettings)) {
                                foreach ($suspensionSettings as $date => $setting) {
                                    $isPaid = $setting['is_paid'] ?? false;
                                    $payRule = $setting['pay_rule'] ?? 'full';
                                    $payApplicableTo = $setting['pay_applicable_to'] ?? 'all';
                                    $settingType = $setting['type'] ?? '';

                                    // Only process partial suspension settings (full suspensions are already skipped above)
                                    $isPartial = $settingType === 'partial_suspension';

                                    // Skip if this is not a partial suspension setting
                                    if (!$isPartial) {
                                        continue;
                                    }

                                    // Check if suspension pay applies to this employee
                                    $employeeHasBenefits = $employee->benefits_status === 'with_benefits';
                                    $shouldReceivePay = false;

                                    if ($isPaid) {
                                        if ($payApplicableTo === 'all') {
                                            $shouldReceivePay = true;
                                        } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                            $shouldReceivePay = true;
                                        } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                            $shouldReceivePay = true;
                                        }
                                    }

                                    // Get rate configuration for dynamic multiplier FIRST (always needed for time log calculation)
                                    // Map the time breakdown type to the proper PayrollRateConfiguration type_name
                                    $rateConfigTypeName = $type === 'partial_suspension' ? 'partial_suspension' : ($type === 'full_day_suspension' ? 'full_day_suspension' : $type);

                                    $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $rateConfigTypeName)
                                        ->where('is_active', true)
                                        ->first();

                                    $dynamicMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.1; // Default to 1.1 (110%) for partial suspension

                                    // Calculate fixed daily rate amount per day (0 if not paid)
                                    $multiplier = ($payRule === 'full') ? 1.0 : 0.5;
                                    $dailyFixedAmount = $shouldReceivePay ? round($dailyRate * $multiplier, 2) : 0;

                                    // PARTIAL SUSPENSION: Always calculate time log earnings since employee worked
                                    // Use same calculation method as regular workday for consistency
                                    $actualTimeLogHoursForDay = $suspensionData['actual_time_log_hours'] ?? 0;
                                    // Also check for regular_hours if actual_time_log_hours is 0
                                    if ($actualTimeLogHoursForDay == 0) {
                                        $actualTimeLogHoursForDay = $suspensionData['regular_hours'] ?? 0;
                                    }
                                    // If still 0, try to get from all available hours data
                                    if ($actualTimeLogHoursForDay == 0) {
                                        $actualTimeLogHoursForDay = ($suspensionData['hours'] ?? 0) + ($suspensionData['overtime_hours'] ?? 0);
                                    }
                                    $dayMinutes = round($actualTimeLogHoursForDay * 60); // Convert hours to minutes

                                    // Always calculate time log amount (even for unpaid suspensions) since employee worked
                                    $dayTimeLogAmount = 0;
                                    if ($actualTimeLogHoursForDay > 0) {
                                        $adjustedHourlyRate = $hourlyRate * $dynamicMultiplier;
                                        $ratePerMinute = $adjustedHourlyRate / 60;
                                        $dayTimeLogAmount = round($ratePerMinute * $dayMinutes, 2);
                                    }

                                    // Accumulate totals across multiple days
                                    $totalFixedAmount += $dailyFixedAmount;
                                    $totalTimeLogAmount += $dayTimeLogAmount;
                                    $totalDays++;
                                    $totalHours += $actualTimeLogHoursForDay;
                                    $totalMinutesSum += $dayMinutes;
                                    $avgMultiplier = $multiplier; // Use the same across all days (should be consistent)
                                    $avgDynamicMultiplier = $dynamicMultiplier; // Use the same across all days
                                }
                            } else {
                                // No suspension settings, but there are time logs for partial suspension (unpaid case)
                                if ($actualTimeLogHours > 0 && $type === 'partial_suspension') {
                                    // Get rate configuration for dynamic multiplier
                                    $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'partial_suspension')
                                        ->where('is_active', true)
                                        ->first();

                                    $dynamicMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.1;

                                    // No payment for suspension, but calculate time log amount
                                    $multiplier = 0.5; // Default multiplier
                                    $dailyFixedAmount = 0; // No fixed amount for unpaid suspension

                                    $dayMinutes = round($actualTimeLogHours * 60);

                                    // Calculate time log amount for the work performed
                                    $dayTimeLogAmount = 0;
                                    if ($actualTimeLogHours > 0) {
                                        $adjustedHourlyRate = $hourlyRate * $dynamicMultiplier;
                                        $ratePerMinute = $adjustedHourlyRate / 60;
                                        $dayTimeLogAmount = round($ratePerMinute * $dayMinutes, 2);
                                    }

                                    // Set totals
                                    $totalFixedAmount = $dailyFixedAmount;
                                    $totalTimeLogAmount = $dayTimeLogAmount;
                                    $totalDays = 1;
                                    $totalHours = $actualTimeLogHours;
                                    $totalMinutesSum = $dayMinutes;
                                    $avgMultiplier = $multiplier;
                                    $avgDynamicMultiplier = $dynamicMultiplier;
                                }
                            }

                            // Create breakdown even if there's no payment to show ₱0.00 like holidays
                            $grandTotal = $totalFixedAmount + $totalTimeLogAmount;
                            // Always show partial suspension breakdown for transparency, even when unpaid
                            if ($totalDays > 0) { // Only check if there are suspension days, not payment amount
                                $breakdown['Partial Suspension'] = [
                                    'hours' => $totalHours,
                                    'days' => $totalDays, // Proper count of suspension days
                                    'minutes' => $totalMinutesSum, // Add minutes for display
                                    'rate' => $hourlyRate, // Use hourly rate like regular workday
                                    'rate_per_minute' => $totalHours > 0 ? ($hourlyRate * $avgDynamicMultiplier) / 60 : 0,
                                    'multiplier' => $avgMultiplier, // This is pay rule multiplier (0.5 or 1.0)
                                    'dynamic_multiplier' => $avgDynamicMultiplier, // This is rate config multiplier
                                    'fixed_amount' => $totalFixedAmount, // Total fixed amount across all days
                                    'time_log_amount' => $totalTimeLogAmount, // Total time log amount across all days
                                    'amount' => $grandTotal, // Total amount across all days
                                    'workday_hours' => 0,
                                    'suspension_hours' => 0,
                                    'include_in_basic_total' => true // Flag to include in basic pay total
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $breakdown;
    }

    /**
     * Create Rest Pay breakdown for snapshot
     */
    private function createRestPayBreakdown($timeBreakdown, $employee, $periodStart = null, $periodEnd = null)
    {
        $breakdown = [];
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $periodStart, $periodEnd);

        // Only include rest day breakdown
        if (isset($timeBreakdown['rest_day'])) {
            $restData = $timeBreakdown['rest_day'];
            $regularHours = $restData['regular_hours']; // Regular hours for rest day pay
            $nightDiffRegularHours = $restData['night_diff_regular_hours'] ?? 0; // Night differential hours for rest day pay

            // Get rate config from the time breakdown (same as draft calculation)
            $rateConfig = $restData['rate_config'] ?? null;

            // If rate config is not available, fetch from database as fallback
            if (!$rateConfig) {
                $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'rest_day')
                    ->where('is_active', true)
                    ->first();
            }

            if ($rateConfig) {
                $multiplier = $rateConfig->regular_rate_multiplier ?? 1.0;

                // Regular rest day hours (without ND)
                if ($regularHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $multiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day'] = [
                        'hours' => $regularHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => $hourlyRate,
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $multiplier,
                        'amount' => $amount
                    ];
                }

                // Rest day hours + Night Differential
                if ($nightDiffRegularHours > 0) {
                    // Get night differential settings for rate calculation
                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                    // Combined rate: rest day rate + night differential bonus
                    $combinedMultiplier = $multiplier + ($nightDiffMultiplier - 1);

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffRegularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day+ND'] = [
                        'hours' => $nightDiffRegularHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => $hourlyRate,
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            } else {
                // Ultimate fallback to hardcoded multiplier if no config found

                // Regular rest day hours (without ND)
                if ($regularHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * 1.3;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day'] = [
                        'hours' => $regularHours,
                        'rate' => number_format($hourlyRate, 2),
                        'multiplier' => 1.3,
                        'amount' => $amount
                    ];
                }

                // Rest day hours + Night Differential
                if ($nightDiffRegularHours > 0) {
                    // Combined rate: rest day rate + night differential bonus (10%)
                    $combinedMultiplier = 1.3 + 0.10; // 1.4

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffRegularHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day+ND'] = [
                        'hours' => $nightDiffRegularHours,
                        'rate' => number_format($hourlyRate, 2),
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            }
        }

        return $breakdown;
    }

    /**
     * Create Overtime Pay breakdown for snapshot
     */
    private function createOvertimePayBreakdown($timeBreakdown, $employee, $periodStart = null, $periodEnd = null)
    {
        $breakdown = [];
        $hourlyRate = $this->calculateHourlyRate($employee, $employee->basic_salary ?? 0, $periodStart, $periodEnd);

        // Regular workday overtime - SPLIT into regular OT and OT+ND
        if (isset($timeBreakdown['regular_workday'])) {
            $regularData = $timeBreakdown['regular_workday'];
            $regularOvertimeHours = $regularData['regular_overtime_hours'] ?? 0;
            $nightDiffOvertimeHours = $regularData['night_diff_overtime_hours'] ?? 0;
            // Get rate config from the time breakdown (same as draft calculation)
            $rateConfig = $regularData['rate_config'] ?? null;

            // If rate config is not available, fetch from database as fallback
            if (!$rateConfig) {
                $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'regular_workday')
                    ->where('is_active', true)
                    ->first();
            }

            if ($rateConfig) {
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;

                // Get night differential settings for dynamic rate
                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                // Regular Workday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Workday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Display actual rate per minute
                        'multiplier' => $overtimeMultiplier,
                        'amount' => $amount
                    ];
                }

                // Regular Workday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: overtime rate + night differential bonus
                    $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Workday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Display actual rate per minute
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            } else {
                // Ultimate fallback to hardcoded multipliers if no config found
                // Regular Workday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * 1.25;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Workday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Display actual rate per minute
                        'multiplier' => 1.25,
                        'amount' => $amount
                    ];
                }

                // Regular Workday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: 1.25 (OT) + 0.10 (ND) = 1.35
                    $combinedMultiplier = 1.25 + 0.10;
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Workday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Display actual rate per minute
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            }
        }

        // Special holiday overtime - SPLIT into regular OT and OT+ND
        if (isset($timeBreakdown['special_holiday'])) {
            $specialData = $timeBreakdown['special_holiday'];
            $regularOvertimeHours = $specialData['regular_overtime_hours'] ?? 0;
            $nightDiffOvertimeHours = $specialData['night_diff_overtime_hours'] ?? 0;
            // Get rate config from the time breakdown (same as draft calculation)
            $rateConfig = $specialData['rate_config'] ?? null;

            // If rate config is not available, fetch from database as fallback
            if (!$rateConfig) {
                $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'special_holiday')
                    ->where('is_active', true)
                    ->first();
            }

            if ($rateConfig) {
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.69;

                // Get night differential settings for dynamic rate
                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                // Special Holiday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Special Holiday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $overtimeMultiplier,
                        'amount' => $amount
                    ];
                }

                // Special Holiday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: overtime rate + night differential bonus
                    $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Special Holiday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            } else {
                // Ultimate fallback to hardcoded multipliers if no config found
                // Special Holiday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * 1.69;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Special Holiday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => 1.69,
                        'amount' => $amount
                    ];
                }

                // Special Holiday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: 1.69 (OT) + 0.10 (ND) = 1.79
                    $combinedMultiplier = 1.69 + 0.10;

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Special Holiday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            }
        }

        // Regular holiday overtime - SPLIT into regular OT and OT+ND
        if (isset($timeBreakdown['regular_holiday'])) {
            $regularHolidayData = $timeBreakdown['regular_holiday'];
            $regularOvertimeHours = $regularHolidayData['regular_overtime_hours'] ?? 0;
            $nightDiffOvertimeHours = $regularHolidayData['night_diff_overtime_hours'] ?? 0;
            // Get rate config from the time breakdown (same as draft calculation)
            $rateConfig = $regularHolidayData['rate_config'] ?? null;

            // If rate config is not available, fetch from database as fallback
            if (!$rateConfig) {
                $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'regular_holiday')
                    ->where('is_active', true)
                    ->first();
            }

            if ($rateConfig) {
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 2.6;

                // Get night differential settings for dynamic rate
                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                // Regular Holiday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Holiday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $overtimeMultiplier,
                        'amount' => $amount
                    ];
                }

                // Regular Holiday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: overtime rate + night differential bonus
                    $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Holiday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            } else {
                // Ultimate fallback to hardcoded multipliers if no config found
                // Regular Holiday OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * 2.6;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Holiday OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => 2.6,
                        'amount' => $amount
                    ];
                }

                // Regular Holiday OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: 2.6 (OT) + 0.10 (ND) = 2.7
                    $combinedMultiplier = 2.6 + 0.10;

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Regular Holiday OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            }
        }

        // Rest day overtime - SPLIT into regular OT and OT+ND
        if (isset($timeBreakdown['rest_day'])) {
            $restData = $timeBreakdown['rest_day'];
            $regularOvertimeHours = $restData['regular_overtime_hours'] ?? 0;
            $nightDiffOvertimeHours = $restData['night_diff_overtime_hours'] ?? 0;
            // Get rate config from the time breakdown (same as draft calculation)
            $rateConfig = $restData['rate_config'] ?? null;

            // If rate config is not available, fetch from database as fallback
            if (!$rateConfig) {
                $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', 'rest_day')
                    ->where('is_active', true)
                    ->first();
            }

            if ($rateConfig) {
                $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.69;

                // Get night differential settings for dynamic rate
                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;

                // Rest Day OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $overtimeMultiplier,
                        'amount' => $amount
                    ];
                }

                // Rest Day OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: overtime rate + night differential bonus
                    $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            } else {
                // Ultimate fallback to hardcoded multipliers if no config found
                // Rest Day OT (without ND)
                if ($regularOvertimeHours > 0) {
                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $regularOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * 1.69;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day OT'] = [
                        'hours' => $regularOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => 1.69,
                        'amount' => $amount
                    ];
                }

                // Rest Day OT + ND
                if ($nightDiffOvertimeHours > 0) {
                    // Combined rate: 1.69 (OT) + 0.10 (ND) = 1.79
                    $combinedMultiplier = 1.69 + 0.10;

                    // Use consistent calculation: hourly rate * multiplier, then multiply by minutes
                    $actualMinutes = $nightDiffOvertimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                    $ratePerMinute = $adjustedHourlyRate / 60; // Use actual rate per minute without truncation
                    $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals

                    $breakdown['Rest Day OT+ND'] = [
                        'hours' => $nightDiffOvertimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => $ratePerMinute, // Actual rate per minute value for display
                        'multiplier' => $combinedMultiplier,
                        'amount' => $amount
                    ];
                }
            }
        }

        // Rest day + holiday overtime - use total overtime hours
        $restHolidayOvertimeTypes = [
            'rest_day_special_holiday' => 'Rest Day Special Holiday OT',
            'rest_day_regular_holiday' => 'Rest Day Regular Holiday OT'
        ];

        foreach ($restHolidayOvertimeTypes as $type => $name) {
            if (isset($timeBreakdown[$type]) && $timeBreakdown[$type]['overtime_hours'] > 0) {
                $data = $timeBreakdown[$type];
                $overtimeHours = $data['overtime_hours'];
                // Get rate config from the time breakdown (same as draft calculation)
                $rateConfig = $data['rate_config'] ?? null;

                // If rate config is not available, fetch from database as fallback
                if (!$rateConfig) {
                    $rateConfig = \App\Models\PayrollRateConfiguration::where('type_name', $type)
                        ->where('is_active', true)
                        ->first();
                }

                if ($rateConfig) {
                    $multiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;
                    // Calculate per-minute amount with rounding (same as draft payroll)
                    $actualMinutes = $overtimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $ratePerMinute = ($hourlyRate * $multiplier) / 60;
                    $amount = $roundedMinutes * $ratePerMinute;

                    $breakdown[$name] = [
                        'hours' => $overtimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => round($ratePerMinute, 4), // Round only for display
                        'multiplier' => $multiplier,
                        'amount' => round($amount, 2) // Round final amount to 2 decimals
                    ];
                } else {
                    // Ultimate fallback to hardcoded multipliers if no config found
                    $fallbackMultipliers = [
                        'rest_day_special_holiday' => 1.95,
                        'rest_day_regular_holiday' => 3.38
                    ];
                    $multiplier = $fallbackMultipliers[$type] ?? 1.25;
                    // Calculate per-minute amount with rounding (same as draft payroll)
                    $actualMinutes = $overtimeHours * 60;
                    $roundedMinutes = round($actualMinutes);
                    $ratePerMinute = ($hourlyRate * $multiplier) / 60;
                    $amount = $roundedMinutes * $ratePerMinute;

                    $breakdown[$name] = [
                        'hours' => $overtimeHours,
                        'minutes' => $roundedMinutes, // Add minutes for display
                        'rate' => number_format($hourlyRate, 2),
                        'rate_per_minute' => round($ratePerMinute, 4), // Round only for display
                        'multiplier' => $multiplier,
                        'amount' => round($amount, 2) // Round final amount to 2 decimals
                    ];
                }
            }
        }

        return $breakdown;
    }

    /**
                    'rate' => number_format($hourlyRate, 2),
                    'multiplier' => 1.69,
                    'amount' => $amount
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Calculate correct Holiday Pay from PayrollDetail
     */
    private function calculateCorrectHolidayPay($detail, $payroll)
    {
        // Get the breakdown data that matches the payroll summary calculation
        if (isset($detail->earnings_breakdown)) {
            $earningsBreakdown = is_string($detail->earnings_breakdown)
                ? json_decode($detail->earnings_breakdown, true)
                : $detail->earnings_breakdown;

            if (is_array($earningsBreakdown) && isset($earningsBreakdown['holiday'])) {
                $holidayTotal = 0;
                foreach ($earningsBreakdown['holiday'] as $holidayData) {
                    $holidayTotal += is_array($holidayData) ? ($holidayData['amount'] ?? $holidayData) : $holidayData;
                }
                return $holidayTotal;
            }
        }

        // Fallback to regular holiday_pay if no breakdown
        return $detail->holiday_pay ?? 0;
    }

    /**
     * Calculate correct Overtime Pay from PayrollDetail
     */
    private function calculateCorrectOvertimePay($detail, $payroll)
    {
        // Get the breakdown data that matches the payroll summary calculation
        if (isset($detail->earnings_breakdown)) {
            $earningsBreakdown = is_string($detail->earnings_breakdown)
                ? json_decode($detail->earnings_breakdown, true)
                : $detail->earnings_breakdown;

            if (is_array($earningsBreakdown) && isset($earningsBreakdown['overtime'])) {
                $overtimeTotal = 0;
                foreach ($earningsBreakdown['overtime'] as $overtimeData) {
                    $overtimeTotal += is_array($overtimeData) ? ($overtimeData['amount'] ?? $overtimeData) : $overtimeData;
                }
                return $overtimeTotal;
            }
        }

        // Fallback to regular overtime_pay if no breakdown
        return $detail->overtime_pay ?? 0;
    }

    /**
     * Calculate correct Holiday Pay from PayrollSnapshot
     */
    private function calculateCorrectHolidayPayFromSnapshot($snapshot)
    {
        // Get the breakdown data from snapshot
        if (isset($snapshot->holiday_breakdown)) {
            $holidayBreakdown = is_string($snapshot->holiday_breakdown)
                ? json_decode($snapshot->holiday_breakdown, true)
                : $snapshot->holiday_breakdown;

            if (is_array($holidayBreakdown)) {
                $holidayTotal = 0;
                foreach ($holidayBreakdown as $type => $data) {
                    $holidayTotal += $data['amount'] ?? 0;
                }
                return $holidayTotal;
            }
        }

        // Fallback to regular holiday_pay if no breakdown
        return $snapshot->holiday_pay ?? 0;
    }

    /**
     * Calculate correct Rest Pay from PayrollDetail
     */
    private function calculateCorrectRestPay($detail, $payroll)
    {
        // Get the breakdown data that matches the payroll summary calculation
        if (isset($detail->earnings_breakdown)) {
            $earningsBreakdown = is_string($detail->earnings_breakdown)
                ? json_decode($detail->earnings_breakdown, true)
                : $detail->earnings_breakdown;

            if (is_array($earningsBreakdown) && isset($earningsBreakdown['rest'])) {
                $restTotal = 0;
                foreach ($earningsBreakdown['rest'] as $restData) {
                    $restTotal += is_array($restData) ? ($restData['amount'] ?? $restData) : $restData;
                }
                return $restTotal;
            }
        }

        // Fallback to regular rest_day_pay if no breakdown
        return $detail->rest_day_pay ?? 0;
    }

    /**
     * Calculate correct Rest Pay from PayrollSnapshot
     */
    private function calculateCorrectRestPayFromSnapshot($snapshot)
    {
        // Get the breakdown data from snapshot
        if (isset($snapshot->rest_breakdown)) {
            $restBreakdown = is_string($snapshot->rest_breakdown)
                ? json_decode($snapshot->rest_breakdown, true)
                : $snapshot->rest_breakdown;

            if (is_array($restBreakdown)) {
                $restTotal = 0;
                foreach ($restBreakdown as $restData) {
                    $restTotal += $restData['amount'] ?? 0;
                }
                return $restTotal;
            }
        }

        // Fallback to regular rest_day_pay if no breakdown
        return $snapshot->rest_day_pay ?? 0;
    }

    /**
     * Calculate correct Overtime Pay from PayrollSnapshot
     */
    private function calculateCorrectOvertimePayFromSnapshot($snapshot)
    {
        // Get the breakdown data from snapshot
        if (isset($snapshot->overtime_breakdown)) {
            $overtimeBreakdown = is_string($snapshot->overtime_breakdown)
                ? json_decode($snapshot->overtime_breakdown, true)
                : $snapshot->overtime_breakdown;

            if (is_array($overtimeBreakdown)) {
                $overtimeTotal = 0;
                foreach ($overtimeBreakdown as $type => $data) {
                    $overtimeTotal += $data['amount'] ?? 0;
                }
                return $overtimeTotal;
            }
        }

        // Fallback to regular overtime_pay if no breakdown
        return $snapshot->overtime_pay ?? 0;
    }

    /**
     * Mark payroll as paid with optional proof upload
     */
    public function markAsPaid(Request $request, Payroll $payroll)
    {
        $this->authorize('mark payrolls as paid');

        if (!$payroll->canBeMarkedAsPaid()) {
            return redirect()->back()->with('error', 'This payroll cannot be marked as paid.');
        }

        $request->validate([
            'payment_notes' => 'nullable|string|max:1000',
            'payment_proof.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', // Max 10MB per file
        ]);

        try {
            DB::beginTransaction();

            $paymentProofFiles = [];

            // Handle file uploads
            if ($request->hasFile('payment_proof')) {
                foreach ($request->file('payment_proof') as $file) {
                    if ($file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $fileName = time() . '_' . str_replace(' ', '_', $originalName);
                        $filePath = $file->storeAs('payroll_proofs', $fileName, 'public');

                        $paymentProofFiles[] = [
                            'original_name' => $originalName,
                            'file_name' => $fileName,
                            'file_path' => $filePath,
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'uploaded_at' => now()->toISOString(),
                        ];
                    }
                }
            }

            // Update payroll
            $payroll->update([
                'is_paid' => true,
                'payment_proof_files' => $paymentProofFiles,
                'payment_notes' => $request->payment_notes,
                'marked_paid_by' => Auth::id(),
                'marked_paid_at' => now(),
            ]);

            // Process cash advance deductions now that payroll is marked as paid
            $this->processCashAdvanceDeductions($payroll);

            // Update employee shares calculations
            $this->updateEmployeeSharesCalculations($payroll);

            DB::commit();

            Log::info('Payroll marked as paid', [
                'payroll_id' => $payroll->id,
                'payroll_number' => $payroll->payroll_number,
                'marked_by' => Auth::id(),
                'proof_files_count' => count($paymentProofFiles),
            ]);

            return redirect()->back()->with('success', 'Payroll marked as paid successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark payroll as paid', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to mark payroll as paid: ' . $e->getMessage());
        }
    }

    /**
     * Unmark payroll as paid (undo)
     */
    public function unmarkAsPaid(Payroll $payroll)
    {
        $this->authorize('mark payrolls as paid');

        if (!$payroll->canBeUnmarkedAsPaid()) {
            return redirect()->back()->with('error', 'This payroll cannot be unmarked as paid.');
        }

        try {
            DB::beginTransaction();

            // Reverse cash advance deductions
            $this->reverseCashAdvanceDeductions($payroll);

            // Reverse employee shares calculations
            $this->reverseEmployeeSharesCalculations($payroll);

            // Update payroll
            $payroll->update([
                'is_paid' => false,
                'payment_proof_files' => null,
                'payment_notes' => null,
                'marked_paid_by' => null,
                'marked_paid_at' => null,
            ]);

            DB::commit();

            Log::info('Payroll unmarked as paid', [
                'payroll_id' => $payroll->id,
                'payroll_number' => $payroll->payroll_number,
                'unmarked_by' => Auth::id(),
            ]);

            return redirect()->back()->with('success', 'Payroll unmarked as paid successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unmark payroll as paid', [
                'payroll_id' => $payroll->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to unmark payroll as paid: ' . $e->getMessage());
        }
    }

    /**
     * Process cash advance deductions when payroll is marked as paid
     */
    private function processCashAdvanceDeductions(Payroll $payroll)
    {
        foreach ($payroll->payrollDetails as $detail) {
            if ($detail->cash_advance_deductions > 0) {
                // Get the cash advance calculation for this employee and period
                $cashAdvanceData = CashAdvance::calculateDeductionForPeriod(
                    $detail->employee_id,
                    $payroll->period_start,
                    $payroll->period_end
                );

                $remainingDeduction = $detail->cash_advance_deductions;

                foreach ($cashAdvanceData['details'] as $deductionDetail) {
                    if ($remainingDeduction <= 0) break;

                    $cashAdvance = CashAdvance::find($deductionDetail['cash_advance_id']);
                    if ($cashAdvance) {
                        $deductionAmount = min($deductionDetail['amount'], $remainingDeduction);

                        if ($deductionAmount > 0) {
                            // Record the payment
                            $cashAdvance->recordPayment(
                                $deductionAmount,
                                $payroll->id,
                                $detail->id,
                                'Payroll deduction for period ' . $payroll->period_start->format('M d') . ' - ' . $payroll->period_end->format('d, Y')
                            );

                            $remainingDeduction -= $deductionAmount;
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse cash advance deductions when payroll is unmarked as paid
     */
    private function reverseCashAdvanceDeductions(Payroll $payroll)
    {
        // Find and reverse payments made from this payroll
        $payments = CashAdvancePayment::where('payroll_id', $payroll->id)->get();

        foreach ($payments as $payment) {
            $cashAdvance = $payment->cashAdvance;
            if ($cashAdvance) {
                // Restore the outstanding balance
                $cashAdvance->increment('outstanding_balance', $payment->payment_amount ?? $payment->amount);

                // If cash advance was marked as fully_paid, revert to approved
                if ($cashAdvance->status === 'fully_paid' && $cashAdvance->outstanding_balance > 0) {
                    $cashAdvance->update(['status' => 'approved']);
                }
            }

            // Delete the payment record
            $payment->delete();
        }
    }

    /**
     * Update employee shares calculations (SSS, PhilHealth, Pag-IBIG)
     */
    private function updateEmployeeSharesCalculations(Payroll $payroll)
    {
        // This method would trigger recalculation of government contribution reports
        // For now, we'll just log the action as the reports are generated on-demand
        Log::info('Employee shares calculations updated for paid payroll', [
            'payroll_id' => $payroll->id,
            'payroll_number' => $payroll->payroll_number,
        ]);

        // In a real implementation, you might want to:
        // 1. Update cached totals for government reports
        // 2. Trigger notifications to accounting
        // 3. Update dashboard metrics
    }

    /**
     * Recalculate deductions from breakdown based on new gross pay
     */
    private function recalculateDeductionsFromBreakdown($snapshot, $newGrossPay)
    {
        $deductionsBreakdown = $snapshot->deductions_breakdown;

        if (is_string($deductionsBreakdown)) {
            $deductionsBreakdown = json_decode($deductionsBreakdown, true);
        }

        if (!is_array($deductionsBreakdown) || empty($deductionsBreakdown)) {
            // Fallback to original total deductions if breakdown is not available
            return $snapshot->total_deductions ?? 0;
        }

        $totalDeductions = 0;

        foreach ($deductionsBreakdown as $deduction) {
            if (!is_array($deduction)) {
                continue;
            }

            $payBasis = $deduction['pay_basis'] ?? 'fixed';
            $payBasisAmount = $deduction['pay_basis_amount'] ?? 0;
            $originalAmount = $deduction['amount'] ?? 0;

            // Recalculate only if deduction is based on total gross pay
            if ($payBasis === 'totalgross' && $payBasisAmount > 0) {
                // Calculate the percentage rate from original calculation
                $rate = $originalAmount / $payBasisAmount;
                $newAmount = $newGrossPay * $rate;
                $totalDeductions += $newAmount;
            } else {
                // Use original amount for fixed deductions or other pay basis types
                $totalDeductions += $originalAmount;
            }
        }

        // Add non-government deductions that are stored separately and NOT included in breakdown
        $totalDeductions += $snapshot->late_deductions ?? 0;
        $totalDeductions += $snapshot->undertime_deductions ?? 0;
        // Note: cash_advance_deductions is already included in the deductions_breakdown, so don't add it separately

        return $totalDeductions;
    }

    /**
     * Reverse employee shares calculations
     */
    private function reverseEmployeeSharesCalculations(Payroll $payroll)
    {
        // This method would reverse the employee shares calculations
        // For now, we'll just log the action
        Log::info('Employee shares calculations reversed for unpaid payroll', [
            'payroll_id' => $payroll->id,
            'payroll_number' => $payroll->payroll_number,
        ]);
    }

    /**
     * Calculate working days for a given period based on employee's day schedule
     */
    private function calculateWorkingDaysForPeriod($employee, $startDate, $endDate)
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        $workingDays = 0;

        $current = $start->copy();
        while ($current <= $end) {
            if ($this->isEmployeeWorkingDay($employee, $current)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Check if a given date is a working day for this employee
     */
    private function isEmployeeWorkingDay($employee, $date)
    {
        // If employee has a daySchedule relationship, use it
        if ($employee->daySchedule) {
            return $employee->daySchedule->isWorkingDay($date);
        }

        // Fallback to the day_schedule column if daySchedule relationship is not loaded
        if ($employee->day_schedule) {
            $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 1=Monday, ..., 6=Saturday

            return match ($employee->day_schedule) {
                'monday_friday' => $dayOfWeek >= 1 && $dayOfWeek <= 5, // Mon-Fri
                'monday_saturday' => $dayOfWeek >= 1 && $dayOfWeek <= 6, // Mon-Sat
                'monday_sunday' => true, // All days
                'tuesday_saturday' => $dayOfWeek >= 2 && $dayOfWeek <= 6, // Tue-Sat
                'sunday_thursday' => $dayOfWeek == 0 || ($dayOfWeek >= 1 && $dayOfWeek <= 4), // Sun-Thu
                default => $dayOfWeek >= 1 && $dayOfWeek <= 5 // Default to Mon-Fri
            };
        }

        // Default fallback - assume Monday to Friday
        $dayOfWeek = $date->dayOfWeek;
        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * Get working days count based on rate type and employee's schedule with period context using PayScheduleSettings
     */
    private function getWorkingDaysForRateTypeWithPeriod($employee, $rateType, $periodStart, $periodEnd)
    {
        $start = \Carbon\Carbon::parse($periodStart);
        $end = \Carbon\Carbon::parse($periodEnd);

        // Get the employee's pay schedule setting from the database
        $scheduleCode = $employee->pay_schedule;
        $scheduleSetting = \App\Models\PayScheduleSetting::where('code', $scheduleCode)
            ->where('is_active', true)
            ->first();

        if (!$scheduleSetting) {
            // Fallback to direct calculation if no schedule setting found
            return $this->calculateWorkingDaysForPeriod($employee, $start, $end);
        }

        switch ($rateType) {
            case 'weekly':
                // For weekly rate type, calculate based on a representative week
                // Use the actual weekly cutoff period from the schedule setting if available
                if ($scheduleSetting->code === 'weekly' && $scheduleSetting->cutoff_periods) {
                    $cutoffPeriods = is_string($scheduleSetting->cutoff_periods)
                        ? json_decode($scheduleSetting->cutoff_periods, true)
                        : $scheduleSetting->cutoff_periods;

                    if (!empty($cutoffPeriods) && isset($cutoffPeriods[0])) {
                        // Calculate a typical weekly period based on the cutoff settings
                        $weekStart = $start->copy()->startOfWeek();
                        $weekEnd = $start->copy()->endOfWeek();
                        return $this->calculateWorkingDaysForPeriod($employee, $weekStart, $weekEnd);
                    }
                }

                // Fallback: use current week
                $weekStart = $start->copy()->startOfWeek();
                $weekEnd = $start->copy()->endOfWeek();
                return $this->calculateWorkingDaysForPeriod($employee, $weekStart, $weekEnd);

            case 'semi_monthly':
                // For semi-monthly, use the actual cutoff periods from the schedule setting
                if ($scheduleSetting->code === 'semi_monthly' && $scheduleSetting->cutoff_periods) {
                    $cutoffPeriods = is_string($scheduleSetting->cutoff_periods)
                        ? json_decode($scheduleSetting->cutoff_periods, true)
                        : $scheduleSetting->cutoff_periods;

                    if (!empty($cutoffPeriods) && count($cutoffPeriods) >= 2) {
                        // Determine which semi-monthly period we're in based on the start date
                        $currentDay = $start->day;
                        $firstPeriod = $cutoffPeriods[0];
                        $secondPeriod = $cutoffPeriods[1];

                        $firstPeriodStart = $this->parseDayNumber($firstPeriod['start_day'] ?? 1);
                        $firstPeriodEnd = $this->parseDayNumber($firstPeriod['end_day'] ?? 15);
                        $secondPeriodStart = $this->parseDayNumber($secondPeriod['start_day'] ?? 16);
                        $secondPeriodEnd = $this->parseDayNumber($secondPeriod['end_day'] ?? -1);

                        // Check if we're in the first or second period
                        $inFirstPeriod = false;
                        if ($firstPeriodStart > $firstPeriodEnd) {
                            // First period crosses month boundary (e.g., 21st to 5th)
                            $inFirstPeriod = ($currentDay >= $firstPeriodStart || $currentDay <= $firstPeriodEnd);
                        } else {
                            // First period is within same month
                            $inFirstPeriod = ($currentDay >= $firstPeriodStart && $currentDay <= $firstPeriodEnd);
                        }

                        if ($inFirstPeriod) {
                            // First period
                            if ($firstPeriodStart > $firstPeriodEnd) {
                                // Period crosses month boundary
                                if ($currentDay >= $firstPeriodStart) {
                                    // Currently in the start month
                                    $semiStart = $start->copy()->setDay($firstPeriodStart);
                                    $semiEnd = $start->copy()->addMonth()->setDay($firstPeriodEnd);
                                } else {
                                    // Currently in the end month
                                    $semiStart = $start->copy()->subMonth()->setDay($firstPeriodStart);
                                    $semiEnd = $start->copy()->setDay($firstPeriodEnd);
                                }
                            } else {
                                // Period is within same month
                                $semiStart = $start->copy()->setDay($firstPeriodStart);
                                if ($firstPeriodEnd == 31 || $firstPeriodEnd == -1) {
                                    $semiEnd = $start->copy()->endOfMonth();
                                } else {
                                    $semiEnd = $start->copy()->setDay(min($firstPeriodEnd, $start->daysInMonth));
                                }
                            }
                        } else {
                            // Second period
                            if ($secondPeriodStart > $secondPeriodEnd) {
                                // Period crosses month boundary
                                if ($currentDay >= $secondPeriodStart) {
                                    // Currently in the start month
                                    $semiStart = $start->copy()->setDay($secondPeriodStart);
                                    $semiEnd = $start->copy()->addMonth()->setDay($secondPeriodEnd);
                                } else {
                                    // Currently in the end month
                                    $semiStart = $start->copy()->subMonth()->setDay($secondPeriodStart);
                                    $semiEnd = $start->copy()->setDay($secondPeriodEnd);
                                }
                            } else {
                                // Period is within same month
                                $semiStart = $start->copy()->setDay($secondPeriodStart);
                                if ($secondPeriodEnd == 31 || $secondPeriodEnd == -1) {
                                    $semiEnd = $start->copy()->endOfMonth();
                                } else {
                                    $semiEnd = $start->copy()->setDay(min($secondPeriodEnd, $start->daysInMonth));
                                }
                            }
                        }

                        return $this->calculateWorkingDaysForPeriod($employee, $semiStart, $semiEnd);
                    }
                }

                // Fallback: use standard semi-monthly calculation
                if ($start->day <= 15) {
                    $semiStart = $start->copy()->startOfMonth();
                    $semiEnd = $start->copy()->setDay(15);
                } else {
                    $semiStart = $start->copy()->setDay(16);
                    $semiEnd = $start->copy()->endOfMonth();
                }
                return $this->calculateWorkingDaysForPeriod($employee, $semiStart, $semiEnd);

            case 'monthly':
                // For monthly, use the actual cutoff periods from the schedule setting
                if ($scheduleSetting->code === 'monthly' && $scheduleSetting->cutoff_periods) {
                    $cutoffPeriods = is_string($scheduleSetting->cutoff_periods)
                        ? json_decode($scheduleSetting->cutoff_periods, true)
                        : $scheduleSetting->cutoff_periods;

                    if (!empty($cutoffPeriods) && isset($cutoffPeriods[0])) {
                        $monthlyPeriod = $cutoffPeriods[0];
                        $monthStart = $start->copy()->setDay((int)($monthlyPeriod['start_day'] ?? 1));
                        $endDay = (int)($monthlyPeriod['end_day'] ?? -1);
                        $monthEnd = $endDay === -1 ? $start->copy()->endOfMonth() : $start->copy()->setDay(min($endDay, $start->daysInMonth));

                        return $this->calculateWorkingDaysForPeriod($employee, $monthStart, $monthEnd);
                    }
                }

                // Fallback: use full month
                $monthStart = $start->copy()->startOfMonth();
                $monthEnd = $start->copy()->endOfMonth();
                return $this->calculateWorkingDaysForPeriod($employee, $monthStart, $monthEnd);

            default:
                return 1; // For daily or hourly, return 1
        }
    }

    /**
     * Display employee's own payslips
     */
    public function myPayslips(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return redirect()->route('dashboard')->with('error', 'Employee profile not found.');
        }

        // Get approved payrolls for this employee
        $query = Payroll::with(['payrollDetails' => function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        }])
            ->where('status', 'approved') // Only show approved payrolls
            ->whereHas('payrollDetails', function ($q) use ($employee) {
                $q->where('employee_id', $employee->id);
            })
            ->orderBy('period_start', 'desc');

        // Filter by year
        if ($request->filled('year')) {
            $query->whereYear('period_start', $request->year);
        }

        $payrolls = $query->paginate(10);

        // Get available years for filter
        $years = Payroll::whereHas('payrollDetails', function ($q) use ($employee) {
            $q->where('employee_id', $employee->id);
        })
            ->where('status', 'approved')
            ->selectRaw('YEAR(period_start) as year')
            ->distinct()
            ->pluck('year')
            ->sort()
            ->values();

        return view('payrolls.my-payslips', compact('payrolls', 'years', 'employee'));
    }
}
