<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\TimeSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimeScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('edit settings');

        $user = Auth::user();
        $query = TimeSchedule::query();

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $timeSchedules = $query->orderBy('name')->get();

        return response()->json($timeSchedules);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('edit settings');

        $request->validate([
            'name' => 'required|string|max:255|unique:time_schedules',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_duration_minutes' => 'nullable|integer|min:0|max:480', // Max 8 hours break
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
        ]);

        // Validate break times if provided
        if ($request->break_start && $request->break_end) {
            $timeIn = Carbon::createFromFormat('H:i', $request->time_in);
            $timeOut = Carbon::createFromFormat('H:i', $request->time_out);
            $breakStart = Carbon::createFromFormat('H:i', $request->break_start);
            $breakEnd = Carbon::createFromFormat('H:i', $request->break_end);

            if (
                $breakStart->lt($timeIn) || $breakStart->gt($timeOut) ||
                $breakEnd->lt($timeIn) || $breakEnd->gt($timeOut) ||
                $breakEnd->lt($breakStart)
            ) {
                return response()->json([
                    'message' => 'Break times must be within work hours and break end must be after break start.',
                    'errors' => [
                        'break_start' => ['Break times must be within work hours.'],
                        'break_end' => ['Break end must be after break start.']
                    ]
                ], 422);
            }
        }

        // Prepare data for creation
        $data = $request->only(['name', 'time_in', 'time_out']);

        // Handle break duration minutes - only include if provided and not empty
        if ($request->filled('break_duration_minutes')) {
            $data['break_duration_minutes'] = $request->break_duration_minutes;
        } else {
            $data['break_duration_minutes'] = null;
        }

        // Only include break times if they are provided and not empty
        if ($request->filled('break_start') && $request->filled('break_end')) {
            $data['break_start'] = $request->break_start;
            $data['break_end'] = $request->break_end;
        } else {
            $data['break_start'] = null;
            $data['break_end'] = null;
        }

        // Set default active status and company_id
        $data['is_active'] = true;
        $data['company_id'] = Auth::user()->company_id;

        $timeSchedule = TimeSchedule::create($data);

        // Calculate and store total hours
        $timeSchedule->total_hours = $timeSchedule->calculateTotalHours();
        $timeSchedule->save();

        return response()->json([
            'message' => 'Time schedule created successfully.',
            'data' => $timeSchedule
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TimeSchedule $timeSchedule)
    {
        $this->authorize('edit settings');

        return response()->json($timeSchedule);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeSchedule $timeSchedule)
    {
        $this->authorize('edit settings');

        $request->validate([
            'name' => 'required|string|max:255|unique:time_schedules,name,' . $timeSchedule->id,
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_duration_minutes' => 'nullable|integer|min:0|max:480',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
        ]);

        // Validate break times if provided
        if ($request->break_start && $request->break_end) {
            $timeIn = Carbon::createFromFormat('H:i', $request->time_in);
            $timeOut = Carbon::createFromFormat('H:i', $request->time_out);
            $breakStart = Carbon::createFromFormat('H:i', $request->break_start);
            $breakEnd = Carbon::createFromFormat('H:i', $request->break_end);

            if (
                $breakStart->lt($timeIn) || $breakStart->gt($timeOut) ||
                $breakEnd->lt($timeIn) || $breakEnd->gt($timeOut) ||
                $breakEnd->lt($breakStart)
            ) {
                return response()->json([
                    'message' => 'Break times must be within work hours and break end must be after break start.',
                    'errors' => [
                        'break_start' => ['Break times must be within work hours.'],
                        'break_end' => ['Break end must be after break start.']
                    ]
                ], 422);
            }
        }

        // Prepare data for update
        $data = $request->only(['name', 'time_in', 'time_out']);

        // Handle break duration minutes - only include if provided and not empty
        if ($request->filled('break_duration_minutes')) {
            $data['break_duration_minutes'] = $request->break_duration_minutes;
        } else {
            $data['break_duration_minutes'] = null;
        }

        // Only include break times if they are provided and not empty
        if ($request->filled('break_start') && $request->filled('break_end')) {
            $data['break_start'] = $request->break_start;
            $data['break_end'] = $request->break_end;
        } else {
            // If break times are not provided, set them to null
            $data['break_start'] = null;
            $data['break_end'] = null;
        }

        $timeSchedule->update($data);

        // Recalculate and update total hours
        $timeSchedule->total_hours = $timeSchedule->calculateTotalHours();
        $timeSchedule->save();

        return response()->json([
            'message' => 'Time schedule updated successfully.',
            'data' => $timeSchedule
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeSchedule $timeSchedule)
    {
        $this->authorize('edit settings');

        // Check if this schedule is being used by any employees
        $employeeCount = $timeSchedule->employees()->count();

        if ($employeeCount > 0) {
            return response()->json([
                'message' => "Cannot delete time schedule. It is currently assigned to {$employeeCount} employee(s)."
            ], 422);
        }

        $timeSchedule->delete();

        return response()->json([
            'message' => 'Time schedule deleted successfully.'
        ]);
    }

    /**
     * Update break periods for a specific time schedule
     */
    public function updateBreakPeriods(Request $request, TimeSchedule $timeSchedule)
    {
        $this->authorize('edit settings');

        $request->validate([
            'break_duration_minutes' => 'nullable|integer|min:0|max:480',
            'break_start' => 'nullable|date_format:H:i',
            'break_end' => 'nullable|date_format:H:i',
        ]);

        // Validate break times if provided
        if ($request->break_start && $request->break_end) {
            $timeIn = Carbon::createFromFormat('H:i', $timeSchedule->time_in);
            $timeOut = Carbon::createFromFormat('H:i', $timeSchedule->time_out);
            $breakStart = Carbon::createFromFormat('H:i', $request->break_start);
            $breakEnd = Carbon::createFromFormat('H:i', $request->break_end);

            if (
                $breakStart->lt($timeIn) || $breakStart->gt($timeOut) ||
                $breakEnd->lt($timeIn) || $breakEnd->gt($timeOut) ||
                $breakEnd->lt($breakStart)
            ) {
                return response()->json([
                    'message' => 'Break times must be within work hours and break end must be after break start.',
                    'errors' => [
                        'break_start' => ['Break times must be within work hours.'],
                        'break_end' => ['Break end must be after break start.']
                    ]
                ], 422);
            }
        }

        // Prepare data for update
        $data = [];

        // Handle break duration minutes - only include if provided and not empty
        if ($request->filled('break_duration_minutes')) {
            $data['break_duration_minutes'] = $request->break_duration_minutes;
        } else {
            $data['break_duration_minutes'] = null;
        }

        // Only include break times if they are provided and not empty
        if ($request->filled('break_start') && $request->filled('break_end')) {
            $data['break_start'] = $request->break_start;
            $data['break_end'] = $request->break_end;
        } else {
            // If break times are not provided, set them to null
            $data['break_start'] = null;
            $data['break_end'] = null;
        }

        $timeSchedule->update($data);

        return response()->json([
            'message' => 'Break periods updated successfully.',
            'data' => $timeSchedule
        ]);
    }
}
