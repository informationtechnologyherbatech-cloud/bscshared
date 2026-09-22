<?php

namespace Tests\Feature;

use App\Models\AccountBalance;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\RatioDefinition;
use App\Models\RatioTarget;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mesin Tingkat 2 harus menghasilkan angka yang SAMA dengan workbook
 * Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx. Masukan diambil dari sheet
 * "Asumsi" bagian G (data ilustrasi 2026 YTD, n = 8) dan target dari kolom
 * "Target FY 2027" sheet "L2 Rasio Keuangan".
 */
class RatioEngineWorkbookTest extends TestCase
{
    use RefreshDatabase;

    /** Sheet Asumsi bagian G — [nilai YTD / saldo akhir, saldo awal]. */
    private const POS_AKUN = [
        'PA01' => [540e9, null],
        'PA02' => [351e9, null],
        'PA03' => [135e9, null],
        'PA04' => [81e9, null],
        'PA05' => [117e9, 108e9],
        'PA06' => [99e9, 90e9],
        'PA07' => [67.5e9, 63e9],
        'PA08' => [58.5e9, 54e9],
        'PA09' => [333e9, 315e9],
        'PA10' => [189e9, 180e9],
        'PA11' => [756e9, 720e9],
        'PA12' => [324e9, 315e9],
        'PA13' => [432e9, 405e9],
        'PA14' => [270e9, 270e9],
        'PA15' => [320, null],
        'PA16' => [450000, null],
    ];

    /** Sheet L2 kolom "Target FY 2027" (persen ditulis dalam persen). */
    private const TARGET = [
        'P1' => 37, 'P2' => 11, 'P3' => 12, 'P4' => 20,
        'A1' => 6, 'A2' => 1.2, 'A3' => 10, 'A4' => 60, 'A5' => 36, 'A6' => 45,
        'D1' => 2.8e9, 'D2' => 1.3e6, 'D3' => 7, 'D4' => 0.7,
        'L1' => 1.8, 'L2' => 1.2, 'L3' => 0.35,
        'S1' => 0.7, 'S2' => 0.4,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(EntityContext::class)->use(Entity::where('code', 'ERDIGMA')->value('id'));
    }

    private function inputs(): array
    {
        return collect(self::POS_AKUN)
            ->map(fn ($v) => ['amount' => (float) $v[0], 'opening' => $v[1] === null ? null : (float) $v[1]])
            ->all();
    }

    private function evaluate(): array
    {
        return app(RatioEngine::class)->evaluateWith($this->inputs(), 8, self::TARGET);
    }

    private function row(array $hasil, string $code): array
    {
        return collect($hasil['rows'])->firstWhere('code', $code);
    }

    public function test_used_values_follow_section_g(): void
    {
        $p = AccountPosts::usedValues($this->inputs(), 8);

        // Aliran disetahunkan ×12 ÷ 8.
        $this->assertEqualsWithDelta(810e9, $p['PA01'], 1);
        $this->assertEqualsWithDelta(526.5e9, $p['PA02'], 1);
        $this->assertEqualsWithDelta(675000, $p['PA16'], 0.01);
        // Neraca dirata-rata saldo awal & akhir.
        $this->assertEqualsWithDelta(112.5e9, $p['PA05'], 1);
        $this->assertEqualsWithDelta(738e9, $p['PA11'], 1);
        // HRIS rata-rata dipakai apa adanya.
        $this->assertEqualsWithDelta(320, $p['PA15'], 0.001);
        // Turunan.
        $this->assertEqualsWithDelta(283.5e9, $p['LK'], 1);
        $this->assertEqualsWithDelta(81e9, $p['LB'], 1);
    }

    public function test_the_nineteen_ratios_match_the_workbook_baseline(): void
    {
        $hasil = $this->evaluate();

        // Kolom "Baseline 2026 / Aktual" sheet L2 (persen dalam persen).
        $harapan = [
            'P1' => 35.0, 'P2' => 10.0, 'P3' => 10.97560976, 'P4' => 19.35483871,
            'A1' => 4.68, 'A2' => 1.097560976, 'A3' => 8.571428571,
            'A4' => 77.99145299, 'A5' => 42.58333333, 'A6' => 45.23504274,
            'D1' => 2531250000, 'D2' => 1200000, 'D3' => 6.666666667, 'D4' => 0.6,
            'L1' => 1.756097561, 'L2' => 1.146341463, 'L3' => 0.3048780488,
            'S1' => 0.7634408602, 'S2' => 0.4329268293,
        ];

        foreach ($harapan as $kode => $nilai) {
            $this->assertEqualsWithDelta($nilai, $this->row($hasil, $kode)['actual'], abs($nilai) * 1e-6, "Rasio {$kode} berbeda dari workbook.");
        }
    }

    public function test_rubric_scores_match_the_workbook(): void
    {
        $hasil = $this->evaluate();

        // Kolom "Skor rubrik" sheet L2: yang bukan 100 hanya enam rasio ini.
        $bukan100 = ['A1' => 70, 'A3' => 80, 'A4' => 70, 'A5' => 80, 'D4' => 80, 'L3' => 80];

        foreach (array_keys(RatioLibrary::all()) as $kode) {
            $this->assertSame((float) ($bukan100[$kode] ?? 100), $this->row($hasil, $kode)['rubric'], "Rubrik {$kode} berbeda dari workbook.");
        }
    }

