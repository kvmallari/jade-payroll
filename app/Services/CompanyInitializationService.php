<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DeductionTaxSetting;
use App\Models\AllowanceBonusSetting;
use App\Models\GracePeriodSetting;
use App\Models\NightDifferentialSetting;
use App\Models\EmployerSetting;
use App\Models\PaySchedule;
use App\Models\PayrollRateConfiguration;
use Illuminate\Support\Facades\DB;

class CompanyInitializationService
{
    /**
     * Initialize complete default settings for a new company (fresh instance)
     */
    public function initializeCompanySettings(Company $company)
    {
        DB::transaction(function () use ($company) {
            // 1. Create default government deductions (SSS, PhilHealth, Pag-IBIG, Withholding Tax)
            $this->createDefaultGovernmentDeductions($company);

            // 2. Create default pay schedule tabs (Daily, Weekly, Semi-Monthly, Monthly) - empty
            $this->createDefaultPayScheduleTabs($company);

            // 3. Create default rate multipliers (Regular, Rest Day, Holiday, Suspension) - all 0%
            $this->createDefaultRateMultipliers($company);

            // 4. Create default grace period setting (0 minutes)
            $this->createDefaultGracePeriodSetting($company);

            // 5. Create default night differential setting (0% rate)
            $this->createDefaultNightDifferentialSetting($company);

            // 6. Create default employer setting (all blank)
            $this->createDefaultEmployerSetting($company);

            // 7. Create default custom deductions (Abuloy)
            $this->createDefaultCustomDeductions($company);

            // 8. Create default allowances (Transportation, 13th Month Bonus, PA Incentives)
            $this->createDefaultAllowances($company);

            // 9. Create default leaves (Vacation Leave)
            $this->createDefaultLeaves($company);

            // 10. Create default holidays (Regular Holidays)
            $this->createDefaultHolidays($company);

            // 11. Create default suspension types (Partial and Full Suspension)
            $this->createDefaultSuspensions($company);

            // Note: The following are intentionally left blank for admins to configure:
            // - Departments & Positions (blank)
            // - Time log settings (blank)
        });
    }

    /**
     * Create default government deductions with inactive status
     */
    private function createDefaultGovernmentDeductions(Company $company)
    {
        $governmentDeductions = [
            [
                'name' => 'SSS Contribution',
                'code' => 'SSS_' . strtoupper($company->code),
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'bracket',
                'tax_table_type' => 'sss',
                'description' => 'SSS Contribution - Please configure using SSS contribution table',
            ],
            [
                'name' => 'PhilHealth Contribution',
                'code' => 'PHILHEALTH_' . strtoupper($company->code),
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'percentage',
                'tax_table_type' => 'philhealth',
                'description' => 'PhilHealth Contribution - Please configure rate and salary cap',
            ],
            [
                'name' => 'Pag-IBIG Contribution',
                'code' => 'PAGIBIG_' . strtoupper($company->code),
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'percentage',
                'tax_table_type' => 'pagibig',
                'description' => 'Pag-IBIG Contribution - Please configure rate and salary cap',
            ],
            [
                'name' => 'Withholding Tax',
                'code' => 'WITHHOLDING_TAX_' . strtoupper($company->code),
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'bracket',
                'tax_table_type' => 'withholding_tax',
                'description' => 'Withholding Tax - Please configure using BIR tax table',
            ],
        ];

        foreach ($governmentDeductions as $index => $deduction) {
            DeductionTaxSetting::create(array_merge($deduction, [
                'company_id' => $company->id,
                'is_active' => false, // Inactive until configured
                'sort_order' => $index + 1,
                'rate_percentage' => 0,
                'fixed_amount' => 0,
                'minimum_amount' => 0,
                'maximum_amount' => 0,
                'salary_cap' => 0,
                'apply_to_regular' => true,
                'apply_to_overtime' => false,
                'apply_to_bonus' => false,
                'apply_to_allowances' => false,
                'apply_to_basic_pay' => true,
                'apply_to_gross_pay' => false,
                'apply_to_taxable_income' => false,
                'apply_to_net_pay' => false,
            ]));
        }
    }

