<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCompanyScope;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateLicense;
use App\Http\Middleware\ValidateRoleEmailMatch;
use App\Http\Middleware\CheckCompanyLicense;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Add TrustProxies globally for Cloudflare support
        $middleware->use([
            TrustProxies::class,
        ]);

        // Register Spatie Permission middleware
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'license' => ValidateLicense::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ValidateLicense::class,
            CheckCompanyLicense::class,  // Check company license for non-super admin users
            SetCompanyScope::class,
            ValidateRoleEmailMatch::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle CSRF token mismatch (419 errors) - redirect to login
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            // Get the route name if available
            $routeName = $request->route()?->getName();

            // For login POST with expired token, regenerate session and show login again
            if ($routeName === 'login.store' || ($request->isMethod('post') && str_contains($request->url(), '/login'))) {
                // Clear the old session
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('warning', 'Your session expired. Please login again.');
            }

            // For other POST requests (form submissions with expired tokens), redirect to login
            if ($request->isMethod('post')) {
                return redirect()->route('login')
                    ->with('warning', 'Your session expired. Please login again.');
            }

            // For GET requests to license activation, allow refresh
            if (str_contains($request->url(), '/license/activate')) {
                return redirect()->route('license.activate');
            }

            // All other cases redirect to login
            return redirect()->route('login')
                ->with('warning', 'Your session expired. Please login again.');
        });

        // Redirect unauthorized access to dashboard
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access that page.');
        });
    })->create();
