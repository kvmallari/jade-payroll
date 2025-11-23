<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class SetAuthorizedEmailForExistingUsers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update all users who don't have authorized_email set
        $users = User::whereNull('authorized_email')->get();

        foreach ($users as $user) {
            $user->update([
                'authorized_email' => $user->email
            ]);
            $this->command->info("Set authorized_email for: {$user->email}");
        }

        $this->command->info("Updated {$users->count()} users with authorized_email");
    }
}
