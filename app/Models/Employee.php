<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Employee extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date',
        'gender',
        'civil_status',
        'phone',
        'address',
        'postal_code',
        'hire_date',
        'paid_leaves',
        'benefits_status',
        'employment_type',
        'employment_type_id',
        'employment_status',
        'pay_schedule',
        'pay_schedule_id',
        'time_schedule_id',
        'day_schedule_id',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'rate_type',
        'fixed_rate',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'paid_leaves' => 'integer',
        'fixed_rate' => 'decimal:2',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'employee_number';
    }

    /**
     * Get the value of the model's route key.
     */
    public function getRouteKey()
    {
        return strtolower($this->getAttribute($this->getRouteKeyName()));
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), strtoupper($value))->first();
    }

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'employment_status', 'fixed_rate'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user associated with the employee.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department that the employee belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position of the employee.
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the time schedule of the employee.
     */
    public function timeSchedule()
    {
        return $this->belongsTo(TimeSchedule::class);
    }

    /**
     * Get the day schedule of the employee.
     */
    public function daySchedule()
    {
        return $this->belongsTo(DaySchedule::class);
    }

    /**
     * Get the employment type of the employee.
     */
    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * Get the pay schedule of the employee (new multiple schedules system)
     */
    public function paySchedule()
    {
        return $this->belongsTo(PaySchedule::class, 'pay_schedule_id');
    }

    /**
     * Get the complete schedule display for the employee.
     */
    public function getScheduleDisplayAttribute()
    {
        $daySchedule = $this->daySchedule;
        $timeSchedule = $this->timeSchedule;

        if (!$daySchedule || !$timeSchedule) {
            return 'No schedule assigned';
        }

        return $daySchedule->days_display . ' | ' . $timeSchedule->time_range_display;
    }

    /**
     * Get the time logs for the employee.
     */
    public function timeLogs()
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the DTR records for the employee.
     */
    public function dtrRecords()
    {
        return $this->hasMany(\App\Models\DTRRecord::class);
    }

    /**
     * Get the payroll details for the employee.
     */
    public function payrollDetails()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    /**
     * Get the deductions for the employee.
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }




    /**
     * Get the work schedules for the employee.
     */
    public function workSchedules()
    {
        return $this->belongsToMany(WorkSchedule::class, 'employee_work_schedules')
            ->withPivot('effective_date', 'end_date', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get the current work schedule for the employee.
     */
    public function currentWorkSchedule()
    {
        return $this->workSchedules()
            ->wherePivot('is_active', true)
            ->wherePivot('effective_date', '<=', now())
            ->where(function ($query) {
                $query->wherePivotNull('end_date')
                    ->orWherePivot('end_date', '>=', now());
            })
            ->latest('pivot_effective_date')
            ->first();
    }

    /**
     * Get the employee's full name.
     */
    public function getFullNameAttribute()
    {
        $name = trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
        return $this->suffix ? "{$name} {$this->suffix}" : $name;
    }

    /**
     * Get the employee's display name.
     */
    public function getDisplayNameAttribute()
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    /**
     * Check if employee is active.
     */
    public function isActive()
    {
        return $this->employment_status === 'active';
    }

    /**
     * Check if employee is regular.
     */
    public function isRegular()
    {
        return $this->employment_type === 'regular';
    }

    /**
     * Calculate daily rate from basic salary.
     */
    public function calculateDailyRate()
    {
        if ($this->daily_rate) {
            return $this->daily_rate;
        }

        // Calculate based on 22 working days per month
        return $this->basic_salary / 22;
    }

    /**
     * Calculate hourly rate from basic salary.
     */
    public function calculateHourlyRate()
    {
        if ($this->hourly_rate) {
            return $this->hourly_rate;
        }

        // Calculate based on 8 hours per day, 22 working days per month
        return $this->basic_salary / (22 * 8);
    }

    /**
     * Scope to filter active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }

    /**
     * Scope to filter by department.
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to filter by employment type.
     */
    public function scopeByEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    /**
     * Get time logs for current month.
     */
    public function thisMonthTimeLogs()
    {
        return $this->timeLogs()->whereMonth('log_date', now()->month)->whereYear('log_date', now()->year);
    }

    /**
     * Get employee's age.
     */
    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    /**
     * Get years of service.
     */
    public function getYearsOfServiceAttribute()
    {
        if (!$this->hire_date) {
            return '0 Days';
        }

        $hireDate = $this->hire_date;
        $currentDate = now();

        // Calculate total years (cast to integer)
        $years = (int) $hireDate->diffInYears($currentDate);

        // Calculate remaining months after years (cast to integer)
        $afterYears = $hireDate->copy()->addYears($years);
        $months = (int) $afterYears->diffInMonths($currentDate);

        // Calculate remaining days after years and months (cast to integer)  
        $afterMonths = $afterYears->copy()->addMonths($months);
        $days = (int) $afterMonths->diffInDays($currentDate);

        if ($years >= 1) {
            // 1 year or more: "X years, Y months"
            if ($months == 0) {
                return $years . ' Year' . ($years != 1 ? 's' : '');
            } else {
                return $years . ' Year' . ($years != 1 ? 's' : '') . ', ' . $months . ' Month' . ($months != 1 ? 's' : '');
            }
        } elseif ($months >= 1) {
            // Less than 1 year: "X months, Y days"
            if ($days == 0) {
                return $months . ' Month' . ($months != 1 ? 's' : '');
            } else {
                return $months . ' Month' . ($months != 1 ? 's' : '') . ', ' . $days . ' Day' . ($days != 1 ? 's' : '');
            }
        } else {
            // Less than 1 month: "X days"
            return $days . ' Day' . ($days != 1 ? 's' : '');
        }
    }

    /**
     * Get activities for this employee.
     */
    public function activities()
    {
        return $this->morphMany(\Spatie\Activitylog\Models\Activity::class, 'subject');
    }

    /**
     * Get working days per week based on day schedule
     */
    public function getWorkingDaysPerWeek()
    {
        return match ($this->day_schedule) {
            'monday_friday' => 5,
            'monday_saturday', 'tuesday_saturday' => 6,
            'monday_sunday' => 7,
            'sunday_thursday' => 5,
            'custom' => 5, // Default for custom, should be configured elsewhere
            default => 5
        };
    }

    /**
     * Get human-readable day schedule
     */
    public function getDayScheduleDisplayAttribute()
    {
        return match ($this->day_schedule) {
            'monday_friday' => 'Monday - Friday (5 days)',
            'monday_saturday' => 'Monday - Saturday (6 days)',
            'monday_sunday' => 'Monday - Sunday (7 days)',
            'tuesday_saturday' => 'Tuesday - Saturday (6 days)',
            'sunday_thursday' => 'Sunday - Thursday (5 days)',
            'custom' => 'Custom Schedule',
            default => 'Monday - Friday (5 days)'
        };
    }

    /**
     * Get working days for a specific month
     */
    public function getWorkingDaysForMonth($year, $month)
    {
        $startDate = \Carbon\Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $workingDays = 0;

        $current = $startDate->copy();
        while ($current <= $endDate) {
            if ($this->isWorkingDay($current)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Calculate Basic Pay for a specific payroll period based on actual time logs
     * Uses the same logic as 13th month pay calculation: sums regular workday amounts + 
     * paid suspension fixed_amounts + paid holiday fixed_amounts (excluding time_log amounts)
     * 
     * @param \Carbon\Carbon $periodStart
     * @param \Carbon\Carbon $periodEnd
     * @return float
     */
    public function calculateBasicPayForPeriod(\Carbon\Carbon $periodStart, \Carbon\Carbon $periodEnd)
    {
        $basicPay = 0;

        // Get time logs for the period
        $timeLogs = $this->timeLogs()
            ->whereBetween('log_date', [$periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')])
            ->get();

        if ($timeLogs->isEmpty()) {
            return 0;
        }

        // Calculate hourly rate for calculations using the same logic as PayrollController
        $hourlyRate = $this->calculateBasicPayHourlyRate($periodStart, $periodEnd);

        // Process each time log
        foreach ($timeLogs as $timeLog) {
            // Get rate configuration for this log type
            $rateConfig = $timeLog->getRateConfiguration();
            if (!$rateConfig) continue;

            $logType = $timeLog->log_type;
            $regularHours = $timeLog->regular_hours ?? 0;

            // Calculate pay based on log type (same logic as 13th month calculation)
            if ($logType === 'regular_workday') {
                // Regular workday: include full amount
                $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                $amount = $regularHours * $hourlyRate * $regularMultiplier;
                $basicPay += $amount;
            } elseif (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                // Paid suspensions: include fixed_amount only (same as 13th month logic)
                $dailyRate = $hourlyRate * 8;

                // Check if this suspension is paid based on employee benefits and suspension settings
                $suspensionSettings = \App\Models\NoWorkSuspendedSetting::where('status', 'active')
                    ->where('date_from', '<=', $timeLog->log_date)
                    ->where('date_to', '>=', $timeLog->log_date)
                    ->first();

                if ($suspensionSettings && $this->benefits_status === 'with_benefits') {
                    $payRule = $suspensionSettings->pay_rule ?? 'full';
                    $multiplier = $payRule === 'half' ? 0.5 : 1.0;
                    $fixedAmount = $dailyRate * $multiplier;
                    $basicPay += $fixedAmount;
                }
            } elseif (in_array($logType, ['special_holiday', 'regular_holiday'])) {
                // Paid holidays: include fixed_amount only (same as 13th month logic)
                $dailyRate = $hourlyRate * 8;

                // Check if holiday is paid for this employee
                $holiday = \App\Models\Holiday::where('date', $timeLog->log_date)
                    ->where('is_paid', true)
                    ->where('is_active', true)
                    ->first();

                if ($holiday && $this->benefits_status === 'with_benefits') {
                    $payRule = $holiday->pay_rule ?? 'full';
                    $multiplier = $payRule === 'half' ? 0.5 : 1.0;
                    $fixedAmount = $dailyRate * $multiplier;
                    $basicPay += $fixedAmount;
                }
            }
        }

        return round($basicPay, 2);
    }

    /**
     * Calculate hourly rate using the same logic as PayrollController
     */
    private function calculateBasicPayHourlyRate($periodStart = null, $periodEnd = null)
    {
        // Use fixed_rate and rate_type if available
        if ($this->fixed_rate && $this->fixed_rate > 0 && $this->rate_type) {
            // Get employee's assigned time schedule total hours for calculation
            $timeSchedule = $this->timeSchedule;
            $dailyHours = $timeSchedule ? $timeSchedule->total_hours : 8; // Default to 8 hours if no schedule

            switch ($this->rate_type) {
                case 'hourly':
                    return $this->fixed_rate;

                case 'daily':
                    return $this->fixed_rate / $dailyHours;

                case 'weekly':
                    // Calculate working days in a typical week
                    $daysPerWeek = $this->getDaysPerWeek();
                    $hoursPerWeek = $daysPerWeek * $dailyHours;
                    return $hoursPerWeek > 0 ? ($this->fixed_rate / $hoursPerWeek) : 0;

                case 'semi_monthly':
                case 'semi-monthly':
                    return $this->fixed_rate / 86.67; // Assuming ~86.67 hours per semi-month

                case 'monthly':
                    return $this->fixed_rate / 173.33; // Assuming ~173.33 hours per month

                default:
                    return $this->fixed_rate / 173.33;
            }
        }

        // Fallback to existing calculateHourlyRate method
        return $this->calculateHourlyRate();
    }



    /**
     * Get working days for a specific period (always Monday-Friday for payroll calculations)
     */
    public function getStandardWorkingDaysForPeriod(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            // Always use Monday-Friday for standard payroll calculations
            if ($current->dayOfWeek >= 1 && $current->dayOfWeek <= 5) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Get working days for a specific period
     */
    public function getWorkingDaysForPeriod(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if ($this->isWorkingDay($current)) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Check if a given date is a working day for this employee
     */
    public function isWorkingDay(\Carbon\Carbon $date)
    {
        // First check if employee has a daySchedule relationship
        if ($this->daySchedule) {
            return $this->daySchedule->isWorkingDay($date);
        }

        // Fallback to day_schedule column
        $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 1=Monday, ..., 6=Saturday

        return match ($this->day_schedule) {
            'monday_friday' => $dayOfWeek >= 1 && $dayOfWeek <= 5, // Mon-Fri
            'monday_saturday' => $dayOfWeek >= 1 && $dayOfWeek <= 6, // Mon-Sat
            'monday_sunday' => true, // All days
            'tuesday_saturday' => $dayOfWeek >= 2 && $dayOfWeek <= 6, // Tue-Sat (rest on Sun-Mon)
            'sunday_thursday' => $dayOfWeek == 0 || ($dayOfWeek >= 1 && $dayOfWeek <= 4), // Sun-Thu
            'custom' => true, // Should be implemented based on custom logic
            default => $dayOfWeek >= 1 && $dayOfWeek <= 5 // Default to Mon-Fri
        };
    }

    /**
     * Get expected working hours per day based on current work schedule
     */
    public function getExpectedHoursPerDay()
    {
        $currentSchedule = $this->currentWorkSchedule();
        if (!$currentSchedule) {
            return 8; // Default 8 hours
        }

        // Calculate hours from work schedule
        $startTime = \Carbon\Carbon::parse($currentSchedule->start_time);
        $endTime = \Carbon\Carbon::parse($currentSchedule->end_time);
        $breakHours = $currentSchedule->break_hours ?? 1; // Default 1 hour break

        return $endTime->diffInHours($startTime) - $breakHours;
    }

    /**
     * Calculate expected working hours for a given period
     */
    public function getExpectedHoursForPeriod(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate)
    {
        $totalHours = 0;
        $current = $startDate->copy();
        $hoursPerDay = $this->getExpectedHoursPerDay();

        while ($current <= $endDate) {
            if ($this->isWorkingDay($current)) {
                $totalHours += $hoursPerDay;
            }
            $current->addDay();
        }

        return $totalHours;
    }

    /**
     * Calculate Monthly Basic Salary (MBS) based on actual time logs from the current month
     * Uses the same logic as 13th month pay calculation: sums regular workday amounts + 
     * paid suspension fixed_amounts + paid holiday fixed_amounts (excluding time_log amounts)
     * 
     * @param \Carbon\Carbon $periodStart - For determining the month to calculate
     * @param \Carbon\Carbon $periodEnd - For determining the month to calculate
     * @return float
     */
    public function calculateMonthlyBasicSalary(\Carbon\Carbon $periodStart = null, \Carbon\Carbon $periodEnd = null)
    {
        // Determine the month to calculate for
        if (!$periodStart) {
            $periodStart = now()->startOfMonth();
            $periodEnd = now()->endOfMonth();
        } else {
            // Always calculate for the full month containing the period
            $monthStart = $periodStart->copy()->startOfMonth();
            $monthEnd = $periodStart->copy()->endOfMonth();
            $periodStart = $monthStart;
            $periodEnd = $monthEnd;
        }

        $monthlyBasicPay = 0;

        // Get time logs for the entire month
        $timeLogs = $this->timeLogs()
            ->whereBetween('log_date', [$periodStart->format('Y-m-d'), $periodEnd->format('Y-m-d')])
            ->get();

        if ($timeLogs->isEmpty()) {
            return 0;
        }

        // Calculate hourly rate for calculations
        $hourlyRate = $this->calculateBasicPayHourlyRate($periodStart, $periodEnd);

        // Process each time log (same logic as basic pay calculation and 13th month calculation)
        foreach ($timeLogs as $timeLog) {
            // Get rate configuration for this log type
            $rateConfig = $timeLog->getRateConfiguration();
            if (!$rateConfig) continue;

            $logType = $timeLog->log_type;
            $regularHours = $timeLog->regular_hours ?? 0;

            // Calculate pay based on log type (same logic as 13th month calculation)
            if ($logType === 'regular_workday') {
                // Regular workday: include full amount
                $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.0;
                $amount = $regularHours * $hourlyRate * $regularMultiplier;
                $monthlyBasicPay += $amount;
            } elseif (in_array($logType, ['full_day_suspension', 'partial_suspension'])) {
                // Paid suspensions: include fixed_amount only (same as 13th month logic)
                $dailyRate = $hourlyRate * 8;

                // Check if this suspension is paid based on employee benefits and suspension settings
                $suspensionSettings = \App\Models\NoWorkSuspendedSetting::where('status', 'active')
                    ->where('date_from', '<=', $timeLog->log_date)
                    ->where('date_to', '>=', $timeLog->log_date)
                    ->first();

                if ($suspensionSettings && $this->benefits_status === 'with_benefits') {
                    $payRule = $suspensionSettings->pay_rule ?? 'full';
                    $multiplier = $payRule === 'half' ? 0.5 : 1.0;
                    $fixedAmount = $dailyRate * $multiplier;
                    $monthlyBasicPay += $fixedAmount;
                }
            } elseif (in_array($logType, ['special_holiday', 'regular_holiday'])) {
                // Paid holidays: include fixed_amount only (same as 13th month logic)
                $dailyRate = $hourlyRate * 8;

                // Check if holiday is paid for this employee
                $holiday = \App\Models\Holiday::where('date', $timeLog->log_date)
                    ->where('is_paid', true)
                    ->where('is_active', true)
                    ->first();

                if ($holiday && $this->benefits_status === 'with_benefits') {
                    $payRule = $holiday->pay_rule ?? 'full';
                    $multiplier = $payRule === 'half' ? 0.5 : 1.0;
                    $fixedAmount = $dailyRate * $multiplier;
                    $monthlyBasicPay += $fixedAmount;
                }
            }
        }

        return round($monthlyBasicPay, 2);
    }

    /**
     * Get Monthly Basic Salary for display (alias method)
     * 
     * @param \Carbon\Carbon $periodStart
     * @param \Carbon\Carbon $periodEnd
     * @return float
     */
    public function getMonthlyBasicSalary(\Carbon\Carbon $periodStart = null, \Carbon\Carbon $periodEnd = null)
    {
        return $this->calculateMonthlyBasicSalary($periodStart, $periodEnd);
    }

    /**
     * Get the number of working days per week for this employee based on their day schedule
     * 
     * @return int
     */
    public function getDaysPerWeek()
    {
        if ($this->daySchedule) {
            // Count the enabled days in the day schedule
            $enabledDays = 0;
            $dayFields = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            foreach ($dayFields as $day) {
                if ($this->daySchedule->$day) {
                    $enabledDays++;
                }
            }

            return $enabledDays;
        }

        // Default fallback: 5 days per week (Monday to Friday)
        return 5;
    }
}
