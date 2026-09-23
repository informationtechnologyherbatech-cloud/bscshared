<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak akses API entitas: siapa memanggil, dari mana, dan hasilnya — termasuk
 * yang DITOLAK, supaya percobaan memakai kunci yang salah ikut terlihat.
 *
 * Tidak menyimpan kunci apa pun; hanya awalan kunci (prefix) sebagai penanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->string('prefix', 24)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('path');
            $table->unsignedSmallInteger('status');
            $table->string('result', 40);           // diterima · kunci salah · kadaluwarsa · ip ditolak · entitas lain
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['created_at']);
            $table->index(['result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_logs');
    }
};
