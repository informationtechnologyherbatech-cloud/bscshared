<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fasing bulanan MANUAL (sheet L1 bagian H).
 *
 * Bagian G memfasing target setahun memakai indeks musiman tahun dasar. Itu
 * tidak selalu cocok: tahun dasar bisa belum punya realisasi, polanya bisa
 * sedang berubah (kampanye besar, pembukaan channel baru), atau direksi memang
 * sudah memegang angka bulanan sendiri. Bagian H menampung angka itu.
 *
 * Disimpan pada lembar perencanaan, bukan langsung ke target bulanan, supaya
 * angkanya dapat disusun dan ditinjau dulu sebelum diterapkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_forecasts', function (Blueprint $table) {
            $table->json('manual_phasing')->nullable()->after('swot_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('revenue_forecasts', function (Blueprint $table) {
            $table->dropColumn('manual_phasing');
        });
    }
};
