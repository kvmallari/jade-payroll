<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyInitializationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Company::withCount(['users', 'employees']);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $companies = $query->latest('created_at')->paginate(10)->withQueryString();

        // Validate license status for each company
        foreach ($companies as $company) {
            if ($company->license_key) {
                $license = \App\Models\SystemLicense::where('license_key', $company->license_key)->first();

                // If license doesn't exist or is expired, clear it
                if (!$license) {
                    $company->update(['license_key' => null]);
                    $company->license_key = null; // Update in-memory for display
                } elseif ($license->expires_at && $license->expires_at->isPast()) {
                    // Keep the license key but mark as expired for display
                    $company->license_expired = true;
                }
            }
        }

        return view('companies.index', compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('companies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CompanyInitializationService $initService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:companies,code',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Set default active status to true (active)
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $company = Company::create($validated);

        // Initialize default settings for the new company
        $initService->initializeCompanySettings($company);

        return redirect()->route('companies.index')
            ->with('success', 'Company created successfully with default settings.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $company->load(['users', 'employees']);
        return view('companies.show', compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        // Return JSON for AJAX modal requests
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'company' => $company
            ]);
        }

        return view('companies.edit', compact('company'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:50', Rule::unique('companies')->ignore($company->id)],
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'license_key' => 'nullable|string|max:255',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Update license key for a company (Super Admin only)
     */
    public function updateLicenseKey(Request $request, Company $company)
    {
        $validated = $request->validate([
            'license_key' => 'nullable|string|max:255',
        ]);

        // If license key is provided, validate it exists in system licenses
        if (!empty($validated['license_key'])) {
            if (!\App\Services\LicenseService::isValidLicenseKey($validated['license_key'])) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'errors' => [
                            'license_key' => ['Invalid license key. The license key must be listed in the system. Run "php artisan license:list" to see available licenses.']
                        ]
                    ], 422);
                }
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'Invalid license key. The license key must be listed in the system. Run "php artisan license:list" to see available licenses.']);
            }

            // Check if license key is already being used by another company
            $existingCompany = Company::where('license_key', $validated['license_key'])
                ->where('id', '!=', $company->id)
                ->first();

            if ($existingCompany) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'errors' => [
                            'license_key' => ['License key is already in use by "' . $existingCompany->name]
                        ]
                    ], 422);
                }
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'License key is already in use by "' . $existingCompany->name]);
            }
        }

        $company->update(['license_key' => $validated['license_key']]);

        // If a license key is set, mark it as active and set expiration
        if (!empty($validated['license_key'])) {
            $license = \App\Models\SystemLicense::where('license_key', $validated['license_key'])->first();
            if ($license && !$license->is_active) {
                $durationDays = $license->plan_info['duration_days'] ?? 30;
                $license->update([
                    'is_active' => true,
                    'activated_at' => now(),
                    'expires_at' => now()->addDays($durationDays)
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'License key updated successfully.']);
        }

        return redirect()->route('companies.index')
            ->with('success', 'License key updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        // Check if company has users or employees
        if ($company->users()->count() > 0 || $company->employees()->count() > 0) {
            return redirect()->route('companies.index')
                ->with('error', 'Cannot delete company with associated users or employees.');
        }

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Toggle company active status.
     */
    public function toggle(Company $company)
    {
        $company->update(['is_active' => !$company->is_active]);

        return redirect()->back()
            ->with('success', 'Company status updated successfully.');
    }
}
