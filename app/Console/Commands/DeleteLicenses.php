<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SystemLicense;

class DeleteLicenses extends Command
{
    protected $signature = 'license:delete {--all : Delete all licenses}';

    protected $description = 'Delete license keys from the system';

    public function handle()
    {
        if ($this->option('all')) {
            return $this->deleteAllLicenses();
        }

        $this->error('Please specify --all flag to delete all licenses.');
        $this->info('Usage: php artisan license:delete --all');
        return 1;
    }

    private function deleteAllLicenses()
    {
        $count = SystemLicense::count();

        if ($count === 0) {
            $this->info('No licenses found to delete.');
            return 0;
        }

        $this->warn("You are about to delete {$count} license(s) from the system.");

        if (!$this->confirm('Are you sure you want to proceed?', false)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Delete all licenses
        SystemLicense::truncate();

        $this->info("Successfully deleted {$count} license(s).");

        return 0;
    }
}
