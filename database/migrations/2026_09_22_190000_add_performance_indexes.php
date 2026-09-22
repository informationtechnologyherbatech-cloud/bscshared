<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk pola query terpenting saat data sudah banyak. Hampir semua layar
 * menyaring per entitas (global scope BelongsToEntity) lalu per periode/status,
 * sedangkan tabel-tabel lama hanya punya indeks foreign key entity_id — basis
 * data harus memindai seluruh baris entitas untuk menemukan satu periode.
 */
return new class extends Migration
{
    /** tabel => [nama indeks => kolom] */
    private const INDEKS = [
        'financial_ratios' => [
            'financial_ratios_entity_period_index' => ['entity_id', 'period', 'source'],
        ],
        'department_objectives' => [
            'dept_objectives_entity_period_index' => ['entity_id', 'period', 'dept_code'],
            'dept_objectives_entity_kpi_code_index' => ['entity_id', 'kpi_code'],
        ],
        'action_plans' => [
            'action_plans_entity_status_index' => ['entity_id', 'status'],
        ],
        'staging_logs' => [
            'staging_logs_entity_created_index' => ['entity_id', 'created_at'],
            'staging_logs_entity_status_index' => ['entity_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEKS as $tabel => $daftar) {
            Schema::table($tabel, function (Blueprint $table) use ($daftar) {
                foreach ($daftar as $nama => $kolom) {
                    $table->index($kolom, $nama);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEKS as $tabel => $daftar) {
            // MySQL membuang diam-diam indeks foreign key entity_id begitu ada indeks
            // gabungan yang diawali entity_id; indeks itu dibuat lagi sebelum
            // indeks gabungan dilepas, agar foreign key tetap punya indeks.
            $adaIndeksEntitas = collect(Schema::getIndexes($tabel))
                ->contains(fn ($i) => $i['columns'] === ['entity_id']);

            if (! $adaIndeksEntitas) {
                Schema::table($tabel, fn (Blueprint $table) => $table->index('entity_id', $tabel.'_entity_id_index'));
            }

            Schema::table($tabel, function (Blueprint $table) use ($daftar) {
                foreach (array_keys($daftar) as $nama) {
                    $table->dropIndex($nama);
                }
            });
        }
    }
};
