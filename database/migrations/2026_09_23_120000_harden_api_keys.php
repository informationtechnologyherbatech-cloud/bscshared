<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci API tidak lagi disimpan apa adanya.
 *
 * Sebelumnya `api_keys.key` berisi kunci yang langsung dapat dipakai, dan dapat
 * ditampilkan penuh dari layar. Artinya cadangan (backup) database entitas —
 * atau siapa pun yang bisa membuka halaman itu — memegang kredensialnya.
 *
 * Sekarang entitas hanya menyimpan sidik jari (hash) kunci, persis seperti kata
 * sandi: cukup untuk MEMERIKSA, tidak cukup untuk MEMAKAI. Kunci utuh
 * ditampilkan sekali saja saat dibuat, lalu hanya dipegang holding (terenkripsi
 * di entity_sources).
 *
 * Ditambahkan pula pembatas pemakaian kunci: daftar IP yang boleh memanggil dan
 * masa berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->after('entity_code');
            $table->string('prefix', 24)->nullable()->after('key_hash');
            $table->string('allowed_ips')->nullable()->after('is_active');
            $table->timestamp('expires_at')->nullable()->after('allowed_ips');
        });

        // Kunci yang sudah ada tetap berlaku: sidik jarinya dihitung dari nilai lama.
        foreach (DB::table('api_keys')->select('id', 'key')->get() as $baris) {
            DB::table('api_keys')->where('id', $baris->id)->update([
                'key_hash' => hash('sha256', (string) $baris->key),
                'prefix' => substr((string) $baris->key, 0, 16),
            ]);
        }

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
            $table->unique('key_hash');
        });
    }

    public function down(): void
    {
        // Kunci utuh tidak dapat dipulihkan dari sidik jarinya; kolomnya dibuat
        // kembali kosong dan kunci lama harus diterbitkan ulang.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key')->nullable();
        });

        DB::table('api_keys')->update(['key' => null, 'is_active' => false]);

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['key_hash']);
            $table->dropColumn(['key_hash', 'prefix', 'allowed_ips', 'expires_at']);
        });
    }
};
