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
        Schema::table('payrolls', function (Blueprint $table) {
            // Change pay_schedule from ENUM to VARCHAR to support new schedule formats like SEMI-2, WEEKLY-1, etc.
            $table->string('pay_schedule', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Revert back to the original ENUM
            $table->enum('pay_schedule', ['weekly', 'semi_monthly', 'monthly'])->default('monthly')->change();
        });
    }
};
