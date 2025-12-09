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
        if (!Schema::hasColumn('settings', 'company_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
                $table->index(['company_id', 'key']);
            });

            // Update existing settings to belong to default company
            $defaultCompany = DB::table('companies')->where('name', 'Default Company')->first();
            if ($defaultCompany) {
                DB::table('settings')
                    ->whereNull('company_id')
                    ->update(['company_id' => $defaultCompany->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'key']);
            $table->dropColumn('company_id');
        });
    }
};
