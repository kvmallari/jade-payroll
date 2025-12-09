<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\SystemLicense;
use App\Models\Employee;
use App\Models\Company;

class SystemSettingsController extends Controller
{
    /**
     * Display the system settings page.
     * Restricted to System Administrator role only.
     */
    public function index()
    {
        // Allow both System Administrator and Super Admin
        if (!Auth::user()->hasRole(['System Administrator', 'Super Admin'])) {
            abort(403, 'Access denied. This page is only available to System Administrators and Super Admins.');
        }

        // Get current theme preference from session or default to 'light'
        $currentTheme = session('theme', 'light');

        // Get current user's company
        $user = Auth::user();
        $company = $user->company;

        // Get email domain setting
        $emailDomain = \App\Models\Setting::get('email_domain', 'jadepayroll.com');

        // Detect environment based on current URL
        $currentDomain = request()->getHost();
        $environment = $currentDomain === 'localhost' || str_contains($currentDomain, '127.0.0.1')
            ? 'local'
            : $currentDomain;

        // Get company-specific license information (using license_key from companies table)
        $currentLicense = SystemLicense::current(); // This will still work for now
        $employeeCount = Employee::where('company_id', $user->company_id)->count();

        $settings = [
            'appearance' => [
                'theme' => $currentTheme,
                'available_themes' => ['light', 'dark'],
            ],
            'system' => [
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'email_domain' => $emailDomain,
                'environment' => $environment,
            ],
            'notifications' => [
                'email_notifications' => true,
                'browser_notifications' => false,
            ],
        ];

        return view('system-settings.index', compact('settings', 'currentLicense', 'employeeCount', 'company'));
    }

    /**
     * Update system settings.
     */
    public function update(Request $request)
    {
        $request->validate([
            'theme' => 'required|in:light,dark',
            'email_notifications' => 'boolean',
            'browser_notifications' => 'boolean',
        ]);

        // Update theme preference in session
        session(['theme' => $request->theme]);

        // You can add more setting updates here as needed
        // For example, store in database for persistent user preferences

        return redirect()->back()->with('success', 'Settings updated successfully!');
    }

    /**
     * Toggle theme between light and dark.
     */
    public function toggleTheme(Request $request)
    {
        $currentTheme = session('theme', 'light');
        $newTheme = $currentTheme === 'light' ? 'dark' : 'light';

        session(['theme' => $newTheme]);

        return response()->json([
            'success' => true,
            'theme' => $newTheme
        ]);
    }

    /**
     * Update email domain setting.
     */
    public function updateDomain(Request $request)
    {
        $request->validate([
            'email_domain' => 'required|string|max:255',
        ]);

        \App\Models\Setting::set(
            'email_domain',
            $request->email_domain,
            'string',
            'system',
            'Default email domain for user accounts'
        );

        return redirect()->back()->with('success', 'Email domain updated successfully!');
    }
}
