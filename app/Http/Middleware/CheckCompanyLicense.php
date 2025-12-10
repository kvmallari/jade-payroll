<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckCompanyLicense
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip license check for unauthenticated users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        // $user = auth()->user();

        // Super admin bypasses all license checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // All other users must have a company with a valid license
        // Refresh the company relationship to get the latest data from database
        $company = $user->company()->first();

        // Check if company exists and has a license key
        if (!$company || empty($company->license_key)) {
            // Allow access to license activation routes and logout
            if (
                $request->routeIs('license.activate') ||
                $request->routeIs('license.activate.store') ||
                $request->routeIs('logout')
            ) {
                return $next($request);
            }

            // Redirect to license activation page
            return redirect()->route('license.activate')
                ->with('warning', 'Please activate your company license to access the system.');
        }

        // Validate license exists in system_licenses table
        $license = \App\Models\SystemLicense::where('license_key', $company->license_key)->first();

        if (!$license) {
            // License doesn't exist in system - clear it from company
            $company->update(['license_key' => null]);

            // Allow access to logout
            if ($request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('license.activate')
                ->with('error', 'Company license is invalid or has been deleted. Please activate a new license.');
        }

        // Check if license is expired
        if ($license->expires_at && $license->expires_at->isPast()) {
            // Allow access to license activation routes and logout
            if (
                $request->routeIs('license.activate') ||
                $request->routeIs('license.activate.store') ||
                $request->routeIs('logout')
            ) {
                return $next($request);
            }

            return redirect()->route('license.activate')
                ->with('warning', 'Your company license has expired. Please activate a new license key.');
        }

        return $next($request);
    }
}
