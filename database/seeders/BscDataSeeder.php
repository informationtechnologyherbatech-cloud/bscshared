<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity;
use App\Models\Period;
use App\Models\AccountBalance;
use App\Models\RatioTarget;
use App\Support\Bsc\RatioEngine;
use App\Models\DepartmentObjective;
use App\Models\KpiCascade;
use App\Models\ActionPlan;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use RuntimeException;

/**
 * Data contoh (periode 2026-08, pos akun & 19 rasio, sasaran mutu, log staging) untuk
 * entitas bawaan instalasi — BSC_DEFAULT_ENTITY, bawaan ERDIGMA.
 *
 * Kode departemen contoh ditulis dalam istilah manufaktur (OPS, MKT, PROD,
 * HRD) lalu dipetakan ke unit kerja entitas tujuan, sehingga yang tampil
 * adalah departemen entitas itu sendiri.
 *
 * Sasaran mutu contoh dibuat lewat jalur resmi: KPI Head di Cascade KPI
 * (status Lolos) lebih dulu, lalu sasaran bulanannya tertaut ke KPI itu —
 * sehingga yang tampil di Objective Departemen juga ada di Cascade KPI.
 */
class BscDataSeeder extends Seeder
{
    /** Kode contoh → unit kerja tiap jenis entitas. */
    private const PETA_UNIT = [
        Entity::MANUFAKTUR => ['OPS' => 'OPS', 'MKT' => 'MKT', 'PROD' => 'PRO', 'HRD' => 'HRD'],
        Entity::DIGITAL_MARKETING => ['OPS' => 'SCM', 'MKT' => 'BMK', 'PROD' => 'MFG', 'HRD' => 'HRG'],
    ];

    /** Unit yang dibuat bila belum ada di katalog entitas (MKT tidak ada di katalog manufaktur). */
    private const UNIT_TAMBAHAN = ['MKT' => 'Marketing & Penjualan'];

    /**
     * Definisi cascade tiap sasaran contoh: [jenis, rasio, pos akun, arah, bobot %].
     * Dampak mengikuti Peta Pos Akun Erdigma (SCM Pemilik PA02 & PA05,
     * Kontributor PA01; BMK Kontributor PA01). Bobot per jabatan Σ 100%.
     */
    private const CASCADE = [
        'OPS-01' => [KpiCascade::DRIVER, 'REV', 'PA01', 'Menaikkan', 25],
        'OPS-02' => [KpiCascade::DRIVER, 'P1', 'PA02', 'Menurunkan', 20],
        'OPS-03' => [KpiCascade::DRIVER, 'A1', 'PA05', 'Menurunkan', 20],
        'OPS-04' => [KpiCascade::GUARDRAIL, null, null, null, 10],
        'OPS-05' => [KpiCascade::GUARDRAIL, null, null, null, 10],
        'OPS-06' => [KpiCascade::DRIVER, 'REV', 'PA01', 'Menaikkan', 15],
        'MKT-01' => [KpiCascade::DRIVER, 'REV', 'PA01', 'Menaikkan', 60],
        'MKT-02' => [KpiCascade::DRIVER, 'REV', 'PA01', 'Menaikkan', 40],
    ];

    /** Sheet Asumsi bagian G — [nilai YTD / saldo akhir, saldo awal] (Rp; HRIS dalam orang/jam). */
    private const POS_AKUN_ILUSTRASI = [
        'PA01' => [540e9, null], 'PA02' => [351e9, null], 'PA03' => [135e9, null], 'PA04' => [81e9, null],
        'PA05' => [117e9, 108e9], 'PA06' => [99e9, 90e9], 'PA07' => [67.5e9, 63e9], 'PA08' => [58.5e9, 54e9],
        'PA09' => [333e9, 315e9], 'PA10' => [189e9, 180e9], 'PA11' => [756e9, 720e9], 'PA12' => [324e9, 315e9],
        'PA13' => [432e9, 405e9], 'PA14' => [270e9, 270e9], 'PA15' => [320, null], 'PA16' => [450000, null],
    ];

    /** Sheet L2 kolom Target (persen ditulis dalam persen). */
    private const TARGET_RASIO_ILUSTRASI = [
        'P1' => 37, 'P2' => 11, 'P3' => 12, 'P4' => 20,
        'A1' => 6, 'A2' => 1.2, 'A3' => 10, 'A4' => 60, 'A5' => 36, 'A6' => 45,
        'D1' => 2.8e9, 'D2' => 1.3e6, 'D3' => 7, 'D4' => 0.7,
        'L1' => 1.8, 'L2' => 1.2, 'L3' => 0.35,
        'S1' => 0.7, 'S2' => 0.4,
    ];

