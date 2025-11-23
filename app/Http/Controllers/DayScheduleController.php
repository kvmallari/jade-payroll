<?php

namespace App\Http\Controllers;

use App\Models\DaySchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class DayScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $daySchedules = DaySchedule::with('employees')
            ->withCount('employees')
            ->where('company_id', $workingCompanyId)
            ->orderBy('name')
            ->get();

        return view('settings.day-schedules.index', compact('daySchedules'));
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
            'name' => 'required|string|max:255|unique:day_schedules',
            'monday' => 'boolean',
            'tuesday' => 'boolean',
            'wednesday' => 'boolean',
            'thursday' => 'boolean',
            'friday' => 'boolean',
            'saturday' => 'boolean',
            'sunday' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Add company_id to validated data
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        $daySchedule = DaySchedule::create($validated);

        return redirect()->route('day-schedules.index')->with('success', 'Day schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DaySchedule $daySchedule)
    {
        $daySchedule->load('employees');
        return response()->json($daySchedule);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DaySchedule $daySchedule)
    {
        return response()->json([
            'message' => 'Edit form data',
            'data' => $daySchedule
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DaySchedule $daySchedule)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('day_schedules')->ignore($daySchedule->id),
            ],
            'monday' => 'boolean',
            'tuesday' => 'boolean',
            'wednesday' => 'boolean',
            'thursday' => 'boolean',
            'friday' => 'boolean',
            'saturday' => 'boolean',
            'sunday' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $daySchedule->update($validated);

        return redirect()->route('day-schedules.index')->with('success', 'Day schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DaySchedule $daySchedule)
    {
        // Check if day schedule has employees
        if ($daySchedule->employees()->count() > 0) {
            return redirect()->route('day-schedules.index')->with('error', 'Cannot delete day schedule with existing employees.');
        }

        $daySchedule->delete();

        return redirect()->route('day-schedules.index')->with('success', 'Day schedule deleted successfully.');
    }
}
