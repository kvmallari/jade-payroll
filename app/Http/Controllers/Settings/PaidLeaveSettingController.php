<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaidLeaveSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaidLeaveSettingController extends Controller
{
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();

        $leaveSettings = PaidLeaveSetting::where('company_id', $workingCompanyId)
            ->orderBy('sort_order')
            ->get();

        return view('settings.leaves.index', compact('leaveSettings'));
    }

    public function create()
    {
        return view('settings.leaves.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_days' => 'required|integer|min:1|max:365',
            'limit_quantity' => 'required|integer|min:1',
            'limit_period' => 'required|in:monthly,quarterly,annually',
            'pay_rule' => 'required|in:full,half',
            'pay_applicable_to' => 'required|in:all,with_benefits,without_benefits',
        ]);

        // Generate a unique code from the name
        $code = strtoupper(substr(str_replace(' ', '', $validated['name']), 0, 10));
        $originalCode = $code;
        $counter = 1;

        while (PaidLeaveSetting::where('code', $code)->exists()) {
            $code = $originalCode . $counter;
            $counter++;
        }

        $validated['code'] = $code;
        $validated['is_active'] = true; // Always active by default
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        PaidLeaveSetting::create($validated);

        return redirect()->route('settings.leaves.index')
            ->with('success', 'Leave setting created successfully.');
    }

    public function show(PaidLeaveSetting $leave)
    {
        return view('settings.leaves.show', compact('leave'));
    }

    public function edit(PaidLeaveSetting $leave)
    {
        return view('settings.leaves.edit', compact('leave'));
    }

    public function update(Request $request, PaidLeaveSetting $leave)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'total_days' => 'required|integer|min:1|max:365',
            'limit_quantity' => 'required|integer|min:1',
            'limit_period' => 'required|in:monthly,quarterly,annually',
            'pay_rule' => 'required|in:full,half',
            'pay_applicable_to' => 'required|in:all,with_benefits,without_benefits',
        ]);

        $validated['is_active'] = true; // Always active by default

        $leave->update($validated);

        return redirect()->route('settings.leaves.index')
            ->with('success', 'Leave setting updated successfully.');
    }

    public function destroy(PaidLeaveSetting $leave)
    {
        if ($leave->is_system_default) {
            return back()->with('error', 'Cannot delete system default leave setting.');
        }

        $leave->delete();

        return redirect()->route('settings.leaves.index')
            ->with('success', 'Leave setting deleted successfully.');
    }

    public function toggle(PaidLeaveSetting $leave)
    {
        $leave->update([
            'is_active' => !$leave->is_active
        ]);

        return back()->with('success', 'Leave setting status updated.');
    }

    public function calculatePreview(Request $request)
    {
        $leave = PaidLeaveSetting::findOrFail($request->leave_id);
        $employeeData = $request->employee ?? [];

        // Mock employee object for calculation
        $employee = (object) array_merge([
            'gender' => 'male',
            'employment_type' => 'regular',
            'employment_status' => 'active',
            'hire_date' => now()->subYear(),
        ], $employeeData);

        $isEligible = $leave->isEmployeeEligible($employee);
        $annualEntitlement = $isEligible ? $leave->calculateAnnualEntitlement($employee) : 0;

        return response()->json([
            'is_eligible' => $isEligible,
            'annual_entitlement' => $annualEntitlement,
            'accrual_rate' => $leave->accrual_rate,
            'minimum_service_months' => $leave->minimum_service_months
        ]);
    }
}
