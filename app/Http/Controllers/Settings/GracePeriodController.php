<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GracePeriodSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class GracePeriodController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display grace period settings
     */
    public function index()
    {
        $this->authorize('edit settings');

        $user = Auth::user();
        $query = GracePeriodSetting::query();

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $gracePeriodSettings = $query->first();

        return response()->json([
            'late_grace_minutes' => $gracePeriodSettings->late_grace_minutes ?? 0,
            'undertime_grace_minutes' => $gracePeriodSettings->undertime_grace_minutes ?? 0,
            // overtime_threshold_minutes removed - now schedule-specific
        ]);
    }

    /**
     * Update grace period settings
     */
    public function update(Request $request)
    {
        $this->authorize('edit settings');

        $request->validate([
            'late_grace_minutes' => 'required|integer|min:0|max:120',
            'undertime_grace_minutes' => 'required|integer|min:0|max:120',
            // overtime_threshold_minutes removed - now schedule-specific
        ]);

        // Update grace period settings in database for current company
        $user = Auth::user();
        $query = GracePeriodSetting::query();

        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        $gracePeriodSetting = $query->first();

        if ($gracePeriodSetting) {
            $gracePeriodSetting->update([
                'late_grace_minutes' => $request->late_grace_minutes,
                'undertime_grace_minutes' => $request->undertime_grace_minutes,
            ]);
        } else {
            $gracePeriodSetting = GracePeriodSetting::create([
                'company_id' => $user->company_id,
                'late_grace_minutes' => $request->late_grace_minutes,
                'undertime_grace_minutes' => $request->undertime_grace_minutes,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => 'Grace period settings updated successfully.',
            'data' => [
                'late_grace_minutes' => $gracePeriodSetting->late_grace_minutes,
                'undertime_grace_minutes' => $gracePeriodSetting->undertime_grace_minutes,
                // overtime_threshold_minutes removed - now schedule-specific
            ]
        ]);
    }
}
