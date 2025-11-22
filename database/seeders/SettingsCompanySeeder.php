<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class SettingsCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the default company
        $defaultCompany = Company::where('code', 'DEFAULT')->first();

        if (!$defaultCompany) {
            $this->command->error('Default company not found. Please run DefaultCompanySeeder first.');
            return;
        }

        $settingsTables = [
            'payroll_settings',
            'deduction_settings',
            'payroll_schedule_settings',
            'pay_schedule_settings',
            'deduction_tax_settings',
            'allowance_bonus_settings',
            'paid_leave_settings',
            'no_work_suspended_settings',
            'settings',
            'time_configuration_settings',
            'grace_period_settings',
            'night_differential_settings',
            'employer_settings',
            'bir2316_settings',
        ];

        foreach ($settingsTables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $updated = DB::table($table)
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompany->id]);

                $this->command->info("Updated {$updated} records in {$table}");
            }
        }

        $this->command->info('All settings have been assigned to the default company.');
    }
}
