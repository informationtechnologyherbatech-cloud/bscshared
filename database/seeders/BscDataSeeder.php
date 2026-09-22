<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity;
use App\Models\Period;
use App\Models\AccountBalance;
use App\Models\RatioTarget;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\WorkbookIllustration;
use App\Models\DepartmentObjective;
use App\Models\KpiCascade;
use App\Models\KpiTest;
use App\Models\RevenueForecastPlan;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\IndicatorTest;
use App\Models\ActionPlan;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use RuntimeException;

/**
 * Data contoh keempat tingkat piramida (revenue 2026 & perencanaan 2027, pos akun &
 * 19 rasio, KPI cascade + uji indikator + sasaran mutu, program kerja, log staging) untuk
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

    /** Target revenue 2026 contoh (Rp) — difasing rata 70 M/bulan. */
    private const TARGET_2026 = 840e9;

    /** Program kerja contoh: kode sasaran asal → [judul, progres %, status]. */
    private const PROGRAM_KERJA = [
        'OPS-01' => ['Perbaikan SLA fulfillment center & kurir', 60, 'On Progress'],
        'OPS-03' => ['Cycle count mingguan & percepatan stok aging', 40, 'On Progress'],
        'MKT-02' => ['Rekrutmen distributor wilayah baru', 100, 'Completed'],
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
        $this->seedRevenue();

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
        foreach (WorkbookIllustration::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::updateOrCreate(
                ['period' => '2026-08', 'code' => $kode],
                ['amount' => $nilai, 'opening' => $awal]
            );
        }
        foreach (WorkbookIllustration::TARGET_RASIO as $kode => $target) {
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

            // Tingkat 4: program kerja perbaikan untuk sasaran yang belum tercapai.
            if ($rencana = self::PROGRAM_KERJA[$kpiAsli] ?? null) {
                ActionPlan::updateOrCreate(
                    ['department_objective_id' => $obj->id, 'title' => $rencana[0]],
                    ['owner_dept' => $obj->dept_code, 'progress_pct' => $rencana[1], 'status' => $rencana[2]]
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

        // Tingkat 3: bukti uji indikator untuk KPI contoh.
        $this->seedIndicatorTests();
    }

    /**
     * Tingkat 1 — revenue 2026 (target disahkan, fasing bulanan, realisasi Jan–Agu
     * dari sheet L1 G) dan Perencanaan Target 2027 lengkap (sheet L1 A–F).
     */
    private function seedRevenue(): void
    {
        RevenuePlan::updateOrCreate(['year' => '2026'], ['approved_target' => self::TARGET_2026, 'revised_target' => null]);

        foreach (WorkbookIllustration::REALISASI_BULANAN as $bulan => $realisasi) {
            RevenueTarget::updateOrCreate(
                ['period' => '2026-'.$bulan],
                ['target' => self::TARGET_2026 / 12, 'actual' => $realisasi]
            );
        }

        $digital = app(EntityContext::class)->entity()?->industry === Entity::DIGITAL_MARKETING;
        RevenueForecastPlan::updateOrCreate(['year' => '2027'], [
            'history' => WorkbookIllustration::REVENUE_HISTORIS,
            'base_ytd' => null,      // otomatis dari realisasi 2026 di atas
            'base_months' => null,
            // Channel workbook milik Erdigma; entitas manufaktur memakai nama channel umum.
            'channels' => $digital ? WorkbookIllustration::CHANNELS : ['Distributor', 'Modern Trade', 'Apotek', 'Online', 'Ekspor'],
            'brands' => WorkbookIllustration::BRANDS,
            'ansoff' => WorkbookIllustration::ANSOFF,
            'swot' => WorkbookIllustration::SWOT,
            'swot_adjustment' => 0,
            'notes' => 'Data contoh ilustrasi workbook.',
        ]);
        // Target 2027 disahkan; fasing bulanannya sengaja dibiarkan untuk dicoba
        // lewat tombol "Terapkan ke Target Revenue" di Perencanaan Target.
        RevenuePlan::updateOrCreate(['year' => '2027'], ['approved_target' => 900e9, 'revised_target' => null]);
    }

    /**
     * Uji Indikator untuk KPI contoh: Uji A (Driver 8 Ya, Guardrail Q5/Q7/Q8) dan,
     * untuk Driver, Uji B dengan koefisien sederhana ke pos akun yang digerakkan —
     * hasilnya dihitung mesin yang sama dengan menu Uji Indikator.
     */
    private function seedIndicatorTests(): void
    {
        $mesin = app(RatioEngine::class);
        $dipakai = AccountPosts::usedValues($mesin->inputs('2026-08'), 8);
        $target = $mesin->targetsFor('2026');

        foreach (KpiCascade::where('year', '2026')->get() as $kpi) {
            $guardrail = $kpi->isGuardrail();
            $jawaban = [];
            foreach (range(1, 8) as $q) {
                $jawaban['q'.$q] = ! $guardrail || in_array($q, [5, 6, 7, 8], true);
            }

            $ujiB = [];
            if (! $guardrail && $kpi->ratio_code && $kpi->post_code) {
                $koef = [$kpi->post_code => $kpi->direction === 'Menurunkan' ? -0.5 : 0.5];
                $sim = IndicatorTest::simulate($dipakai, 0.05, $koef, $kpi->ratio_code, $target);
                $ujiB = [
                    'uji_b_period' => '2026-08',
                    'uji_b_improvement' => 0.05,
                    'uji_b_coefficients' => $koef,
                    'uji_b_notes' => [$kpi->post_code => 'Koefisien contoh'],
                    'uji_b_result' => $sim['result'],
                ];
            }

            KpiTest::updateOrCreate(['kpi_cascade_id' => $kpi->id], $jawaban + $ujiB + [
                'uji_a_result' => $guardrail ? IndicatorTest::LOLOS_GUARDRAIL : IndicatorTest::LOLOS,
                'notes' => 'Data contoh.',
                'tested_at' => now(),
            ]);
        }
    }
}
