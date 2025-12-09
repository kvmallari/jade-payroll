<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\DaySchedule;
use Illuminate\Support\Facades\Auth;

class DayScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('edit settings');

        $user = Auth::user();
        $query = DaySchedule::query();

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $daySchedules = $query->orderBy('name')->get();

        return response()->json($daySchedules);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('edit settings');

        return response()->json([
            'days_of_week' => [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday',
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('edit settings');

        $request->validate([
            'name' => 'required|string|max:255|unique:day_schedules',
            'days' => 'required|array|min:1',
            'days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $daySchedule = DaySchedule::create([
            'company_id' => Auth::user()->company_id,
            'name' => $request->name,
            'monday' => in_array('monday', $request->days),
            'tuesday' => in_array('tuesday', $request->days),
            'wednesday' => in_array('wednesday', $request->days),
            'thursday' => in_array('thursday', $request->days),
            'friday' => in_array('friday', $request->days),
            'saturday' => in_array('saturday', $request->days),
            'sunday' => in_array('sunday', $request->days),
        ]);

        return response()->json([
            'message' => 'Day schedule created successfully.',
            'data' => $daySchedule
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DaySchedule $daySchedule)
    {
        $this->authorize('edit settings');

        return response()->json($daySchedule);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DaySchedule $daySchedule)
    {
        $this->authorize('edit settings');

        return response()->json([
            'data' => $daySchedule,
            'days_of_week' => [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday',
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DaySchedule $daySchedule)
    {
        $this->authorize('edit settings');

        $request->validate([
            'name' => 'required|string|max:255|unique:day_schedules,name,' . $daySchedule->id,
            'days' => 'required|array|min:1',
            'days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $daySchedule->update([
            'name' => $request->name,
            'monday' => in_array('monday', $request->days),
            'tuesday' => in_array('tuesday', $request->days),
            'wednesday' => in_array('wednesday', $request->days),
            'thursday' => in_array('thursday', $request->days),
            'friday' => in_array('friday', $request->days),
            'saturday' => in_array('saturday', $request->days),
            'sunday' => in_array('sunday', $request->days),
        ]);

        return response()->json([
            'message' => 'Day schedule updated successfully.',
            'data' => $daySchedule
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DaySchedule $daySchedule)
    {
        $this->authorize('edit settings');

        // Check if this schedule is being used by any employees
        $employeeCount = $daySchedule->employees()->count();

        if ($employeeCount > 0) {
            return response()->json([
                'message' => "Cannot delete day schedule. It is currently assigned to {$employeeCount} employee(s)."
            ], 422);
        }

        $daySchedule->delete();

        return response()->json([
            'message' => 'Day schedule deleted successfully.'
        ]);
    }
}
