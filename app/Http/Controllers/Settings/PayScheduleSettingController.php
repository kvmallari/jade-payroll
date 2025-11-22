<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PayScheduleSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayScheduleSettingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $schedules = PayScheduleSetting::query()
            ->when(!$user->isSuperAdmin(), function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            })
            ->get();

        return view('settings.pay-schedules.index', compact('schedules'));
    }

    public function show(PayScheduleSetting $paySchedule)
    {
        return view('settings.pay-schedules.show', compact('paySchedule'));
    }

    public function edit(PayScheduleSetting $paySchedule)
    {
        // Only allow editing system defaults
        if (!$paySchedule->is_system_default) {
            abort(404);
        }

        return view('settings.pay-schedules.edit', compact('paySchedule'));
    }

    public function update(Request $request, PayScheduleSetting $paySchedule)
    {
        // Only allow editing system defaults
        if (!$paySchedule->is_system_default) {
            abort(404);
        }

        $rules = [
            'is_active' => 'boolean',
            'move_if_holiday' => 'boolean',
            'move_if_weekend' => 'boolean',
            'move_direction' => 'required|in:before,after',
        ];

        // Type-specific validation based on pay schedule type
        switch ($paySchedule->code) {
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
                    'cutoff_1_start' => 'required|integer|between:1,31',
                    'cutoff_1_end' => 'required|integer|between:1,31',
                    'pay_date_1' => 'required|integer|between:1,31',
                    // Second cutoff period  
                    'cutoff_2_start' => 'required|integer|between:1,31',
                    'cutoff_2_end' => 'required|integer|between:1,31',
                    'pay_date_2' => 'required|integer|between:1,31',
                ]);
                break;

            case 'monthly':
                $rules = array_merge($rules, [
                    'cutoff_start_day' => 'required|integer|between:1,31',
                    'cutoff_end_day' => 'required|integer|between:1,31',
                    'pay_date' => 'required|integer|between:1,31',
                ]);
                break;
        }

        $validated = $request->validate($rules);

        // Structure the cutoff periods based on schedule type
        $cutoffPeriods = [];

        switch ($paySchedule->code) {
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

        $paySchedule->update([
            'cutoff_periods' => $cutoffPeriods,
            'move_if_holiday' => $validated['move_if_holiday'] ?? false,
            'move_if_weekend' => $validated['move_if_weekend'] ?? false,
            'move_direction' => $validated['move_direction'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('settings.pay-schedules.index')
            ->with('success', 'Pay schedule updated successfully.');
    }

    public function destroy(PayScheduleSetting $paySchedule)
    {
        // System defaults cannot be deleted
        return back()->with('error', 'System default pay schedules cannot be deleted.');
    }

    public function toggle(PayScheduleSetting $paySchedule)
    {
        $paySchedule->update([
            'is_active' => !$paySchedule->is_active
        ]);

        return back()->with('success', 'Pay schedule status updated.');
    }
}
