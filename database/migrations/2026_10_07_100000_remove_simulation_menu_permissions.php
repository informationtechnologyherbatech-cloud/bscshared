<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuang izin lima menu simulasi yang dihapus.
 *
 * Menu Uji Dampak / What-If, Simulasi CoA, Konsensus IBP, Sensitivitas, dan
 * Skenario tidak pernah dibangun (kelimanya hanya halaman "segera hadir") dan
 * dipastikan tidak diperlukan. Rutenya sudah dibuang; izinnya ikut dibuang di
 * sini supaya basis data yang sudah berjalan tidak menyimpan izin yang tak
 * menjaga apa pun — izin yatim seperti itu masih tampil di layar Manajemen
 * Pengguna dan menyesatkan saat menyusun peran.
 *
 * 'can_simulate' ikut dibuang karena satu-satunya gunanya menemani menu Dampak.
 *
 * Menghapus baris di tabel permissions otomatis melepas kaitannya di
 * role_has_permissions dan model_has_permissions (foreign key cascade bawaan
 * spatie/laravel-permission), jadi tidak ada baris menggantung.
 *
 * down(): izinnya dibuat kembali tetapi SENGAJA tidak diberikan ke peran mana
 * pun — tanpa rute dan tanpa menu, pemberiannya tidak berarti apa-apa. Yang
 * dipulihkan hanyalah keberadaan barisnya, supaya migrasi tetap dapat dibalik.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view dampak',
        'can_simulate',
        'view coa',
        'manage coa',
        'view ibp',
        'view sensitivity',
        'view skenario',
        'manage skenario',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $nama) {
            Permission::firstOrCreate(['name' => $nama, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
