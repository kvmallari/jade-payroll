<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\DaySchedule;
use App\Models\TimeSchedule;
use App\Models\Employee;
use App\Models\NightDifferentialSetting;
use Illuminate\Support\Facades\Auth;

class TimeLogSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the main Time Log Settings page
     */
    public function index()
    {
        $this->authorize('edit settings');

        $user = Auth::user();

        // Get day schedules filtered by company
        $dayQuery = DaySchedule::query();
        if (!$user->isSuperAdmin()) {
            $dayQuery->where('company_id', $user->company_id);
        }
        $daySchedules = $dayQuery->orderBy('name')->get();

        // Get time schedules filtered by company
        $timeQuery = TimeSchedule::query();
        if (!$user->isSuperAdmin()) {
            $timeQuery->where('company_id', $user->company_id);
        }
        $timeSchedules = $timeQuery->orderBy('name')->get();

        // Get all active employees
        $employees = Employee::with('user')->active()->orderBy('employee_number')->get();

        // Get grace period settings from database filtered by company
        $gracePeriodQuery = \App\Models\GracePeriodSetting::query();
        if (!$user->isSuperAdmin()) {
            $gracePeriodQuery->where('company_id', $user->company_id);
        }
        $gracePeriodSettings = $gracePeriodQuery->first() ?? \App\Models\GracePeriodSetting::getDefault();
        $gracePeriodData = [
            'late_grace_minutes' => $gracePeriodSettings->late_grace_minutes ?? 0,
            'undertime_grace_minutes' => $gracePeriodSettings->undertime_grace_minutes ?? 0,
            // overtime_threshold_minutes removed - now schedule-specific
        ];

        // Get night differential settings filtered by company
        $nightQuery = NightDifferentialSetting::query();
        if (!$user->isSuperAdmin()) {
            $nightQuery->where('company_id', $user->company_id);
        }
        $nightDifferentialSetting = $nightQuery->where('is_active', true)->first();

        // Provide default values if no setting exists
        if (!$nightDifferentialSetting) {
            $nightDifferentialData = [
                'start_time' => '22:00:00',
                'end_time' => '05:00:00',
                'rate_multiplier' => 1.10,
                'description' => 'Standard night differential',
                'is_active' => true,
            ];
        } else {
            $nightDifferentialData = [
                'start_time' => $nightDifferentialSetting->start_time,
                'end_time' => $nightDifferentialSetting->end_time,
                'rate_multiplier' => $nightDifferentialSetting->rate_multiplier,
                'description' => $nightDifferentialSetting->description,
                'is_active' => $nightDifferentialSetting->is_active,
            ];
        }

        return view('settings.time-logs.index', compact(
            'daySchedules',
            'timeSchedules',
            'employees',
            'gracePeriodData',
            'nightDifferentialData'
        ));
    }
}
