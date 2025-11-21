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
        Schema::create('pay_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Custom name given by user (e.g., "Dev Team Schedule", "Office Staff Schedule")
            $table->enum('type', ['weekly', 'semi_monthly', 'monthly']); // Base type
            $table->text('description')->nullable(); // User's description

            // Schedule Configuration - JSON structure for flexibility
            $table->json('cutoff_periods'); // Array of cutoff period configs

            // Holiday & Weekend Settings
            $table->boolean('move_if_holiday')->default(true);
            $table->boolean('move_if_weekend')->default(true);
            $table->enum('move_direction', ['before', 'after'])->default('before');

            // Status & Control
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Only one per type can be default
            $table->integer('sort_order')->default(0);

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index('is_active');
            $table->index('is_default');

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_schedules');
    }
};
