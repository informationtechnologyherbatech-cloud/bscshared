<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan kode akun sistem sumber (Odoo, atau berkas CSV) ke pos akun PA01–PA16.
 *
 * Bagan akun tiap entitas berbeda-beda, sedangkan 19 rasio keuangan selalu
 * disusun dari 16 pos yang sama. Di sinilah keduanya dipertemukan: entitas
 * memetakan kode akunnya sendiri ke pos akun, sehingga sistem sumber cukup
 * mengirim saldo per kode akunnya tanpa perlu tahu apa pun tentang BSC.
 *
 * Beberapa kode akun boleh menunjuk pos yang sama — nilainya dijumlahkan.
 *
 * `invert` untuk akun yang di buku besar bersaldo kredit (penjualan, utang,
 * ekuitas): di Odoo saldonya negatif, sedangkan pos akun BSC memakai angka
 * positif apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('source_code', 50);          // kode akun di sistem sumber
            $table->string('source_name')->nullable();  // nama akun, untuk dibaca manusia
            $table->string('post_code', 4);             // PA01–PA16
            $table->boolean('invert')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_id', 'source_code']);
            $table->index(['entity_id', 'post_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
