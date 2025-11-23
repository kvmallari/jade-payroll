<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $positions = Position::with(['department', 'employees'])
            ->withCount('employees')
            ->where('company_id', $workingCompanyId)
            ->orderBy('title')
            ->get();

        return view('settings.positions.index', compact('positions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $departments = Department::active()
            ->where('company_id', $workingCompanyId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Create form data',
            'data' => [
                'departments' => $departments
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        // Auto-assign company_id
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        $position = Position::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Position created successfully.',
                'data' => $position
            ]);
        }

        return redirect()->route('positions.index')->with('success', 'Position created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position)
    {
        $position->load(['department', 'employees']);
        return response()->json($position);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Position $position)
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $departments = Department::active()
            ->where('company_id', $workingCompanyId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Edit form data',
            'data' => [
                'position' => $position,
                'departments' => $departments
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Position $position)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'is_active' => 'boolean',
        ]);

        $position->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Position updated successfully.',
                'data' => $position
            ]);
        }

        return redirect()->route('positions.index')->with('success', 'Position updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position)
    {
        // Check if position has employees
        if ($position->employees()->count() > 0) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete position with existing employees.'
                ], 400);
            }
            return redirect()->route('positions.index')->with('error', 'Cannot delete position with existing employees.');
        }

        $position->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Position deleted successfully.'
            ]);
        }

        return redirect()->route('positions.index')->with('success', 'Position deleted successfully.');
    }
}
