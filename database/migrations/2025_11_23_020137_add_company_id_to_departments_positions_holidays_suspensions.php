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
        // Add company_id to departments
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
        });

        // Add company_id to positions
        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasColumn('positions', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
        });

        // Add company_id to holidays
        if (Schema::hasTable('holidays')) {
            Schema::table('holidays', function (Blueprint $table) {
                if (!Schema::hasColumn('holidays', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        if (Schema::hasTable('holidays')) {
            Schema::table('holidays', function (Blueprint $table) {
                if (Schema::hasColumn('holidays', 'company_id')) {
                    $table->dropForeign(['company_id']);
                    $table->dropColumn('company_id');
                }
            });
        }
    }
};
