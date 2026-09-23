<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber data tiap entitas, dikelola dari layar (Setting → Sumber Data Entitas)
 * pada pemasangan HOLDING.
 *
 * Tabel ini milik holding: ia hanya menyebut DI MANA data sebuah entitas berada
 * (alamat API + kunci, atau nama database), bukan datanya. Kunci API disimpan
 * terenkripsi. Bila sebuah entitas belum diatur di sini, nilai dari .env
 * (BSC_SOURCE_<KODE>_*) tetap dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained('entities')->cascadeOnDelete();
            // lokal · database · api
            $table->string('driver', 20)->default('lokal');
            $table->string('api_url')->nullable();
            $table->text('api_key')->nullable(); // terenkripsi
            $table->string('database_name')->nullable();
            $table->string('last_status', 20)->nullable();   // ok · galat
            $table->string('last_message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_sources');
    }
};
