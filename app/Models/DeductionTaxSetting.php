<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeductionTaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'type',
        'category',
        'calculation_type',
        'tax_table_type',
        'pay_frequency',
        'distribution_method',
        'enable_proration',
        'custom_distribution_percentages',
        'rate_percentage',
        'fixed_amount',
        'bracket_rates',
        'minimum_amount',
        'maximum_amount',
        'salary_cap',
        'apply_to_regular',
        'apply_to_overtime',
        'apply_to_bonus',
        'apply_to_allowances',
        'apply_to_basic_pay',
        'apply_to_gross_pay',
        'apply_to_taxable_income',
        'apply_to_net_pay',
        'apply_to_monthly_basic_salary',
        'apply_to_monthly_basic_salary_with_allowances',
        'employer_share_rate',
        'employer_share_fixed',
        'employee_share_percentage',
        'employer_share_percentage',
        'sharing_notes',
        'share_with_employer',
        'is_active',
        'is_system_default',
        'sort_order',
        'benefit_eligibility',
        'deduction_frequency',
        'semi_monthly_period',
        'frequency_notes',
        'deduct_on_monthly_payroll',
        'distribution_method',
    ];

    protected $casts = [
        'bracket_rates' => 'array',
        'custom_distribution_percentages' => 'array',
        'rate_percentage' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'salary_cap' => 'decimal:2',
        'employer_share_rate' => 'decimal:4',
        'employer_share_fixed' => 'decimal:2',
        'employee_share_percentage' => 'decimal:4',
        'employer_share_percentage' => 'decimal:4',
        'apply_to_regular' => 'boolean',
        'apply_to_overtime' => 'boolean',
        'apply_to_bonus' => 'boolean',
        'apply_to_allowances' => 'boolean',
        'apply_to_basic_pay' => 'boolean',
        'apply_to_gross_pay' => 'boolean',
        'apply_to_taxable_income' => 'boolean',
        'apply_to_net_pay' => 'boolean',
        'apply_to_monthly_basic_salary' => 'boolean',
        'apply_to_monthly_basic_salary_with_allowances' => 'boolean',
        'share_with_employer' => 'boolean',
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
        'enable_proration' => 'boolean',
        'deduct_on_monthly_payroll' => 'boolean',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to get only active deductions
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
     * Scope to get government deductions
     */
    public function scopeGovernment($query)
    {
        return $query->where('type', 'government');
    }

    /**
     * Check if this deduction should be applied for a specific payroll period
     * based on the distribution settings
     */
    public function shouldApplyForPeriod($payrollStart, $payrollEnd, $employeePaySchedule = null)
    {
        // If no distribution method is set (empty/null), apply to all payrolls (default behavior)
        if (empty($this->distribution_method)) {
            return true;
        }

        // For all pay frequencies, apply the same distribution logic
        $periodStart = \Carbon\Carbon::parse($payrollStart);
        $periodEnd = \Carbon\Carbon::parse($payrollEnd);

        switch ($this->distribution_method) {
            case 'last_payroll':
                return $this->isLastPayrollOfMonth($periodStart, $periodEnd);

            case 'equally_distributed':
                return true; // Will be handled in calculateDistributedAmount

            default:
                return true; // Default: apply to all payrolls
        }
    }

    /**
     * Calculate the distributed deduction amount for a specific payroll period
     */
    public function calculateDistributedAmount($originalAmount, $payrollStart, $payrollEnd, $employeePaySchedule = null)
    {
        // If this deduction shouldn't be applied for this period, return 0
        if (!$this->shouldApplyForPeriod($payrollStart, $payrollEnd, $employeePaySchedule)) {
            return 0;
        }

        // If distribution method is not 'equally_distributed', return full amount
        if ($this->distribution_method !== 'equally_distributed') {
            return $originalAmount;
        }

        // For distribute equally, calculate the number of payrolls in the month and divide
        $periodStart = \Carbon\Carbon::parse($payrollStart);
        $payrollsInMonth = $this->getPayrollCountInMonth($periodStart, $employeePaySchedule);

        return $payrollsInMonth > 0 ? round($originalAmount / $payrollsInMonth, 2) : $originalAmount;
    }

    /**
     * Check if the given period is the first payroll of the month
     */
    private function isFirstPayrollOfMonth($periodStart, $periodEnd)
    {
        $monthStart = $periodStart->copy()->startOfMonth();

        // For semi-monthly: first payroll typically covers 1st to 15th
        if ($periodStart->day <= 15 && $periodEnd->day <= 15) {
            return true;
        }

        // For weekly/daily: check if this is the first period that starts in this month
        return $periodStart->day <= 7;
    }

    /**
     * Check if the given period is the last payroll of the month
     */
    private function isLastPayrollOfMonth($periodStart, $periodEnd)
    {
        $monthEnd = $periodStart->copy()->endOfMonth();

        // For semi-monthly: last payroll typically covers 16th to end of month
        if ($periodStart->day > 15 && $periodEnd->day >= $monthEnd->day - 5) {
            return true;
        }

        // For weekly/daily: check if this period ends near or at month end
        return $periodEnd->day >= $monthEnd->day - 7;
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
     * Calculate deduction amount based on salary components
     */
    public function calculateDeduction($basicPay = 0, $overtime = 0, $bonus = 0, $allowances = 0, $grossPay = null, $taxableIncome = null, $netPay = null, $monthlyBasicSalary = null, $payFrequency = 'semi_monthly')
    {
        // Calculate gross pay if not provided
        if ($grossPay === null) {
            $grossPay = $basicPay + $overtime + $bonus + $allowances;
        }

        // Determine the base amount to apply deduction to
        $applicableSalary = 0;

        if ($this->apply_to_basic_pay) $applicableSalary += $basicPay;
        if ($this->apply_to_regular) $applicableSalary += $basicPay; // backwards compatibility
        if ($this->apply_to_overtime) $applicableSalary += $overtime;
        if ($this->apply_to_bonus) $applicableSalary += $bonus;
        if ($this->apply_to_allowances) $applicableSalary += $allowances;
        if ($this->apply_to_gross_pay && $grossPay) $applicableSalary = $grossPay;
        if ($this->apply_to_taxable_income && $taxableIncome) $applicableSalary = $taxableIncome;
        if ($this->apply_to_net_pay && $netPay) $applicableSalary = $netPay;

        // For monthly basic salary, always use monthly basic salary for proper calculation
        // The distribution will be handled in calculateDistributedAmount method
        if ($this->apply_to_monthly_basic_salary && $monthlyBasicSalary) {
            $applicableSalary = $monthlyBasicSalary;
        }

        // Apply salary cap if set
        if ($this->salary_cap && $applicableSalary > $this->salary_cap) {
            $applicableSalary = $this->salary_cap;
        }

        $deduction = 0;

        switch ($this->calculation_type) {
            case 'percentage':
                $deduction = $applicableSalary * ($this->rate_percentage / 100);
                break;

            case 'fixed_amount':
                $deduction = $this->fixed_amount;
                break;

            case 'bracket':
                if ($this->tax_table_type) {
                    // For PhilHealth and Pag-IBIG, always use monthly basic salary for accurate table calculations
                    // Distribution will be handled in calculateDistributedAmount method
                    if (($this->tax_table_type === 'philhealth' || $this->tax_table_type === 'pagibig') && $monthlyBasicSalary) {
                        $deduction = $this->calculateTaxTableDeduction($monthlyBasicSalary, $this->tax_table_type, $payFrequency);
                    } else {
                        $deduction = $this->calculateTaxTableDeduction($applicableSalary, $this->tax_table_type, $payFrequency);
                    }
                } else {
                    $deduction = $this->calculateBracketDeduction($applicableSalary);
                }
                break;
        }

        // Apply minimum and maximum limits
        if ($this->minimum_amount && $deduction < $this->minimum_amount) {
            $deduction = $this->minimum_amount;
        }

        if ($this->maximum_amount && $deduction > $this->maximum_amount) {
            $deduction = $this->maximum_amount;
        }

        return round($deduction, 2);
    }

    /**
     * Calculate bracket-based deduction (for tax brackets)
     */
    private function calculateBracketDeduction($amount)
    {
        if (!$this->bracket_rates) return 0;

        $totalDeduction = 0;
        $remainingAmount = $amount;

        foreach ($this->bracket_rates as $bracket) {
            $bracketMin = $bracket['min'] ?? 0;
            $bracketMax = $bracket['max'] ?? PHP_INT_MAX;
            $rate = $bracket['rate'] ?? 0;

            if ($remainingAmount <= $bracketMin) break;

            $taxableInBracket = min($remainingAmount - $bracketMin, $bracketMax - $bracketMin);
            $totalDeduction += $taxableInBracket * ($rate / 100);
        }

        return $totalDeduction;
    }

    /**
     * Calculate tax table-based deduction (SSS, PhilHealth, Pag-IBIG, Withholding Tax)
     */
    private function calculateTaxTableDeduction($amount, $type, $payFrequency = 'semi_monthly')
    {
        switch ($type) {
            case 'sss':
                return $this->calculateSSSDeduction($amount);
            case 'philhealth':
                return $this->calculatePhilHealthDeduction($amount);
            case 'pagibig':
                return $this->calculatePagibigDeduction($amount);
            case 'withholding_tax':
                return $this->calculateWithholdingTaxDeduction($amount, $payFrequency);
            default:
                return 0;
        }
    }

    /**
     * Calculate SSS deduction based on database contribution table and sharing setting
     */
    private function calculateSSSDeduction($salary)
    {
        // Query the SSS tax table from database for the salary range
        $sssContribution = DB::table('sss_tax_table')
            ->where('range_start', '<=', $salary)
            ->where(function ($query) use ($salary) {
                $query->where('range_end', '>=', $salary)
                    ->orWhereNull('range_end'); // For "above" ranges
            })
            ->where('is_active', true)
            ->first();

        if (!$sssContribution) {
            return 0; // No matching range found
        }

        $employeeShare = (float) $sssContribution->employee_share;
        $employerShare = (float) $sssContribution->employer_share;

        if ($this->share_with_employer) {
            // If shared with employer, only deduct employee share from employee salary
            return $employeeShare;
        } else {
            // If not shared, deduct both employee and employer shares from employee salary
            return $employeeShare + $employerShare;
        }
    }

    /**
     * Calculate PhilHealth deduction using the PhilHealth tax table
     */
    private function calculatePhilHealthDeduction($salary)
    {
        // Use the PhilHealthTaxTable model for calculation
        $contribution = \App\Models\PhilHealthTaxTable::calculateContribution($salary);

        if (!$contribution) {
            return 0; // No contribution required (e.g., salary below minimum)
        }

        $employeeShare = $contribution['employee_share'];
        $employerShare = $contribution['employer_share'];

        if ($this->share_with_employer) {
            // If shared with employer, only deduct employee share from employee salary
            return $employeeShare;
        } else {
            // If not shared, deduct both employee and employer shares from employee salary
            return $employeeShare + $employerShare;
        }
    }

    /**
     * Calculate Pag-IBIG deduction using the Pag-IBIG tax table
     */
    private function calculatePagibigDeduction($salary)
    {
        // Use the PagibigTaxTable model for calculation
        $contribution = \App\Models\PagibigTaxTable::calculateContribution($salary);

        if (!$contribution) {
            return 0; // No contribution required (e.g., salary below minimum)
        }

        $employeeShare = $contribution['employee_share'];
        $employerShare = $contribution['employer_share'];

        if ($this->share_with_employer) {
            // If shared with employer, only deduct employee share from employee salary
            return $employeeShare;
        } else {
            // If not shared, deduct both employee and employer shares from employee salary
            return $employeeShare + $employerShare;
        }
    }

    /**
     * Calculate BIR Withholding Tax deduction using the new WithholdingTaxTable
     */
    private function calculateWithholdingTaxDeduction($taxableIncome, $payFrequency = 'semi_monthly')
    {
        // Use the WithholdingTaxTable model for calculation
        return \App\Models\WithholdingTaxTable::calculateWithholdingTax($taxableIncome, $payFrequency);
    }
    /**
     * Calculate employer share
     */
    public function calculateEmployerShare($employeeDeduction, $salary)
    {
        if ($this->employer_share_rate) {
            return $salary * ($this->employer_share_rate / 100);
        }

        if ($this->employer_share_fixed) {
            return $this->employer_share_fixed;
        }

        return 0;
    }

    /**
     * Get share percentage display for UI badge
     */
    public function getSharePercentageAttribute()
    {
        if (!$this->share_with_employer) {
            return null; // No sharing
        }

        // Return standard sharing percentages for government deductions
        switch ($this->tax_table_type) {
            case 'sss':
                return '50%'; // Typically employee pays about 1/3, employer pays 2/3
            case 'philhealth':
                return '50%'; // Equal sharing 2.5% each
            case 'pagibig':
                return '50%'; // Equal sharing for most salary ranges
            default:
                return '50%';
        }
    }

    /**
     * Check if this deduction supports employer sharing
     */
    public function getSupportsEmployerSharingAttribute()
    {
        return in_array($this->tax_table_type, ['sss', 'philhealth', 'pagibig']);
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

    /**
     * Calculate employer share only for reporting purposes
     */
    public function calculateEmployerShareOnly($salary)
    {
        if (!$this->share_with_employer) {
            return 0; // No employer share if not sharing
        }

        switch ($this->tax_table_type) {
            case 'sss':
                return $this->calculateSSSEmployerShare($salary);
            case 'philhealth':
                return $this->calculatePhilHealthEmployerShare($salary);
            case 'pagibig':
                return $this->calculatePagibigEmployerShare($salary);
            default:
                return 0;
        }
    }

    /**
     * Calculate SSS employer share only
     */
    private function calculateSSSEmployerShare($salary)
    {
        if (!$this->share_with_employer) {
            return 0;
        }

        $sssContribution = DB::table('sss_tax_table')
            ->where('range_start', '<=', $salary)
            ->where(function ($query) use ($salary) {
                $query->where('range_end', '>=', $salary)
                    ->orWhereNull('range_end');
            })
            ->where('is_active', true)
            ->first();

        return $sssContribution ? (float) $sssContribution->employer_share : 0;
    }

    /**
     * Calculate PhilHealth employer share only
     */
    private function calculatePhilHealthEmployerShare($salary)
    {
        if (!$this->share_with_employer) {
            return 0;
        }

        $employerRate = 0.025; // 2.5%
        $maxSalary = 80000;

        if ($salary > $maxSalary) {
            return $maxSalary * $employerRate;
        }

        return $salary * $employerRate;
    }

    /**
     * Calculate Pag-IBIG employer share only
     */
    private function calculatePagibigEmployerShare($salary)
    {
        if (!$this->share_with_employer) {
            return 0;
        }

        $employerRate = $salary <= 1500 ? 0.02 : 0.02; // 2% for all salary levels
        return min($salary * $employerRate, 200);
    }
}
