<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\n=== TESTING COMPANY INITIALIZATION ===\n\n";

// Create test company
$company = App\Models\Company::create([
    'name' => 'Test Company ABC',
    'code' => 'TESTABC',
    'is_active' => true,
]);

echo "Created Company: {$company->name} (ID: {$company->id})\n\n";

// Initialize company settings
$service = new App\Services\CompanyInitializationService();
$service->initializeCompanySettings($company);

echo "=== INITIALIZATION RESULTS ===\n";

// Check government deductions
$deductions = App\Models\DeductionTaxSetting::where('company_id', $company->id)->get();
echo "Government Deductions: " . $deductions->count() . "\n";
foreach ($deductions as $ded) {
    echo "  - {$ded->name} ({$ded->code})\n";
    echo "    Type: {$ded->type}, Tax Table: {$ded->tax_table_type}, Active: " . ($ded->is_active ? 'Yes' : 'No') . "\n";
}
echo "\n";

// Check rate multipliers
$rates = App\Models\PayrollRateConfiguration::where('company_id', $company->id)->get();
echo "Rate Multipliers: " . $rates->count() . "\n";
foreach ($rates as $rate) {
    echo "  - {$rate->display_name}: Regular={$rate->regular_rate_multiplier}, OT={$rate->overtime_rate_multiplier}\n";
}
echo "\n";

// Check other settings
echo "Grace Period Setting: " . (App\Models\GracePeriodSetting::where('company_id', $company->id)->exists() ? 'Created' : 'Missing') . "\n";
echo "Night Differential Setting: " . (App\Models\NightDifferentialSetting::where('company_id', $company->id)->exists() ? 'Created' : 'Missing') . "\n";
echo "Employer Setting: " . (App\Models\EmployerSetting::where('company_id', $company->id)->exists() ? 'Created' : 'Missing') . "\n";

echo "\n=== CLEANUP ===\n";
$company->delete();
echo "Test company deleted.\n\n";