    public function test_f2_and_group_scores_match_the_workbook(): void
    {
        $hasil = $this->evaluate();

        // "TOTAL — Skor Tingkat 2 (F2, skala 0–100)" = 94,1.
        $this->assertSame(94.1, $hasil['f2']);
        $this->assertSame(19, $hasil['scored']);

        // Blok "SKOR PER KELOMPOK".
        $this->assertEqualsWithDelta(30, $hasil['groups']['Profitabilitas']['weighted'], 1e-6);
        $this->assertEqualsWithDelta(20.7, $hasil['groups']['Aktivitas']['weighted'], 1e-6);
        $this->assertEqualsWithDelta(19.4, $hasil['groups']['Produktivitas']['weighted'], 1e-6);
        $this->assertEqualsWithDelta(14, $hasil['groups']['Likuiditas']['weighted'], 1e-6);
        $this->assertEqualsWithDelta(10, $hasil['groups']['Solvabilitas']['weighted'], 1e-6);
    }

    public function test_the_top_score_matches_the_workbook(): void
    {
        // "Skor puncak = bobot F1 × F1 + bobot F2 × F2" dengan F1 = 0,9 → 0,92255.
        $f2 = $this->evaluate()['f2'];
        $puncak = 0.45 * 90 + 0.55 * $f2;

        $this->assertEqualsWithDelta(92.255, $puncak, 1e-9);
    }

    public function test_achievement_follows_each_polarity(): void
    {
        // Naik: GPM 35% vs 37% → 94,6%.
        $this->assertEqualsWithDelta(94.59, RatioLibrary::achievement(35, 37, RatioLibrary::NAIK), 0.01);
        // Turun: DSO 42,6 hari vs 36 → 84,5%.
        $this->assertEqualsWithDelta(84.54, RatioLibrary::achievement(42.58333333, 36, RatioLibrary::TURUN), 0.01);
        // Rentang: DPO 45,2 hari vs 45 → 99,5%.
        $this->assertEqualsWithDelta(99.48, RatioLibrary::achievement(45.23504274, 45, RatioLibrary::RENTANG), 0.01);
        // Melebihi target tidak menambah nilai.
        $this->assertSame(100.0, RatioLibrary::achievement(50, 37, RatioLibrary::NAIK));
    }

    public function test_the_consistency_checks_pass_for_the_workbook_targets(): void
    {
        $cek = RatioEngine::consistencyChecks(array_map('floatval', self::TARGET), 100.0);

        foreach ($cek as $c) {
            $this->assertTrue($c['ok'], 'Cek gagal: '.$c['label']);
        }
    }

    public function test_an_inconsistent_target_is_flagged(): void
    {
        $target = array_map('floatval', self::TARGET);
        $target['P2'] = 40; // NPM > GPM

        $cek = collect(RatioEngine::consistencyChecks($target, 100.0))->keyBy('label');

        $this->assertFalse($cek['NPM target ≤ GPM target']['ok']);
    }

    /* ------------------------------------------------ dari basis data */

    public function test_the_engine_reads_saved_posts_and_writes_ratio_rows(): void
    {
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }
        foreach (self::TARGET as $kode => $target) {
            RatioTarget::create(['year' => '2026', 'code' => $kode, 'target' => $target]);
        }

        $hasil = app(RatioEngine::class)->materialize('2026-08');

        $this->assertSame(94.1, $hasil['f2']);
        $this->assertSame(94.1, RatioEngine::storedScore('2026-08'));
        $this->assertSame(19, FinancialRatio::where('source', RatioEngine::SOURCE_COMPUTED)->count());
    }

    public function test_a_deactivated_ratio_drops_out_and_the_weights_are_renormalised(): void
    {
        foreach (self::POS_AKUN as $kode => [$nilai, $awal]) {
            AccountBalance::create(['period' => '2026-08', 'code' => $kode, 'amount' => $nilai, 'opening' => $awal]);
        }
        foreach (self::TARGET as $kode => $target) {
            RatioTarget::create(['year' => '2026', 'code' => $kode, 'target' => $target]);
        }
        app(RatioEngine::class)->materialize('2026-08');

        // A1 (rubrik 70, bobot 6) dinonaktifkan: (94,1 − 4,2) ÷ 94 × 100.
        RatioDefinition::where('code', 'A1')->update(['is_active' => false]);
        $hasil = app(RatioEngine::class)->materialize('2026-08');

        $this->assertSame(18, $hasil['scored']);
        $this->assertSame(round((94.1 - 4.2) / 94 * 100, 2), $hasil['f2']);
        $this->assertSame(0, FinancialRatio::where('ratio_code', 'A1')->count());
    }

    public function test_a_ratio_without_a_target_is_computed_but_not_scored(): void
    {
        $hasil = app(RatioEngine::class)->evaluateWith($this->inputs(), 8, []);

        $gpm = $this->row($hasil, 'P1');
        $this->assertEqualsWithDelta(35.0, $gpm['actual'], 1e-6);
        $this->assertNull($gpm['rubric']);
        $this->assertSame(RatioEngine::TANPA_TARGET, $gpm['status']);
        $this->assertNull($hasil['f2']);
    }
}
