<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NoWorkSuspendedSetting;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoWorkSuspendedSettingController extends Controller
{
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();

        // Get all suspensions ordered by date (latest first), maintain this order throughout
        $allSuspensions = NoWorkSuspendedSetting::where('company_id', $workingCompanyId)
            ->orderBy('date_from', 'desc')
            ->get();

        // Group by status but preserve the date ordering within each group
        $suspensions = $allSuspensions->groupBy('status');

        // For the view, we want a single ordered list (latest dates first, regardless of status)
        $orderedSuspensions = $allSuspensions;

        return view('settings.suspension.index', compact('suspensions', 'orderedSuspensions'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get(['id', 'name']);
        $positions = Position::where('is_active', true)->get(['id', 'title', 'department_id']);
        $employees = Employee::where('employment_status', 'active')
            ->with(['user:id,name', 'department:id,name', 'position:id,title'])
            ->get(['id', 'user_id', 'employee_number', 'department_id', 'position_id']);

        return view('settings.suspension.create', compact('departments', 'positions', 'employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i|after:time_from',
            'type' => 'required|in:full_day_suspension,partial_suspension',
            'reason' => 'required|in:weather,system_maintenance,emergency,government_order,other',
            'is_paid' => 'boolean',
            'pay_rule' => 'nullable|in:full,half',
            'pay_applicable_to' => 'nullable|in:all,with_benefits,without_benefits',
        ]);

        // Set default values for removed fields
        $validated['code'] = 'SUSP-' . now()->format('Ymd-His'); // Auto-generate code
        $validated['status'] = 'active'; // Default to active

        // If date_to is not provided, set it to date_from (single day suspension)
        if (empty($validated['date_to'])) {
            $validated['date_to'] = $validated['date_from'];
        }

        // For full day suspension, set time fields to null to avoid SQL errors
        if ($validated['type'] === 'full_day_suspension') {
            $validated['time_from'] = null;
            $validated['time_to'] = null;
        }

        // Handle empty time strings for partial suspension
        if (empty($validated['time_from'])) {
            $validated['time_from'] = null;
        }
        if (empty($validated['time_to'])) {
            $validated['time_to'] = null;
        }

        // Handle is_paid checkbox (defaults to false if not checked)
        $validated['is_paid'] = $request->has('is_paid') ? true : false;

        // Set default values for pay settings if not paid
        if (!$validated['is_paid']) {
            $validated['pay_rule'] = null;
            $validated['pay_applicable_to'] = null;
        } else {
            // Set default pay rule if paid but not specified
            if (empty($validated['pay_rule'])) {
                $validated['pay_rule'] = 'full';
            }
        }

        // Add company_id from logged in user
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        NoWorkSuspendedSetting::create($validated);

        return redirect()->route('settings.suspension.index')
            ->with('success', 'Suspension day created successfully.');
    }

    public function show(NoWorkSuspendedSetting $suspension)
    {
        return view('settings.suspension.show', compact('suspension'));
    }

    public function edit(NoWorkSuspendedSetting $suspension)
    {
        $departments = Department::where('is_active', true)->get(['id', 'name']);
        $positions = Position::where('is_active', true)->get(['id', 'title', 'department_id']);
        $employees = Employee::where('employment_status', 'active')
            ->with(['user:id,name', 'department:id,name', 'position:id,title'])
            ->get(['id', 'user_id', 'employee_number', 'department_id', 'position_id']);

        return view('settings.suspension.edit', compact('suspension', 'departments', 'positions', 'employees'));
    }

    public function update(Request $request, NoWorkSuspendedSetting $suspension)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i|after:time_from',
            'type' => 'required|in:full_day_suspension,partial_suspension',
            'reason' => 'required|in:weather,system_maintenance,emergency,government_order,other',
            'is_paid' => 'boolean',
            'pay_rule' => 'nullable|in:full,half',
            'pay_applicable_to' => 'nullable|in:all,with_benefits,without_benefits',
        ]);

        // For full day suspension, set time fields to null to avoid SQL errors
        if ($validated['type'] === 'full_day_suspension') {
            $validated['time_from'] = null;
            $validated['time_to'] = null;
        }

        // Handle empty time strings for partial suspension
        if (empty($validated['time_from'])) {
            $validated['time_from'] = null;
        }
        if (empty($validated['time_to'])) {
            $validated['time_to'] = null;
        }

        // Handle is_paid checkbox (defaults to false if not checked)
        $validated['is_paid'] = $request->has('is_paid') ? true : false;

        // Set default values for pay settings if not paid
        if (!$validated['is_paid']) {
            $validated['pay_rule'] = null;
            $validated['pay_applicable_to'] = null;
        } else {
            // Set default pay rule if paid but not specified
            if (empty($validated['pay_rule'])) {
                $validated['pay_rule'] = 'full';
            }
        }

        $suspension->update($validated);

        return redirect()->route('settings.suspension.index')
            ->with('success', 'Suspension setting updated successfully.');
    }

    public function destroy(NoWorkSuspendedSetting $suspension)
    {
        $suspension->delete();

        return redirect()->route('settings.suspension.index')
            ->with('success', 'Suspension setting deleted successfully.');
    }

    public function toggle(NoWorkSuspendedSetting $suspension)
    {
        $newStatus = $suspension->status === 'active' ? 'cancelled' : 'active';
        $suspension->update(['status' => $newStatus]);

        return back()->with('success', 'Suspension setting ' . ($newStatus === 'active' ? 'activated' : 'deactivated') . ' successfully.');
    }

    public function activate(NoWorkSuspendedSetting $suspension)
    {
        $suspension->update(['status' => 'active']);

        return back()->with('success', 'No Work/Suspended setting activated.');
    }

    public function complete(NoWorkSuspendedSetting $suspension)
    {
        $suspension->update(['status' => 'completed']);

        return back()->with('success', 'No Work/Suspended setting marked as completed.');
    }

    public function cancel(NoWorkSuspendedSetting $suspension)
    {
        $suspension->update(['status' => 'cancelled']);

        return back()->with('success', 'No Work/Suspended setting cancelled.');
    }

    public function getAffectedEmployees(NoWorkSuspendedSetting $suspension)
    {
        $employees = collect();

        switch ($suspension->scope) {
            case 'company_wide':
                $employees = Employee::where('employment_status', 'active')->get();
                break;

            case 'department':
                if ($suspension->affected_departments) {
                    $employees = Employee::whereIn('department_id', $suspension->affected_departments)
                        ->where('employment_status', 'active')
                        ->get();
                }
                break;

            case 'position':
                if ($suspension->affected_positions) {
                    $employees = Employee::whereIn('position_id', $suspension->affected_positions)
                        ->where('employment_status', 'active')
                        ->get();
                }
                break;

            case 'specific_employees':
                if ($suspension->affected_employees) {
                    $employees = Employee::whereIn('id', $suspension->affected_employees)
                        ->where('employment_status', 'active')
                        ->get();
                }
                break;
        }

        return response()->json([
            'affected_employees' => $employees->load(['user', 'department', 'position']),
            'total_count' => $employees->count()
        ]);
    }
}
