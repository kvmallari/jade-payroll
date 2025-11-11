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
        Schema::table('bir2316_settings', function (Blueprint $table) {
            $table->date('date_signed_by_authorized_person')->nullable()->after('amount_paid_ctc');
            $table->date('date_signed_by_employee')->nullable()->after('date_signed_by_authorized_person');
            $table->date('date_issued')->nullable()->after('date_signed_by_employee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bir2316_settings', function (Blueprint $table) {
            $table->dropColumn([
                'date_signed_by_authorized_person',
                'date_signed_by_employee',
                'date_issued'
            ]);
        });
    }
};
