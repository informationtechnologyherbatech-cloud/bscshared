<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\KpiCascade;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\IndicatorTest;
use App\Support\EntityContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sheet "L4 Uji Indikator" harus tereproduksi: hasil Uji A (TTC-H01, SCM-S03,
 * CMP-H01) dan contoh Uji B (TTC-H02 ad cost ratio, klaim P2).
 */
class IndicatorTestWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private const POS_AKUN = [
        'PA01' => [540e9, null], 'PA02' => [351e9, null], 'PA03' => [135e9, null], 'PA04' => [81e9, null],
        'PA05' => [117e9, 108e9], 'PA06' => [99e9, 90e9], 'PA07' => [67.5e9, 63e9], 'PA08' => [58.5e9, 54e9],
        'PA09' => [333e9, 315e9], 'PA10' => [189e9, 180e9], 'PA11' => [756e9, 720e9], 'PA12' => [324e9, 315e9],
        'PA13' => [432e9, 405e9], 'PA14' => [270e9, 270e9], 'PA15' => [320, null], 'PA16' => [450000, null],
    ];

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

    private function baseline(): array
    {
        $masukan = collect(self::POS_AKUN)->map(fn ($v) => ['amount' => (float) $v[0], 'opening' => $v[1] === null ? null : (float) $v[1]])->all();

        return AccountPosts::usedValues($masukan, 8);
    }

    private function contohUjiB(): array
    {
        return IndicatorTest::simulate($this->baseline(), 0.05, ['PA01' => 0.4, 'PA02' => 0.4, 'PA03' => -0.3], 'P2', self::TARGET);
    }

    public function test_uji_b_moves_the_account_posts_like_the_workbook(): void
    {
        $pos = $this->contohUjiB()['used'];

        $this->assertEqualsWithDelta(826200000000, $pos['PA01']['scenario'], 1);
        $this->assertEqualsWithDelta(537030000000, $pos['PA02']['scenario'], 1);
        $this->assertEqualsWithDelta(199462500000, $pos['PA03']['scenario'], 1);
        $this->assertEqualsWithDelta(-0.015, $pos['PA03']['delta_pct'], 1e-12);
        $this->assertEqualsWithDelta(121500000000, $pos['PA04']['scenario'], 1);
    }

    public function test_uji_b_scenario_ratios_match_the_workbook(): void
    {
        $baris = collect($this->contohUjiB()['rows'])->keyBy('code');

        // Kolom "Skenario" (6 digit seperti tampilan workbook; persen ditulis dalam persen).
        $harapan = [
            'P1' => 35.0, 'P2' => 10.8578, 'P3' => 12.1555, 'P4' => 21.4355,
            'A1' => 4.7736, 'A4' => 76.462209, 'A5' => 41.748366, 'A6' => 44.348081,
            'D1' => 2581875000, 'D2' => 1224000, 'D3' => 6.8, 'D4' => 0.621,
            'L1' => 1.756098, 'S1' => 0.763441,
        ];
        foreach ($harapan as $kode => $nilai) {
            $this->assertEqualsWithDelta($nilai, $baris[$kode]['scenario'], abs($nilai) * 1e-5, "Skenario {$kode} berbeda dari workbook.");
        }
    }

    public function test_uji_b_conclusion_matches_the_workbook(): void
    {
        $hasil = $this->contohUjiB();

        $this->assertEqualsWithDelta(0.8578431, $hasil['claimed_delta'], 1e-6); // 0,008578 dalam pecahan
        $this->assertTrue($hasil['moved']);
        $this->assertTrue($hasil['favourable']);
        $this->assertSame(12, $hasil['others_moved']);
        $this->assertSame(94.1, $hasil['f2_baseline']);
        $this->assertSame(94.1, $hasil['f2_scenario']);
        $this->assertSame(0.0, $hasil['delta_score']);
        $this->assertSame(IndicatorTest::LOLOS, $hasil['result']);
    }

    public function test_uji_b_rejects_a_claim_the_scenario_does_not_move(): void
    {
        // Koefisien hanya ke Penjualan & HPP searah: GPM (P1) tidak bergerak.
        $hasil = IndicatorTest::simulate($this->baseline(), 0.05, ['PA01' => 0.4, 'PA02' => 0.4], 'P1', self::TARGET);

        $this->assertFalse($hasil['moved']);
        $this->assertSame(IndicatorTest::REVISI, $hasil['result']);

        // Arah salah: DSO (Turun) justru naik bila piutang naik.
        $salah = IndicatorTest::simulate($this->baseline(), 0.05, ['PA06' => 1], 'A5', self::TARGET);
        $this->assertTrue($salah['moved']);
        $this->assertFalse($salah['favourable']);
        $this->assertSame(IndicatorTest::REVISI, $salah['result']);
    }

    public function test_uji_b_for_revenue_claims_uses_sales(): void
    {
        $hasil = IndicatorTest::simulate($this->baseline(), 0.1, ['PA01' => 0.5], 'REV', self::TARGET);

        $this->assertEqualsWithDelta(810e9 * 0.05, $hasil['claimed_delta'], 1);
        $this->assertSame(IndicatorTest::LOLOS, $hasil['result']);
    }

    public function test_uji_a_results_match_the_workbook(): void
    {
        $ya = array_fill(1, 8, true);

        // TTC-H01 & SCM-S03: 8 Ya → LOLOS.
        $this->assertSame(IndicatorTest::LOLOS, IndicatorTest::ujiAResult(false, $ya));
        // 7 Ya → REVISI MINOR; lebih sedikit → REVISI.
        $this->assertSame(IndicatorTest::REVISI_MINOR, IndicatorTest::ujiAResult(false, [6 => false] + $ya));
        $this->assertSame(IndicatorTest::REVISI, IndicatorTest::ujiAResult(false, [1 => false, 6 => false] + $ya));
        // CMP-H01 guardrail: Q1–Q4 Tidak, Q5–Q8 Ya → LOLOS (guardrail).
        $cmp = [1 => false, 2 => false, 3 => false, 4 => false, 5 => true, 6 => true, 7 => true, 8 => true];
        $this->assertSame(IndicatorTest::LOLOS_GUARDRAIL, IndicatorTest::ujiAResult(true, $cmp));
        $this->assertSame(IndicatorTest::REVISI, IndicatorTest::ujiAResult(true, [5 => false] + $cmp));
        // Belum semua dijawab → belum ada hasil.
        $this->assertNull(IndicatorTest::ujiAResult(false, [2 => null] + $ya));
    }

    public function test_recommended_status_requires_both_tests_for_drivers(): void
    {
        $driver = new KpiCascade(['kpi_type' => KpiCascade::DRIVER]);
        $guardrail = new KpiCascade(['kpi_type' => KpiCascade::GUARDRAIL]);

        $this->assertSame(KpiCascade::LOLOS, IndicatorTest::recommendedStatus($driver, 'LOLOS', 'LOLOS'));
        $this->assertNull(IndicatorTest::recommendedStatus($driver, 'LOLOS', null));
        $this->assertSame(KpiCascade::REVISI, IndicatorTest::recommendedStatus($driver, 'REVISI MINOR', 'LOLOS'));
        $this->assertSame(KpiCascade::REVISI, IndicatorTest::recommendedStatus($driver, 'LOLOS', 'REVISI'));
        $this->assertSame(KpiCascade::LOLOS, IndicatorTest::recommendedStatus($guardrail, 'LOLOS (guardrail)', null));
    }

    public function test_the_adjusted_target_follows_the_revision_factor(): void
    {
        // Revenue direvisi turun 10% → faktor 0,9.
        $ttc = new KpiCascade(['target' => 162.4e9, 'elasticity' => 1.0]);
        $scm = new KpiCascade(['target' => 6, 'elasticity' => 0.8]);
        $cmp = new KpiCascade(['target' => 1, 'elasticity' => 0]);

        $this->assertEqualsWithDelta(146.16e9, $ttc->adjustedTarget(0.9), 1);
        $this->assertEqualsWithDelta(5.52, $scm->adjustedTarget(0.9), 1e-9);
        $this->assertEqualsWithDelta(1.0, $cmp->adjustedTarget(0.9), 1e-9); // guardrail dikunci
        $this->assertEqualsWithDelta(6.0, $scm->adjustedTarget(1.0), 1e-9);
    }
}
