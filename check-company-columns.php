<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "\n=== CHECKING TABLES FOR company_id COLUMN ===\n\n";

$tables = [
    'deduction_tax_settings',
    'allowance_bonus_settings',
    'grace_period_settings',
    'night_differential_settings',
    'employer_settings',
    'payroll_rate_configurations',
    'paid_leave_settings',
    'holiday_settings',
    'suspension_types',
    'departments',
    'positions',
];

foreach ($tables as $table) {
    try {
        $hasColumn = Schema::hasColumn($table, 'company_id');
        echo sprintf("%-35s : %s\n", $table, $hasColumn ? '✓ HAS company_id' : '✗ MISSING company_id');
    } catch (Exception $e) {
        echo sprintf("%-35s : ✗ TABLE NOT FOUND\n", $table);
    }
}

echo "\n";
