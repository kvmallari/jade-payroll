<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GrantSuperAdminPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:grant-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant all permissions to Super Admin role';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $allPermissions = Permission::all();

        $superAdmin->syncPermissions($allPermissions);

        $this->info("✅ Super Admin role now has {$allPermissions->count()} permissions.");

        return 0;
    }
}
