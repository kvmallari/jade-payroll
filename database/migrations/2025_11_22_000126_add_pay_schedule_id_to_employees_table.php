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
        Schema::table('employees', function (Blueprint $table) {
            // Add pay_schedule_id column
            $table->unsignedBigInteger('pay_schedule_id')->nullable()->after('pay_schedule');

            // Add foreign key constraint
            $table->foreign('pay_schedule_id')->references('id')->on('pay_schedules')->onDelete('set null');

            // Add index for performance
            $table->index('pay_schedule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['pay_schedule_id']);

            // Drop the column
            $table->dropColumn('pay_schedule_id');
        });
    }
};
