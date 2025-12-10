<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SystemLicense;
use Carbon\Carbon;

class ListLicenseKeys extends Command
{
    protected $signature = 'license:list {--active : Show only active licenses}';

    protected $description = 'List all license keys in the system';

    public function handle()
    {
        $activeOnly = $this->option('active');

        $query = SystemLicense::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $licenses = $query->orderBy('created_at', 'desc')->get();

        if ($licenses->isEmpty()) {
            $this->info('No license keys found.');
            return 0;
        }

        $this->info('License Keys:');
        $this->line('');

        $headers = ['ID', 'Customer', 'Max Employees', 'Price', 'Status', 'License Key', 'Used By', 'Activated At', 'Expires At'];
        $rows = [];

        foreach ($licenses as $license) {
            $planInfo = $license->plan_info ?? [];

            // Truncate license key for display
            $licenseKeyDisplay = $license->license_key;
            if (strlen($licenseKeyDisplay) > 40) {
                $licenseKeyDisplay = substr($licenseKeyDisplay, 0, 20) . '...' . substr($licenseKeyDisplay, -17);
            }

            // Check if license is being used by any company
            $usedBy = \App\Models\Company::where('license_key', $license->license_key)->value('name');

            $rows[] = [
                $license->id,
                $planInfo['customer'] ?? 'N/A',
                $planInfo['max_employees'] ?? 'N/A',
                isset($planInfo['price']) ? '₱' . number_format($planInfo['price'], 2) : 'N/A',
                $this->getStatusLabel($license, $usedBy),
                $licenseKeyDisplay,
                $usedBy ?? '<fg=gray>Not Used</>',
                $license->activated_at ? $license->activated_at->format('Y-m-d H:i') : 'Not Activated',
                $license->expires_at ? $license->expires_at->format('Y-m-d H:i') : 'N/A'
            ];
        }

        $this->line('');
        $this->line('Tip: Copy the full license key from the database or generation output.');

        $this->table($headers, $rows);

        return 0;
    }

    private function getStatusLabel($license, $usedBy = null)
    {
        // Check if license is expired
        if ($license->expires_at && $license->expires_at->isPast()) {
            return '<fg=red>Expired</>';
        }

        // If license is being used by a company, show as In-Use
        if ($usedBy) {
            return '<fg=green>In-Use</>';
        }

        // Not being used by any company
        return '<fg=gray>Not Used</>';
    }
}
