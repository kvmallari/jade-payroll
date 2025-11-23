<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmployerSetting;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class EmployerSettingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the employer settings form.
     */
    public function index()
    {
        // Only allow Super Admin, System Administrator and HR Head to access employer settings
        if (!Auth::user()->hasRole(['Super Admin', 'System Administrator', 'HR Head'])) {
            abort(403, 'This action is unauthorized.');
        }

        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $settings = EmployerSetting::getSettings($workingCompanyId);

        return view('settings.employer-settings.index', compact('settings'));
    }

    /**
     * Update employer settings.
     */
    public function update(Request $request)
    {
        // Only allow Super Admin, System Administrator and HR Head to update employer settings
        if (!Auth::user()->hasRole(['Super Admin', 'System Administrator', 'HR Head'])) {
            abort(403, 'This action is unauthorized.');
        }

        $validated = $request->validate([
            'registered_business_name' => 'nullable|string|max:255',
            'tax_identification_number' => 'nullable|string|max:50',
            'rdo_code' => 'nullable|string|max:20',
            'sss_employer_number' => 'nullable|string|max:50',
            'philhealth_employer_number' => 'nullable|string|max:50',
            'hdmf_employer_number' => 'nullable|string|max:50',
            'registered_address' => 'nullable|string',
            'postal_zip_code' => 'nullable|string|max:20',
            'landline_mobile' => 'nullable|string|max:50',
            'office_business_email' => 'nullable|email|max:255',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_designation' => 'nullable|string|max:255',
        ]);

        EmployerSetting::updateSettings($validated, Auth::user()->getWorkingCompanyId());

        return redirect()->route('settings.employer.index')
            ->with('success', 'Employer settings updated successfully!');
    }
}
