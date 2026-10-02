<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lebar kolom pemetaan disamakan dengan lebar kode pos akun.
 *
 * `account_mappings.post_code` dibuat varchar(4) ketika pos akun masih tetap
 * PA01–PA16. Sejak pos akun dapat ditambah sendiri (kodenya sampai 10 karakter,
 * lihat account_post_definitions), memetakan akun Odoo ke pos seperti `REBATE`
 * gagal dengan "Data too long" — padahal pos itu ditawarkan di layar pemetaan.
 *
 * Kolom lain yang menyimpan kode pos (account_balances.code,
 * kpi_cascades.post_code) sudah 10 karakter sejak semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_mappings', function (Blueprint $table) {
            $table->string('post_code', 10)->change();
        });
    }

    public function down(): void
    {
        // Pemetaan ke pos berkode panjang tidak muat lagi; dilepas supaya
        // penyempitan kolomnya tidak gagal di MySQL mode strict.
        Schema::table('account_mappings', function (Blueprint $table) {
            $table->string('post_code', 10)->change();
        });

        DB::table('account_mappings')
            ->whereRaw('CHAR_LENGTH(post_code) > 4')
            ->delete();

        Schema::table('account_mappings', function (Blueprint $table) {
            $table->string('post_code', 4)->change();
        });
    }
};
