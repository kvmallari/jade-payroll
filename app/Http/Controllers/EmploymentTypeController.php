<?php

namespace App\Http\Controllers;

use App\Models\EmploymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmploymentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        $query = EmploymentType::query();

        // Scope by company
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $employmentTypes = $query->orderBy('name')->get();

        return view('settings.employment-types.index', compact('employmentTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('settings.employment-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'has_benefits' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $employmentType = EmploymentType::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'has_benefits' => $request->input('has_benefits') == '1' || $request->input('has_benefits') === 1,
            'description' => $request->description,
            'is_active' => $request->input('is_active') == '1' || $request->input('is_active') === 1
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Employment type created successfully.', 'data' => $employmentType]);
        }

        return redirect()->route('employment-types.index')
            ->with('success', 'Employment type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(EmploymentType $employmentType)
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json($employmentType);
        }

        return view('settings.employment-types.show', compact('employmentType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmploymentType $employmentType)
    {
        return view('settings.employment-types.edit', compact('employmentType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmploymentType $employmentType)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'has_benefits' => 'boolean',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $employmentType->update([
            'name' => $request->name,
            'has_benefits' => $request->input('has_benefits') == '1' || $request->input('has_benefits') === 1,
            'description' => $request->description,
            'is_active' => $request->input('is_active') == '1' || $request->input('is_active') === 1
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Employment type updated successfully.', 'data' => $employmentType]);
        }

        return redirect()->route('employment-types.index')
            ->with('success', 'Employment type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmploymentType $employmentType)
    {
        // Check if any employees are using this employment type
        if ($employmentType->employees()->exists()) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['error' => 'Cannot delete employment type. It is being used by employees.'], 400);
            }
            return redirect()->route('employment-types.index')
                ->with('error', 'Cannot delete employment type. It is being used by employees.');
        }

        $employmentType->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Employment type deleted successfully.']);
        }

        return redirect()->route('employment-types.index')
            ->with('success', 'Employment type deleted successfully.');
    }
}
