<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);

        // Get System Administrator role
        $systemAdminRole = Role::where('name', 'System Administrator')->first();

        if ($superAdminRole && $systemAdminRole) {
            // Copy all permissions from System Administrator to Super Admin
            $permissions = $systemAdminRole->permissions;
            $superAdminRole->syncPermissions($permissions);

            // Update user with ID 1 (superadmin@jadepayroll.com) to Super Admin role
            $superAdmin = User::find(1);
            if ($superAdmin) {
                // Remove all existing roles
                $superAdmin->syncRoles([]);
                // Assign Super Admin role
                $superAdmin->assignRole('Super Admin');

                $this->command->info("Updated {$superAdmin->email} to Super Admin role");
            }

            $this->command->info('Super Admin role created with all permissions');
        }

        // Note: "System Administrator" role is now for company admins
        // They will be assigned when creating company admin users
    }
}
