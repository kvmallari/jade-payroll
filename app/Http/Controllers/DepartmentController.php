<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $departments = Department::with('employees')
            ->withCount('employees')
            ->where('company_id', $workingCompanyId)
            ->orderBy('name')
            ->get();

        return view('settings.departments.index', compact('departments'));
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
            'name' => 'required|string|max:255|unique:departments',
            'code' => 'nullable|string|max:10|unique:departments',
            'is_active' => 'boolean',
        ]);

        // Auto-assign company_id
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        $department = Department::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Department created successfully.',
                'data' => $department
            ]);
        }

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $department->load('employees');
        return response()->json($department);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        return response()->json([
            'message' => 'Edit form data',
            'data' => $department
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments')->ignore($department->id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('departments')->ignore($department->id),
            ],
            'is_active' => 'boolean',
        ]);

        $department->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully.',
                'data' => $department
            ]);
        }

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        // Check if department has employees
        if ($department->employees()->count() > 0) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot delete department with existing employees.'
                ], 400);
            }
            return redirect()->route('departments.index')->with('error', 'Cannot delete department with existing employees.');
        }

        $department->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully.'
            ]);
        }

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }

    /**
     * Get positions for a specific department
     */
    public function positions(Department $department)
    {
        $positions = $department->positions()->select('id', 'title')->get();
        return response()->json($positions);
    }
}
