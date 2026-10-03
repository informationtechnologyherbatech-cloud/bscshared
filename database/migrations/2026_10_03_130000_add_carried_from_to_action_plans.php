<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda program kerja lanjutan.
 *
 * Saat periode baru dibuat, program kerja yang BELUM selesai ikut dibawa ke
 * periode itu supaya pekerjaan yang masih berjalan tidak hilang dari layar —
 * sementara yang sudah 100% ditinggalkan di periodenya sendiri (awal yang
 * bersih). Kolom ini menyimpan periode asalnya, sehingga yang terbawa dapat
 * ditandai "Lanjutan" dan ketahuan dari mana asalnya.
 *
 * Null = program kerja yang memang dibuat pada periode itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->string('carried_from', 7)->nullable()->after('period');
        });
    }

    public function down(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->dropColumn('carried_from');
        });
    }
};
