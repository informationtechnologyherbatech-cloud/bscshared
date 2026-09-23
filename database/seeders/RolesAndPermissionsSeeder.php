<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 13 Menu + granular per PRD §4 & §5 (Tabel 4: 6 peran x 13 menu)
        // Menu mapping: 1 Piramida, 2 Rasio, 3 Objective, 4 Wiring, 5 Dampak/What-If, 6 Simulasi CoA,
        // 7 ActionPlan, 8 IBP, 9 Sensitivitas, 10 Skenario, 11 Dokumentasi, 12 Gateway&Staging, 13 Admin
        $permissions = [
            // FR-01 .. FR-13 view
            'view dashboard',       // 1 Piramida
            'view ratios',          // 2
            'manage ratios',        // 2 write (Admin FAT, Super Admin)
            'view objectives',      // 3
            'manage objectives',    // 3 write = can_write_kpi
            'view wiring',          // 4
            'view dampak',          // 5 What-If Sandbox (can_simulate)
            'view coa',             // 6 Simulasi CoA
            'manage coa',           // 6 write
            'view actionplans',     // 7
            'manage actionplans',
            'view ibp',             // 8 Konsensus IBP
            'view sensitivity',     // 9 Sensitivitas
            'view skenario',        // 10 Skenario
            'manage skenario',
            'view dokumentasi',     // 11 Dokumentasi Metode
            'view integration',     // 12 Gateway
            'view staging',         // 12 Staging
            'view gateway',         // 12 alias umbrella
            'manage integration',   // 12 write: terima payload, simulasi inbound, unggah CSV
            // FR-13 Admin
            'manage units',         // unit kerja per entitas
            'manage revenue',       // target & realisasi revenue (L1)
            'view consolidation',   // konsolidasi holding (pengguna level holding)
            'manage consolidation', // eliminasi penjualan antarentitas
            'manage users',         // can_manage_users
            'manage settings',
            'manage apikey',
            'view systeminfo',
            // granular can_* (lintas-peran §4)
            'can_write_kpi',
            'can_simulate',
            'can_override',
            'can_manage_users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 6 Roles PRD Tabel 4 — Menu relevan counts must match spec
        $roles = [
            // 13/13 — Kontrol penuh
            'Super Admin' => $permissions,
            // 12/13 — semua kecuali Super Admin (FR-13 Admin). Kelola CoA, rasio, kaskade, stress-test
            'Admin FAT' => [
                'view dashboard', 'view ratios', 'manage ratios',
                'view objectives', 'manage objectives', 'can_write_kpi', 'can_override',
                'view wiring', 'view dampak', 'can_simulate', 'view coa', 'manage coa',
                'view actionplans', 'manage actionplans',
                'view ibp', 'view sensitivity', 'view skenario', 'manage skenario',
                'view dokumentasi',
                'view integration', 'view staging', 'view gateway', 'manage integration', 'manage apikey',
                'manage revenue',
                'view consolidation', 'manage consolidation',
            ],
            // 9/13 — Kelola Sasaran Mutu, pantau capaian, tindak lanjut program kerja (HRIS & Mutu)
            'Admin HRIS' => [
                'view dashboard',
                'view objectives', 'manage objectives', 'can_write_kpi',
                'view wiring',
                'view actionplans', 'manage actionplans',
                'view staging', 'view gateway', 'view integration', 'manage integration',
                'manage units',
                'view dokumentasi', 'view ibp',
            ],
            // 8/13 — Pantau skor unitnya, input realisasi KPI timnya, uji dampak
            'Kepala Departemen' => [
                'view dashboard', 'view ratios',
                'view objectives', 'manage objectives', 'can_write_kpi',
                'view wiring', 'view dampak', 'can_simulate',
                'view actionplans', 'manage actionplans',
                'view gateway', 'view staging',
                'view dokumentasi',
            ],
            // 4/13 — Input realisasi KPI, perbarui status program kerja (staf harian)
            'Operator' => [
                'view dashboard',
                'view objectives', 'manage objectives', 'can_write_kpi',
                'view actionplans', 'manage actionplans',
                'view wiring',
            ],
            // 7/13 baca-saja — Memantau skor dan tren tanpa risiko mengubah data
            'Viewer' => [
                'view dashboard', 'view ratios', 'view objectives', 'view wiring',
                'view staging', 'view gateway', 'view dokumentasi',
            ],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }

        // Ensure Super Admin user exists.
        // Kata sandi awal diambil dari SUPERADMIN_PASSWORD agar instalasi baru
        // tidak memakai kata sandi yang mudah ditebak. Akun yang sudah ada
        // tidak diubah (firstOrCreate).
        $superAdmin = User::firstOrCreate(
            ['email' => env('SUPERADMIN_EMAIL') ?: 'superadmin@emc.co.id'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt(env('SUPERADMIN_PASSWORD') ?: 'Bsc#Admin2026'),
                'is_active' => true,
                // Kata sandi bawaan tercantum di repositori → di server (bukan lokal/testing)
                // wajib diganti saat login pertama.
                'must_change_password' => ! env('SUPERADMIN_PASSWORD') && ! app()->environment(['local', 'testing']),
            ]
        );
        if (! $superAdmin->hasRole('Super Admin')) {
            $superAdmin->assignRole('Super Admin');
        }

        // Keep test@example.com as Viewer for testing
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser && ! $testUser->hasAnyRole(Role::all()->pluck('name')->toArray())) {
            $testUser->assignRole('Viewer');
        }

        // Pengaturan identitas entitas & aplikasi (default dari config/entity.php)
        $defaults = config('entity.defaults', []) + [
            'app_logo' => '',
            'app_favicon' => '',
        ];
        foreach ($defaults as $k => $v) {
            AppSetting::firstOrCreate(['key' => $k], ['value' => $v]);
        }
        AppSetting::flushCache();

        // Kunci API TIDAK dibuat otomatis: kunci hanya dapat dibaca sekali saat
        // dibuat, jadi kunci bawaan yang tak pernah terlihat hanya menambah
        // kredensial menganggur. Buat lewat Setting Sistem → tab API.
    }
}
