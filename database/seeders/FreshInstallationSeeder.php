<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\PayScheduleSetting;
use App\Models\PayrollScheduleSetting;
use App\Models\DeductionTaxSetting;
use App\Models\AllowanceBonusSetting;
use App\Models\PaidLeaveSetting;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use Illuminate\Support\Facades\Hash;
use App\Models\PayrollRateConfiguration;

class FreshInstallationSeeder extends Seeder
{
    /**
     * Run the database seeds for fresh installation.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting fresh installation seeding...');

        // 1. Create Permissions and Roles
        $this->createPermissionsAndRoles();

        // 2. Create System Administrator User
        $this->createSystemAdministrator();

        // 3. Create HR Head and HR Staff accounts
        $this->createHRAccounts();

        // 4. Seed Pay Schedule Settings
        $this->seedPayScheduleSettings();

        // 5. Seed Tax and Deduction Settings (with tax tables)
        $this->seedTaxAndDeductionSettings();

        // 6. Seed Allowance and Bonus Settings
        $this->seedAllowanceBonusSettings();

        // 7. Seed Paid Leave Settings
        $this->seedPaidLeaveSettings();

        // 8. Seed Holiday Settings
        $this->seedHolidaySettings();

        // 9. Seed Default Payroll Configuration Settings
        $this->seedPayrollConfigurationSettings();

        $this->command->info('✅ Fresh installation seeding completed!');
    }

    private function createPermissionsAndRoles(): void
    {
        $this->command->info('📋 Creating permissions and roles...');

        // Create all permissions
        $permissions = [
            // Dashboard
            'view dashboard',

            // Employees
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'manage employee documents',

            // Payrolls
            'view payrolls',
            'create payrolls',
            'edit payrolls',
            'delete payrolls',
            'approve payrolls',
            'process payrolls',
            'generate payslips',
            'send payslips',

            // Time & Attendance
            'view time logs',
            'create time logs',
            'edit time logs',
            'delete time logs',
            'approve time logs',
            'import time logs',

            // Leave Management
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',
            'approve leave requests',

            // Deductions & Advances
            'view deductions',
            'create deductions',
            'edit deductions',
            'delete deductions',
            'view cash advances',
            'create cash advances',
            'edit cash advances',
            'delete cash advances',
            'approve cash advances',

            // Schedules & Holidays
            'view schedules',
            'create schedules',
            'edit schedules',
            'delete schedules',
            'view holidays',
            'create holidays',
            'edit holidays',
            'delete holidays',

            // Organization
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view positions',
            'create positions',
            'edit positions',
            'delete positions',

            // Reports
            'view reports',
            'export reports',
            'generate reports',
            'view payroll reports',
            'view employee reports',
            'view financial reports',

            // Government Forms
            'view government forms',
            'generate government forms',
            'export government forms',
            'generate bir forms',
            'generate sss forms',
            'generate philhealth forms',
            'generate pagibig forms',

            // Settings & Administration
            'view settings',
            'edit settings',
            'view activity logs',

            // Profile
            'view own profile',
            'edit own profile',
            'view own payslips',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $systemAdmin = Role::firstOrCreate(['name' => 'System Administrator']);
        $hrHead = Role::firstOrCreate(['name' => 'HR Head']);
        $hrStaff = Role::firstOrCreate(['name' => 'HR Staff']);
        $employee = Role::firstOrCreate(['name' => 'Employee']);

        // Super Admin - All permissions (highest privilege)
        $superAdmin->syncPermissions(Permission::all());

        // System Administrator - All permissions
        $systemAdmin->syncPermissions(Permission::all());

        // HR Head - Same as System Admin (all permissions)
        $hrHead->syncPermissions(Permission::all());

        // HR Staff - All except employee creation/editing and settings
        $hrStaffPermissions = Permission::whereNotIn('name', [
            'create employees',
            'edit employees',
            'delete employees',
            'view settings',
            'edit settings'
        ])->get();
        $hrStaff->syncPermissions($hrStaffPermissions);

        // Employee - Basic permissions
        $employeePermissions = [
            'view dashboard',
            'view own profile',
            'edit own profile',
            'view own payslips',
            'view leave requests',
            'create leave requests'
        ];
        $employee->syncPermissions($employeePermissions);
    }

    private function createSystemAdministrator(): void
    {
        $this->command->info('👤 Creating System Administrator account...');

        $systemAdmin = User::firstOrCreate(
            ['email' => 'admin@payroll.com'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@payroll.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        $systemAdmin->assignRole('System Administrator');
    }

    private function createHRAccounts(): void
    {
        $this->command->info('👥 Creating HR accounts...');

        // HR Head
        $hrHead = User::firstOrCreate(
            ['email' => 'hr.head@payroll.com'],
            [
                'name' => 'HR Head',
                'email' => 'hr.head@payroll.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $hrHead->assignRole('HR Head');

        // HR Staff
        $hrStaff = User::firstOrCreate(
            ['email' => 'hr.staff@payroll.com'],
            [
                'name' => 'HR Staff',
                'email' => 'hr.staff@payroll.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $hrStaff->assignRole('HR Staff');
    }

    private function seedPayScheduleSettings(): void
    {
        $this->command->info('📅 Seeding pay schedule settings...');

        $schedules = [
            [
                'name' => 'Daily',
                'code' => 'daily',
                'description' => 'Daily payroll processing - pays current day',
                'cutoff_periods' => json_encode([]),
                'pay_day_offset' => 0,
                'pay_day_type' => 'fixed',
                'pay_day_weekday' => null,
                'move_if_holiday' => false,
                'move_if_weekend' => false,
                'move_direction' => 'before',
                'is_active' => true,
                'is_system_default' => true,
            ],
            [
                'name' => 'Weekly',
                'code' => 'weekly',
                'description' => 'Weekly payroll processing - Monday to Saturday, pay on Saturday',
                'cutoff_periods' => json_encode([
                    [
                        'start_day' => 'Monday',
                        'end_day' => 'Saturday',
                        'pay_day' => 'Saturday'
                    ]
                ]),
                'pay_day_offset' => 0,
                'pay_day_type' => 'fixed',
                'pay_day_weekday' => 6,
                'move_if_holiday' => false,
                'move_if_weekend' => false,
                'move_direction' => 'before',
                'is_active' => true,
                'is_system_default' => true,
            ],
            [
                'name' => 'Semi Monthly',
                'code' => 'semi_monthly',
                'description' => 'Semi-monthly payroll processing - 1st-15th and 16th-31st periods',
                'cutoff_periods' => json_encode([
                    [
                        'period' => '1st period',
                        'start_day' => '1st Day',
                        'end_day' => '15th Day',
                        'pay_day' => '15th Day'
                    ],
                    [
                        'period' => '2nd period',
                        'start_day' => '16th Day',
                        'end_day' => '31st Day',
                        'pay_day' => '31st Day'
                    ]
                ]),
                'pay_day_offset' => 0,
                'pay_day_type' => 'fixed',
                'pay_day_weekday' => null,
                'move_if_holiday' => false,
                'move_if_weekend' => false,
                'move_direction' => 'before',
                'is_active' => true,
                'is_system_default' => true,
            ],
            [
                'name' => 'Monthly',
                'code' => 'monthly',
                'description' => 'Monthly payroll processing - 1st to last day of month',
                'cutoff_periods' => json_encode([
                    [
                        'start_day' => '1st Day',
                        'end_day' => '31st Day',
                        'pay_day' => '31st Day'
                    ]
                ]),
                'pay_day_offset' => 0,
                'pay_day_type' => 'fixed',
                'pay_day_weekday' => null,
                'move_if_holiday' => false,
                'move_if_weekend' => false,
                'move_direction' => 'before',
                'is_active' => true,
                'is_system_default' => true,
            ],
        ];

        foreach ($schedules as $schedule) {
            PayScheduleSetting::firstOrCreate(
                ['code' => $schedule['code']],
                $schedule
            );
        }
    }

    private function seedTaxAndDeductionSettings(): void
    {
        $this->command->info('💰 Seeding tax and deduction settings with tax tables...');

        // SSS Contribution Table (keep the table for reference)
        $sssRates = [
            // Monthly Salary Range => [Employee Rate, Employer Rate, Total]
            [3250, 135, 380, 515],
            [3750, 157.50, 442.50, 600],
            [4250, 180, 505, 685],
            [4750, 202.50, 567.50, 770],
            [5250, 225, 630, 855],
            [5750, 247.50, 692.50, 940],
            [6250, 270, 755, 1025],
            [6750, 292.50, 817.50, 1110],
            [7250, 315, 880, 1195],
            [7750, 337.50, 942.50, 1280],
            [8250, 360, 1005, 1365],
            [8750, 382.50, 1067.50, 1450],
            [9250, 405, 1130, 1535],
            [9750, 427.50, 1192.50, 1620],
            [10250, 450, 1255, 1705],
            [10750, 472.50, 1317.50, 1790],
            [11250, 495, 1380, 1875],
            [11750, 517.50, 1442.50, 1960],
            [12250, 540, 1505, 2045],
            [12750, 562.50, 1567.50, 2130],
            [13250, 585, 1630, 2215],
            [13750, 607.50, 1692.50, 2300],
            [14250, 630, 1755, 2385],
            [14750, 652.50, 1817.50, 2470],
            [15250, 675, 1880, 2555],
            [15750, 697.50, 1942.50, 2640],
            [16250, 720, 2005, 2725],
            [16750, 742.50, 2067.50, 2810],
            [17250, 765, 2130, 2895],
            [17750, 787.50, 2192.50, 2980],
            [18250, 810, 2255, 3065],
            [18750, 832.50, 2317.50, 3150],
            [19250, 855, 2380, 3235],
            [19750, 877.50, 2442.50, 3320],
            [20000, 900, 2505, 3405], // Max for employees earning 20,000 and above
        ];

        // Create SSS Setting - ACTIVE with fixed 10 pesos
        DeductionTaxSetting::firstOrCreate(
            ['code' => 'sss'],
            [
                'name' => 'Social Security System (SSS)',
                'code' => 'sss',
                'description' => 'SSS contributions based on salary bracket table',
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'fixed_amount',
                'rate_percentage' => null,
                'fixed_amount' => 10.00,
                'bracket_rates' => json_encode($sssRates), // Keep tax table
                'minimum_amount' => null,
                'maximum_amount' => null,
                'salary_cap' => null,
                'apply_to_regular' => true,
                'apply_to_overtime' => false,
                'apply_to_bonus' => false,
                'apply_to_allowances' => false,
                'apply_to_basic_pay' => false,
                'apply_to_gross_pay' => true, // Deduct on gross
                'apply_to_taxable_income' => false,
                'apply_to_net_pay' => false,
                'employer_share_rate' => null,
                'employer_share_fixed' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 1,
            ]
        );

        // PhilHealth Contribution - ACTIVE with fixed 10 pesos
        DeductionTaxSetting::firstOrCreate(
            ['code' => 'philhealth'],
            [
                'name' => 'Philippine Health Insurance Corporation (PhilHealth)',
                'code' => 'philhealth',
                'description' => 'PhilHealth contributions: 3.25% shared equally between employee and employer',
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'fixed_amount',
                'rate_percentage' => null,
                'fixed_amount' => 10.00,
                'bracket_rates' => null,
                'minimum_amount' => null,
                'maximum_amount' => null,
                'salary_cap' => null,
                'apply_to_regular' => true,
                'apply_to_overtime' => false,
                'apply_to_bonus' => false,
                'apply_to_allowances' => false,
                'apply_to_basic_pay' => false,
                'apply_to_gross_pay' => true, // Deduct on gross
                'apply_to_taxable_income' => false,
                'apply_to_net_pay' => false,
                'employer_share_rate' => null,
                'employer_share_fixed' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 2,
            ]
        );

        // PagIBIG Contribution - ACTIVE with fixed 10 pesos
        DeductionTaxSetting::firstOrCreate(
            ['code' => 'pagibig'],
            [
                'name' => 'Home Development Mutual Fund (Pag-IBIG)',
                'code' => 'pagibig',
                'description' => 'Pag-IBIG contributions: 1% employee, 2% employer (max P100 for employee)',
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'fixed_amount',
                'rate_percentage' => null,
                'fixed_amount' => 10.00,
                'bracket_rates' => null,
                'minimum_amount' => null,
                'maximum_amount' => null,
                'salary_cap' => null,
                'apply_to_regular' => true,
                'apply_to_overtime' => false,
                'apply_to_bonus' => false,
                'apply_to_allowances' => false,
                'apply_to_basic_pay' => false,
                'apply_to_gross_pay' => true, // Deduct on gross
                'apply_to_taxable_income' => false,
                'apply_to_net_pay' => false,
                'employer_share_rate' => null,
                'employer_share_fixed' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 3,
            ]
        );

        // Withholding Tax Table (Train Law) - Keep tax table but DISABLED
        $withholdingTaxTable = [
            // Annual Income Ranges and Tax Rates
            ['min' => 0, 'max' => 250000, 'rate' => 0, 'base_tax' => 0],
            ['min' => 250001, 'max' => 400000, 'rate' => 0.15, 'base_tax' => 0],
            ['min' => 400001, 'max' => 800000, 'rate' => 0.20, 'base_tax' => 22500],
            ['min' => 800001, 'max' => 2000000, 'rate' => 0.25, 'base_tax' => 102500],
            ['min' => 2000001, 'max' => 8000000, 'rate' => 0.30, 'base_tax' => 402500],
            ['min' => 8000001, 'max' => null, 'rate' => 0.35, 'base_tax' => 2202500],
        ];

        // Withholding Tax - ACTIVE with fixed 10 pesos on taxable income
        DeductionTaxSetting::firstOrCreate(
            ['code' => 'withholding_tax'],
            [
                'name' => 'Withholding Tax (Train Law)',
                'code' => 'withholding_tax',
                'description' => 'Income tax based on Train Law tax brackets',
                'type' => 'government',
                'category' => 'mandatory',
                'calculation_type' => 'fixed_amount',
                'rate_percentage' => null,
                'fixed_amount' => 10.00,
                'bracket_rates' => json_encode($withholdingTaxTable), // Keep tax table
                'minimum_amount' => null,
                'maximum_amount' => null,
                'salary_cap' => null,
                'apply_to_regular' => true,
                'apply_to_overtime' => true,
                'apply_to_bonus' => true,
                'apply_to_allowances' => true,
                'apply_to_basic_pay' => false,
                'apply_to_gross_pay' => false,
                'apply_to_taxable_income' => true, // Deduct on taxable
                'apply_to_net_pay' => false,
                'employer_share_rate' => null,
                'employer_share_fixed' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 4,
            ]
        );
    }

    private function seedPayrollConfigurationSettings(): void
    {
        $this->command->info('⚙️ Seeding default payroll configuration settings...');

        // Seed default rate configurations
        $configurations = PayrollRateConfiguration::getDefaults();
        foreach ($configurations as $config) {
            PayrollRateConfiguration::updateOrCreate(
                ['type_name' => $config['type_name']],
                $config
            );
        }
        $this->command->info('   ✓ Rate multiplier configurations seeded');

        // Create a basic payroll setting with default values
        PayrollSetting::firstOrCreate(
            ['payroll_frequency' => 'monthly'],
            [
                'payroll_frequency' => 'monthly',
                'payroll_periods' => json_encode([
                    'start_day' => 1,
                    'end_day' => 31,
                    'pay_day' => 30,
                ]),
                'pay_delay_days' => 0,
                'adjust_for_weekends' => true,
                'adjust_for_holidays' => true,
                'weekend_adjustment' => 'before',
                'notes' => 'Default monthly payroll configuration for fresh installation',
                'is_active' => true,
                'created_by' => 1, // System Administrator
                'updated_by' => 1,
            ]
        );

        $this->command->info('   ✓ Basic payroll configuration created');
        $this->command->info('   ℹ️ Configure additional settings in the admin panel');
    }

    private function seedAllowanceBonusSettings(): void
    {
        $this->command->info('💰 Seeding allowance and bonus settings...');

        // Common Allowances - ACTIVE
        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'rice_allowance'],
            [
                'name' => 'Rice Allowance',
                'code' => 'rice_allowance',
                'description' => 'Monthly rice subsidy allowance',
                'type' => 'allowance',
                'category' => 'regular',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 2000.00,
                'is_taxable' => false,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => true,
                'apply_to_rest_days' => true,
                'frequency' => 'monthly',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 1,
            ]
        );

        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'transportation_allowance'],
            [
                'name' => 'Transportation Allowance',
                'code' => 'transportation_allowance',
                'description' => 'Daily transportation allowance',
                'type' => 'allowance',
                'category' => 'regular',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 200.00,
                'is_taxable' => true,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => true,
                'apply_to_rest_days' => false,
                'frequency' => 'daily',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => 22, // Max working days per month
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 2,
            ]
        );

        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'meal_allowance'],
            [
                'name' => 'Meal Allowance',
                'code' => 'meal_allowance',
                'description' => 'Daily meal allowance',
                'type' => 'allowance',
                'category' => 'regular',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 100.00,
                'is_taxable' => false,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => true,
                'apply_to_rest_days' => false,
                'frequency' => 'daily',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => 22,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 3,
            ]
        );

        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'clothing_allowance'],
            [
                'name' => 'Clothing Allowance',
                'code' => 'clothing_allowance',
                'description' => 'Annual clothing/uniform allowance',
                'type' => 'allowance',
                'category' => 'regular',
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 5000.00,
                'is_taxable' => false,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => false,
                'apply_to_rest_days' => false,
                'frequency' => 'annually',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => null,
                'is_active' => false, // DISABLED by default
                'is_system_default' => true,
                'sort_order' => 4,
            ]
        );

        // Common Bonuses - ACTIVE
        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => '13th_month_bonus'],
            [
                'name' => '13th Month Pay',
                'code' => '13th_month_bonus',
                'description' => 'Mandatory 13th month pay bonus',
                'type' => 'bonus',
                'category' => 'regular',
                'calculation_type' => 'percentage',
                'rate_percentage' => 100.0, // 100% of monthly basic salary
                'is_taxable' => true,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => false,
                'apply_to_rest_days' => false,
                'frequency' => 'annually',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 1,
            ]
        );

        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'performance_bonus'],
            [
                'name' => 'Performance Bonus',
                'code' => 'performance_bonus',
                'description' => 'Performance-based incentive bonus',
                'type' => 'bonus',
                'category' => 'conditional',
                'calculation_type' => 'percentage',
                'rate_percentage' => 10.0, // 10% of basic salary
                'is_taxable' => true,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => false,
                'apply_to_rest_days' => false,
                'frequency' => 'quarterly',
                'minimum_amount' => 1000.00,
                'maximum_amount' => 50000.00,
                'max_days_per_period' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 2,
            ]
        );

        \App\Models\AllowanceBonusSetting::firstOrCreate(
            ['code' => 'christmas_bonus'],
            [
                'name' => 'Christmas Bonus',
                'code' => 'christmas_bonus',
                'description' => 'Year-end Christmas bonus',
                'type' => 'bonus',
                'category' => 'one_time', // Changed from 'seasonal' to 'one_time'
                'calculation_type' => 'fixed_amount',
                'fixed_amount' => 5000.00,
                'is_taxable' => true,
                'apply_to_regular_days' => true,
                'apply_to_overtime' => false,
                'apply_to_holidays' => false,
                'apply_to_rest_days' => false,
                'frequency' => 'annually',
                'minimum_amount' => null,
                'maximum_amount' => null,
                'max_days_per_period' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 3,
            ]
        );
    }

    private function seedPaidLeaveSettings(): void
    {
        $this->command->info('🏖️ Seeding paid leave settings...');

        // Vacation Leave - ACTIVE
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'VL'],
            [
                'name' => 'Vacation Leave',
                'code' => 'VL',
                'description' => 'Annual vacation leave entitlement',
                'days_per_year' => 15,
                'accrual_method' => 'monthly',
                'accrual_rate' => 1.25, // 15 days / 12 months
                'minimum_service_months' => 6,
                'prorated_first_year' => true,
                'minimum_days_usage' => 1,
                'maximum_days_usage' => 0, // No limit
                'notice_days_required' => 3,
                'can_carry_over' => true,
                'max_carry_over_days' => 5,
                'expires_annually' => true,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => null, // All genders
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 1,
            ]
        );

        // Sick Leave - ACTIVE
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'SL'],
            [
                'name' => 'Sick Leave',
                'code' => 'SL',
                'description' => 'Annual sick leave entitlement',
                'days_per_year' => 15,
                'accrual_method' => 'monthly',
                'accrual_rate' => 1.25,
                'minimum_service_months' => 1,
                'prorated_first_year' => true,
                'minimum_days_usage' => 1,
                'maximum_days_usage' => 0,
                'notice_days_required' => 0, // Can be taken without advance notice
                'can_carry_over' => true,
                'max_carry_over_days' => 5,
                'expires_annually' => true,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => null,
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 2,
            ]
        );

        // Maternity Leave - DISABLED by default
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'ML'],
            [
                'name' => 'Maternity Leave',
                'code' => 'ML',
                'description' => 'Maternity leave benefits (105 days)',
                'days_per_year' => 105, // 15 weeks
                'accrual_method' => 'yearly',
                'accrual_rate' => 105.0,
                'minimum_service_months' => 6,
                'prorated_first_year' => false,
                'minimum_days_usage' => 60, // Minimum maternity leave period
                'maximum_days_usage' => 105,
                'notice_days_required' => 30,
                'can_carry_over' => false,
                'max_carry_over_days' => 0,
                'expires_annually' => false,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => json_encode(['female']), // Female only
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 3,
            ]
        );

        // Paternity Leave - DISABLED by default
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'PL'],
            [
                'name' => 'Paternity Leave',
                'code' => 'PL',
                'description' => 'Leave for male employees during spouse childbirth (7 days)',
                'days_per_year' => 7,
                'accrual_method' => 'yearly',
                'accrual_rate' => 7,
                'minimum_service_months' => 0,
                'prorated_first_year' => false,
                'minimum_days_usage' => 1,
                'maximum_days_usage' => 7,
                'notice_days_required' => 30,
                'can_carry_over' => false,
                'max_carry_over_days' => 0,
                'expires_annually' => false,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => json_encode(['male']), // Male only
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 4,
            ]
        );

        // Emergency Leave - DISABLED by default
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'EL'],
            [
                'name' => 'Emergency Leave',
                'code' => 'EL',
                'description' => 'Emergency leave for unforeseen circumstances (5 days)',
                'days_per_year' => 5,
                'accrual_method' => 'yearly',
                'accrual_rate' => 5,
                'minimum_service_months' => 3,
                'prorated_first_year' => true,
                'minimum_days_usage' => 1,
                'maximum_days_usage' => 5,
                'notice_days_required' => 0, // Can be used immediately for emergencies
                'can_carry_over' => false,
                'max_carry_over_days' => 0,
                'expires_annually' => true,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => null, // All genders
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 5,
            ]
        );

        // Bereavement Leave - DISABLED by default
        \App\Models\PaidLeaveSetting::firstOrCreate(
            ['code' => 'BL'],
            [
                'name' => 'Bereavement Leave',
                'code' => 'BL',
                'description' => 'Leave for death of immediate family member (3 days)',
                'days_per_year' => 3,
                'accrual_method' => 'yearly',
                'accrual_rate' => 3,
                'minimum_service_months' => 0,
                'prorated_first_year' => false,
                'minimum_days_usage' => 1,
                'maximum_days_usage' => 3,
                'notice_days_required' => 0, // Can be used immediately
                'can_carry_over' => false,
                'max_carry_over_days' => 0,
                'expires_annually' => true,
                'expiry_month' => 12,
                'can_convert_to_cash' => false,
                'cash_conversion_rate' => 1.0,
                'max_convertible_days' => 0,
                'applicable_gender' => null, // All genders
                'applicable_employment_types' => null,
                'applicable_employment_status' => null,
                'is_active' => true, // ACTIVE
                'is_system_default' => true,
                'sort_order' => 6,
            ]
        );
    }

    private function seedHolidaySettings(): void
    {
        $this->command->info('🎉 Seeding holiday settings...');

        // Regular Holidays (All ACTIVE)
        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-01-01'],
            [
                'name' => 'New Year\'s Day',
                'date' => '2025-01-01',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'New Year celebration',
                'is_recurring' => true,
                'is_active' => true, // ACTIVE
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-04-09'],
            [
                'name' => 'Araw ng Kagitingan (Day of Valor)',
                'date' => '2025-04-09',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'Day of Valor - commemorates the fall of Bataan',
                'is_recurring' => true,
                'is_active' => true, // ACTIVE
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-05-01'],
            [
                'name' => 'Labor Day',
                'date' => '2025-05-01',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'International Workers Day',
                'is_recurring' => true,
                'is_active' => true, // ACTIVE
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-06-12'],
            [
                'name' => 'Independence Day',
                'date' => '2025-06-12',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'Philippine Independence Day',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-08-26'],
            [
                'name' => 'National Heroes Day',
                'date' => '2025-08-26',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'National Heroes Day (Last Monday of August)',
                'is_recurring' => false, // Date varies
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-11-30'],
            [
                'name' => 'Bonifacio Day',
                'date' => '2025-11-30',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'Andres Bonifacio Day',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-12-25'],
            [
                'name' => 'Christmas Day',
                'date' => '2025-12-25',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'Christmas celebration',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-12-30'],
            [
                'name' => 'Rizal Day',
                'date' => '2025-12-30',
                'type' => 'regular',
                'rate_multiplier' => 2.00,
                'is_double_pay' => true,
                'double_pay_rate' => 2.00,
                'pay_rule' => 'double_pay',
                'description' => 'National hero Jose Rizal commemoration',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        // Special Non-working Holidays (All DISABLED by default)
        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-01-29'],
            [
                'name' => 'Chinese New Year',
                'date' => '2025-01-29',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Chinese New Year celebration',
                'is_recurring' => false, // Date changes yearly
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-04-17'],
            [
                'name' => 'Maundy Thursday',
                'date' => '2025-04-17',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Holy Week observance',
                'is_recurring' => false, // Date changes yearly
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-04-18'],
            [
                'name' => 'Good Friday',
                'date' => '2025-04-18',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Holy Week observance',
                'is_recurring' => false,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-08-21'],
            [
                'name' => 'Ninoy Aquino Day',
                'date' => '2025-08-21',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Commemoration of Ninoy Aquino',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-11-01'],
            [
                'name' => 'All Saints Day',
                'date' => '2025-11-01',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'All Saints Day',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-12-08'],
            [
                'name' => 'Feast of the Immaculate Conception',
                'date' => '2025-12-08',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Feast of the Immaculate Conception',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );

        \App\Models\Holiday::firstOrCreate(
            ['date' => '2025-12-31'],
            [
                'name' => 'New Year\'s Eve',
                'date' => '2025-12-31',
                'type' => 'special_non_working',
                'rate_multiplier' => 1.30,
                'is_double_pay' => false,
                'double_pay_rate' => 1.30,
                'pay_rule' => 'holiday_rate',
                'description' => 'Last Day of the Year',
                'is_recurring' => true,
                'is_active' => false, // DISABLED by default
                'year' => 2025,
            ]
        );
    }
}
