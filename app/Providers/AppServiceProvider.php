<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS URLs when behind proxy (like Cloudflare)
        // if (config('app.env') === 'production' || env('FORCE_HTTPS', false)) {
        //     URL::forceScheme('https');
        // }

        // Trust proxies (for Cloudflare)
        // if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        //     request()->setTrustedProxies(['*'], \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL);
        // }

        // Share company data with all views
        View::composer('*', function ($view) {
            if (Auth::check()) {
                $user = Auth::user();
                $selectedCompanyId = session('selected_company_id');

                // For Super Admin, use selected company or default to user's company
                if ($user->isSuperAdmin()) {
                    $workingCompanyId = $selectedCompanyId ?? $user->company_id;
                    $allCompanies = \App\Models\Company::where('is_active', true)->orderBy('name')->get();
                } else {
                    // For non-super admin, always use their own company
                    $workingCompanyId = $user->company_id;
                    $allCompanies = collect();
                }

                $selectedCompany = $workingCompanyId ? \App\Models\Company::find($workingCompanyId) : null;

                $view->with([
                    'isSuperAdmin' => $user->isSuperAdmin(),
                    'selectedCompanyId' => $workingCompanyId,
                    'selectedCompany' => $selectedCompany,
                    'allCompanies' => $allCompanies,
                ]);
            }
        });
    }
}
