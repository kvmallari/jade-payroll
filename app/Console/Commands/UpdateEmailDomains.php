<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateEmailDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:update-domains {oldDomain} {newDomain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update email domains in users table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oldDomain = $this->argument('oldDomain');
        $newDomain = $this->argument('newDomain');

        $users = DB::table('users')->where('email', 'LIKE', '%@' . $oldDomain)->get();

        if ($users->isEmpty()) {
            $this->info('No emails found with domain @' . $oldDomain);
            return 0;
        }

        $this->info('Found ' . $users->count() . ' email(s) with domain @' . $oldDomain);

        foreach ($users as $user) {
            $newEmail = str_replace('@' . $oldDomain, '@' . $newDomain, $user->email);
            DB::table('users')->where('id', $user->id)->update(['email' => $newEmail]);
            $this->line('Updated: ' . $user->email . ' -> ' . $newEmail);
        }

        $this->info('Successfully updated ' . $users->count() . ' email address(es)');

        return 0;
    }
}
