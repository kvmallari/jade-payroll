<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Dashboard
            'view dashboard',

            // Employee Management
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'manage employee documents',

            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',

            // Company Management
            'view companies',
            'create companies',
            'edit companies',
            'delete companies',
            'view all companies',
            'manage all users',

            // Payroll Management
            'view payrolls',
            'create payrolls',
            'edit payrolls',
            'delete payrolls',
            'delete approved payrolls',
            'approve payrolls',
            'process payrolls',
            'mark payrolls as paid',
            'generate payslips',
            'send payslips',
            'email payslip',
            'email all payslips',
            'download payslips',

            // Time Management
            'view time logs',
            'create time logs',
            'edit time logs',
            'delete time logs',
            'approve time logs',
            'import time logs',
            'view own time logs',
            'edit own time logs',

            // Leave Management
            'view leave requests',
            'create leave requests',
            'edit leave requests',
            'delete leave requests',
            'approve leave requests',
            'view own leave requests',
            'create own leave requests',

            // Deduction Management
            'view deductions',
            'create deductions',
            'edit deductions',
            'delete deductions',

            // Cash Advance Management
            'view cash advances',
            'create cash advances',
            'edit cash advances',
            'delete cash advances',
            'approve cash advances',
            'view own cash advances',
            'create own cash advances',

            // Paid Leave Management
            'view paid leaves',
            'create paid leaves',
            'edit paid leaves',
            'delete paid leaves',
            'approve paid leaves',
            'view own paid leaves',
            'create own paid leaves',

            // Schedule Management
            'view schedules',
            'create schedules',
            'edit schedules',
            'delete schedules',

            // Holiday Management
            'view holidays',
            'create holidays',
            'edit holidays',
            'delete holidays',

            // Department Management
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',

            // Position Management
            'view positions',
            'create positions',
            'edit positions',
            'delete positions',

            // Reports
            'view reports',
            'export reports',
            'generate reports',
            'view payroll reports',
            'view employee reports',
            'view financial reports',

            // Government Forms
            'view government forms',
            'generate government forms',
            'export government forms',
            'generate bir forms',
            'generate sss forms',
            'generate philhealth forms',
            'generate pagibig forms',

            // Settings
            'view settings',
            'edit settings',

            // Activity History
            'view activity logs',

            // Own Profile
            'view own profile',
            'edit own profile',
            'view own payslips',

            // Payslip Management
            'view payslips',
            'view all payslips',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - ABSOLUTE FULL ACCESS TO EVERYTHING
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->syncPermissions(Permission::all());

        // System Administrator - Full access EXCEPT company creation and viewing all users across companies
        $systemAdmin = Role::firstOrCreate(['name' => 'System Administrator']);
        $systemAdminPermissions = Permission::whereNotIn('name', [
            'view all companies',
            'create companies',
            'delete companies',
            'manage all users',
        ])->pluck('name')->toArray();
        $systemAdmin->syncPermissions($systemAdminPermissions);

        // HR Head - Full access to employees, deductions, holidays, approvals, settings
        $hrHead = Role::firstOrCreate(['name' => 'HR Head']);
        $hrHead->syncPermissions([
            'view dashboard',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'manage employee documents',
            'view payrolls',
            'create payrolls',
            'edit payrolls',
            'delete payrolls',
            'delete approved payrolls',
            'approve payrolls',
            'process payrolls',
            'mark payrolls as paid',
            'generate payslips',
            'send payslips',
            'email payslip',
            'email all payslips',
            'download payslips',
            'view time logs',
            'create time logs',
            'edit time logs',
            'approve time logs',
            'delete time logs',
            'import time logs',
            'view leave requests',
            'edit leave requests',
            'approve leave requests',
            'view deductions',
            'create deductions',
            'edit deductions',
            'delete deductions',
            'view cash advances',
            'create cash advances',
            'edit cash advances',
            'delete cash advances',
            'approve cash advances',
            'view paid leaves',
            'create paid leaves',
            'edit paid leaves',
            'delete paid leaves',
            'approve paid leaves',
            'view schedules',
            'create schedules',
            'edit schedules',
            'delete schedules',
            'view holidays',
            'create holidays',
            'edit holidays',
            'delete holidays',
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view positions',
            'create positions',
            'edit positions',
            'delete positions',
            'view reports',
            'export reports',
            'generate reports',
            'view payroll reports',
            'view employee reports',
            'view financial reports',
            'view government forms',
            'generate government forms',
            'export government forms',
            'generate bir forms',
            'generate sss forms',
            'generate philhealth forms',
            'generate pagibig forms',
            'view settings',
            'edit settings',
            'view activity logs',
            'view own profile',
            'edit own profile',
            'view own payslips',
            'view payslips',
            'view all payslips',
        ]);

        // HR Staff - Can create/send payroll and import time logs
        $hrStaff = Role::firstOrCreate(['name' => 'HR Staff']);
        $hrStaff->syncPermissions([
            'view dashboard',
            'view employees',
            'create employees',
            'edit employees',
            'manage employee documents',
            'view payrolls',
            'create payrolls',
            'edit payrolls',
            'process payrolls',
            'mark payrolls as paid',
            'approve payrolls',
            'delete payrolls',
            'email all payslips',
            'email payslip',
            'generate payslips',
            'send payslips',
            'download payslips',
            'view time logs',
            'create time logs',
            'edit time logs',
            'import time logs',
            'view leave requests',
            'view deductions',
            'view cash advances',
            'create cash advances',
            'edit cash advances',
            'delete cash advances',
            'view paid leaves',
            'create paid leaves',
            'edit paid leaves',
            'delete paid leaves',
            'view schedules',
            'view holidays',
            'view departments',
            'view positions',
            'view reports',
            'export reports',
            'view payroll reports',
            'view employee reports',
            'generate reports',
            'generate bir forms',
            'view own profile',
            'edit own profile',
            'view own payslips',
            'view payslips',
            'view all payslips',
        ]);

        // Employee - Can view DTR, payslip, request leave, and email own payslips
        $employee = Role::firstOrCreate(['name' => 'Employee']);
        $employee->syncPermissions([
            'view dashboard',
            'view own time logs',
            'edit own time logs',
            'view own leave requests',
            'create own leave requests',
            'view own cash advances',
            'create own cash advances',
            'view own paid leaves',
            'create own paid leaves',
            'view own profile',
            'edit own profile',
            'view own payslips',
            'email payslip', // Allow employees to email their own payslips
        ]);

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Created roles: System Admin, HR Head, HR Staff, Employee');
        $this->command->info('Total permissions: ' . count($permissions));
    }
}
