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
        // Clean postal_code data: set to null if longer than 4 chars or contains non-digits
        DB::table('employees')->where(function($query) {
            $query->whereRaw('LENGTH(postal_code) > 4')
                  ->orWhereRaw('postal_code REGEXP "[^0-9]"');
        })->update(['postal_code' => null]);
        
        // Set default postal code for entries that are null (you can change this default)
        DB::table('employees')->whereNull('postal_code')->update(['postal_code' => '0000']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data cleaning
    }
};
