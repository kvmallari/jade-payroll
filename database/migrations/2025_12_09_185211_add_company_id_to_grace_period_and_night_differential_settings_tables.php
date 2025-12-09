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
        // Add company_id to grace_period_settings
        if (Schema::hasTable('grace_period_settings') && !Schema::hasColumn('grace_period_settings', 'company_id')) {
            Schema::table('grace_period_settings', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('grace_period_settings')->whereNull('company_id')->update(['company_id' => 1]);
        }

        // Add company_id to night_differential_settings
        if (Schema::hasTable('night_differential_settings') && !Schema::hasColumn('night_differential_settings', 'company_id')) {
            Schema::table('night_differential_settings', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('night_differential_settings')->whereNull('company_id')->update(['company_id' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('grace_period_settings', 'company_id')) {
            Schema::table('grace_period_settings', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('night_differential_settings', 'company_id')) {
            Schema::table('night_differential_settings', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
