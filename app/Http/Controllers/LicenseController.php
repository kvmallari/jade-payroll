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

                // If already activated, show success message
                if ($hasLicense) {
                    return view('license.company-activate', [
                        'company' => $company,
                        'user' => $user,
                        'activationMessage' => 'License already activated.'
                    ]);
                }

                // System admin can activate
                if ($isSystemAdmin) {
                    return view('license.company-activate', [
                        'company' => $company,
                        'user' => $user,
                        'activationMessage' => 'Your company requires a license key to access the system. Please enter the license key below.',
                        'canActivate' => true
                    ]);
                }

                // Regular employees cannot activate
                return view('license.company-activate', [
                    'company' => $company,
                    'user' => $user,
                    'activationMessage' => 'System not activated. Please contact your system administrator to activate the license.',
                    'canActivate' => false
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

            $company = $user->company;
            $company->update(['license_key' => $request->license_key]);

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
}
