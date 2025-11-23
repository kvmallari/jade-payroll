<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaySchedule;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('edit settings');

        // Build query with company scope
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $query = DB::table('pay_schedules')
            ->where('company_id', $workingCompanyId);

        // FORCE fetch from pay_schedules table directly
        $schedules = collect($query
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get())
            ->map(function ($item) {
                // Convert to object with proper properties
                $obj = new \stdClass();
                foreach ($item as $key => $value) {
                    $obj->$key = $value;
                }
                $obj->cutoff_periods = json_decode($obj->cutoff_periods ?? '[]', true);
                return $obj;
            });

        return view('settings.pay-schedules.index', compact('schedules'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('edit settings');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:daily,weekly,semi_monthly,monthly',
            'is_active' => 'boolean',
            'cutoff_periods' => 'required|array',
            'cutoff_periods.*.start_day' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
            'cutoff_periods.*.end_day' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
            'cutoff_periods.*.pay_date' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
        ]);

        $schedule = PaySchedule::create([
            'company_id' => Auth::user()->getWorkingCompanyId(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'cutoff_periods' => $validated['cutoff_periods'],
            'move_if_holiday' => true,
            'move_if_weekend' => true,
            'move_direction' => 'before',
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => false, // Never set as default via form
            'sort_order' => PaySchedule::where('type', $validated['type'])->max('sort_order') + 1,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('settings.pay-schedules.index')
            ->with('success', 'Pay schedule created successfully. Please configure the cutoff periods.');
    }

    /**
     * Display the specified resource.
     */
    public function show(PaySchedule $paySchedule)
    {
        $this->authorize('view settings');

        return view('settings.pay-schedules.show', compact('paySchedule'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaySchedule $paySchedule)
    {
        $this->authorize('edit settings');

        // Return JSON for AJAX requests
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'schedule' => $paySchedule
            ]);
        }

        return view('settings.pay-schedules.edit', compact('paySchedule'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaySchedule $paySchedule)
    {
        $this->authorize('edit settings');

        $rules = [
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ];

        // Add validation for cutoff_periods array format (from modal)
        if ($request->has('cutoff_periods')) {
            $rules['cutoff_periods'] = 'required|array';
        }

        // Add type-specific validation rules for legacy format (from edit page)
        if (!$request->has('cutoff_periods')) {
            switch ($paySchedule->type) {
                case 'weekly':
                    $rules = array_merge($rules, [
                        'cutoff_start_day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                        'cutoff_end_day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                        'pay_day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
                    ]);
                    break;

                case 'semi_monthly':
                    $rules = array_merge($rules, [
                        // First cutoff period
                        'cutoff_1_start' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'cutoff_1_end' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'pay_date_1' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        // Second cutoff period  
                        'cutoff_2_start' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'cutoff_2_end' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'pay_date_2' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                    ]);
                    break;

                case 'monthly':
                    $rules = array_merge($rules, [
                        'cutoff_start_day' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'cutoff_end_day' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                        'pay_date' => 'required|regex:/^(3[01]|[12][0-9]|[1-9]|EOM)$/',
                    ]);
                    break;
            }
        }

        $validated = $request->validate($rules);

        // Handle cutoff periods based on input format
        $cutoffPeriods = [];

        if ($request->has('cutoff_periods')) {
            // New format from modal
            $cutoffPeriods = $validated['cutoff_periods'];
        } else {
            // Legacy format from edit page
            switch ($paySchedule->type) {
                case 'weekly':
                    $cutoffPeriods = [[
                        'start_day' => $validated['cutoff_start_day'],
                        'end_day' => $validated['cutoff_end_day'],
                        'pay_day' => $validated['pay_day']
                    ]];
                    break;

                case 'semi_monthly':
                    $cutoffPeriods = [
                        [
                            'start_day' => $validated['cutoff_1_start'],
                            'end_day' => $validated['cutoff_1_end'],
                            'pay_date' => $validated['pay_date_1']
                        ],
                        [
                            'start_day' => $validated['cutoff_2_start'],
                            'end_day' => $validated['cutoff_2_end'],
                            'pay_date' => $validated['pay_date_2']
                        ]
                    ];
                    break;

                case 'monthly':
                    $cutoffPeriods = [[
                        'start_day' => $validated['cutoff_start_day'],
                        'end_day' => $validated['cutoff_end_day'],
                        'pay_date' => $validated['pay_date']
                    ]];
                    break;
            }
        }

        $paySchedule->update([
            'name' => $validated['name'],
            'cutoff_periods' => $cutoffPeriods,
            'is_active' => $validated['is_active'] ?? false,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('settings.pay-schedules.index')
            ->with('success', 'Pay schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaySchedule $paySchedule)
    {
        $this->authorize('edit settings');

        try {
            $paySchedule->delete();
            $activeTab = request()->input('active_tab', 'semi_monthly');
            return redirect()->route('settings.pay-schedules.index')
                ->withFragment($activeTab)
                ->with('success', 'Pay schedule deleted successfully.');
        } catch (\Exception $e) {
            $activeTab = request()->input('active_tab', 'semi_monthly');
            return redirect()->route('settings.pay-schedules.index')
                ->withFragment($activeTab)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Create default schedules if none exist
     */
    private function createDefaultSchedules()
    {
        $defaultSchedules = [
            [
                'name' => 'Default Weekly Schedule',
                'type' => 'weekly',
                'description' => 'Standard Monday to Friday weekly schedule',
                'cutoff_periods' => [
                    [
                        'start_day' => 'monday',
                        'end_day' => 'friday',
                        'pay_day' => 'friday'
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Default Semi-Monthly Schedule',
                'type' => 'semi_monthly',
                'description' => 'Standard 1st-15th and 16th-end of month schedule',
                'cutoff_periods' => [
                    [
                        'start_day' => 1,
                        'end_day' => 15,
                        'pay_date' => 16
                    ],
                    [
                        'start_day' => 16,
                        'end_day' => 31,
                        'pay_date' => 5 // 5th of next month
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Default Monthly Schedule',
                'type' => 'monthly',
                'description' => 'Standard full month schedule',
                'cutoff_periods' => [
                    [
                        'start_day' => 1,
                        'end_day' => 31,
                        'pay_date' => 31
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($defaultSchedules as $schedule) {
            PaySchedule::create(array_merge($schedule, [
                'move_if_holiday' => true,
                'move_if_weekend' => true,
                'move_direction' => 'before',
                'is_active' => true,
                'created_by' => Auth::id(),
            ]));
        }
    }

    /**
     * Get default cutoff periods for a schedule type
     */
    private function getDefaultCutoffPeriods($type)
    {
        switch ($type) {
            case 'weekly':
                return [
                    [
                        'start_day' => 'monday',
                        'end_day' => 'friday',
                        'pay_day' => 'friday'
                    ]
                ];

            case 'semi_monthly':
                return [
                    [
                        'start_day' => 1,
                        'end_day' => 15,
                        'pay_date' => 16
                    ],
                    [
                        'start_day' => 16,
                        'end_day' => 31,
                        'pay_date' => 5 // 5th of next month
                    ]
                ];

            case 'monthly':
                return [
                    [
                        'start_day' => 1,
                        'end_day' => 31,
                        'pay_date' => 31
                    ]
                ];

            default:
                return [];
        }
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggle(PaySchedule $paySchedule)
    {
        $this->authorize('edit settings');

        $paySchedule->update([
            'is_active' => !$paySchedule->is_active,
            'updated_by' => Auth::id(),
        ]);

        $status = $paySchedule->is_active ? 'activated' : 'deactivated';
        $activeTab = request()->input('active_tab', 'semi_monthly');
        return back()->withFragment($activeTab)->with('success', "Pay schedule '{$paySchedule->name}' has been {$status}.");
    }
}
