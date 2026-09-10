<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menambahkan izin "manage integration".
 *
 * Sebelumnya menu Gateway & Staging hanya dijaga izin "view", sehingga peran
 * baca-saja (Viewer) tetap dapat menulis data lewat aksi di halaman tersebut.
 * Aksi tulisnya kini menuntut izin ini, jadi izinnya harus sudah ada sebelum
 * aplikasi versi baru dipakai — tanpa perlu menjalankan seeder secara manual.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage integration';

    /** Peran yang memang mengoperasikan gerbang integrasi. */
    private const ROLES = ['Super Admin', 'Admin FAT', 'Admin HRIS'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
