<?php

namespace App\Http\Controllers;

use App\Models\TimeSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TimeScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $timeSchedules = TimeSchedule::with('employees')
            ->withCount('employees')
            ->where('company_id', $workingCompanyId)
            ->orderBy('name')
            ->get();

        return view('settings.time-schedules.index', compact('timeSchedules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json([
            'message' => 'Create form data',
            'data' => []
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:time_schedules',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_hours' => 'nullable|numeric|min:0|max:8',
            'total_hours' => 'nullable|numeric|min:0|max:24',
            'is_active' => 'boolean',
        ]);

        // Add company_id to validated data
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        $timeSchedule = TimeSchedule::create($validated);

        return redirect()->route('time-schedules.index')->with('success', 'Time schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TimeSchedule $timeSchedule)
    {
        $timeSchedule->load('employees');
        return response()->json($timeSchedule);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TimeSchedule $timeSchedule)
    {
        return response()->json([
            'message' => 'Edit form data',
            'data' => $timeSchedule
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TimeSchedule $timeSchedule)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('time_schedules')->ignore($timeSchedule->id),
            ],
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'required|date_format:H:i|after:time_in',
            'break_hours' => 'nullable|numeric|min:0|max:8',
            'total_hours' => 'nullable|numeric|min:0|max:24',
            'is_active' => 'boolean',
        ]);

        $timeSchedule->update($validated);

        return redirect()->route('time-schedules.index')->with('success', 'Time schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TimeSchedule $timeSchedule)
    {
        // Check if time schedule has employees
        if ($timeSchedule->employees()->count() > 0) {
            return redirect()->route('time-schedules.index')->with('error', 'Cannot delete time schedule with existing employees.');
        }

        $timeSchedule->delete();

        return redirect()->route('time-schedules.index')->with('success', 'Time schedule deleted successfully.');
    }
}
