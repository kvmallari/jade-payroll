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
        Schema::table('payroll_rate_configurations', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->dropUnique(['type_name']); // Remove old unique constraint
            $table->unique(['company_id', 'type_name']); // Add composite unique
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_rate_configurations', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'type_name']);
            $table->unique('type_name');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
