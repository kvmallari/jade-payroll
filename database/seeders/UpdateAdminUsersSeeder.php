<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use Spatie\Permission\Models\Role;

class UpdateAdminUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get default company
        $defaultCompany = Company::where('code', 'DEFAULT')->first();

        if (!$defaultCompany) {
            $this->command->error('Default company not found!');
            return;
        }

        // Update System Administrator (superadmin - sees all companies)
        $superadmin = User::find(1);
        if ($superadmin) {
            $superadmin->update([
                'name' => 'Super Administrator',
                'email' => 'superadmin@jadepayroll.com',
                'password' => Hash::make('password'),
                'company_id' => null, // No specific company - manages all
                'authorized_email' => 'superadmin@jadepayroll.com', // Lock email to prevent role manipulation
            ]);
            $this->command->info('Updated Super Administrator: superadmin@jadepayroll.com');
        }

        // Update HR Head for default company
        $hrHead = User::find(2);
        if ($hrHead) {
            $hrHead->update([
                'name' => 'HR Head - Default Company',
                'email' => 'hrhead.default@jadepayroll.com',
                'password' => Hash::make('password'),
                'company_id' => $defaultCompany->id,
                'authorized_email' => 'hrhead.default@jadepayroll.com', // Lock email to prevent role manipulation
            ]);
            $this->command->info('Updated HR Head: hrhead.default@jadepayroll.com');
        }

        // Update HR Staff for default company
        $hrStaff = User::find(3);
        if ($hrStaff) {
            $hrStaff->update([
                'name' => 'HR Staff - Default Company',
                'email' => 'hrstaff.default@jadepayroll.com',
                'password' => Hash::make('password'),
                'company_id' => $defaultCompany->id,
                'authorized_email' => 'hrstaff.default@jadepayroll.com', // Lock email to prevent role manipulation
            ]);
            $this->command->info('Updated HR Staff: hrstaff.default@jadepayroll.com');
        }

        $this->command->info('Admin users updated successfully!');
        $this->command->info('All passwords set to: password');
    }
}
