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
        $company = $user->company;

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

        return $next($request);
    }
}
