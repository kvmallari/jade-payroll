<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NightDifferentialSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class NightDifferentialController extends Controller
{
    use AuthorizesRequests;

    /**
     * Update night differential settings
     */
    public function update(Request $request)
    {
        $this->authorize('edit settings');

        $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'rate_multiplier' => 'required|numeric|min:1|max:2',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        // Get current setting for company or create new one
        $user = Auth::user();
        $query = NightDifferentialSetting::query();

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $setting = $query->first();

        if (!$setting) {
            $setting = new NightDifferentialSetting();
            $setting->company_id = $user->company_id;
        }

        $setting->fill([
            'start_time' => $request->start_time . ':00',
            'end_time' => $request->end_time . ':00',
            'rate_multiplier' => $request->rate_multiplier,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active')
        ]);

        $setting->save();

        // Trigger recalculation of all draft payrolls when night differential settings change
        $this->recalculateDraftPayrollsAfterSettingChange();

        return response()->json([
            'success' => true,
            'message' => 'Night differential settings updated successfully'
        ]);
    }

    /**
     * Recalculate all draft payrolls after night differential settings change
     */
    private function recalculateDraftPayrollsAfterSettingChange()
    {
        // Get all draft payrolls
        $draftPayrolls = \App\Models\Payroll::where('status', 'draft')->get();

        foreach ($draftPayrolls as $payroll) {
            try {
                // Recalculate all time logs for this payroll period
                $this->recalculateTimeLogsForPayroll($payroll);

                Log::info('Recalculated draft payroll after night differential setting change', [
                    'payroll_id' => $payroll->id,
                    'payroll_number' => $payroll->payroll_number
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to recalculate draft payroll after setting change', [
                    'payroll_id' => $payroll->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Recalculate time log hours for all employees in a payroll period
     */
    private function recalculateTimeLogsForPayroll(\App\Models\Payroll $payroll)
    {
        $employeeIds = $payroll->payrollDetails->pluck('employee_id');

        $timeLogs = \App\Models\TimeLog::whereIn('employee_id', $employeeIds)
            ->whereBetween('log_date', [$payroll->period_start, $payroll->period_end])
            ->get();

        $payrollController = app(\App\Http\Controllers\PayrollController::class);

        foreach ($timeLogs as $timeLog) {
            // Skip if incomplete record
            if (!$timeLog->time_in || !$timeLog->time_out) {
                continue;
            }

            // Use reflection to access the private method from PayrollController
            $reflection = new \ReflectionClass($payrollController);
            $method = $reflection->getMethod('calculateTimeLogHoursDynamically');
            $method->setAccessible(true);

            // Recalculate hours using the dynamic calculation method
            $dynamicCalculation = $method->invoke($payrollController, $timeLog);

            // Update the stored values with the new calculations including breakdown
            $timeLog->regular_hours = $dynamicCalculation['regular_hours'];
            $timeLog->overtime_hours = $dynamicCalculation['overtime_hours'];
            $timeLog->regular_overtime_hours = $dynamicCalculation['regular_overtime_hours'] ?? 0;
            $timeLog->night_diff_overtime_hours = $dynamicCalculation['night_diff_overtime_hours'] ?? 0;
            $timeLog->total_hours = $dynamicCalculation['total_hours'];
            $timeLog->late_hours = $dynamicCalculation['late_hours'];
            $timeLog->undertime_hours = $dynamicCalculation['undertime_hours'];
            $timeLog->save();
        }
    }
}
