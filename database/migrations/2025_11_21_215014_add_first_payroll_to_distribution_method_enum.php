<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update allowance_bonus_settings enum to include 'first_payroll' option
        if (Schema::hasTable('allowance_bonus_settings') && Schema::hasColumn('allowance_bonus_settings', 'distribution_method')) {
            Schema::table('allowance_bonus_settings', function (Blueprint $table) {
                $table->enum('distribution_method', ['first_payroll', 'last_payroll', 'equally_distributed'])->default('last_payroll')->change();
            });
        }

        // Update deduction_tax_settings enum to include 'first_payroll' option
        Schema::table('deduction_tax_settings', function (Blueprint $table) {
            $table->enum('distribution_method', ['first_payroll', 'last_payroll', 'equally_distributed'])->default('last_payroll')->change();
        });

        // Update deduction_settings enum if the table exists
        if (Schema::hasTable('deduction_settings') && Schema::hasColumn('deduction_settings', 'distribution_method')) {
            Schema::table('deduction_settings', function (Blueprint $table) {
                $table->enum('distribution_method', ['first_payroll', 'last_payroll', 'equally_distributed'])->default('last_payroll')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, update any existing records with 'first_payroll' to 'last_payroll' as fallback
        DB::table('deduction_tax_settings')
            ->where('distribution_method', 'first_payroll')
            ->update(['distribution_method' => 'last_payroll']);

        // Update allowance_bonus_settings if it exists and has first_payroll records
        if (Schema::hasTable('allowance_bonus_settings') && Schema::hasColumn('allowance_bonus_settings', 'distribution_method')) {
            DB::table('allowance_bonus_settings')
                ->where('distribution_method', 'first_payroll')
                ->update(['distribution_method' => 'last_payroll']);
        }

        // Update deduction_settings if it exists and has first_payroll records
        if (Schema::hasTable('deduction_settings') && Schema::hasColumn('deduction_settings', 'distribution_method')) {
            DB::table('deduction_settings')
                ->where('distribution_method', 'first_payroll')
                ->update(['distribution_method' => 'last_payroll']);
        }

        // Revert enum changes to remove 'first_payroll' option
        Schema::table('deduction_tax_settings', function (Blueprint $table) {
            $table->enum('distribution_method', ['last_payroll', 'equally_distributed'])->default('last_payroll')->change();
        });

        if (Schema::hasTable('allowance_bonus_settings') && Schema::hasColumn('allowance_bonus_settings', 'distribution_method')) {
            Schema::table('allowance_bonus_settings', function (Blueprint $table) {
                $table->enum('distribution_method', ['last_payroll', 'equally_distributed'])->default('last_payroll')->change();
            });
        }

        if (Schema::hasTable('deduction_settings') && Schema::hasColumn('deduction_settings', 'distribution_method')) {
            Schema::table('deduction_settings', function (Blueprint $table) {
                $table->enum('distribution_method', ['last_payroll', 'equally_distributed'])->default('last_payroll')->change();
            });
        }
    }
};
