<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci API menyebut entitas pemiliknya.
 *
 * Kunci dibuat di aplikasi entitas dan dipakai holding untuk membaca ringkasan
 * entitas itu. Bila beberapa pemasangan berbagi satu database (keadaan lama
 * sebelum database per entitas), kunci milik entitas A tetap tidak dapat dipakai
 * pada pemasangan entitas B — pemeriksaannya ada di middleware VerifyApiKey.
 *
 * Kosong = kunci lama; tetap diterima agar pemasangan berjalan tidak terganggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('entity_code', 20)->nullable()->after('name');
        });

        // Kunci yang sudah ada dianggap milik entitas pemasangan ini.
        if ($kode = config('bsc.default_entity')) {
            DB::table('api_keys')->whereNull('entity_code')->update(['entity_code' => strtoupper((string) $kode)]);
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('entity_code');
        });
    }
};
