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
        // Add company_id to pay_schedules
        if (!Schema::hasColumn('pay_schedules', 'company_id')) {
            Schema::table('pay_schedules', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('pay_schedules')->whereNull('company_id')->update(['company_id' => 1]);
        }

        // Add company_id to deduction_tax_settings if not exists
        if (!Schema::hasColumn('deduction_tax_settings', 'company_id')) {
            Schema::table('deduction_tax_settings', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('deduction_tax_settings')->whereNull('company_id')->update(['company_id' => 1]);
        }

        // Add company_id to allowance_bonus_settings if not exists
        if (!Schema::hasColumn('allowance_bonus_settings', 'company_id')) {
            Schema::table('allowance_bonus_settings', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('allowance_bonus_settings')->whereNull('company_id')->update(['company_id' => 1]);
        }

        // holidays table company_id was already added in previous migration
        // but ensure it's there
        if (Schema::hasTable('holidays') && !Schema::hasColumn('holidays', 'company_id')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('holidays')->whereNull('company_id')->update(['company_id' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pay_schedules', 'company_id')) {
            Schema::table('pay_schedules', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('deduction_tax_settings', 'company_id')) {
            Schema::table('deduction_tax_settings', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('allowance_bonus_settings', 'company_id')) {
            Schema::table('allowance_bonus_settings', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasTable('holidays') && Schema::hasColumn('holidays', 'company_id')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
