<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('companies')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['company_id']);
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};
