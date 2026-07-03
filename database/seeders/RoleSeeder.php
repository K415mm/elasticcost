<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Seed one user per role for testing.
     */
    public function run(): void
    {
        $roles = [
            'client' => 'Client User',
            'manager' => 'Manager User',
            'sales_manager' => 'Sales Manager User',
            'partner' => 'Partner User',
            'ceo' => 'CEO User',
        ];

        foreach ($roles as $role => $name) {
            User::firstOrCreate(
                ['email' => "{$role}@elasticcost.com"],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
