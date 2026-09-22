<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Tanpa WithoutModelEvents: event "creating" dibutuhkan BelongsToEntity untuk
    // mengisi entity_id data contoh, dan AppSetting membersihkan cache-nya lewat event.

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
