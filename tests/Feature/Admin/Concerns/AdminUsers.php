<?php

namespace Tests\Feature\Admin\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class AdminUsers
{
    public static function admin(): User
    {
        return User::create([
            'name' => 'admin',
            'email' => 'admin@lerama.local',
            'password' => Hash::make('strong-password-123'),
        ]);
    }
}
