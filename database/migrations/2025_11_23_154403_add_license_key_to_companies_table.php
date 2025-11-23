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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('license_key')->nullable()->unique()->after('code');
        });

        // Generate license keys for existing companies
        $companies = DB::table('companies')->whereNull('license_key')->get();
        foreach ($companies as $company) {
            $licenseKey = 'LIC-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
            DB::table('companies')->where('id', $company->id)->update(['license_key' => $licenseKey]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('license_key');
        });
    }
};
