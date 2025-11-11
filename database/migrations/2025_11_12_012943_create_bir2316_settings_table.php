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
        Schema::create('bir2316_settings', function (Blueprint $table) {
            $table->id();
            $table->year('tax_year'); // Year for which these settings apply
            $table->decimal('statutory_minimum_wage_per_day', 10, 2)->nullable(); // Statutory Minimum Wage rate per day
            $table->decimal('statutory_minimum_wage_per_month', 10, 2)->nullable(); // Statutory Minimum Wage rate per month
            $table->string('period_from', 5)->nullable(); // Format: MM-DD (month and day only)
            $table->string('period_to', 5)->nullable(); // Format: MM-DD (month and day only)
            $table->string('place_of_issue')->nullable(); // Text input for place of issue
            $table->decimal('amount_paid_ctc', 12, 2)->nullable(); // Amount paid CTC
            $table->timestamps();

            // Make tax_year unique to prevent duplicate settings for the same year
            $table->unique('tax_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir2316_settings');
    }
};
