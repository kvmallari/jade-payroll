<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidLeaveSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        // Simplified fields
        'total_days',
        'limit_quantity',
        'limit_period',
        'applicable_to',
        'pay_percentage',
        'pay_rule',
        'pay_applicable_to',
        // Original fields for backward compatibility
        'days_per_year',
        'accrual_method',
        'accrual_rate',
        'minimum_service_months',
        'prorated_first_year',
        'minimum_days_usage',
        'maximum_days_usage',
        'notice_days_required',
        'can_carry_over',
        'max_carry_over_days',
        'expires_annually',
        'expiry_month',
        'can_convert_to_cash',
        'cash_conversion_rate',
        'max_convertible_days',
        'applicable_gender',
        'applicable_employment_types',
        'applicable_employment_status',
        'is_active',
        'is_system_default',
        'sort_order',
        'benefit_eligibility',
    ];

    protected $casts = [
        'pay_percentage' => 'decimal:2',
        'pay_rule' => 'string',
        'pay_applicable_to' => 'string',
        'accrual_rate' => 'decimal:4',
        'cash_conversion_rate' => 'decimal:4',
        'applicable_gender' => 'array',
        'applicable_employment_types' => 'array',
        'applicable_employment_status' => 'array',
        'prorated_first_year' => 'boolean',
        'can_carry_over' => 'boolean',
        'expires_annually' => 'boolean',
        'can_convert_to_cash' => 'boolean',
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
     * Scope to get only active leave settings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if employee is eligible for this leave type
     */
    public function isEmployeeEligible($employee)
    {
        // Check gender eligibility
        if ($this->applicable_gender && !in_array($employee->gender, $this->applicable_gender)) {
            return false;
        }

        // Check employment type eligibility
        if ($this->applicable_employment_types && !in_array($employee->employment_type, $this->applicable_employment_types)) {
            return false;
        }

        // Check employment status eligibility
        if ($this->applicable_employment_status && !in_array($employee->employment_status, $this->applicable_employment_status)) {
            return false;
        }

        // Check minimum service requirement
        $monthsOfService = $employee->hire_date->diffInMonths(now());
        if ($monthsOfService < $this->minimum_service_months) {
            return false;
        }

        return true;
    }

    /**
     * Calculate annual leave entitlement for employee
     */
    public function calculateAnnualEntitlement($employee, $year = null)
    {
        if (!$this->isEmployeeEligible($employee)) {
            return 0;
        }

        $year = $year ?: date('Y');
        $entitlement = $this->days_per_year;

        // If prorated in first year
        if ($this->prorated_first_year && $employee->hire_date->year == $year) {
            $monthsWorked = 12 - $employee->hire_date->month + 1;
            $entitlement = ($this->days_per_year / 12) * $monthsWorked;
        }

        return round($entitlement, 2);
    }

    /**
     * Calculate leave accrual for a period
     */
    public function calculateAccrual($employee, $periodStart, $periodEnd)
    {
        if (!$this->isEmployeeEligible($employee)) {
            return 0;
        }

        switch ($this->accrual_method) {
            case 'yearly':
                // Accrual happens once per year, usually on hire anniversary or calendar year
                return 0; // Would be calculated separately in yearly accrual process

            case 'monthly':
                $months = $periodStart->diffInMonths($periodEnd) + 1;
                return $this->accrual_rate * $months;

            case 'per_payroll':
                return $this->accrual_rate;

            default:
                return 0;
        }
    }

    /**
     * Get cash conversion value for leave days
     */
    public function calculateCashValue($days, $dailyRate)
    {
        if (!$this->can_convert_to_cash || $days <= 0) {
            return 0;
        }

        $convertibleDays = $this->max_convertible_days > 0
            ? min($days, $this->max_convertible_days)
            : $days;

        return $dailyRate * $convertibleDays * $this->cash_conversion_rate;
    }

    /**
     * Check if leave expires this year
     */
    public function isExpiringThisYear($year = null)
    {
        if (!$this->expires_annually) {
            return false;
        }

        $year = $year ?: date('Y');
        $currentMonth = date('n');

        return $currentMonth >= $this->expiry_month;
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
