<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Program kerja mendapat periodenya sendiri.
 *
 * Sebelumnya periode program kerja hanya dapat disimpulkan dari sasaran mutu
 * yang dimitigasinya, padahal kaitan itu OPSIONAL di layar Program Kerja.
 * Akibatnya program kerja yang dibuat tanpa memilih sasaran mutu tersimpan dan
 * tampil di menunya, tetapi tidak pernah muncul di Tingkat 4 piramida —
 * terbaca sebagai "data belum lengkap" tanpa penjelasan apa pun.
 *
 * Dengan kolom ini tiap program kerja selalu dapat ditempatkan pada satu
 * periode, baik tertaut sasaran mutu maupun tidak.
 *
 * Pengisian data lama, berurutan:
 *   1. periode sasaran mutu yang dimitigasinya, bila ada;
 *   2. bulan saat program kerja itu dibuat;
 *   3. periode terawal yang dikenal entitas itu, sebagai jaring pengaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->string('period', 7)->nullable()->after('department_objective_id');
            $table->index(['entity_id', 'period']);
        });

        DB::table('action_plans')->whereNull('period')->update([
            'period' => DB::raw('COALESCE((SELECT o.period FROM department_objectives o WHERE o.id = action_plans.department_objective_id), '
                .$this->bulanDibuat().')'),
        ]);

        // Baris yang entah bagaimana masih kosong (tanpa sasaran & tanpa tanggal)
        // ditempelkan ke periode terawal entitasnya.
        foreach (DB::table('action_plans')->whereNull('period')->distinct()->pluck('entity_id') as $entitas) {
            $awal = DB::table('periods')->where('entity_id', $entitas)->min('period');

            DB::table('action_plans')->where('entity_id', $entitas)->whereNull('period')
                ->update(['period' => $awal ?: now()->format('Y-m')]);
        }
    }

    /** Bulan pembuatan baris, ditulis sesuai basis data yang dipakai. */
    private function bulanDibuat(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', action_plans.created_at)"
            : "DATE_FORMAT(action_plans.created_at, '%Y-%m')";
    }

    public function down(): void
    {
        Schema::table('action_plans', function (Blueprint $table) {
            $table->dropIndex(['entity_id', 'period']);
            $table->dropColumn('period');
        });
    }
};
