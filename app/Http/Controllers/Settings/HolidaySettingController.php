<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HolidaySettingController extends Controller
{
    public function index()
    {
        $currentYear = date('Y');
        $workingCompanyId = Auth::user()->getWorkingCompanyId();

        // Build base query with company scope
        $query = Holiday::where('year', $currentYear)
            ->where('company_id', $workingCompanyId);

        // Get holidays with custom ordering: Regular first, then Special, each sorted by date desc
        $holidays = collect();

        // Get Regular Holidays first (ordered from Dec to Jan)
        $regularHolidays = (clone $query)
            ->where('type', 'regular')
            ->orderBy('date', 'desc')
            ->get();

        if ($regularHolidays->count() > 0) {
            $holidays->put('regular', $regularHolidays);
        }

        // Get Special Holidays second (ordered from Dec to Jan)
        $specialHolidays = (clone $query)
            ->whereIn('type', ['special', 'special_non_working'])
            ->orderBy('date', 'desc')
            ->get();

        if ($specialHolidays->count() > 0) {
            // Group them under a single key for display
            $holidays->put('special_non_working', $specialHolidays);
        }

        // Get years scoped to company
        $workingCompanyId = Auth::user()->getWorkingCompanyId();
        $years = Holiday::selectRaw('DISTINCT year')
            ->where('company_id', $workingCompanyId)
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('settings.holidays.index', compact('holidays', 'years', 'currentYear'));
    }

    public function create()
    {
        return view('settings.holidays.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:regular,special_non_working',
            'is_paid' => 'boolean',
            'pay_applicable_to' => 'nullable|in:all,with_benefits,without_benefits',
            'pay_rule' => 'nullable|in:full,half',
        ]);

        // Auto-extract year from date
        $validated['year'] = date('Y', strtotime($validated['date']));

        // Handle checkbox value
        $validated['is_paid'] = $request->has('is_paid') && $request->is_paid;

        // Set pay_applicable_to to null if not paid, or default to 'all' if paid but not specified
        if (!$validated['is_paid']) {
            $validated['pay_applicable_to'] = null;
        } elseif (empty($validated['pay_applicable_to'])) {
            $validated['pay_applicable_to'] = 'all';
        }

        // Handle pay_rule field
        if (!$validated['is_paid']) {
            $validated['pay_rule'] = null;
        } elseif ($validated['is_paid'] && empty($validated['pay_rule'])) {
            $validated['pay_rule'] = 'full';
        }

        // Auto-assign company_id
        $validated['company_id'] = Auth::user()->getWorkingCompanyId();

        // Check for duplicates within the same company (same date only)
        $existingHoliday = Holiday::where('date', $validated['date'])
            ->where('company_id', $validated['company_id'])
            ->first();

        if ($existingHoliday) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'date' => 'A holiday already exists on this date. Each date can only have one holiday.'
                ]);
        }

        Holiday::create($validated);

        return redirect()->route('settings.holidays.index')
            ->with('success', 'Holiday created successfully.');
    }

    public function show(Holiday $holiday)
    {
        return view('settings.holidays.show', compact('holiday'));
    }

    public function edit(Holiday $holiday)
    {
        return view('settings.holidays.edit', compact('holiday'));
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'type' => 'required|in:regular,special_non_working',
            'is_paid' => 'boolean',
            'pay_applicable_to' => 'nullable|in:all,with_benefits,without_benefits',
            'pay_rule' => 'nullable|in:full,half',
        ]);

        // Auto-extract year from date
        $validated['year'] = date('Y', strtotime($validated['date']));

        // Handle checkbox value
        $validated['is_paid'] = $request->has('is_paid') && $request->is_paid;

        // Set pay_applicable_to to null if not paid, or default to 'all' if paid but not specified
        if (!$validated['is_paid']) {
            $validated['pay_applicable_to'] = null;
        } elseif (empty($validated['pay_applicable_to'])) {
            $validated['pay_applicable_to'] = 'all';
        }

        // Handle pay_rule field
        if (!$validated['is_paid']) {
            $validated['pay_rule'] = null;
        } elseif ($validated['is_paid'] && empty($validated['pay_rule'])) {
            $validated['pay_rule'] = 'full';
        }

        // Check for duplicates (same date only - each date can only have one holiday) excluding current holiday
        $existingHoliday = Holiday::where('date', $validated['date'])
            ->where('id', '!=', $holiday->id)
            ->first();

        if ($existingHoliday) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'date' => 'A holiday already exists on this date. Each date can only have one holiday.'
                ]);
        }

        $holiday->update($validated);

        return redirect()->route('settings.holidays.index')
            ->with('success', 'Holiday updated successfully.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return redirect()->route('settings.holidays.index')
            ->with('success', 'Holiday deleted successfully.');
    }

    public function toggle(Holiday $holiday)
    {
        $holiday->update([
            'is_active' => !$holiday->is_active
        ]);

        return back()->with('success', 'Holiday status updated.');
    }

    public function filterByYear(Request $request)
    {
        $year = $request->year ?? date('Y');

        // Get holidays with custom ordering: Regular first, then Special, each sorted by date desc
        $holidays = collect();

        // Get Regular Holidays first (ordered from Dec to Jan)
        $regularHolidays = Holiday::where('year', $year)
            ->where('type', 'regular')
            ->orderBy('date', 'desc')
            ->get();

        if ($regularHolidays->count() > 0) {
            $holidays->put('regular', $regularHolidays);
        }

        // Get Special Holidays second (ordered from Dec to Jan)
        $specialHolidays = Holiday::where('year', $year)
            ->whereIn('type', ['special', 'special_non_working'])
            ->orderBy('date', 'desc')
            ->get();

        if ($specialHolidays->count() > 0) {
            // Group them under a single key for display
            $holidays->put('special_non_working', $specialHolidays);
        }

        return response()->json([
            'holidays' => $holidays
        ]);
    }

    public function generateRecurring(Request $request)
    {
        $targetYear = $request->validate(['year' => 'required|integer|min:2020|max:2030'])['year'];

        $recurringHolidays = Holiday::where('is_recurring', true)
            ->where('year', $targetYear - 1)
            ->get();

        $created = 0;
        foreach ($recurringHolidays as $holiday) {
            $newDate = date('Y-m-d', strtotime(str_replace($targetYear - 1, $targetYear, $holiday->date)));

            $exists = Holiday::where('date', $newDate)
                ->where('year', $targetYear)
                ->exists();

            if (!$exists) {
                Holiday::create([
                    'name' => $holiday->name,
                    'date' => $newDate,
                    'type' => $holiday->type,
                    'rate_multiplier' => $holiday->rate_multiplier,
                    'is_double_pay' => $holiday->is_double_pay,
                    'double_pay_rate' => $holiday->double_pay_rate,
                    'pay_rule' => $holiday->pay_rule,
                    'description' => $holiday->description,
                    'is_recurring' => $holiday->is_recurring,
                    'is_active' => $holiday->is_active,
                    'year' => $targetYear,
                ]);
                $created++;
            }
        }

        return back()->with('success', "Generated {$created} recurring holidays for {$targetYear}.");
    }
}
