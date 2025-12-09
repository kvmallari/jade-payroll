<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateLicenseKey extends Command
{
    protected $signature = 'license:generate 
                           {--employees= : Maximum number of employees allowed (required)}
                           {--price= : Price in Philippine Pesos (required)}
                           {--duration= : License duration in days (required)}
                           {--customer= : Customer name (required)}';

    protected $description = 'Generate a license key with subscription plan information';

    public function handle()
    {
        // Check if all required parameters are provided
        $employees = $this->option('employees');
        $price = $this->option('price');
        $duration = $this->option('duration');
        $customer = $this->option('customer');

        // Validate that all required parameters are provided
        if (empty($employees)) {
            $this->error('--employees parameter is required');
            return 1;
        }

        if (empty($price)) {
            $this->error('--price parameter is required');
            return 1;
        }

        if (empty($duration)) {
            $this->error('--duration parameter is required');
            return 1;
        }

        if (empty($customer)) {
            $this->error('--customer parameter is required');
            return 1;
        }

        // Convert to appropriate types after validation
        $employees = (int) $employees;
        $price = (float) $price;
        $duration = (int) $duration;

        // Validate input ranges
        if ($employees <= 0) {
            $this->error('Employee limit must be greater than 0');
            return 1;
        }

        if ($price <= 0) {
            $this->error('Price must be greater than 0');
            return 1;
        }

        if ($duration <= 0) {
            $this->error('Duration must be greater than 0 days');
            return 1;
        }

        // Generate license key with subscription plan information
        $licenseData = [
            'max_employees' => $employees,
            'price' => $price,
            'duration_days' => $duration,
            'currency' => 'PHP',
            'issued_at' => Carbon::now()->timestamp,
            'expires_at' => Carbon::now()->addDays($duration)->timestamp,
            'customer' => $customer,
            'features' => [
                'payroll_management',
                'employee_management',
                'time_tracking',
                'reports',
                'email_notifications'
            ],
            'version' => '2.0'
        ];

        $licenseKey = $this->generateLicenseKey($licenseData);

        // Save to database immediately
        $this->saveLicenseToDatabase($licenseKey, $licenseData);

        // Display results
        $this->info('License Key Generated Successfully!');
        $this->line('');
        $this->line('Customer: ' . ($customer ?: 'N/A'));
        $this->line('Max Employees: ' . $employees);
        $this->line('Price: ₱' . number_format($price, 2));
        $this->line('Duration: ' . $duration . ' days');
        $this->line('Valid Until: ' . Carbon::createFromTimestamp($licenseData['expires_at'])->format('Y-m-d H:i:s'));
        $this->line('');
        $this->line('LICENSE KEY:');
        $this->line('================================================================================');
        $this->info($licenseKey);
        $this->line('================================================================================');
        $this->line('');
        $this->line('<fg=green>✓</> License saved to database and available in system.');

        // Save to file for record keeping
        $this->saveLicenseRecord($licenseKey, $licenseData);

        return 0;
    }

    private function generateLicenseKey(array $data)
    {
        // Generate LIC-XXXXXXXX-XXXXXXXX format
        // Create a unique hash based on the license data
        $dataString = json_encode([
            'e' => $data['max_employees'],
            'p' => $data['price'],
            'd' => $data['duration_days'],
            't' => Carbon::now()->timestamp,
            'c' => $data['customer'],
            'r' => bin2hex(random_bytes(8)) // Add randomness
        ]);

        $secret = config('app.license_secret', config('app.key'));
        $hash = hash_hmac('sha256', $dataString, $secret);

        // Take first 8 and next 8 characters from hash, convert to uppercase
        $part1 = strtoupper(substr($hash, 0, 8));
        $part2 = strtoupper(substr($hash, 8, 8));

        // Format: LIC-XXXXXXXX-XXXXXXXX
        return "LIC-{$part1}-{$part2}";
    }

    private function saveLicenseRecord($licenseKey, $data)
    {
        $record = [
            'license_key' => $licenseKey,
            'generated_at' => Carbon::now()->toISOString(),
            'data' => $data
        ];

        $filename = 'licenses_' . date('Y-m') . '.json';
        $filepath = storage_path('app/licenses/' . $filename);

        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        // Load existing records
        $records = [];
        if (file_exists($filepath)) {
            $records = json_decode(file_get_contents($filepath), true) ?: [];
        }

        // Add new record
        $records[] = $record;

        // Save
        file_put_contents($filepath, json_encode($records, JSON_PRETTY_PRINT));

        $this->line("License record saved to: {$filepath}");
    }

    private function saveLicenseToDatabase($licenseKey, $data)
    {
        // Check if license already exists
        $existing = \App\Models\SystemLicense::where('license_key', $licenseKey)->first();

        if ($existing) {
            $this->warn('License key already exists in database.');
            return;
        }

        // Create new license record (not activated yet)
        \App\Models\SystemLicense::create([
            'license_key' => $licenseKey,
            'plan_info' => [
                'max_employees' => $data['max_employees'],
                'price' => $data['price'],
                'duration_days' => $data['duration_days'],
                'currency' => 'PHP',
                'customer' => $data['customer'],
                'features' => $data['features'],
            ],
            'is_active' => false, // Not active until used
            'activated_at' => null,
            'expires_at' => null, // Will be set when activated
            'server_fingerprint' => '', // Empty until activated on a server
        ]);
    }
}
