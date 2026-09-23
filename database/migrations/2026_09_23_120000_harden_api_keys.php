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
 *
 * PERHATIAN: migrasi ini searah untuk DATA. Skemanya dapat dikembalikan, tetapi
 * kunci utuh tidak — sidik jari tidak bisa dibalik. Sesudah `migrate:rollback`
 * semua kunci menjadi kosong dan nonaktif, dan harus diterbitkan ulang dari
 * halaman Setting lalu didaftarkan lagi ke holding.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak dapat membatalkan perubahan struktur di tengah jalan, jadi
        // tiap langkah di sini dibuat aman diulang: kolom hanya ditambah bila
        // belum ada, dan kolom `key` baru dibuang SESUDAH sidik jarinya jadi.
        if (! Schema::hasColumn('api_keys', 'key_hash')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->string('key_hash', 64)->nullable()->after('entity_code');
                $table->string('prefix', 24)->nullable()->after('key_hash');
                $table->string('allowed_ips')->nullable()->after('is_active');
                $table->timestamp('expires_at')->nullable()->after('allowed_ips');
            });
        }

        if (Schema::hasColumn('api_keys', 'key')) {
            // Kunci yang sudah ada tetap berlaku: sidik jarinya dihitung dari
            // nilai lama. Baris tanpa kunci (sisa rollback) dibiarkan tanpa
            // sidik jari — kunci kosong tidak boleh bisa dipakai masuk.
            foreach (DB::table('api_keys')->select('id', 'key')->get() as $baris) {
                $kunci = (string) ($baris->key ?? '');

                DB::table('api_keys')->where('id', $baris->id)->update($kunci === '' ? [
                    'key_hash' => null,
                    'prefix' => null,
                    'is_active' => false,
                ] : [
                    'key_hash' => hash('sha256', $kunci),
                    'prefix' => substr($kunci, 0, 16),
                ]);
            }

            // Sidik jari kembar berarti ada kunci lama yang sama persis. Berhenti
            // SEBELUM kolom `key` dibuang, selagi kuncinya masih dapat dibaca.
            $kembar = DB::table('api_keys')->whereNotNull('key_hash')
                ->select('key_hash')->groupBy('key_hash')->havingRaw('count(*) > 1')->count();

            if ($kembar > 0) {
                throw new RuntimeException(
                    'Ada kunci API yang nilainya kembar; rapikan dulu tabel api_keys sebelum migrasi ini dijalankan.'
                );
            }

            Schema::table('api_keys', function (Blueprint $table) {
                $table->unique('key_hash');
            });

            Schema::table('api_keys', function (Blueprint $table) {
                $table->dropUnique(['key']);
                $table->dropColumn('key');
            });
        }
    }

    public function down(): void
    {
        // Kunci utuh tidak dapat dipulihkan dari sidik jarinya; kolomnya dibuat
        // kembali kosong dan kunci lama harus diterbitkan ulang. Indeks uniknya
        // ikut dipasang kembali supaya skemanya benar-benar sama seperti sebelum
        // migrasi ini — tanpa itu, menjalankan up() lagi gagal di dropUnique()
        // dan meninggalkan tabel separuh jadi.
        if (! Schema::hasColumn('api_keys', 'key')) {
            Schema::table('api_keys', function (Blueprint $table) {
                $table->string('key')->nullable();
            });

            DB::table('api_keys')->update(['key' => null, 'is_active' => false]);

            Schema::table('api_keys', function (Blueprint $table) {
                $table->unique('key');
            });
        }

        if (Schema::hasColumn('api_keys', 'key_hash')) {
            Schema::table('api_keys', function (Blueprint $table) {
                if (Schema::hasIndex('api_keys', ['key_hash'])) {
                    $table->dropUnique(['key_hash']);
                }

                $table->dropColumn(['key_hash', 'prefix', 'allowed_ips', 'expires_at']);
            });
        }
    }
};
