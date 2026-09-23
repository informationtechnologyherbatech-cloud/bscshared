<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kredensial baca-saja per entitas untuk mode "database terpisah".
 *
 * Sebelumnya holding membuka database entitas memakai kredensial DB_* miliknya
 * sendiri — satu pengguna MySQL yang harus punya akses ke SEMUA database
 * entitas. Satu kredensial bocor berarti seluruh entitas terbuka.
 *
 * Sekarang tiap entitas dapat diberi pengguna MySQL sendiri yang hanya boleh
 * SELECT di databasenya, dan kata sandinya disimpan terenkripsi di sini.
 * Dikosongkan = perilaku lama (memakai kredensial aplikasi ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_sources', function (Blueprint $table) {
            $table->string('db_host')->nullable()->after('database_name');
            $table->string('db_port', 10)->nullable()->after('db_host');
            $table->string('db_username')->nullable()->after('db_port');
            $table->text('db_password')->nullable()->after('db_username'); // terenkripsi
        });
    }

    public function down(): void
    {
        Schema::table('entity_sources', function (Blueprint $table) {
            $table->dropColumn(['db_host', 'db_port', 'db_username', 'db_password']);
        });
    }
};
