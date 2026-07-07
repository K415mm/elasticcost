<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'role' => 'ceo',
            ]
        );

        User::firstOrCreate(
            ['email' => 'demo@elasticcost.com'],
            [
                'name' => 'Demo Guest User',
                'password' => bcrypt('password'),
                'role' => 'ceo',
            ]
        );

        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SizingSeeder::class,
            MsspSeeder::class,
        ]);
    }
}