    private array $peta = [];

    /** KPI Head cascade untuk satu sasaran contoh; null bila tak didefinisikan. */
    private function cascade(string $kpiAsli, array $objektif): ?KpiCascade
    {
        $def = self::CASCADE[$kpiAsli] ?? null;

        if (! $def) {
            return null;
        }

        [$jenis, $rasio, $pos, $arah, $bobot] = $def;

        return KpiCascade::updateOrCreate(
            ['year' => substr($objektif['period'], 0, 4), 'code' => $objektif['kpi_code']],
            [
                'unit_code' => $objektif['dept_code'],
                'level' => KpiCascade::HEAD,
                'position' => 'Kepala '.$objektif['dept_code'],
                'objective' => $objektif['kpi_name'],
                'measure_type' => 'Lag',
                'target' => $objektif['target'],
                'polarity' => $objektif['polarity'],
                'reporting_period' => 'Bulanan',
                'weight' => $bobot,
                'kpi_type' => $jenis,
                'elasticity' => $jenis === KpiCascade::GUARDRAIL ? 0 : 1,
                'ratio_code' => $rasio,
                'post_code' => $pos,
                'direction' => $arah,
                'validation_status' => KpiCascade::LOLOS,
                'finance_notes' => 'Data contoh.',
            ]
        );
    }

    public function run(): void
    {
        $entitas = Entity::configuredDefault();

        if (! $entitas) {
            throw new RuntimeException('Entitas bawaan "'.config('bsc.default_entity').'" tidak ditemukan atau nonaktif. '
                .'Periksa BSC_DEFAULT_ENTITY di .env (HERBAEMAS, HERBATECH, AEJ, atau ERDIGMA).');
        }

        $this->peta = self::PETA_UNIT[$entitas->industry] ?? self::PETA_UNIT[Entity::MANUFAKTUR];

        // Seeder berjalan tanpa pengguna login; tanpa ini entity_id data contoh
        // kosong dan datanya tidak tampil di entitas mana pun.
        app(EntityContext::class)->runAs($entitas->id, fn () => $this->seed());

        $this->command?->info('Data contoh BSC dimuat untuk entitas '.$entitas->code.' ('.$entitas->legal_name.').');
    }

    /** Kode departemen contoh → kode unit entitas; unitnya dipastikan ada. */
    private function unit(string $kode): string
    {
        $tujuan = $this->peta[$kode] ?? $kode;

        if (! WorkUnit::where('code', $tujuan)->exists()) {
            WorkUnit::create([
                'code' => $tujuan,
                'name' => self::UNIT_TAMBAHAN[$tujuan] ?? $tujuan,
                'is_active' => true,
                'sort' => (int) WorkUnit::max('sort') + 1,
            ]);
        }

        return $tujuan;
    }

    /** OPS-02 → SCM-02 mengikuti kode unit tujuan. */
    private function kpi(string $kode): string
    {
        return preg_replace_callback('/^(KPI-)?([A-Z]+)(?=-)/', fn ($m) => $m[1].($this->peta[$m[2]] ?? $m[2]), $kode);
    }

