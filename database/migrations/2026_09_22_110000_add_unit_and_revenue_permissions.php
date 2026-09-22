<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin untuk dua menu baru:
 *   - "manage units"   — mengelola unit kerja entitas (Super Admin, Admin HRIS)
 *   - "manage revenue" — mengisi target & realisasi revenue L1 (Super Admin, Admin FAT)
 *
 * Dipasang lewat migrasi agar langsung berlaku tanpa menjalankan seeder manual.
 */
return new class extends Migration
{
    private const IZIN = [
        'manage units' => ['Super Admin', 'Admin HRIS'],
        'manage revenue' => ['Super Admin', 'Admin FAT'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::IZIN as $nama => $peran) {
            $izin = Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);

            foreach ($peran as $namaPeran) {
                $role = Role::where('name', $namaPeran)->where('guard_name', 'web')->first();

                if ($role && ! $role->hasPermissionTo($izin)) {
                    $role->givePermissionTo($izin);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', array_keys(self::IZIN))->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
