<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto pengguna.
 *
 * Menyimpan LOKASI berkas pada disk `public` (mis. "avatar/abc.jpg"), bukan
 * isinya — sama seperti logo & favicon aplikasi. Kosong = memakai inisial nama,
 * sehingga pengguna lama tetap tampil wajar tanpa perlu mengunggah apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
