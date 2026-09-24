<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan Odoo yang dibaca sambungan ini.
 *
 * Satu database Odoo boleh memuat beberapa perusahaan — dan pada grup seperti
 * EMC itu justru lazim. Tanpa penyaring, saldo seluruh perusahaan terjumlah
 * menjadi satu: angkanya salah, tetapi tidak ada satu pun pesan galat yang
 * memberi tahu. Dikosongkan = database itu memang hanya berisi satu perusahaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odoo_connections', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('database_name');
            $table->string('company_name')->nullable()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('odoo_connections', function (Blueprint $table) {
            $table->dropColumn(['company_id', 'company_name']);
        });
    }
};
