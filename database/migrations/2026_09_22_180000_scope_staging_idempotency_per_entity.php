<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci idempotensi staging log berlaku per entitas. Sebelumnya unik di seluruh
 * aplikasi, sehingga dua entitas yang mengirim data pada detik yang sama (kunci
 * dibentuk dari kode + waktu) atau seeder contoh untuk entitas lain gagal dengan
 * galat duplikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_logs', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['entity_id', 'idempotency_key'], 'staging_logs_entity_idempotency_unique');
        });
    }

    public function down(): void
    {
        // Gagal bila kunci yang sama sudah dipakai lebih dari satu entitas.
        // Di MySQL indeks gabungan ini juga melayani foreign key entity_id, jadi
        // foreign key diberi indeks sendiri lebih dulu sebelum indeksnya dilepas.
        if (! Schema::hasIndex('staging_logs', 'staging_logs_entity_id_index')) {
            Schema::table('staging_logs', function (Blueprint $table) {
                $table->index('entity_id', 'staging_logs_entity_id_index');
            });
        }
        Schema::table('staging_logs', function (Blueprint $table) {
            $table->dropUnique('staging_logs_entity_idempotency_unique');
            $table->unique('idempotency_key');
        });
    }
};
