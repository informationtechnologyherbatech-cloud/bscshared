<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Identitas entitas (nama entitas & nama perusahaan) mengikuti jenis instalasi
 * di .env — BSC_DEFAULT_ENTITY / BSC_HOLDING_MODE.
 *
 * Dulu identitas bawaan ditulis mati sebagai Herbatech, sehingga instalasi
 * Erdigma tetap bertuliskan "PT Herbatech Innopharma Industry". Migrasi ini
 * hanya mengganti nilai yang MASIH sama dengan bawaan lama itu (atau kosong);
 * identitas yang sudah diubah pengguna lewat Setting Sistem tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lama = config('entity.legacy_defaults', []);

        foreach (['entity_name', 'company_name'] as $key) {
            $baru = (string) config('entity.defaults.'.$key, '');
            $sekarang = DB::table('app_settings')->where('key', $key)->value('value');

            if ($baru === '' || ($sekarang !== null && $sekarang !== '' && $sekarang !== ($lama[$key] ?? null))) {
                continue;
            }

            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $baru, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        AppSetting::flushCache();
    }

    public function down(): void
    {
        // Tidak dikembalikan: nilai lama adalah bawaan yang keliru untuk instalasi ini.
    }
};
