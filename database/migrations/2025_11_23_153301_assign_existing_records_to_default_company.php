<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update all existing records to belong to the default company (ID 1).
     * This ensures old/existing data doesn't show up for newly created companies.
     */
    public function up(): void
    {
        // Get default company ID (should be 1)
        $defaultCompanyId = DB::table('companies')->where('code', 'DEFAULT')->value('id') ?? 1;

        // List of all tables with company_id that need to be updated
        $tables = [
            // Core employee/user tables
            'users',
            'employees',
            'departments',
            'positions',

            // Pay schedule settings
            'pay_schedules',

            // Deduction & Tax settings
            'deduction_tax_settings',

            // Allowance & Bonus settings
            'allowance_bonus_settings',

            // Leave settings
            'paid_leave_settings',

            // Holiday settings
            'holidays',

            // Suspension/No work settings
            'no_work_suspended_settings',

            // Rate configurations
            'payroll_rate_configurations',

            // Time & attendance settings
            'grace_period_settings',
            'night_differential_settings',

            // Employer/company settings
            'employer_settings',

            // BIR 2316 settings
            'bir_2316_settings',
            'bir2316_settings',

            // Other settings tables (if they exist)
            'payroll_settings',
            'deduction_settings',
            'payroll_schedule_settings',
            'pay_schedule_settings',
            'settings',
            'time_configuration_settings',
        ];

        foreach ($tables as $table) {
            // Check if table exists and has company_id column
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                // Update records where company_id is null or not set to default company
                $updated = DB::table($table)
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompanyId]);

                if ($updated > 0) {
                    echo "Updated {$updated} records in {$table} to default company\n";
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - we're just assigning existing data to default company
        // The data relationship should remain intact
    }
};
