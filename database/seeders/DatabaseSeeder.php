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
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt(env('TEST_USER_PASSWORD') ?: 'Bsc#Test2026'),
            ]
        );

        $this->call(EntityStructureSeeder::class);
        $this->call(RatioCatalogSeeder::class);
        $this->call(AccountPostRoleSeeder::class);
        $this->call(BscDataSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
