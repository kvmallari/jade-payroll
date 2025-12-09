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
            'code' => 'nullable|string|max:50|unique:companies,code',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Auto-generate code from name if not provided
        if (empty($validated['code'])) {
            $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $validated['name']), 0, 10));
            $code = $baseCode;
            $counter = 1;

            while (Company::where('code', $code)->exists()) {
                $code = $baseCode . $counter;
                $counter++;
            }

            $validated['code'] = $code;
        }

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
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'Invalid license key. The license key must be listed in the system. Run "php artisan license:list" to see available licenses.']);
            }
        }

        $company->update(['license_key' => $validated['license_key']]);

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
