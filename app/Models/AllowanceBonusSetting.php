<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllowanceBonusSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'category',
        'calculation_type',
        'rate_percentage',
        'fixed_amount',
        'multiplier',
        'is_taxable',
        'apply_to_regular_days',
        'apply_to_overtime',
        'apply_to_holidays',
        'apply_to_rest_days',
        'frequency',
        'distribution_method',
        'conditions',
        'minimum_amount',
        'maximum_amount',
        'max_days_per_period',
        'is_active',
        'is_system_default',
        'sort_order',
        'benefit_eligibility',
        'requires_perfect_attendance',
    ];

    protected $casts = [
        'rate_percentage' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'multiplier' => 'decimal:4',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'conditions' => 'array',
        'is_taxable' => 'boolean',
        'apply_to_regular_days' => 'boolean',
        'apply_to_overtime' => 'boolean',
        'apply_to_holidays' => 'boolean',
        'apply_to_rest_days' => 'boolean',
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
        'requires_perfect_attendance' => 'boolean',
    ];

    /**
     * Mutators to handle empty decimal values
     */
    public function setRatePercentageAttribute($value)
    {
        $this->attributes['rate_percentage'] = empty($value) ? null : $value;
    }

    public function setFixedAmountAttribute($value)
    {
        $this->attributes['fixed_amount'] = empty($value) ? null : $value;
    }

    public function setMultiplierAttribute($value)
    {
        $this->attributes['multiplier'] = empty($value) ? null : $value;
    }

    public function setMinimumAmountAttribute($value)
    {
        $this->attributes['minimum_amount'] = empty($value) ? null : $value;
    }

    public function setMaximumAmountAttribute($value)
    {
        $this->attributes['maximum_amount'] = empty($value) ? null : $value;
    }

    /**
     * Scope to get only active allowances/bonuses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get allowances
     */
    public function scopeAllowances($query)
    {
        return $query->where('type', 'allowance');
    }

    /**
     * Scope to get bonuses
     */
    public function scopeBonuses($query)
    {
        return $query->where('type', 'bonus');
    }

    /**
     * Scope to get incentives
     */
    public function scopeIncentives($query)
    {
        return $query->where('type', 'incentives');
    }

    /**
     * Scope to get by frequency
     */
    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Calculate allowance/bonus amount
     */
    public function calculateAmount($basicSalary, $dailyRate = null, $workingDays = null, $employee = null, $breakdownData = null)
    {
        $amount = 0;

        switch ($this->calculation_type) {
            case 'percentage':
                $amount = $basicSalary * ($this->rate_percentage / 100);
                break;

            case 'fixed_amount':
                $amount = $this->fixed_amount;
                break;

            case 'daily_rate_multiplier':
                if ($dailyRate && $workingDays) {
                    $applicableDays = min($workingDays, $this->max_days_per_period ?: $workingDays);
                    $amount = $dailyRate * $this->multiplier * $applicableDays;
                }
                break;

            case 'automatic':
                // 13th month pay calculation: sum all basic pay earned in current year / 12
                if ($employee) {
                    // For 13th month pay calculation, calculate current period fixed amounts from breakdown data
                    $currentFixedPay = null;
                    if ($this->name && (strpos(strtolower($this->name), '13th') !== false || strpos(strtolower($this->name), 'thirteenth') !== false)) {
                        $currentFixedPay = $this->calculateCurrentPeriodFixedPay($breakdownData);

                        Log::info("13th Month calculation logic check", [
                            'employee_id' => $employee->id,
                            'has_breakdown_data' => !empty($breakdownData),
                            'current_fixed_pay' => $currentFixedPay,
                            'breakdown_data_count' => is_array($breakdownData) ? count($breakdownData) : 'not_array',
                            'will_use_current_only' => (!empty($breakdownData) && $currentFixedPay > 0)
                        ]);

                        // For snapshot calculations (when breakdown data is provided), 
                        // return only the current period amount to avoid double-counting
                        if ($breakdownData && !empty($breakdownData) && $currentFixedPay > 0) {
                            $monthlyAmount = round($currentFixedPay / 12, 2);

                            Log::info("Using current period only for 13th month (snapshot mode)", [
                                'employee_id' => $employee->id,
                                'current_period_fixed_pay' => $currentFixedPay,
                                'monthly_amount' => $monthlyAmount,
                                'reason' => 'Snapshot calculation - using current period only'
                            ]);

                            $amount = $monthlyAmount;
                        } else {
                            // For draft calculations (no breakdown data), use full year calculation
                            $amount = $this->calculate13thMonthPay($employee, $currentFixedPay);
                        }
                    } else {
                        $amount = $this->calculate13thMonthPay($employee, $currentFixedPay);
                    }
                }
                break;
        }

        // Apply conditions if any
        if ($this->conditions && $employee) {
            $amount = $this->applyConditions($amount, $employee);
        }

        // Apply minimum and maximum limits
        if ($this->minimum_amount && $amount < $this->minimum_amount) {
            $amount = $this->minimum_amount;
        }

        if ($this->maximum_amount && $amount > $this->maximum_amount) {
            $amount = $this->maximum_amount;
        }

        return round($amount, 2);
    }

    /**
     * Apply conditional rules
     */
    private function applyConditions($amount, $employee)
    {
        if (!$this->conditions || empty($this->conditions)) {
            return $amount;
        }

        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '';
            $value = $condition['value'] ?? '';
            $action = $condition['action'] ?? '';
            $actionValue = $condition['action_value'] ?? 0;

            $employeeValue = data_get($employee, $field);

            $conditionMet = $this->evaluateCondition($employeeValue, $operator, $value);

            if ($conditionMet) {
                switch ($action) {
                    case 'multiply':
                        $amount *= $actionValue;
                        break;
                    case 'add':
                        $amount += $actionValue;
                        break;
                    case 'subtract':
                        $amount -= $actionValue;
                        break;
                    case 'set':
                        $amount = $actionValue;
                        break;
                    case 'percentage':
                        $amount = $amount * ($actionValue / 100);
                        break;
                }
            }
        }

        return $amount;
    }

    /**
     * Evaluate a condition
     */
    private function evaluateCondition($employeeValue, $operator, $conditionValue)
    {
        switch ($operator) {
            case 'equals':
                return $employeeValue == $conditionValue;
            case 'greater_than':
                return $employeeValue > $conditionValue;
            case 'less_than':
                return $employeeValue < $conditionValue;
            case 'contains':
                return str_contains($employeeValue, $conditionValue);
            case 'in':
                return in_array($employeeValue, (array)$conditionValue);
            default:
                return false;
        }
    }

    /**
     * Calculate 13th month pay based on total basic pay earned in current year
     */
    private function calculate13thMonthPay($employee, $includeCurrentBasicPay = null)
    {
        // CRITICAL FIX: For snapshot calculations, if $includeCurrentBasicPay is provided,
        // it represents the current period's fixed pay and should be the ONLY amount used
        // to avoid double-counting with existing payroll records
        if ($includeCurrentBasicPay !== null && $includeCurrentBasicPay > 0) {
            $monthlyAmount = round($includeCurrentBasicPay / 12, 2);

            Log::info("13th Month Pay - Using current period only (snapshot mode)", [
                'employee_id' => $employee->id,
                'current_period_fixed_pay' => $includeCurrentBasicPay,
                'monthly_13th_month_amount' => $monthlyAmount,
                'reason' => 'Snapshot calculation - avoiding historical data double-counting'
            ]);

            return $monthlyAmount;
        }

        // Original logic for draft calculations (when no current period data provided)
        $currentYear = date('Y');

        // Get all payroll details for the employee in the current year
        $payrollDetails = DB::table('payroll_details')
            ->join('payrolls', 'payroll_details.payroll_id', '=', 'payrolls.id')
            ->where('payroll_details.employee_id', $employee->id)
            ->whereYear('payrolls.period_start', $currentYear)
            ->whereIn('payrolls.status', ['approved', 'processing'])
            ->get();

        $totalBasicPay = 0;

        Log::info("13th Month Pay - Processing existing payroll details (draft mode)", [
            'employee_id' => $employee->id,
            'year' => $currentYear,
            'payroll_count' => $payrollDetails->count()
        ]);

        foreach ($payrollDetails as $detail) {
            // FIXED: Include regular_pay for basic pay (includes suspension pay)
            $totalBasicPay += $detail->regular_pay;

            // FIXED: Include holiday_pay for holiday fixed amounts  
            $totalBasicPay += $detail->holiday_pay;

            Log::info("Adding payroll detail to 13th month", [
                'payroll_id' => $detail->payroll_id,
                'regular_pay' => $detail->regular_pay,
                'holiday_pay' => $detail->holiday_pay,
                'running_total' => $totalBasicPay
            ]);
        }

        $monthlyAmount = round($totalBasicPay / 12, 2);

        Log::info("13th Month Pay final calculation (draft mode)", [
            'employee_id' => $employee->id,
            'total_basic_pay_for_year' => $totalBasicPay,
            'monthly_13th_month_amount' => $monthlyAmount
        ]);

        return $monthlyAmount;
    }

    /**
     * Calculate fixed pay amounts from current period breakdown data
     */
    private function calculateCurrentPeriodFixedPay($breakdownData)
    {
        if (!$breakdownData || !is_array($breakdownData)) {
            return 0;
        }

        $fixedPay = 0;



        // Extract fixed amounts from basic breakdown (suspensions)
        if (isset($breakdownData['basic']) && is_array($breakdownData['basic'])) {
            foreach ($breakdownData['basic'] as $type => $data) {
                Log::info("Processing basic type: $type", [
                    'has_fixed_amount' => isset($data['fixed_amount']),
                    'fixed_amount' => $data['fixed_amount'] ?? 'N/A',
                    'total_amount' => $data['amount'] ?? 'N/A',
                    'will_add_fixed' => isset($data['fixed_amount']),
                    'will_add_full' => !in_array($type, ['Paid Suspension', 'Paid Partial Suspension', 'Full Suspension', 'Partial Suspension']) && isset($data['amount'])
                ]);

                if (isset($data['fixed_amount'])) {
                    // Include fixed amounts from suspensions (Full Suspension, Partial Suspension)
                    $fixedPay += $data['fixed_amount'];
                    Log::info("Added fixed amount for $type: {$data['fixed_amount']}, total: $fixedPay");
                } elseif (!in_array($type, ['Paid Suspension', 'Paid Partial Suspension', 'Full Suspension', 'Partial Suspension']) && isset($data['amount'])) {
                    // For regular workdays (no fixed_amount separation), include full amount
                    $fixedPay += $data['amount'];
                    Log::info("Added full amount for $type: {$data['amount']}, total: $fixedPay");
                }
            }
        }

        // Extract fixed amounts from holiday breakdown 
        if (isset($breakdownData['holiday']) && is_array($breakdownData['holiday'])) {
            foreach ($breakdownData['holiday'] as $type => $data) {
                Log::info("Processing holiday type: $type", [
                    'has_fixed_amount' => isset($data['fixed_amount']),
                    'fixed_amount' => $data['fixed_amount'] ?? 'N/A',
                    'total_amount' => $data['amount'] ?? 'N/A'
                ]);

                if (isset($data['fixed_amount'])) {
                    // Include only fixed amounts from holiday pay (Special Holiday, Regular Holiday)
                    $fixedPay += $data['fixed_amount'];
                    Log::info("Added holiday fixed amount for $type: {$data['fixed_amount']}, total: $fixedPay");
                }
            }
        }

        Log::info("13th Month Final Calculation", [
            'total_fixed_pay' => $fixedPay,
            'expected_total' => 2780.0,
            'monthly_13th' => $fixedPay / 12
        ]);

        return $fixedPay;
    }

    /**
     * Check if this setting applies to the given employee based on their benefit status
     */
    public function appliesTo($employee)
    {
        if ($this->benefit_eligibility === 'both') {
            return true;
        }

        return $this->benefit_eligibility === $employee->benefits_status;
    }

    /**
     * Check if employee has perfect attendance for a given payroll period
     * Perfect attendance means: worked the expected number of days with complete time compliance
     * FLEXIBLE: Allows rest day swapping - counts total workdays, not specific scheduled days
     */
    public function hasPerfectAttendance($employee, $periodStart, $periodEnd)
    {
        // Convert dates to Carbon instances
        $startDate = \Carbon\Carbon::parse($periodStart);
        $endDate = \Carbon\Carbon::parse($periodEnd);

        // Get employee's schedules
        $daySchedule = $employee->daySchedule;
        $timeSchedule = $employee->timeSchedule;

        if (!$daySchedule || !$timeSchedule) {
            return false; // Can't determine perfect attendance without schedules
        }

        // STEP 1: Calculate expected workdays based on day schedule
        $expectedWorkdays = $this->calculateExpectedWorkdays($daySchedule, $startDate, $endDate);

        if ($expectedWorkdays === 0) {
            return false; // No work expected in this period
        }

        // STEP 2: Get all actual time logs in the period (regardless of original day schedule)
        $actualWorkLogs = \App\Models\TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->get();

        // STEP 3: Filter logs to only include SCHEDULED workdays (exclude rest day work)
        // Perfect attendance means working ALL scheduled days, not extra rest day work
        $scheduledWorkLogs = $actualWorkLogs->filter(function ($timeLog) use ($employee) {
            return $this->isScheduledWorkday($timeLog, $employee);
        });

        // STEP 4: Check if scheduled workdays count matches expected workdays exactly
        // Must work ALL scheduled days - no substitutions allowed
        if ($scheduledWorkLogs->count() !== $expectedWorkdays) {
            return false; // Missing scheduled workdays or somehow worked more than expected scheduled days
        }

        // STEP 5: Ensure all scheduled workdays have perfect time compliance
        foreach ($scheduledWorkLogs as $timeLog) {
            if (!$this->hasCompleteDayAttendance($employee, $timeLog, $timeSchedule)) {
                return false; // One of the scheduled workdays has time issues
            }
        }

        return true; // Perfect attendance achieved!
    }

    /**
     * Calculate expected number of workdays in a period based on day schedule
     */
    private function calculateExpectedWorkdays($daySchedule, $startDate, $endDate)
    {
        $expectedWorkdays = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayOfWeek = strtolower($date->format('l'));

            // Count this day if it's a scheduled workday
            if ($daySchedule->$dayOfWeek) {
                $expectedWorkdays++;
            }
        }

        return $expectedWorkdays;
    }

    /**
     * Check if a time log represents a scheduled workday (not rest day work)
     * Only Regular Workday, Regular Holiday, and Special Holiday count toward perfect attendance
     * Rest day work is bonus work and doesn't count toward attendance requirements
     */
    private function isScheduledWorkday($timeLog, $employee)
    {
        // Must have both time in and time out
        if (!$timeLog->time_in || !$timeLog->time_out) {
            return false;
        }

        // Check log_type - only scheduled workdays count toward perfect attendance
        // Rest day work (any variant) should NOT count toward perfect attendance
        $allowedLogTypes = [
            'regular_workday',
            'regular_holiday',
            'special_holiday'
        ];

        if (!in_array($timeLog->log_type, $allowedLogTypes)) {
            // This is rest day work or other non-scheduled work - doesn't count toward perfect attendance
            return false;
        }

        // Verify sufficient work hours for the scheduled workday
        $timeInTime = \Carbon\Carbon::parse($timeLog->time_in)->format('H:i:s');
        $timeOutTime = \Carbon\Carbon::parse($timeLog->time_out)->format('H:i:s');
        $logDate = \Carbon\Carbon::parse($timeLog->log_date)->format('Y-m-d');

        $timeIn = \Carbon\Carbon::parse($logDate . ' ' . $timeInTime);
        $timeOut = \Carbon\Carbon::parse($logDate . ' ' . $timeOutTime);

        // Handle overnight shifts
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        // Calculate worked duration
        $workedHours = $timeIn->diffInHours($timeOut);

        // Must work at least 4 hours to count as a valid scheduled workday
        if ($workedHours < 4) {
            return false;
        }

        return true; // This is a valid scheduled workday
    }

    /**
     * Check if a time log represents a valid workday (not a rest day)
     * A valid workday must have both time_in and time_out with reasonable duration
     */
    private function isValidWorkday($timeLog, $timeSchedule)
    {
        // Must have both time in and time out
        if (!$timeLog->time_in || !$timeLog->time_out) {
            return false;
        }

        // Extract time parts from datetime fields and combine with log date
        $timeInTime = \Carbon\Carbon::parse($timeLog->time_in)->format('H:i:s');
        $timeOutTime = \Carbon\Carbon::parse($timeLog->time_out)->format('H:i:s');
        $logDate = \Carbon\Carbon::parse($timeLog->log_date)->format('Y-m-d');

        // Create proper datetime using log_date + time
        $timeIn = \Carbon\Carbon::parse($logDate . ' ' . $timeInTime);
        $timeOut = \Carbon\Carbon::parse($logDate . ' ' . $timeOutTime);

        // Handle overnight shifts
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        // Calculate worked duration (end - start)
        $workedHours = $timeIn->diffInHours($timeOut);

        // A valid workday should have at least 4 hours of work (reasonable minimum)
        // This prevents counting brief appearances as full workdays
        if ($workedHours < 4) {
            return false;
        }

        return true; // This is a valid workday
    }

    /**
     * Check if employee has complete attendance for a specific time log
     * Only applies strict time compliance to SCHEDULED workdays
     * Rest day work doesn't need time compliance (already filtered out in perfect attendance check)
     */
    private function hasCompleteDayAttendance($employee, $timeLog, $timeSchedule)
    {
        // Check time in/out completeness
        if (!$timeLog->time_in || !$timeLog->time_out) {
            return false; // Incomplete time logs
        }

        // For scheduled workdays, apply strict time compliance
        // Parse scheduled and actual times
        $scheduledTimeIn = \Carbon\Carbon::parse($timeSchedule->time_in);
        $scheduledTimeOut = \Carbon\Carbon::parse($timeSchedule->time_out);

        // Parse actual times from TimeLog - extract time part only for comparison
        $actualTimeIn = \Carbon\Carbon::parse($timeLog->time_in);
        $actualTimeOut = \Carbon\Carbon::parse($timeLog->time_out);

        // Check for late arrival (no grace period - must be exactly on time or early)
        if ($actualTimeIn->format('H:i:s') > $scheduledTimeIn->format('H:i:s')) {
            return false; // Late arrival
        }

        // Check for early departure (must complete scheduled hours)
        if ($actualTimeOut->format('H:i:s') < $scheduledTimeOut->format('H:i:s')) {
            return false; // Early departure
        }

        return true; // Passed all checks
    }
    /**
     * Calculate the distributed allowance/bonus amount for a specific payroll period based on frequency and distribution method
     */
    public function calculateDistributedAmount($originalAmount, $payrollStart, $payrollEnd, $employeePaySchedule = null)
    {
        // If frequency is per_payroll, always return the full amount
        if ($this->frequency === 'per_payroll') {
            return $originalAmount;
        }

        // Parse the payroll period dates
        $periodStart = \Carbon\Carbon::parse($payrollStart);
        $periodEnd = \Carbon\Carbon::parse($payrollEnd);

        // Apply frequency and distribution logic
        switch ($this->frequency) {
            case 'monthly':
                return $this->calculateMonthlyDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule);
            case 'quarterly':
                return $this->calculateQuarterlyDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule);
            case 'annually':
                return $this->calculateAnnualDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule);
            default:
                return $originalAmount;
        }
    }

    /**
     * Calculate monthly distribution based on distribution method
     */
    private function calculateMonthlyDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule)
    {
        switch ($this->distribution_method) {
            case 'first_payroll':
                return $this->isFirstPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'last_payroll':
                return $this->isLastPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'equally_distributed':
                $payrollsInMonth = $this->getPayrollCountInMonth($periodStart, $employeePaySchedule);
                return $payrollsInMonth > 0 ? round($originalAmount / $payrollsInMonth, 2) : $originalAmount;

            default:
                return $originalAmount;
        }
    }

    /**
     * Calculate quarterly distribution based on distribution method
     */
    private function calculateQuarterlyDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule)
    {
        switch ($this->distribution_method) {
            case 'first_payroll':
                return $this->isFirstPayrollOfQuarter($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'last_payroll':
                return $this->isLastPayrollOfQuarter($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'equally_distributed':
                $payrollsInQuarter = $this->getPayrollCountInQuarter($periodStart, $employeePaySchedule);
                return $payrollsInQuarter > 0 ? round($originalAmount / $payrollsInQuarter, 2) : $originalAmount;

            default:
                return $originalAmount;
        }
    }

    /**
     * Calculate annual distribution based on distribution method
     */
    private function calculateAnnualDistribution($originalAmount, $periodStart, $periodEnd, $employeePaySchedule)
    {
        switch ($this->distribution_method) {
            case 'first_payroll':
                return $this->isFirstPayrollOfYear($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'last_payroll':
                return $this->isLastPayrollOfYear($periodStart, $periodEnd, $employeePaySchedule) ? $originalAmount : 0;

            case 'equally_distributed':
                $payrollsInYear = $this->getPayrollCountInYear($periodStart, $employeePaySchedule);
                return $payrollsInYear > 0 ? round($originalAmount / $payrollsInYear, 2) : $originalAmount;

            default:
                return $originalAmount;
        }
    }

    /**
     * Check if the given period is the first payroll of the month
     */
    private function isFirstPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule)
    {
        // For semi-monthly: first payroll typically covers 1st to 15th
        if (in_array($employeePaySchedule, ['semi_monthly', 'semi-monthly'])) {
            return $periodStart->day <= 15;
        }

        // For weekly: check if this is the first week of the month
        if ($employeePaySchedule === 'weekly') {
            return $periodStart->day <= 7;
        }

        // For daily: check if this is the first day of the month
        if ($employeePaySchedule === 'daily') {
            return $periodStart->day === 1;
        }

        // For monthly: always true
        return true;
    }

    /**
     * Check if the given period is the last payroll of the month
     */
    private function isLastPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule)
    {
        $monthEnd = $periodStart->copy()->endOfMonth();

        // For semi-monthly: last payroll typically covers 16th to end of month
        if (in_array($employeePaySchedule, ['semi_monthly', 'semi-monthly'])) {
            return $periodStart->day > 15;
        }

        // For weekly: check if this period ends near month end
        if ($employeePaySchedule === 'weekly') {
            return $periodEnd->day >= $monthEnd->day - 7;
        }

        // For daily: check if this is the last day of the month
        if ($employeePaySchedule === 'daily') {
            return $periodEnd->day === $monthEnd->day;
        }

        // For monthly: always true
        return true;
    }

    /**
     * Check if the given period is the first payroll of the quarter
     */
    private function isFirstPayrollOfQuarter($periodStart, $periodEnd, $employeePaySchedule)
    {
        $quarterStart = $periodStart->copy()->firstOfQuarter();

        // Check if this payroll period is in the first month and first payroll of that month
        if ($periodStart->month === $quarterStart->month) {
            return $this->isFirstPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule);
        }

        return false;
    }

    /**
     * Check if the given period is the last payroll of the quarter
     */
    private function isLastPayrollOfQuarter($periodStart, $periodEnd, $employeePaySchedule)
    {
        $quarterEnd = $periodStart->copy()->lastOfQuarter();

        // Check if this payroll period is in the last month and last payroll of that month
        if ($periodStart->month === $quarterEnd->month) {
            return $this->isLastPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule);
        }

        return false;
    }

    /**
     * Check if the given period is the first payroll of the year
     */
    private function isFirstPayrollOfYear($periodStart, $periodEnd, $employeePaySchedule)
    {
        // Check if this is January and first payroll of January
        if ($periodStart->month === 1) {
            return $this->isFirstPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule);
        }

        return false;
    }

    /**
     * Check if the given period is the last payroll of the year
     */
    private function isLastPayrollOfYear($periodStart, $periodEnd, $employeePaySchedule)
    {
        // Check if this is December and last payroll of December
        if ($periodStart->month === 12) {
            return $this->isLastPayrollOfMonth($periodStart, $periodEnd, $employeePaySchedule);
        }

        return false;
    }

    /**
     * Get the estimated number of payrolls in a month for a given pay schedule
     */
    private function getPayrollCountInMonth($date, $paySchedule)
    {
        switch ($paySchedule) {
            case 'semi_monthly':
            case 'semi-monthly':
                return 2;
            case 'weekly':
                return 4; // Approximate
            case 'daily':
                return $date->daysInMonth; // Working days in month
            case 'monthly':
            default:
                return 1;
        }
    }

    /**
     * Get the estimated number of payrolls in a quarter for a given pay schedule
     */
    private function getPayrollCountInQuarter($date, $paySchedule)
    {
        switch ($paySchedule) {
            case 'semi_monthly':
            case 'semi-monthly':
                return 6; // 3 months × 2 payrolls
            case 'weekly':
                return 13; // Approximate: 3 months × 4.33 weeks
            case 'daily':
                return 65; // Approximate: 3 months × ~22 working days
            case 'monthly':
            default:
                return 3;
        }
    }

    /**
     * Get the estimated number of payrolls in a year for a given pay schedule
     */
    private function getPayrollCountInYear($date, $paySchedule)
    {
        switch ($paySchedule) {
            case 'semi_monthly':
            case 'semi-monthly':
                return 24; // 12 months × 2 payrolls
            case 'weekly':
                return 52; // 52 weeks in a year
            case 'daily':
                return 260; // Approximate: 52 weeks × 5 working days
            case 'monthly':
            default:
                return 12;
        }
    }

    /**
     * Scope to filter settings by benefit eligibility
     */
    public function scopeForBenefitStatus($query, $benefitStatus)
    {
        return $query->where(function ($q) use ($benefitStatus) {
            $q->where('benefit_eligibility', 'both')
                ->orWhere('benefit_eligibility', $benefitStatus);
        });
    }
}
