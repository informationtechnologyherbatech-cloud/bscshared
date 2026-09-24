<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sambungan ke Odoo milik SATU entitas.
 *
 * Arahnya menarik, bukan menerima: aplikasi inilah yang memanggil Odoo pada
 * waktu yang dijadwalkan, lalu memasukkan hasilnya ke pos akun. Dengan begitu
 * tidak ada yang perlu dipasang di sisi Odoo selain satu pengguna khusus yang
 * cukup berhak MEMBACA jurnal.
 *
 * Kata sandi / kunci API Odoo disimpan terenkripsi dan tidak pernah ditampilkan
 * utuh lagi setelah disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odoo_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            $table->string('base_url');                    // https://erp.entitas.co.id
            $table->string('database_name', 100);           // nama database Odoo
            $table->string('username', 150);
            $table->text('api_key');                        // terenkripsi
            $table->boolean('is_active')->default(true);
            // Realisasi revenue bulanan ikut diisi dari pos Penjualan (PA01).
            $table->boolean('fills_revenue')->default(true);
            $table->string('last_status', 20)->nullable();  // ok · galat
            $table->string('last_message', 500)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odoo_connections');
    }
};