    /**
     * Create default pay schedule tabs (empty schedules, just the structure)
     */
    private function createDefaultPayScheduleTabs(Company $company)
    {
        // Create empty pay schedules for each type
        // Admins will add actual schedules under each type
        $scheduleTypes = [
            ['name' => 'Daily Schedules', 'type' => 'daily', 'description' => 'Daily pay schedules'],
            ['name' => 'Weekly Schedules', 'type' => 'weekly', 'description' => 'Weekly pay schedules'],
            ['name' => 'Semi-Monthly Schedules', 'type' => 'semi-monthly', 'description' => 'Semi-monthly pay schedules'],
            ['name' => 'Monthly Schedules', 'type' => 'monthly', 'description' => 'Monthly pay schedules'],
        ];

        // Note: These are just tabs/categories. Actual schedules are added by admins.
        // The PaySchedule model handles this with type filtering.
    }

    /**
     * Create default rate multipliers - all set to 0%
     */
    private function createDefaultRateMultipliers(Company $company)
    {
        $rateConfigs = [
            // Regular Day
            [
                'type_name' => 'regular_workday',
                'display_name' => 'Regular Workday',
                'regular_rate_multiplier' => 0.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Regular working day rates',
            ],

            // Rest Day
            [
                'type_name' => 'rest_day',
                'display_name' => 'Rest Day',
                'regular_rate_multiplier' => 0.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Rest day rates',
            ],

            // Regular Holiday
            [
                'type_name' => 'regular_holiday',
                'display_name' => 'Regular Holiday',
                'regular_rate_multiplier' => 0.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Regular holiday rates',
            ],

            // Special Holiday
            [
                'type_name' => 'special_holiday',
                'display_name' => 'Special (Non-Working) Holiday',
                'regular_rate_multiplier' => 0.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Special non-working holiday rates',
            ],

            // Suspension
            [
                'type_name' => 'suspension',
                'display_name' => 'Suspension of Work',
                'regular_rate_multiplier' => 0.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Suspension of work rates',
            ],
        ];

        foreach ($rateConfigs as $index => $config) {
            PayrollRateConfiguration::create(array_merge($config, [
                'company_id' => $company->id,
                'is_active' => true,
                'is_system' => false,
                'sort_order' => $index + 1,
            ]));
        }
    }

