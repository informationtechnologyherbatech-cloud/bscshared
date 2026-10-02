<?php

use App\Support\Bsc\RatioLibrary;
use Database\Seeders\AccountPostCatalogSeeder;
use Database\Seeders\RatioCatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pos akun dan rasio menjadi DATA, bukan lagi hanya kode.
 *
 * Sebelum ini 16 pos akun dan 19 rasio ditulis mati di App\Support\Bsc, sehingga
 * entitas yang susunan akunnya berbeda tidak dapat menyesuaikan. Dua perubahan:
 *
 *  - account_post_definitions : katalog pos akun per entitas — pos bawaan boleh
 *    diubah namanya/keterangannya atau dinonaktifkan, dan pos baru boleh
 *    ditambah (PA17, PA18, … atau kode sendiri).
 *  - ratio_definitions         : ditambah nama, kelompok, rumus, satuan, dan
 *    polaritas, supaya entitas dapat menambah rasio sendiri atau mengubah rumus
 *    rasio bawaan. Kolom `expression` inilah yang BENAR-BENAR dihitung mesin
 *    rumus (App\Support\Bsc\Formula); kolom `formula` hanya teks yang dibaca
 *    manusia.
 *
 * Baris bawaan diisi SEEDER (AccountPostCatalogSeeder & RatioCatalogSeeder)
 * supaya satu sumber kebenaran tetap di pustaka kodenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_post_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('name');
            // aliran · neraca · hris_rata · hris_aliran (lihat AccountPosts)
            $table->string('kind', 20);
            $table->string('source', 20)->default('GL');
            $table->string('hint', 500)->nullable();
            $table->boolean('is_active')->default(true);
            // Pos bawaan tidak boleh dihapus; kodenya dipakai rumus bawaan.
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
        });

        Schema::table('ratio_definitions', function (Blueprint $table) {
            $table->string('name')->nullable()->after('code');
            // "group" kata kunci SQL, jadi kolomnya ratio_group.
            $table->string('ratio_group', 50)->nullable()->after('name');
            $table->string('formula', 500)->nullable()->after('ratio_group');
            $table->string('expression', 500)->nullable()->after('formula');
            $table->string('unit', 10)->nullable()->after('expression');
            $table->string('polarity', 10)->nullable()->after('unit');
            $table->boolean('is_builtin')->default(true)->after('is_active');
        });

        // Katalog yang sudah ada diberi nama, kelompok, dan rumusnya dari
        // pustaka — firstOrCreate di seeder tidak menyentuh baris lama.
        foreach (RatioLibrary::all() as $kode => $rasio) {
            DB::table('ratio_definitions')->where('code', $kode)->update([
                'name' => $rasio['name'],
                'ratio_group' => $rasio['group'],
                'formula' => $rasio['formula'],
                'expression' => $rasio['expression'],
                'unit' => $rasio['unit'],
                'polarity' => $rasio['polarity'],
                'is_builtin' => true,
            ]);
        }

        (new AccountPostCatalogSeeder)->run();
        (new RatioCatalogSeeder)->run();
    }

    public function down(): void
    {
        // Rasio buatan pengguna tidak punya rumus di pustaka kode, jadi tanpa
        // kolom-kolom ini barisnya tidak lagi berarti apa-apa.
        DB::table('ratio_definitions')->where('is_builtin', false)->delete();

        Schema::table('ratio_definitions', function (Blueprint $table) {
            $table->dropColumn(['name', 'ratio_group', 'formula', 'expression', 'unit', 'polarity', 'is_builtin']);
        });

        Schema::dropIfExists('account_post_definitions');
    }
};
