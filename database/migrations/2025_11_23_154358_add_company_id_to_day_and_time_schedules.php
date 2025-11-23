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
        // Add company_id to day_schedules
        if (Schema::hasTable('day_schedules') && !Schema::hasColumn('day_schedules', 'company_id')) {
            Schema::table('day_schedules', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('day_schedules')->whereNull('company_id')->update(['company_id' => 1]);
        }

        // Add company_id to time_schedules
        if (Schema::hasTable('time_schedules') && !Schema::hasColumn('time_schedules', 'company_id')) {
            Schema::table('time_schedules', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });

            // Set existing records to default company (ID 1)
            DB::table('time_schedules')->whereNull('company_id')->update(['company_id' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('day_schedules', 'company_id')) {
            Schema::table('day_schedules', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('time_schedules', 'company_id')) {
            Schema::table('time_schedules', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
