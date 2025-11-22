<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayScheduleSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'cutoff_periods',
        'pay_day_offset',
        'pay_day_type',
        'pay_day_weekday',
        'move_if_holiday',
        'move_if_weekend',
        'move_direction',
        'is_active',
        'is_system_default',
    ];

    protected $casts = [
        'cutoff_periods' => 'array',
        'pay_day_offset' => 'integer',
        'pay_day_weekday' => 'integer',
        'move_if_holiday' => 'boolean',
        'move_if_weekend' => 'boolean',
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get employees using this pay schedule
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'pay_schedule_setting_id');
    }

    /**
     * Get payrolls using this schedule
     */
    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'pay_schedule_setting_id');
    }

    /**
     * Scope to get only active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive schedules
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get system default schedules
     */
    public function scopeSystemDefault($query)
    {
        return $query->where('is_system_default', true);
    }

    /**
     * Scope to get system default schedules (plural alias)
     */
    public function scopeSystemDefaults($query)
    {
        return $query->where('is_system_default', true);
    }

    /**
     * Scope to get custom schedules
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system_default', false);
    }

    /**
     * Get formatted frequency name
     */
    public function getFormattedFrequencyAttribute()
    {
        return match ($this->code) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'bi_weekly' => 'Bi-Weekly',
            'semi_monthly' => 'Semi-Monthly',
            'monthly' => 'Monthly',
            default => ucfirst($this->name),
        };
    }

    /**
     * Check if this schedule can be deleted
     */
    public function canBeDeleted()
    {
        // Cannot delete system defaults
        if ($this->is_system_default) {
            return false;
        }

        // Cannot delete if it has employees or payrolls associated
        return $this->employees()->count() === 0 && $this->payrolls()->count() === 0;
    }

    /**
     * Check if this schedule is currently in use
     */
    public function isInUse()
    {
        return $this->employees()->count() > 0 || $this->payrolls()->count() > 0;
    }

    /**
     * Get pay frequency based on payroll period using dynamic pay schedule settings
     * 
     * @param \Carbon\Carbon $periodStart
     * @param \Carbon\Carbon $periodEnd
     * @return string
     */
    public static function detectPayFrequencyFromPeriod(\Carbon\Carbon $periodStart, \Carbon\Carbon $periodEnd)
    {
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;

        try {
            // Get all active pay schedule settings ordered by priority
            $schedules = self::where('is_active', true)->get();

            foreach ($schedules as $schedule) {
                if ($schedule->matchesPeriodLength($periodDays)) {
                    return $schedule->code;
                }
            }
        } catch (\Exception $e) {
            // Log the error but continue with fallback logic
            \Illuminate\Support\Facades\Log::warning('Error detecting pay frequency from dynamic schedules: ' . $e->getMessage());
        }

        // Fallback to hardcoded logic if no dynamic schedules match or if there's an error
        if ($periodDays <= 1) {
            return 'daily';
        } elseif ($periodDays <= 7) {
            return 'weekly';
        } elseif ($periodDays <= 16) {
            return 'semi_monthly';
        } else {
            return 'monthly';
        }
    }
    /**
     * Check if this pay schedule matches the given period length
     * 
     * @param int $periodDays
     * @return bool
     */
    public function matchesPeriodLength($periodDays)
    {
        if (!$this->cutoff_periods || !is_array($this->cutoff_periods)) {
            return false;
        }

        try {
            // Check if any cutoff period matches the period length
            foreach ($this->cutoff_periods as $period) {
                $expectedDays = $this->calculatePeriodLength($period);
                if ($expectedDays == $periodDays) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If there's an error calculating period length, return false
            return false;
        }

        return false;
    }

    /**
     * Calculate expected period length from cutoff period configuration
     * 
     * @param array $period
     * @return int
     */
    private function calculatePeriodLength($period)
    {
        // Ensure we have a valid array
        if (!is_array($period)) {
            return $this->getDefaultPeriodLength();
        }

        // This depends on how cutoff_periods is structured
        // Based on the migration, it seems to store period configurations
        // You may need to adjust this based on the actual structure

        if (isset($period['start_day']) && isset($period['end_day'])) {
            // Convert to integers to avoid string subtraction error
            $startDay = (int) $period['start_day'];
            $endDay = (int) $period['end_day'];

            // Validate the values
            if ($startDay < 1 || $endDay < 1 || $startDay > $endDay) {
                return $this->getDefaultPeriodLength();
            }

            return $endDay - $startDay + 1;
        }

        return $this->getDefaultPeriodLength();
    }

    /**
     * Get default period length based on schedule type
     * 
     * @return int
     */
    private function getDefaultPeriodLength()
    {
        // Default estimates based on schedule type
        switch ($this->code) {
            case 'daily':
                return 1;
            case 'weekly':
                return 7;
            case 'semi_monthly':
                return 15; // Average
            case 'monthly':
                return 30; // Average
            default:
                return 15;
        }
    }

    /**
     * Get cutoff periods for this pay schedule
     * 
     * @return array
     */
    public function getCutoffPeriods()
    {
        return $this->cutoff_periods ?? [];
    }
}
