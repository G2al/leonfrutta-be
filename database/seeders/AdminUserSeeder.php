<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create (or refresh) the administrator account used to access the Filament panel.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@leonfrutta.it'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
