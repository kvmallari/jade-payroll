<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use App\Models\PayScheduleSetting;
use App\Models\TimeSchedule;
use App\Models\DaySchedule;
use App\Models\EmploymentType;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EmployeeSettingController extends Controller
{
    public function index()
    {
        $settings = $this->getEmployeeSettings();
        
        // Scope data to user's working company
        $user = Auth::user();
        $workingCompanyId = $user->getWorkingCompanyId();
        
        $departmentsQuery = Department::where('company_id', $workingCompanyId);
        $positionsQuery = Position::with('department')->where('company_id', $workingCompanyId);
        
        $departments = $departmentsQuery->get();
        $positions = $positionsQuery->get();
        $timeSchedules = TimeSchedule::all();
        $daySchedules = DaySchedule::all();
        $paySchedules = PayScheduleSetting::all();
        $employmentTypes = EmploymentType::all();

        return view('settings.employee-settings.index', compact(
            'settings',
            'departments',
            'positions',
            'timeSchedules',
            'daySchedules',
            'paySchedules',
            'employmentTypes'
        ));
    }    public function update(Request $request)
    {
        $validated = $request->validate([
            'employee_number_prefix' => 'required|string|max:10',
            'employee_number_start' => 'required|integer|min:1',
            'auto_generate_employee_number' => 'boolean',
            'default_department_id' => 'nullable|exists:departments,id',
            'default_position_id' => 'nullable|exists:positions,id',
            'default_employment_type' => 'nullable|string|in:regular,contractual,probationary,part_time,casual',
            'default_employment_status' => 'nullable|string|in:active,inactive,terminated,resigned',
            'default_time_schedule_id' => 'nullable|exists:work_schedules,id',
            'default_day_schedule' => 'nullable|string',
            'default_pay_schedule' => 'nullable|string',
            'default_paid_leaves' => 'nullable|integer|min:0|max:365',
            'require_department' => 'boolean',
            'require_position' => 'boolean',
            'require_time_schedule' => 'boolean',
            'allow_custom_employee_number' => 'boolean',
        ]);

        // Store settings in cache and database
        foreach ($validated as $key => $value) {
            Cache::put("employee_setting_{$key}", $value, now()->addDays(30));

            // Also store in settings table if you have one
            DB::table('settings')->updateOrInsert(
                ['key' => "employee_{$key}"],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'Employee settings updated successfully!');
    }

    private function getEmployeeSettings()
    {
        return [
            'employee_number_prefix' => Cache::get('employee_setting_employee_number_prefix', 'EMP'),
            'employee_number_start' => Cache::get('employee_setting_employee_number_start', 1),
            'auto_generate_employee_number' => Cache::get('employee_setting_auto_generate_employee_number', true),
            'default_department_id' => Cache::get('employee_setting_default_department_id'),
            'default_position_id' => Cache::get('employee_setting_default_position_id'),
            'default_employment_type' => Cache::get('employee_setting_default_employment_type', 'regular'),
            'default_employment_status' => Cache::get('employee_setting_default_employment_status', 'active'),
            'default_time_schedule_id' => Cache::get('employee_setting_default_time_schedule_id'),
            'default_day_schedule' => Cache::get('employee_setting_default_day_schedule', 'monday_to_friday'),
            'default_pay_schedule' => Cache::get('employee_setting_default_pay_schedule'),
            'default_paid_leaves' => Cache::get('employee_setting_default_paid_leaves', 15),
            'require_department' => Cache::get('employee_setting_require_department', true),
            'require_position' => Cache::get('employee_setting_require_position', true),
            'require_time_schedule' => Cache::get('employee_setting_require_time_schedule', true),
        ];
    }

    public function getNextEmployeeNumber()
    {
        $prefix = Cache::get('employee_setting_employee_number_prefix', 'EMP');
        $startNumber = Cache::get('employee_setting_employee_number_start', 1);

        // Get the current year
        $currentYear = date('Y');

        // Find the last employee number with the same prefix and year pattern
        $pattern = $prefix . '-' . $currentYear . '-';
        $lastEmployee = \App\Models\Employee::where('employee_number', 'LIKE', $pattern . '%')
            ->orderBy('employee_number', 'desc')
            ->first();

        if ($lastEmployee && preg_match('/-(\d+)$/', $lastEmployee->employee_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = $startNumber;
        }

        // Format: PREFIX-YEAR-NUMBER (e.g., EMP-2025-0001)
        $employeeNumber = $prefix . '-' . $currentYear . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        return response()->json(['employee_number' => $employeeNumber]);
    }

    public function reset()
    {
        try {
            // Define default settings
            $defaultSettings = [
                'employee_number_prefix' => 'EMP',
                'employee_number_start' => '1',
                'auto_generate_employee_number' => '1',
                'require_employee_number' => '1',
                'require_first_name' => '1',
                'require_last_name' => '1',
                'require_email' => '1',
                'require_phone' => '1',
                'require_address' => '1',
                'require_birth_date' => '1',
                'require_hire_date' => '1',
                'require_department' => '1',
                'require_position' => '1',
                'require_employment_type' => '1',
                'require_time_schedule' => '1',
                'require_day_schedule' => '1',
                'default_department' => '',
                'default_position' => '',
                'default_employment_type' => '',
                'default_time_schedule' => '',
                'default_day_schedule' => '',
                'default_pay_frequency' => '',
                'default_paid_leaves' => ''
            ];

            // Clear cache and update settings
            Cache::forget('employee_settings');

            foreach ($defaultSettings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            return redirect()->back()->with('success', 'Employee settings have been reset to default values.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reset employee settings: ' . $e->getMessage());
        }
    }
}
