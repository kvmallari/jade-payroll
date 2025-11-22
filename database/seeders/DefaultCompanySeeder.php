<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default company
        $defaultCompany = Company::create([
            'name' => 'Default Company',
            'code' => 'DEFAULT',
            'description' => 'Default company for existing users and employees',
            'is_active' => true,
        ]);

        // Update all existing users without a company to the default company
        User::whereNull('company_id')->update([
            'company_id' => $defaultCompany->id
        ]);

        // Update all existing employees without a company to the default company
        Employee::whereNull('company_id')->update([
            'company_id' => $defaultCompany->id
        ]);

        $this->command->info('Default company created and assigned to existing users and employees.');
    }
}
