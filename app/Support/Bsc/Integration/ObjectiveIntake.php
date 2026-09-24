<?php

namespace App\Support\Bsc\Integration;

use App\Models\DepartmentObjective;
use App\Models\Period;
use App\Models\StagingLog;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Jalan masuk realisasi KPI (sasaran mutu) dari luar — unggahan berkas maupun
 * payload API.
 *
 * Aturannya sama dengan pengisian dari layar Objective Departemen: capaian
 * mengikuti polaritas KPI, dan target KPI yang tertaut Cascade KPI TIDAK boleh
 * ditimpa payload — target itu hasil kesepakatan cascade, bukan hasil
 * pencatatan harian.
 */
class ObjectiveIntake
{
    /**
     * @param  array<int, array{dept_code: string, kpi_code: string, actual: mixed, target?: mixed}>  $rows
     * @param  array{source?: string, idempotency_key?: string}  $opsi
     */
    public function apply(string $period, array $rows, array $opsi = []): IntakeResult
    {
        $sumber = $opsi['source'] ?? 'Integrasi';
        $kunci = $opsi['idempotency_key'] ?? 'IDEMP-KPI-'.now()->format('Ymd-His-v');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return new IntakeResult(IntakeResult::DITOLAK, $period, 'Periode harus berbentuk YYYY-MM.');
        }

        if (Period::where('period', $period)->first()?->isClosed()) {
            return $this->tolak($period, $sumber, $opsi, 'Periode '.$period.' sudah ditutup; realisasinya tidak diubah.');
        }

        if (StagingLog::where('idempotency_key', $kunci)->exists()) {
            return new IntakeResult(IntakeResult::GANDA, $period,
                'Kiriman dengan penanda '.$kunci.' sudah pernah diproses; tidak ada yang diubah.');
        }

        $terisi = [];
        $bermasalah = [];

        foreach ($rows as $baris) {
            $dept = strtoupper(trim((string) ($baris['dept_code'] ?? '')));
            $kpi = strtoupper(trim((string) ($baris['kpi_code'] ?? '')));

            if ($dept === '' || $kpi === '') {
                continue;
            }

            if (! is_numeric($baris['actual'] ?? null)) {
                $bermasalah[] = $kpi.': realisasinya bukan angka';

                continue;
            }

            $objektif = DepartmentObjective::where('period', $period)
                ->where('dept_code', $dept)->where('kpi_code', $kpi)->first();

            if (! $objektif) {
                $bermasalah[] = $kpi.': tidak ada di departemen '.$dept.' pada periode '.$period;

                continue;
            }

            // Target KPI cascade ditetapkan di Cascade KPI, bukan oleh kiriman.
            $target = $objektif->kpi_cascade_id
                ? (float) $objektif->target
                : (is_numeric($baris['target'] ?? null) ? (float) $baris['target'] : (float) $objektif->target);

            $capaian = RatioLibrary::objectiveAchievement((float) $baris['actual'], $target, $objektif->polarity);

            $objektif->update([
                'actual' => (float) $baris['actual'],
                'target' => $target,
                'achievement_pct' => $capaian,
                'status' => MonitoringSync::status($capaian),
            ]);

            $terisi[] = $dept.'/'.$kpi;
        }

        if ($terisi === []) {
            return $this->tolak($period, $sumber, $opsi,
                'Tidak ada satu pun KPI yang cocok dengan periode '.$period.'.', $bermasalah);
        }

        $pesan = $sumber.': '.count($terisi).' realisasi KPI diperbarui ('
            .implode(', ', array_slice($terisi, 0, 5)).(count($terisi) > 5 ? ', …' : '').')'
            .($bermasalah !== [] ? '; '.count($bermasalah).' baris dilewati' : '').'.';

        try {
            DB::transaction(fn () => StagingLog::create([
                'period' => $period,
                'dept_code' => strtoupper($rows[0]['dept_code'] ?? 'BATCH'),
                'idempotency_key' => $kunci,
                'status' => 'SCORED',
                'source_version' => 1,
                'message' => $pesan,
            ]));
        } catch (QueryException $e) {
            if (! StagingLog::where('idempotency_key', $kunci)->exists()) {
                throw $e;
            }
        }

        return new IntakeResult(IntakeResult::DITERIMA, $period, $pesan, problems: $bermasalah);
    }

    /**
     * Tolak kiriman — dan catat penolakannya, sama seperti pemasukan pos akun.
     * Jejak audit yang hanya memuat keberhasilan tidak menjawab "kirimannya
     * sampai atau tidak?".
     *
     * @param  array{source?: string, idempotency_key?: string}  $opsi
     * @param  array<int, string>  $bermasalah
     */
    private function tolak(string $period, string $sumber, array $opsi, string $pesan, array $bermasalah = []): IntakeResult
    {
        StagingLog::create([
            'period' => $period,
            'dept_code' => 'BATCH',
            'idempotency_key' => mb_substr(($opsi['idempotency_key'] ?? 'IDEMP-KPI').'-TOLAK-'.Str::random(6), 0, 120),
            'status' => 'ERROR',
            'source_version' => 1,
            'message' => $sumber.' ditolak: '.$pesan
                .($bermasalah !== [] ? ' ('.implode('; ', array_slice($bermasalah, 0, 3)).')' : ''),
        ]);

        return new IntakeResult(IntakeResult::DITOLAK, $period, $pesan, problems: $bermasalah);
    }
}
