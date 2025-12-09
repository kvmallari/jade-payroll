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
        Schema::table('deduction_tax_settings', function (Blueprint $table) {
            // Make distribution_method nullable
            $table->enum('distribution_method', ['last_payroll', 'equally_distributed'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deduction_tax_settings', function (Blueprint $table) {
            // Revert back to not nullable (if needed)
            $table->enum('distribution_method', ['last_payroll', 'equally_distributed'])->nullable(false)->change();
        });
    }
};
