<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity;
use App\Models\Period;
use App\Models\FinancialRatio;
use App\Models\DepartmentObjective;
use App\Models\ActionPlan;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use RuntimeException;

/**
 * Data contoh (periode 2026-08, rasio, sasaran mutu, log staging) untuk
 * entitas bawaan instalasi — BSC_DEFAULT_ENTITY, bawaan ERDIGMA.
 *
 * Kode departemen contoh ditulis dalam istilah manufaktur (OPS, MKT, PROD,
 * HRD) lalu dipetakan ke unit kerja entitas tujuan, sehingga yang tampil
 * adalah departemen entitas itu sendiri.
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

    private array $peta = [];

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

        // 2. Financial Ratios
        $ratios = [
            [
                'period' => '2026-08',
                'category' => 'Likuiditas',
                'ratio_name' => 'Current Ratio',
                'target' => 2.00,
                'actual' => 2.10,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'category' => 'Likuiditas',
                'ratio_name' => 'Quick Ratio',
                'target' => 1.50,
                'actual' => 1.45,
                'achievement_pct' => 96.67,
                'status' => 'Waspada',
            ],
            [
                'period' => '2026-08',
                'category' => 'Solvabilitas',
                'ratio_name' => 'Debt to Equity Ratio',
                'target' => 0.80,
                'actual' => 0.75,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'category' => 'Aktivitas',
                'ratio_name' => 'Inventory Turnover',
                'target' => 6.00,
                'actual' => 5.80,
                'achievement_pct' => 96.67,
                'status' => 'Waspada',
            ],
            [
                'period' => '2026-08',
                'category' => 'Profitabilitas',
                'ratio_name' => 'Net Profit Margin (%)',
                'target' => 15.00,
                'actual' => 14.80,
                'achievement_pct' => 98.67,
                'status' => 'Waspada',
            ],
            [
                'period' => '2026-08',
                'category' => 'Profitabilitas',
                'ratio_name' => 'Return on Equity / ROE (%)',
                'target' => 18.00,
                'actual' => 18.50,
                'achievement_pct' => 100.00,
                'status' => 'Tercapai',
            ],
            [
                'period' => '2026-08',
                'category' => 'Produktivitas',
                'ratio_name' => 'Revenue per Employee (Juta IDR)',
                'target' => 120.00,
                'actual' => 118.00,
                'achievement_pct' => 98.33,
                'status' => 'Waspada',
            ],
        ];

        foreach ($ratios as $r) {
            FinancialRatio::updateOrCreate(
                ['period' => $r['period'], 'ratio_name' => $r['ratio_name']],
                $r
            );
        }

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