    private function seed(): void
    {
        // 1. Period
        $period = Period::updateOrCreate(
            ['period' => '2026-08'],
            [
                'status' => 'OPEN',
                'apex_score' => 96.50,
            ]
        );

        // 2. Rasio keuangan — lewat jalur resmi: 16 pos akun + target rasio →
        // mesin 19 rasio (RatioEngine). Angkanya data ILUSTRASI workbook
        // (sheet Asumsi bagian G, 2026 YTD Jan–Agu = periode 2026-08; target dari
        // sheet L2), sehingga F2 contoh = 94,1 persis seperti Excel. Ganti dengan
        // data GL/HRIS aktual lewat menu Pos Akun & Katalog Rasio.
        foreach (self::POS_AKUN_ILUSTRASI as $kode => [$nilai, $awal]) {
            AccountBalance::updateOrCreate(
                ['period' => '2026-08', 'code' => $kode],
                ['amount' => $nilai, 'opening' => $awal]
            );
        }
        foreach (self::TARGET_RASIO_ILUSTRASI as $kode => $target) {
            RatioTarget::updateOrCreate(['year' => '2026', 'code' => $kode], ['target' => $target]);
        }
        app(RatioEngine::class)->materialize('2026-08');

        // 3. Department Objectives
        $objectives = [
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-02',
                'kpi_name' => 'Kontribusi biaya operasional terhadap omset',
                'polarity' => 'Turun',
                'target' => 5.00,
                'actual' => 4.50,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-04',
                'kpi_name' => 'Pemastian sistem berjalan sesuai standar',
                'polarity' => 'Turun',
                'target' => 0.00,
                'actual' => 0.00,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-05',
                'kpi_name' => 'Implementasi RFT di operasional sistem',
                'polarity' => 'Turun',
                'target' => 0.00,
                'actual' => 0.00,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-01',
                'kpi_name' => 'Pemenuhan service level',
                'polarity' => 'Naik',
                'target' => 90.00,
                'actual' => 85.00,
                'achievement_pct' => 94.44,
                'status' => 'Waspada',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-06',
                'kpi_name' => 'Launching produk baru',
                'polarity' => 'Naik',
                'target' => 10.00,
                'actual' => 10.00,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'OPS',
                'kpi_code' => 'OPS-03',
                'kpi_name' => 'Cycle inventory turnover',
                'polarity' => 'Naik',
                'target' => 6.00,
                'actual' => 5.00,
                'achievement_pct' => 83.33,
                'status' => 'Waspada',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'MKT',
                'kpi_code' => 'MKT-01',
                'kpi_name' => 'Capaian Omzet Penjualan Produk Utama Herbal',
                'polarity' => 'Naik',
                'target' => 100.00,
                'actual' => 104.50,
                'achievement_pct' => 104.50,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'dept_code' => 'MKT',
                'kpi_code' => 'MKT-02',
                'kpi_name' => 'Penambahan Channel Distributor Baru (Cabang)',
                'polarity' => 'Naik',
                'target' => 15.00,
                'actual' => 12.00,
                'achievement_pct' => 80.00,
                'status' => 'Waspada',
            ],
        ];

        foreach ($objectives as $objData) {
            $kpiAsli = $objData['kpi_code'];
            $objData['dept_code'] = $this->unit($objData['dept_code']);
            $objData['kpi_code'] = $this->kpi($kpiAsli);
            $objData['kpi_cascade_id'] = $this->cascade($kpiAsli, $objData)?->id;
            $obj = DepartmentObjective::updateOrCreate(
                ['period' => $objData['period'], 'kpi_code' => $objData['kpi_code']],
                $objData
            );

            // Action Plans for specific objectives
            if ($kpiAsli === 'KPI-HRD-001') {
                ActionPlan::updateOrCreate(
                    ['department_objective_id' => $obj->id, 'title' => 'Program Intensifikasi Pelatihan Teknis Produksi & K3'],
                    [
                        'owner_dept' => $this->unit('HRD'),
                        'progress_pct' => 75,
                        'status' => 'On Progress',
                    ]
                );
            } elseif ($kpiAsli === 'KPI-PROD-001') {
                ActionPlan::updateOrCreate(
                    ['department_objective_id' => $obj->id, 'title' => 'Kalibrasi Ulang Mesin Utama Line 2'],
                    [
                        'owner_dept' => $this->unit('PROD'),
                        'progress_pct' => 90,
                        'status' => 'On Progress',
                    ]
                );
            } elseif ($kpiAsli === 'KPI-MKT-001') {
                ActionPlan::updateOrCreate(
                    ['department_objective_id' => $obj->id, 'title' => 'Kampanye Digital Marketing Q3 Produk Herbal'],
                    [
                        'owner_dept' => $this->unit('MKT'),
                        'progress_pct' => 100,
                        'status' => 'Completed',
                    ]
                );
            }
        }

        // 4. Staging Logs
        StagingLog::updateOrCreate(
            ['idempotency_key' => 'IDEMP-PROD-202608-001'],
            [
                'period' => '2026-08',
                'dept_code' => $this->unit('PROD'),
                'status' => 'SCORED',
                'source_version' => 1,
                'message' => 'Data realisasi produksi berhasil dihitung dan diterbitkan.',
            ]
        );

        StagingLog::updateOrCreate(
            ['idempotency_key' => 'IDEMP-HRD-202608-001'],
            [
                'period' => '2026-08',
                'dept_code' => $this->unit('HRD'),
                'status' => 'DELIVERED',
                'source_version' => 1,
                'message' => 'Data jam pelatihan diterima dari HRIS, dalam antrean skoring.',
            ]
        );
    }
}
