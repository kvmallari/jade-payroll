<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AllowanceBonusSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AllowanceBonusSettingController extends Controller
{
    public function index()
    {
        $settings = AllowanceBonusSetting::orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');

        return view('settings.allowances.index', compact('settings'));
    }

    public function create()
    {
        return view('settings.allowances.create');
    }

    public function store(Request $request)
    {
        // Clean up empty decimal values before validation
        $requestData = $request->all();
        $decimalFields = ['rate_percentage', 'fixed_amount', 'multiplier', 'minimum_amount', 'maximum_amount'];

        foreach ($decimalFields as $field) {
            if (isset($requestData[$field]) && ($requestData[$field] === '' || $requestData[$field] === null)) {
                $requestData[$field] = null;
            }
        }

        $request->merge($requestData);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:allowance,bonus,incentives',
            'calculation_type' => 'required|in:percentage,fixed_amount,daily_rate_multiplier,automatic',
            'rate_percentage' => 'nullable|numeric|min:0|max:100|required_if:calculation_type,percentage',
            'fixed_amount' => 'nullable|numeric|min:0|required_if:calculation_type,fixed_amount',
            'daily_rate_multiplier' => 'nullable|numeric|min:0|required_if:calculation_type,daily_rate_multiplier',
            'is_taxable' => 'boolean',
            'frequency' => 'required|in:per_payroll,monthly,quarterly,annually',
            'distribution_method' => 'required|in:first_payroll,last_payroll,equally_distributed',
            'is_active' => 'boolean',
            'benefit_eligibility' => 'required|in:both,with_benefits,without_benefits',
            'requires_perfect_attendance' => 'boolean',
        ]);

        // Auto-generate code from name
        $baseCode = Str::slug($validated['name'], '_');
        $code = $baseCode;
        $counter = 1;

        // Ensure unique code
        while (AllowanceBonusSetting::where('code', $code)->exists()) {
            $code = $baseCode . '_' . $counter;
            $counter++;
        }

        $validated['code'] = $code;

        AllowanceBonusSetting::create($validated);

        return redirect()->route('settings.allowances.index')
            ->with('success', 'Allowance/Bonus setting created successfully.');
    }

    public function show(AllowanceBonusSetting $allowance)
    {
        return view('settings.allowances.show', compact('allowance'));
    }

    public function edit(AllowanceBonusSetting $allowance)
    {
        return view('settings.allowances.edit', compact('allowance'));
    }

    public function update(Request $request, AllowanceBonusSetting $allowance)
    {
        // Clean up empty decimal values before validation
        $requestData = $request->all();
        $decimalFields = ['rate_percentage', 'fixed_amount', 'multiplier', 'minimum_amount', 'maximum_amount'];

        foreach ($decimalFields as $field) {
            if (isset($requestData[$field]) && ($requestData[$field] === '' || $requestData[$field] === null)) {
                $requestData[$field] = null;
            }
        }

        $request->merge($requestData);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:allowance,bonus,incentives',
            'calculation_type' => 'required|in:percentage,fixed_amount,daily_rate_multiplier,automatic',
            'rate_percentage' => 'nullable|numeric|min:0|max:100|required_if:calculation_type,percentage',
            'fixed_amount' => 'nullable|numeric|min:0|required_if:calculation_type,fixed_amount',
            'multiplier' => 'nullable|numeric|min:0|required_if:calculation_type,daily_rate_multiplier',
            'is_taxable' => 'boolean',
            'frequency' => 'required|in:per_payroll,monthly,quarterly,annually',
            'distribution_method' => 'required|in:first_payroll,last_payroll,equally_distributed',
            'is_active' => 'boolean',
            'benefit_eligibility' => 'required|in:both,with_benefits,without_benefits',
            'requires_perfect_attendance' => 'boolean',
        ]);

        $allowance->update($validated);

        return redirect()->route('settings.allowances.index')
            ->with('success', 'Allowance/Bonus setting updated successfully.');
    }

    public function destroy(AllowanceBonusSetting $allowance)
    {
        if ($allowance->is_active) {
            return back()->with('error', 'Cannot delete active allowance/bonus. Please deactivate it first.');
        }

        $allowance->delete();

        return redirect()->route('settings.allowances.index')
            ->with('success', 'Allowance/Bonus setting deleted successfully.');
    }

    public function toggle(AllowanceBonusSetting $allowance)
    {
        $allowance->update([
            'is_active' => !$allowance->is_active
        ]);

        return back()->with('success', 'Allowance/Bonus status updated.');
    }

    public function calculatePreview(Request $request)
    {
        $setting = AllowanceBonusSetting::findOrFail($request->setting_id);
        $basicSalary = $request->basic_salary ?? 0;
        $dailyRate = $request->daily_rate ?? 0;
        $workingDays = $request->working_days ?? 22;

        $amount = $setting->calculateAmount($basicSalary, $dailyRate, $workingDays);

        return response()->json([
            'amount' => $amount,
            'is_taxable' => $setting->is_taxable,
            'frequency' => $setting->frequency
        ]);
    }
}
