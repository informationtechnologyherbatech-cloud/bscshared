<?php

namespace App\Support\Bsc;

use App\Models\DepartmentObjective;
use App\Models\KpiCascade;
use App\Models\RevenuePlan;
use Illuminate\Support\Facades\DB;

/**
 * Menyalurkan KPI cascade berstatus Lolos ke monitoring bulanan (Objective
 * Departemen) untuk satu periode. Dipakai Cascade KPI ("Masukkan ke
 * monitoring") dan pembuatan periode baru di Piramida BSC.
 *
 * Realisasi yang sudah diisi tidak disentuh; definisi & target diperbarui
 * memakai "Target disesuaikan" (faktor revisi revenue tahun itu).
 */
class MonitoringSync
{
    /** Status sasaran dari pencapaian, sama dengan skala piramida. */
    public static function status(float $capaian): string
    {
        return match (true) {
            $capaian >= 100 => 'Tercapai',
            $capaian >= 80 => 'Waspada',
            default => 'Di Bawah Target',
        };
    }

    /** @return array{created: int, updated: int} */
    public static function syncPeriod(string $period): array
    {
        $tahun = substr($period, 0, 4);
        $lolos = KpiCascade::where('year', $tahun)->where('validation_status', KpiCascade::LOLOS)->get();
        $faktor = RevenuePlan::factorFor($tahun);
        $hasil = ['created' => 0, 'updated' => 0];

        DB::transaction(function () use ($lolos, $period, $faktor, &$hasil) {
            foreach ($lolos as $kpi) {
                $objektif = DepartmentObjective::where('period', $period)
                    ->where(fn ($q) => $q->where('kpi_cascade_id', $kpi->id)->orWhere('kpi_code', $kpi->code))
                    ->first();

                $definisi = [
                    'kpi_cascade_id' => $kpi->id,
                    'dept_code' => $kpi->unit_code,
                    'kpi_code' => $kpi->code,
                    'kpi_name' => mb_substr($kpi->objective.($kpi->brand ? ' — '.$kpi->brand : ''), 0, 255),
                    'polarity' => $kpi->polarity,
                    'target' => $kpi->adjustedTarget($faktor) ?? 0,
                ];

                if ($objektif) {
                    // Sasaran yang realisasinya belum dilaporkan (masih keadaan awal:
                    // 0 / 0% / Di Bawah Target) tidak dihitung ulang — kalau dihitung,
                    // KPI berpolaritas Turun dengan realisasi 0 langsung "Tercapai" 100%.
                    $belumDilaporkan = (float) $objektif->actual == 0.0
                        && (float) $objektif->achievement_pct == 0.0
                        && $objektif->status === 'Di Bawah Target';
                    $capaian = $belumDilaporkan
                        ? 0.0
                        : RatioLibrary::objectiveAchievement((float) $objektif->actual, (float) $definisi['target'], $kpi->polarity);
                    $objektif->update($definisi + ['achievement_pct' => $capaian, 'status' => self::status($capaian)]);
                    $hasil['updated']++;
                } else {
                    DepartmentObjective::create($definisi + [
                        'period' => $period,
                        'actual' => 0,
                        'achievement_pct' => 0,
                        'status' => 'Di Bawah Target',
                    ]);
                    $hasil['created']++;
                }
            }
        });

        return $hasil;
    }
}
