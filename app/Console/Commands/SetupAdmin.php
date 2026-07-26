<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupAdmin extends Command
{
    protected $signature = 'lerama:setup-admin';

    protected $description = 'Create or update the admin user from the ADMIN_* variables';

    public function handle(): int
    {
        $username = (string) config('lerama.admin.username', 'admin');
        $password = (string) config('lerama.admin.password', '');
        $email = (string) config('lerama.admin.email', '');

        if (strlen($password) < 8) {
            $this->warn('ADMIN_PASSWORD unset or shorter than 8 characters; admin not created.');

            return self::FAILURE;
        }

        if ($email === '') {
            $email = $username.'@lerama.local';
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $username,
                'password' => Hash::make($password),
            ]
        );

        $this->info("Admin [{$user->name}] configured ({$user->email}).");

        return self::SUCCESS;
    }
}
