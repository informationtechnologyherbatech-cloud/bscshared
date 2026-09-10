<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan pengaturan identitas entitas ke tabel app_settings.
 *
 * Sebelumnya identitas perusahaan (nama, tagline, tahun) ditulis langsung
 * di dalam view. Mulai sekarang seluruh identitas — nama entitas, nama
 * perusahaan, alamat, kontak, logo dan favicon — disimpan sebagai
 * pengaturan sehingga aplikasi dapat dipakai entitas mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (config('entity.defaults', []) as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            DB::table('app_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Logo & favicon tidak punya nilai default (diunggah oleh pengguna),
        // tetapi barisnya disiapkan agar pengaturan konsisten.
        foreach (['app_logo', 'app_favicon'] as $key) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('app_settings')->insert([
                'key' => $key,
                'value' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->whereIn('key', [
                'entity_name',
                'company_name',
                'company_address',
                'company_phone',
                'company_email',
                'company_website',
            ])
            ->delete();
    }
};