    /**
     * Create default grace period setting (0 minutes)
     */
    private function createDefaultGracePeriodSetting(Company $company)
    {
        GracePeriodSetting::create([
            'company_id' => $company->id,
            'late_grace_minutes' => 0,
            'undertime_grace_minutes' => 0,
            'overtime_threshold_minutes' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Create default night differential setting (0% rate)
     */
    private function createDefaultNightDifferentialSetting(Company $company)
    {
        NightDifferentialSetting::create([
            'company_id' => $company->id,
            'start_time' => '22:00',
            'end_time' => '06:00',
            'rate_multiplier' => 0.00,
            'description' => 'Default night differential - please configure the rate',
            'is_active' => false,
        ]);
    }

    /**
     * Create default employer setting (all blank/null)
     */
    private function createDefaultEmployerSetting(Company $company)
    {
        EmployerSetting::create([
            'company_id' => $company->id,
            'registered_business_name' => null,
            'tax_identification_number' => null,
            'rdo_code' => null,
            'sss_employer_number' => null,
            'philhealth_employer_number' => null,
            'hdmf_employer_number' => null,
            'registered_address' => null,
            'postal_zip_code' => null,
            'landline_mobile' => null,
            'office_business_email' => null,
            'signatory_name' => null,
            'signatory_designation' => null,
        ]);
    }

    /**
     * Create default custom deductions (Abuloy)
     */
    private function createDefaultCustomDeductions(Company $company)
    {
        DeductionTaxSetting::create([
            'company_id' => $company->id,
            'name' => 'Abuloy',
            'code' => 'ABULOY_' . strtoupper($company->code),
            'type' => 'custom',
            'category' => 'voluntary',
            'calculation_type' => 'fixed_amount',
            'fixed_amount' => 0,
            'rate_percentage' => 0,
            'description' => 'Abuloy deduction - configure amount as needed',
            'is_active' => true,
            'apply_to_regular' => true,
            'apply_to_overtime' => false,
            'apply_to_bonus' => false,
            'apply_to_allowances' => false,
            'apply_to_basic_pay' => false,
            'apply_to_gross_pay' => false,
            'apply_to_taxable_income' => false,
            'apply_to_net_pay' => true,
            'sort_order' => 100,
        ]);
    }

    /**
     * Create default allowances
     */
    private function createDefaultAllowances(Company $company)
    {
        $allowances = [
            [
                'name' => 'Transportation Allowance',
                'code' => 'TRANS_ALLOW_' . strtoupper($company->code),
                'type' => 'allowance',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 0,
                'rate_percentage' => 0,
                'description' => 'Transportation allowance for employees',
            ],
            [
                'name' => '13th Month Bonus',
                'code' => '13TH_MONTH_' . strtoupper($company->code),
                'type' => 'bonus',
                'calculation_type' => 'percentage',
                'rate_percentage' => 0,
                'fixed_amount' => 0,
                'description' => '13th month pay bonus',
            ],
            [
                'name' => 'PA Incentives',
                'code' => 'PA_INCENTIVES_' . strtoupper($company->code),
                'type' => 'incentives',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 0,
                'rate_percentage' => 0,
                'description' => 'Performance-based incentives',
            ],
        ];

        foreach ($allowances as $index => $allowance) {
            AllowanceBonusSetting::create(array_merge($allowance, [
                'company_id' => $company->id,
                'is_active' => true,
                'is_taxable' => false,
                'sort_order' => $index + 1,
            ]));
        }
    }

    /**
     * Create default leaves
     */
    private function createDefaultLeaves(Company $company)
    {
        \App\Models\PaidLeaveSetting::create([
            'company_id' => $company->id,
            'name' => 'Vacation Leave',
            'code' => 'VL_' . strtoupper($company->code),
            'total_days' => 0,
            'days_per_year' => 0,
            'pay_percentage' => 100,
            'pay_rule' => 'full',
            'is_active' => true,
            'description' => 'Vacation leave - configure total days as needed',
        ]);
    }

    /**
     * Create default holidays
     */
    private function createDefaultHolidays(Company $company)
    {
        $currentYear = date('Y');

        // Add 1 example Regular Holiday
        \App\Models\Holiday::create([
            'company_id' => $company->id,
            'name' => "New Year's Day",
            'date' => "$currentYear-01-01",
            'type' => 'regular',
            'is_recurring' => true,
            'is_paid' => true,
            'is_active' => true,
            'year' => $currentYear,
            'description' => 'Regular holiday example - New Year\'s Day',
        ]);

        // Add 1 example Special Non-Working Holiday
        \App\Models\Holiday::create([
            'company_id' => $company->id,
            'name' => 'EDSA Revolution Anniversary',
            'date' => "$currentYear-02-25",
            'type' => 'special_non_working',
            'is_recurring' => true,
            'is_paid' => true,
            'is_active' => true,
            'year' => $currentYear,
            'description' => 'Special non-working holiday example - EDSA Revolution Anniversary',
        ]);
    }

    /**
     * Create default suspension types
     */
    private function createDefaultSuspensions(Company $company)
    {
        $currentDate = date('Y-m-d');
        $suspensions = [
            [
                'type' => 'partial_suspension',
                'name' => 'Partial Suspension',
                'code' => 'PARTIAL_SUSP_' . strtoupper($company->code),
                'description' => 'Partial suspension of work - configure pay rule as needed',
            ],
            [
                'type' => 'full_day_suspension',
                'name' => 'Full Suspension',
                'code' => 'FULL_SUSP_' . strtoupper($company->code),
                'description' => 'Full suspension of work - configure pay rule as needed',
            ],
        ];

        foreach ($suspensions as $suspension) {
            \App\Models\NoWorkSuspendedSetting::create([
                'company_id' => $company->id,
                'type' => $suspension['type'],
                'name' => $suspension['name'],
                'code' => $suspension['code'],
                'description' => $suspension['description'],
                'date_from' => $currentDate,
                'date_to' => $currentDate,
                'is_paid' => false,
                'status' => 'active',
            ]);
        }
    }
}
