<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SettingsController extends Controller
{
    use AuthorizesRequests;
    /**
     * Show payroll settings
     */
    public function payroll()
    {
        $this->authorize('manage settings');

        $autoPayrollEnabled = config('app.auto_payroll_enabled', false);

        return view('settings.payroll', compact('autoPayrollEnabled'));
    }

    /**
     * Update payroll settings
     */
    public function updatePayroll(Request $request)
    {
        $this->authorize('manage settings');

        $validated = $request->validate([
            'auto_payroll_enabled' => 'boolean'
        ]);

        // Update the .env file
        $this->updateEnvFile('AUTO_PAYROLL_ENABLED', $validated['auto_payroll_enabled'] ? 'true' : 'false');

        return redirect()->route('settings.payroll')
            ->with('success', 'Payroll settings updated successfully.');
    }

    /**
     * Update environment file
     */
    private function updateEnvFile($key, $value)
    {
        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            $envContent = File::get($envPath);

            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }

            File::put($envPath, $envContent);
        }
    }

    /**
     * Test automatic payroll creation (dry run)
     */
    public function testAutoPayroll()
    {
        $this->authorize('manage settings');

        Artisan::call('payroll:auto-create', ['--dry-run' => true]);
        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'output' => $output
        ]);
    }

    /**
     * Get next employee number
     */
    public function getNextEmployeeNumber()
    {
        $this->authorize('create employees');

        $prefix = cache('employee_setting_employee_number_prefix', 'EMP');
        $lastEmployee = \App\Models\Employee::where('employee_number', 'LIKE', $prefix . '%')
            ->orderBy('employee_number', 'desc')
            ->first();

        if ($lastEmployee) {
            // Extract the numeric part and increment
            $lastNumber = str_replace($prefix, '', $lastEmployee->employee_number);
            $nextNumber = intval($lastNumber) + 1;
        } else {
            $nextNumber = 1;
        }

        $employeeNumber = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return response()->json([
            'employee_number' => $employeeNumber
        ]);
    }

    /**
     * Store a custom time schedule
     */
    public function storeTimeSchedule(Request $request)
    {
        $this->authorize('create employees');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:time_schedules,name',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i|after:break_start',
        ]);

        $timeSchedule = \App\Models\TimeSchedule::create([
            'name' => $validated['name'],
            'time_in' => $validated['time_in'],
            'time_out' => $validated['time_out'],
            'break_start' => $validated['break_start'],
            'break_end' => $validated['break_end'],
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'schedule' => $timeSchedule,
            'message' => 'Time schedule created successfully!'
        ]);
    }

    /**
     * Store a custom day schedule
     */
    public function storeDaySchedule(Request $request)
    {
        $this->authorize('create employees');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:day_schedules,name',
            'days' => 'required|array|min:1',
            'days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $daySchedule = \App\Models\DaySchedule::create([
            'name' => $validated['name'],
            'monday' => in_array('monday', $validated['days']),
            'tuesday' => in_array('tuesday', $validated['days']),
            'wednesday' => in_array('wednesday', $validated['days']),
            'thursday' => in_array('thursday', $validated['days']),
            'friday' => in_array('friday', $validated['days']),
            'saturday' => in_array('saturday', $validated['days']),
            'sunday' => in_array('sunday', $validated['days']),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'schedule' => $daySchedule,
            'message' => 'Day schedule created successfully!'
        ]);
    }

    /**
     * Show employee settings page
     */
    public function employeeSettings()
    {
        $this->authorize('manage settings');

        $user = Auth::user();

        // Scope data by company
        $departments = \App\Models\Department::where('company_id', $user->company_id)->get();
        $positions = \App\Models\Position::where('company_id', $user->company_id)->with('department')->get();
        $timeSchedules = \App\Models\TimeSchedule::all();
        $daySchedules = \App\Models\DaySchedule::all();

        $settings = [
            'employee_number_prefix' => cache('employee_setting_employee_number_prefix', 'EMP'),
            'employee_number_start' => cache('employee_setting_employee_number_start', 1),
            'auto_generate_employee_number' => cache('employee_setting_auto_generate_employee_number', true),
        ];

        return view('settings.employee-settings.index', compact(
            'departments',
            'positions',
            'timeSchedules',
            'daySchedules',
            'settings'
        ));
    }

    /**
     * Update employee settings
     */
    public function updateEmployeeSettings(Request $request)
    {
        $this->authorize('manage settings');

        $validated = $request->validate([
            'employee_number_prefix' => 'required|string|max:10',
            'employee_number_start' => 'required|integer|min:1',
            'auto_generate_employee_number' => 'boolean',
        ]);

        // Save settings to cache
        foreach ($validated as $key => $value) {
            cache(['employee_setting_' . $key => $value], now()->addYears(1));
        }

        return redirect()->route('settings.employee')
            ->with('success', 'Employee configuration updated successfully.');
    }
}
