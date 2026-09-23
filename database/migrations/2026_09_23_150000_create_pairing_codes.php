<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode pendaftaran entitas ke holding (pairing).
 *
 * Holding menerbitkan kode sekali pakai berumur pendek; admin entitas menempelnya
 * di aplikasinya, lalu aplikasi entitas mendaftarkan dirinya sendiri — mengirim
 * alamat dan kunci APInya. Dengan begitu tidak ada kunci yang perlu diketik di
 * holding.
 *
 * Yang disimpan hanya sidik jari kodenya (seperti kunci API), ditambah jejak
 * pemakaian: kapan dipakai, dari IP mana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->string('label', 24);              // penanda di layar, mis. "PAIR-7K3M-…"
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->string('used_url')->nullable();   // alamat entitas yang mendaftar
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pairing_codes');
    }
};
