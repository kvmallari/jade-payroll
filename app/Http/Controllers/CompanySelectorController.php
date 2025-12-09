<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;

class CompanySelectorController extends Controller
{
    /**
     * Set the selected company for Super Admin
     */
    public function setCompany(Request $request)
    {
        $user = Auth::user();

        // Only Super Admin can select companies
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id'
        ]);

        $company = Company::find($validated['company_id']);

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        }

        // Store in session
        session(['selected_company_id' => $company->id]);

        return response()->json([
            'success' => true,
            'message' => 'Company selected successfully',
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
            ]
        ]);
    }

    /**
     * Clear the selected company (revert to user's own company)
     */
    public function clearCompany()
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        session()->forget('selected_company_id');

        return response()->json([
            'success' => true,
            'message' => 'Company selection cleared'
        ]);
    }
}
