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
        // Data capaian contoh hanya bila diminta (BSC_SEED_DEMO=true); bawaannya
        // pengguna mengisi sendiri seluruh angka lewat menu.
        if (config('bsc.seed_demo')) {
            $this->call(BscDataSeeder::class);
        } else {
            $this->command?->info('Data contoh dilewati (BSC_SEED_DEMO=false) — isi periode, target & realisasi lewat menu. Lihat Dokumentasi Metode.');
        }
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
