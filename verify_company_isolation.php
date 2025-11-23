<?php

/**
 * Company Isolation Verification Script
 * 
 * Run this script to verify that company data isolation is working correctly
 * 
 * Usage: php artisan tinker < verify_company_isolation.php
 */

echo "\n========================================\n";
echo "Company Isolation Verification\n";
echo "========================================\n\n";

// Get companies
$companies = \App\Models\Company::all(['id', 'name', 'code']);

echo "Companies in Database:\n";
foreach ($companies as $company) {
    echo "  - ID: {$company->id}, Name: {$company->name}, Code: {$company->code}\n";
}

echo "\n----------------------------------------\n";
echo "Default Company (ID: 1) Data:\n";
echo "----------------------------------------\n";

$defaultCompanyId = 1;

echo "Departments: " . \App\Models\Department::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Positions: " . \App\Models\Position::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Pay Schedules: " . \App\Models\PaySchedule::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Holidays: " . \App\Models\Holiday::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Rate Configs: " . \App\Models\PayrollRateConfiguration::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Deductions: " . \App\Models\DeductionTaxSetting::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Allowances: " . \App\Models\AllowanceBonusSetting::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Leave Settings: " . \App\Models\PaidLeaveSetting::where('company_id', $defaultCompanyId)->count() . "\n";
echo "Suspensions: " . \App\Models\NoWorkSuspendedSetting::where('company_id', $defaultCompanyId)->count() . "\n";

echo "\n----------------------------------------\n";
echo "818 Cafe (ID: 5) Data:\n";
echo "----------------------------------------\n";

$cafeCompanyId = 5;

echo "Departments: " . \App\Models\Department::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Positions: " . \App\Models\Position::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Pay Schedules: " . \App\Models\PaySchedule::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Holidays: " . \App\Models\Holiday::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Rate Configs: " . \App\Models\PayrollRateConfiguration::where('company_id', $cafeCompanyId)->count() . " (Should be 5)\n";
echo "Deductions: " . \App\Models\DeductionTaxSetting::where('company_id', $cafeCompanyId)->count() . " (Should be 4 - govt deductions)\n";
echo "Allowances: " . \App\Models\AllowanceBonusSetting::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Leave Settings: " . \App\Models\PaidLeaveSetting::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";
echo "Suspensions: " . \App\Models\NoWorkSuspendedSetting::where('company_id', $cafeCompanyId)->count() . " (Should be 0)\n";

echo "\n----------------------------------------\n";
echo "Government Deductions Status (818 Cafe):\n";
echo "----------------------------------------\n";

$govDeductions = \App\Models\DeductionTaxSetting::where('company_id', $cafeCompanyId)
    ->select('name', 'is_active', 'type')
    ->get();

foreach ($govDeductions as $deduction) {
    $status = $deduction->is_active ? 'ACTIVE' : 'INACTIVE';
    echo "  - {$deduction->name}: {$status}\n";
}

echo "\n----------------------------------------\n";
echo "Rate Multipliers Status (818 Cafe):\n";
echo "----------------------------------------\n";

$rateConfigs = \App\Models\PayrollRateConfiguration::where('company_id', $cafeCompanyId)
    ->select('display_name', 'regular_rate_multiplier', 'is_active')
    ->get();

foreach ($rateConfigs as $config) {
    $rate = ($config->regular_rate_multiplier * 100);
    $status = $config->is_active ? 'ACTIVE' : 'INACTIVE';
    echo "  - {$config->display_name}: {$rate}% ({$status})\n";
}

echo "\n========================================\n";
echo "Verification Complete!\n";
echo "========================================\n\n";

echo "✅ Expected Results:\n";
echo "  - Default Company should have all existing data\n";
echo "  - 818 Cafe should have:\n";
echo "    • 0 departments (blank)\n";
echo "    • 0 positions (blank)\n";
echo "    • 0 pay schedules (blank)\n";
echo "    • 0 holidays (blank)\n";
echo "    • 5 rate configs (with 0% rates)\n";
echo "    • 4 government deductions (inactive)\n";
echo "    • 0 allowances (blank)\n";
echo "    • 0 leave settings (blank)\n";
echo "    • 0 suspensions (blank)\n\n";
