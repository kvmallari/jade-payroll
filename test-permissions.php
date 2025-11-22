<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=== SUPER ADMIN vs SYSTEM ADMIN COMPARISON ===\n\n";

$superAdmin = Spatie\Permission\Models\Role::where('name', 'Super Admin')->first();
$systemAdmin = Spatie\Permission\Models\Role::where('name', 'System Administrator')->first();

echo "Super Admin Permissions: " . $superAdmin->permissions->count() . "\n";
echo "System Admin Permissions: " . $systemAdmin->permissions->count() . "\n\n";

echo "=== COMPANY MANAGEMENT PERMISSIONS ===\n";
$companyPerms = ['view companies', 'create companies', 'edit companies', 'delete companies', 'view all companies', 'manage all users'];

foreach ($companyPerms as $perm) {
    echo sprintf(
        "%-25s | Super Admin: %-3s | System Admin: %-3s\n",
        $perm,
        $superAdmin->hasPermissionTo($perm) ? 'Yes' : 'No',
        $systemAdmin->hasPermissionTo($perm) ? 'Yes' : 'No'
    );
}

echo "\n=== USER ACCOUNT CHECK ===\n";
$user = App\Models\User::find(1);
echo "User: " . $user->name . "\n";
echo "Email: " . $user->email . "\n";
echo "Role Field: " . $user->role . "\n";
echo "Spatie Role: " . $user->roles->first()->name . "\n";
echo "isSuperAdmin(): " . ($user->isSuperAdmin() ? 'Yes' : 'No') . "\n";
echo "Total Permissions: " . $user->getAllPermissions()->count() . "\n";
echo "Can create companies: " . ($user->can('create companies') ? 'Yes' : 'No') . "\n";
echo "Can view all companies: " . ($user->can('view all companies') ? 'Yes' : 'No') . "\n";
echo "Can manage all users: " . ($user->can('manage all users') ? 'Yes' : 'No') . "\n";
echo "\n";
