<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class ValidateLicense
{
    /**
     * Routes that should be excluded from license validation
     * ONLY license activation should be accessible without a valid license
     */
    protected $excludedRoutes = [
        'license.activate',
        'license.activate.store',
        'logout',
        'login.store'  // Allow login POST to process even with expired CSRF
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip license validation for excluded routes
        if ($this->shouldSkipValidation($request)) {
            return $next($request);
        }

        // Skip license validation for authenticated users without a company (super admin)
        $user = auth()->user();
        if ($user && !$user->company_id) {
            return $next($request);
        }

        $validation = LicenseService::validateLicense();

        if (!$validation['valid']) {
            // All license failures redirect to activation page
            return redirect()->route('license.activate')
                ->with('error', $validation['reason'] ?? 'Invalid license. Please activate a valid license.');
        }

        // Add license info to view
        view()->share('currentLicense', $validation['license']);

        return $next($request);
    }

    /**
     * Determine if license validation should be skipped for this request
     */
    protected function shouldSkipValidation(Request $request): bool
    {
        $currentRouteName = $request->route()?->getName();

        // Skip if current route is in excluded list
        if (in_array($currentRouteName, $this->excludedRoutes)) {
            return true;
        }

        // Skip if current route is the login route
        if ($currentRouteName === 'login') {
            return true;
        }

        // Skip if URL starts with license paths or auth paths
        $path = $request->path();
        if (
            $path === '/' ||  // Skip root path (login page)
            $path === 'logout' ||  // Skip logout path
            str_starts_with($path, 'license/') ||
            str_starts_with($path, 'login') ||
            str_starts_with($path, 'register') ||
            str_starts_with($path, 'password/') ||
            str_starts_with($path, 'email/verify')
        ) {
            return true;
        }

        return false;
    }
}
