<?php

namespace App\Http\Controllers;

use App\Models\SystemLicense;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;


class LicenseController extends Controller
{
    /**
     * Show the license activation page
     */
    public function showActivation(Request $request)
    {
        $user = Auth::user();

        // For company users (not super admin)
        if ($user && !$user->isSuperAdmin()) {
            $company = $user->company;

            if ($company) {
                $isSystemAdmin = $user->isSystemAdmin();
                $hasLicense = !empty($company->license_key);
                $isExpired = false;
                $expiredLicenseKey = null;

                // Check if license is expired
                if ($hasLicense) {
                    $license = \App\Models\SystemLicense::where('license_key', $company->license_key)->first();
                    if ($license && $license->expires_at && $license->expires_at->isPast()) {
                        $isExpired = true;
                        $expiredLicenseKey = $company->license_key;
                    }
                }

                // If already activated and not expired, show success message
                if ($hasLicense && !$isExpired) {
                    return view('license.company-activate', [
                        'company' => $company,
                        'user' => $user,
                        'activationMessage' => 'License already activated.'
                    ]);
                }

                // System admin can activate
                if ($isSystemAdmin) {
                    $message = $isExpired
                        ? 'Your company license has expired. Please enter a new license key below.'
                        : 'Your company requires a license key to access the system. Please enter the license key below.';

                    return view('license.company-activate', [
                        'company' => $company,
                        'user' => $user,
                        'activationMessage' => $message,
                        'canActivate' => true,
                        'expiredLicenseKey' => $expiredLicenseKey,
                        'isExpired' => $isExpired
                    ]);
                }

                // Regular employees cannot activate
                $message = $isExpired
                    ? 'Company license has expired. Please contact your system administrator to activate a new license.'
                    : 'System not activated. Please contact your system administrator to activate the license.';

                return view('license.company-activate', [
                    'company' => $company,
                    'user' => $user,
                    'activationMessage' => $message,
                    'canActivate' => false,
                    'expiredLicenseKey' => $expiredLicenseKey,
                    'isExpired' => $isExpired
                ]);
            }
        }

        // Original system license activation for super admin
        $currentLicense = SystemLicense::current();
        $isUpgrade = $request->has('upgrade') && $request->get('upgrade') == '1';

        if ($isUpgrade && (!$currentLicense || !$currentLicense->isValid())) {
            return redirect()->route('license.activate')
                ->with('error', 'You must have an active license before you can upgrade.');
        }

        return view('license.activate', [
            'currentLicense' => $currentLicense,
            'isUpgrade' => $isUpgrade
        ]);
    }
    /**
     * Activate a license key
     */
    public function activate(Request $request)
    {
        $user = Auth::user();

        // Check if this is company license activation by logged-in user
        if ($user && $user->isSystemAdmin() && !$user->isSuperAdmin()) {
            $request->validate([
                'license_key' => 'required|string|min:10'
            ]);

            // Validate license key exists in system licenses
            if (!LicenseService::isValidLicenseKey($request->license_key)) {
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'Invalid license key. Please contact the super admin for a valid license key.']);
            }

            // Check if license is expired
            $license = \App\Models\SystemLicense::where('license_key', $request->license_key)->first();
            if ($license && $license->expires_at && $license->expires_at->isPast()) {
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'This license key has expired. Please use a valid license key.']);
            }

            // Check if license key is already being used by another company
            $existingCompany = \App\Models\Company::where('license_key', $request->license_key)
                ->where('id', '!=', $user->company_id)
                ->first();

            if ($existingCompany) {
                return back()
                    ->withInput()
                    ->withErrors(['license_key' => 'License key is already in use by "' . $existingCompany->name]);
            }

            $company = $user->company;
            $company->update(['license_key' => $request->license_key]);

            // Mark the license as in-use (set is_active to true and set expires_at)
            $license = \App\Models\SystemLicense::where('license_key', $request->license_key)->first();
            if ($license && !$license->is_active) {
                $durationDays = $license->plan_info['duration_days'] ?? 30;
                $license->update([
                    'is_active' => true,
                    'activated_at' => now(),
                    'expires_at' => now()->addDays($durationDays)
                ]);
            }

            // Refresh the user's company relationship to ensure middleware sees the new license
            $user->refresh();
            $user->load('company');

            return redirect()->route('dashboard')
                ->with('success', 'License activated successfully! You can now access the system.');
        }

        // Original system license activation
        $request->validate([
            'license_key' => 'required|string|min:32'
        ]);

        $result = LicenseService::activateLicense($request->license_key);

        if ($result['success']) {
            return redirect()->route('license.activate')
                ->with('success', 'License activated successfully!');
        }

        return back()
            ->withInput()
            ->withErrors(['license_key' => $result['message']]);
    }
    public function status(): View
    {
        $validation = LicenseService::validateLicense();
        $currentLicense = SystemLicense::current();

        return view('license.status', [
            'validation' => $validation,
            'license' => $currentLicense,
            'employeeCount' => \App\Models\Employee::count()
        ]);
    }

    public function manage(): View
    {
        $currentLicense = SystemLicense::current();
        $allLicenses = SystemLicense::orderBy('created_at', 'desc')->get();

        return view('license.manage', [
            'currentLicense' => $currentLicense,
            'allLicenses' => $allLicenses
        ]);
    }

    public function expired(): View
    {
        return view('license.expired');
    }

    public function invalid(): View
    {
        return view('license.invalid');
    }

    public function limitExceeded(): View
    {
        $license = SystemLicense::current();
        $employeeCount = \App\Models\Employee::count();

        return view('license.limit-exceeded', [
            'license' => $license,
            'employeeCount' => $employeeCount
        ]);
    }

    /**
     * Get license information for API
     */
    public function getLicenseInfo($licenseKey)
    {
        $license = SystemLicense::where('license_key', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'License not found'
            ], 404);
        }

        $planInfo = $license->plan_info ?? [];

        return response()->json([
            'success' => true,
            'license' => [
                'cost' => isset($planInfo['price']) ? '₱' . number_format($planInfo['price'], 2) : 'N/A',
                'activated_at' => $license->activated_at ? $license->activated_at->format('M d, Y - g:i A') : 'Not Activated',
                'expires_at' => $license->expires_at ? $license->expires_at->format('M d, Y - g:i A') : 'N/A',
                'max_employees' => $planInfo['max_employees'] ?? 'N/A',
                'customer' => $planInfo['customer'] ?? 'N/A'
            ]
        ]);
    }
}
