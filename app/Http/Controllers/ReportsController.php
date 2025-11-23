<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DeductionTaxSetting;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function employerShares(Request $request)
    {
        // Get filter parameters
        $payrollPeriod = $request->input('payroll_period', 'all');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', null);

        // Base query for payroll snapshots - only include PAID payrolls
        $query = \App\Models\PayrollSnapshot::join('payrolls', 'payroll_snapshots.payroll_id', '=', 'payrolls.id')
            ->join('employees', 'payroll_snapshots.employee_id', '=', 'employees.id')
            ->whereYear('payrolls.period_start', $year)
            ->where('payrolls.is_paid', true) // Only include paid payrolls
            ->whereNotNull('payroll_snapshots.employer_deductions_breakdown'); // Only include snapshots with employer breakdown

        // Company filtering for Super Admin
        if (Auth::user()->isSuperAdmin() && $request->filled('company')) {
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
            if ($company) {
                $query->where('employees.company_id', $company->id);
            }
        } elseif (!Auth::user()->isSuperAdmin()) {
            $query->where('employees.company_id', Auth::user()->company_id);
        }

        // Apply month filter if provided
        if ($month) {
            $query->whereMonth('payrolls.period_start', $month);
        }

        // Get ALL government deduction settings and common deductions
        $allDeductions = DeductionTaxSetting::whereIn('name', ['SSS', 'PhilHealth', 'Pag-IBIG', 'BIR', 'Withholding Tax'])
            ->get();

        // If we don't have Withholding Tax in settings, create a virtual one for display
        $withholdingTaxExists = $allDeductions->where('name', 'Withholding Tax')->first() || $allDeductions->where('name', 'BIR')->first();
        if (!$withholdingTaxExists) {
            $virtualWithholdingTax = new \stdClass();
            $virtualWithholdingTax->id = 'withholding_tax';
            $virtualWithholdingTax->name = 'Withholding Tax';
            $virtualWithholdingTax->share_with_employer = false;
            $allDeductions->push($virtualWithholdingTax);
        }

        // Group deductions by normalized name to avoid duplicates
        $uniqueDeductions = collect();
        $processedNames = [];

        foreach ($allDeductions as $deduction) {
            $normalizedName = strtolower(str_replace([' ', '-'], '', $deduction->name));

            // Skip if we already processed this deduction type
            if (in_array($normalizedName, $processedNames)) {
                continue;
            }

            $processedNames[] = $normalizedName;
            $uniqueDeductions->push($deduction);
        }

        // Calculate totals for each deduction type
        $shareData = collect();

        foreach ($uniqueDeductions as $deduction) {
            // Get employee share from deductions_breakdown JSON field
            $eeShare = 0;
            $payrollCount = 0;
            $employeeCount = 0;

            $snapshots = $query->clone()
                ->select('payroll_snapshots.deductions_breakdown', 'payroll_snapshots.payroll_id')
                ->whereNotNull('payroll_snapshots.deductions_breakdown')
                ->get();

            $uniquePayrolls = collect();

            foreach ($snapshots as $snapshot) {
                if ($snapshot->deductions_breakdown) {
                    $deductionsBreakdown = is_string($snapshot->deductions_breakdown)
                        ? json_decode($snapshot->deductions_breakdown, true)
                        : $snapshot->deductions_breakdown;

                    if (is_array($deductionsBreakdown)) {
                        foreach ($deductionsBreakdown as $breakdown) {
                            // Improved matching logic for deduction names and codes
                            $isMatch = false;

                            if (isset($breakdown['name']) && isset($breakdown['code'])) {
                                // Direct name match
                                if (strcasecmp($breakdown['name'], $deduction->name) === 0) {
                                    $isMatch = true;
                                }
                                // Code-based matching
                                elseif (strcasecmp($breakdown['code'], strtolower(str_replace(['-', ' '], '', $deduction->name))) === 0) {
                                    $isMatch = true;
                                }
                                // Special cases for common deduction name variations
                                elseif (
                                    (strcasecmp($deduction->name, 'BIR') === 0 && strcasecmp($breakdown['name'], 'Withholding Tax') === 0) ||
                                    (strcasecmp($deduction->name, 'Withholding Tax') === 0 && strcasecmp($breakdown['code'], 'withholding_tax') === 0) ||
                                    (strcasecmp($deduction->name, 'Pag-IBIG') === 0 && strcasecmp($breakdown['code'], 'pagibig') === 0) ||
                                    (strcasecmp($deduction->name, 'PhilHealth') === 0 && strcasecmp($breakdown['code'], 'philhealth') === 0) ||
                                    (strcasecmp($deduction->name, 'SSS') === 0 && strcasecmp($breakdown['code'], 'sss') === 0)
                                ) {
                                    $isMatch = true;
                                }
                            }

                            if ($isMatch) {
                                $eeShare += $breakdown['amount'] ?? 0;
                                $employeeCount++;

                                // Track unique payrolls
                                if (!$uniquePayrolls->contains($snapshot->payroll_id)) {
                                    $uniquePayrolls->push($snapshot->payroll_id);
                                }
                            }
                        }
                    }
                }
            }

            $payrollCount = $uniquePayrolls->count();

            // Calculate employer share from snapshots' employer_deductions_breakdown
            $erShare = 0;
            if ($deduction->share_with_employer) {
                $erSnapshots = $query->clone()
                    ->select('payroll_snapshots.employer_deductions_breakdown')
                    ->whereNotNull('payroll_snapshots.employer_deductions_breakdown')
                    ->get();

                foreach ($erSnapshots as $snapshot) {
                    if ($snapshot->employer_deductions_breakdown) {
                        $employerBreakdown = is_string($snapshot->employer_deductions_breakdown)
                            ? json_decode($snapshot->employer_deductions_breakdown, true)
                            : $snapshot->employer_deductions_breakdown;

                        if (is_array($employerBreakdown)) {
                            foreach ($employerBreakdown as $breakdown) {
                                // Use the same improved matching logic
                                $isMatch = false;

                                if (isset($breakdown['name'])) {
                                    // Direct name match
                                    if (strcasecmp($breakdown['name'], $deduction->name) === 0) {
                                        $isMatch = true;
                                    }
                                    // Special cases for common deduction name variations
                                    elseif (
                                        (strcasecmp($deduction->name, 'BIR') === 0 && strcasecmp($breakdown['name'], 'Withholding Tax') === 0) ||
                                        (strcasecmp($deduction->name, 'Withholding Tax') === 0 && strcasecmp($breakdown['name'], 'Withholding Tax') === 0) ||
                                        (strcasecmp($deduction->name, 'Pag-IBIG') === 0 && strcasecmp($breakdown['name'], 'Pag-IBIG') === 0) ||
                                        (strcasecmp($deduction->name, 'PhilHealth') === 0 && strcasecmp($breakdown['name'], 'PhilHealth') === 0) ||
                                        (strcasecmp($deduction->name, 'SSS') === 0 && strcasecmp($breakdown['name'], 'SSS') === 0)
                                    ) {
                                        $isMatch = true;
                                    }
                                }

                                if ($isMatch) {
                                    $erShare += $breakdown['amount'] ?? 0;
                                }
                            }
                        }
                    }
                }
            }

            $sharePercentage = $deduction->share_with_employer ? 50 : 0;

            $shareData->push((object)[
                'id' => $deduction->id,
                'name' => $deduction->name,
                'total_ee_share' => round($eeShare, 2),
                'total_er_share' => round($erShare, 2),
                'total_combined' => round($eeShare + $erShare, 2),
                'share_percentage' => $sharePercentage,
                'payroll_count' => $payrollCount,
                'employee_count' => $employeeCount,
                'is_shared' => $deduction->share_with_employer ?? false,
                'share_status' => ($deduction->share_with_employer ?? false) ? 'Shared' : 'Not Shared',
            ]);
        }

        // Calculate grand totals
        $grandTotals = [
            'total_ee_share' => $shareData->sum('total_ee_share'),
            'total_er_share' => $shareData->sum('total_er_share'),
            'total_combined' => $shareData->sum('total_combined'),
            'total_payrolls' => $shareData->max('payroll_count'),
            'total_employees' => $shareData->sum('employee_count')
        ];

        // Get available years for filter
        $availableYears = Payroll::selectRaw('YEAR(period_start) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        // Handle AJAX requests by returning JSON with HTML partial
        if ($request->wantsJson() || $request->ajax()) {
            $html = view('reports.partials.employer-shares-content', compact(
                'shareData',
                'grandTotals'
            ))->render();

            return response()->json([
                'html' => $html,
                'grandTotals' => $grandTotals,
                'shareData' => $shareData
            ]);
        }

        $companies = Auth::user()->isSuperAdmin() ? \App\Models\Company::latest('created_at')->get() : collect();

        return view('reports.employer-shares', compact(
            'shareData',
            'grandTotals',
            'payrollPeriod',
            'year',
            'month',
            'availableYears',
            'companies'
        ));
    }

    /**
     * Generate employer shares summary
     */
    public function generateEmployerSharesSummary(Request $request)
    {
        $format = $request->input('export', 'pdf');
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', null);

        // Get the same data as the main report
        $query = \App\Models\PayrollSnapshot::join('payrolls', 'payroll_snapshots.payroll_id', '=', 'payrolls.id')
            ->whereYear('payrolls.period_start', $year)
            ->where('payrolls.is_paid', true)
            ->whereNotNull('payroll_snapshots.employer_deductions_breakdown');

        if ($month) {
            $query->whereMonth('payrolls.period_start', $month);
        }

        $allDeductions = \App\Models\DeductionTaxSetting::whereIn('name', ['SSS', 'PhilHealth', 'Pag-IBIG', 'BIR', 'Withholding Tax'])
            ->get();

        // Calculate share data for export
        $shareData = collect();
        foreach ($allDeductions as $deduction) {
            $eeShare = 0;
            $erShare = 0;
            $snapshots = $query->clone()
                ->select('payroll_snapshots.deductions_breakdown', 'payroll_snapshots.employer_deductions_breakdown')
                ->get();

            foreach ($snapshots as $snapshot) {
                // Calculate employee share
                if ($snapshot->deductions_breakdown) {
                    $deductionsBreakdown = is_string($snapshot->deductions_breakdown)
                        ? json_decode($snapshot->deductions_breakdown, true)
                        : $snapshot->deductions_breakdown;

                    if (is_array($deductionsBreakdown)) {
                        foreach ($deductionsBreakdown as $breakdown) {
                            if (strcasecmp($breakdown['name'] ?? '', $deduction->name) === 0) {
                                $eeShare += $breakdown['amount'] ?? 0;
                            }
                        }
                    }
                }

                // Calculate employer share
                if ($snapshot->employer_deductions_breakdown && $deduction->share_with_employer) {
                    $employerBreakdown = is_string($snapshot->employer_deductions_breakdown)
                        ? json_decode($snapshot->employer_deductions_breakdown, true)
                        : $snapshot->employer_deductions_breakdown;

                    if (is_array($employerBreakdown)) {
                        foreach ($employerBreakdown as $breakdown) {
                            if (strcasecmp($breakdown['name'] ?? '', $deduction->name) === 0) {
                                $erShare += $breakdown['amount'] ?? 0;
                            }
                        }
                    }
                }
            }

            $shareData->push((object)[
                'name' => $deduction->name,
                'total_ee_share' => round($eeShare, 2),
                'total_er_share' => round($erShare, 2),
                'total_combined' => round($eeShare + $erShare, 2),
                'is_shared' => $deduction->share_with_employer ?? false,
            ]);
        }

        if ($format === 'excel') {
            return $this->exportEmployerSharesExcel($shareData, $year, $month);
        } else {
            return $this->exportEmployerSharesPDF($shareData, $year, $month);
        }
    }

    /**
     * Export employer shares as PDF
     */
    private function exportEmployerSharesPDF($shareData, $year, $month)
    {
        $fileName = 'employer_shares_summary_' . $year . ($month ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '') . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $periodLabel = $year . ($month ? ' - ' . date('F', mktime(0, 0, 0, $month, 1)) : ' (All Months)');

        // Calculate totals
        $totalEEShare = $shareData->sum('total_ee_share');
        $totalERShare = $shareData->sum('total_er_share');
        $totalCombined = $shareData->sum('total_combined');

        // Create HTML content for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Employer Shares Summary</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; color: #333; font-size: 18px; }
                .header p { margin: 5px 0; color: #666; font-size: 12px; }
                .summary { margin-bottom: 20px; }
                .summary table { width: 100%; border-collapse: collapse; }
                .summary td { padding: 8px; border: 1px solid #ddd; text-align: center; }
                .summary .label { background-color: #f8f9fa; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .currency { text-align: right; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .shared-yes { color: green; font-weight: bold; }
                .shared-no { color: red; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Employer Shares Summary Report</h1>
                <p>Period: ' . $periodLabel . '</p>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <div class="summary">
                <table>
                    <tr>
                        <td class="label">Total Employee Share</td>
                        <td class="label">Total Employer Share</td>
                        <td class="label">Grand Total</td>
                    </tr>
                    <tr>
                        <td>₱' . number_format($totalEEShare, 2) . '</td>
                        <td>₱' . number_format($totalERShare, 2) . '</td>
                        <td>₱' . number_format($totalCombined, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Deduction Type</th>
                        <th class="currency">Employee Share</th>
                        <th class="currency">Employer Share</th>
                        <th class="currency">Total Amount</th>
                        <th>Shared with Employer</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($shareData as $data) {
            $sharedClass = $data->is_shared ? 'shared-yes' : 'shared-no';
            $sharedText = $data->is_shared ? 'Yes' : 'No';

            $html .= '
                    <tr>
                        <td>' . $data->name . '</td>
                        <td class="currency">₱' . number_format($data->total_ee_share, 2) . '</td>
                        <td class="currency">₱' . number_format($data->total_er_share, 2) . '</td>
                        <td class="currency">₱' . number_format($data->total_combined, 2) . '</td>
                        <td class="' . $sharedClass . '">' . $sharedText . '</td>
                    </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td><strong>TOTALS</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalEEShare, 2) . '</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalERShare, 2) . '</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalCombined, 2) . '</strong></td>
                        <td></td>
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
     * Export employer shares as Excel
     */
    private function exportEmployerSharesExcel($shareData, $year, $month)
    {
        $fileName = 'employer_shares_summary_' . $year . ($month ? '_' . str_pad($month, 2, '0', STR_PAD_LEFT) : '') . '_' . date('Y-m-d_H-i-s') . '.csv';

        // Create CSV content with proper headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->streamDownload(function () use ($shareData) {
            $output = fopen('php://output', 'w');

            // Initialize totals
            $totalEEShare = 0;
            $totalERShare = 0;
            $totalCombined = 0;

            // Write header row
            fputcsv($output, [
                'Deduction Type',
                'Employee Share',
                'Employer Share',
                'Total Amount',
                'Shared with Employer'
            ]);

            // Write data rows
            foreach ($shareData as $data) {
                $totalEEShare += $data->total_ee_share;
                $totalERShare += $data->total_er_share;
                $totalCombined += $data->total_combined;

                fputcsv($output, [
                    $data->name,
                    number_format($data->total_ee_share, 2),
                    number_format($data->total_er_share, 2),
                    number_format($data->total_combined, 2),
                    $data->is_shared ? 'Yes' : 'No'
                ]);
            }

            // Write totals row
            fputcsv($output, [
                'TOTALS',
                number_format($totalEEShare, 2),
                number_format($totalERShare, 2),
                number_format($totalCombined, 2),
                ''
            ]);

            fclose($output);
        }, $fileName, $headers);
    }
}
