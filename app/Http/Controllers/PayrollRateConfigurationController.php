<?php

namespace App\Http\Controllers;

use App\Models\PayrollRateConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollRateConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $configurations = PayrollRateConfiguration::ordered()
            ->where('company_id', $workingCompanyId)
            ->get();

        return view('admin.payroll-rate-configurations.index', compact('configurations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.payroll-rate-configurations.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type_name' => 'required|string|max:255|unique:payroll_rate_configurations,type_name',
            'display_name' => 'required|string|max:255',
            'regular_rate_multiplier' => 'required|numeric|min:0|max:1000',
            'overtime_rate_multiplier' => 'required|numeric|min:0|max:1000',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Convert percentages to decimals
        $validated['regular_rate_multiplier'] = $validated['regular_rate_multiplier'] / 100;
        $validated['overtime_rate_multiplier'] = $validated['overtime_rate_multiplier'] / 100;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Auto-assign company_id
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        PayrollRateConfiguration::create($validated);

        return redirect()->route('payroll-rate-configurations.index')
            ->with('success', 'Rate configuration created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PayrollRateConfiguration $payrollRateConfiguration)
    {
        return view('admin.payroll-rate-configurations.edit', compact('payrollRateConfiguration'));
    }

    /**
     * Display the specified resource.
     */
    public function show(PayrollRateConfiguration $payrollRateConfiguration)
    {
        return view('admin.payroll-rate-configurations.show', compact('payrollRateConfiguration'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PayrollRateConfiguration $payrollRateConfiguration)
    {
        $validated = $request->validate([
            'type_name' => 'required|string|max:255|unique:payroll_rate_configurations,type_name,' . $payrollRateConfiguration->id,
            'display_name' => 'required|string|max:255',
            'regular_rate_multiplier' => 'required|numeric|min:0|max:1000',
            'overtime_rate_multiplier' => 'required|numeric|min:0|max:1000',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Convert percentages to decimals
        $validated['regular_rate_multiplier'] = $validated['regular_rate_multiplier'] / 100;
        $validated['overtime_rate_multiplier'] = $validated['overtime_rate_multiplier'] / 100;
        $validated['is_active'] = $request->boolean('is_active', true);

        $payrollRateConfiguration->update($validated);

        return redirect()->route('payroll-rate-configurations.index')
            ->with('success', 'Rate configuration updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PayrollRateConfiguration $payrollRateConfiguration)
    {
        $payrollRateConfiguration->delete();

        return redirect()->route('payroll-rate-configurations.index')
            ->with('success', 'Rate configuration deleted successfully.');
    }

    /**
     * Initialize default configurations
     */
    public function initializeDefaults()
    {
        PayrollRateConfiguration::createDefaults();

        return redirect()->route('payroll-rate-configurations.index')
            ->with('success', 'Default rate configurations initialized successfully.');
    }

    /**
     * Toggle the status of a rate configuration
     */
    public function toggle(PayrollRateConfiguration $payrollRateConfiguration)
    {
        $payrollRateConfiguration->is_active = !$payrollRateConfiguration->is_active;
        $payrollRateConfiguration->save();

        $status = $payrollRateConfiguration->is_active ? 'activated' : 'deactivated';

        return redirect()->route('payroll-rate-configurations.index')
            ->with('success', "Rate configuration '{$payrollRateConfiguration->display_name}' has been {$status}.");
    }
}
