<?php

namespace Tests\Feature;

use App\Support\Bsc\RevenueForecast;
use Tests\TestCase;

/**
 * Sheet "L1 Target Revenue" bagian A–G harus tereproduksi persis dengan angka
 * contoh workbook (target 2027, tahun dasar 2026 Jan–Agu).
 */
class RevenueForecastWorkbookTest extends TestCase
{
    private const CHANNELS = ['SOC', 'TTC', 'ECO', 'OFD', 'PTN'];

    /** Bagian C baris 28–32. */
    private const BRANDS = [
        ['name' => 'Eyebost', 'cells' => ['SOC' => 190e9, 'TTC' => 145e9, 'ECO' => 75e9, 'OFD' => 48e9, 'PTN' => 18e9], 'growth' => 0.12],
        ['name' => 'Brand 2', 'cells' => ['SOC' => 96e9, 'TTC' => 76e9, 'ECO' => 38e9, 'OFD' => 19e9, 'PTN' => 9e9], 'growth' => 0.15],
        ['name' => 'Brand 3', 'cells' => ['SOC' => 38e9, 'TTC' => 29e9, 'ECO' => 19e9, 'OFD' => 10e9, 'PTN' => 0], 'growth' => 0.20],
        ['name' => 'Brand 4', 'cells' => [], 'growth' => 0],
        ['name' => 'Brand 5', 'cells' => [], 'growth' => 0],
    ];

    /** Bagian D baris 39–42. */
    private const ANSOFF = [
        ['revenue' => 25e9, 'probability' => 0.7],
        ['revenue' => 40e9, 'probability' => 0.4],
        ['revenue' => 50e9, 'probability' => 0.5],
        ['revenue' => 0, 'probability' => 0],
    ];

    /** Bagian G kolom B: realisasi 2026 Jan–Agu. */
    private const REALISASI_2026 = [
        '01' => 63e9, '02' => 65e9, '03' => 68e9, '04' => 66e9, '05' => 71e9, '06' => 67e9,
        '07' => 69e9, '08' => 71e9, '09' => null, '10' => null, '11' => null, '12' => null,
    ];

    private function deret(): array
    {
        return [2023 => 560e9, 2024 => 640e9, 2025 => 730e9, 2026 => RevenueForecast::yearEndEstimate(540e9, 8)];
    }

    public function test_part_a_and_b_match_the_workbook(): void
    {
        $this->assertEqualsWithDelta(810e9, RevenueForecast::yearEndEstimate(540e9, 8), 0.01);

        $cagr = RevenueForecast::cagr($this->deret());
        $this->assertEqualsWithDelta(0.130921, $cagr, 1e-6);
        $this->assertEqualsWithDelta(916046140972, 810e9 * (1 + $cagr), 1);
        $this->assertEqualsWithDelta(895e9, RevenueForecast::linearForecast($this->deret(), 2027), 1);

        $yoy = RevenueForecast::yoy($this->deret());
        $this->assertNull($yoy[2023]);
        $this->assertEqualsWithDelta(0.142857, $yoy[2024], 1e-6);
        $this->assertEqualsWithDelta(0.109589, $yoy[2026], 1e-6);
    }

    public function test_part_c_bottom_up_matches_the_workbook(): void
    {
        $c = RevenueForecast::bottomUp(self::CHANNELS, self::BRANDS);

        $this->assertEqualsWithDelta(533120000000, $c['brands'][0]['target'], 1);
        $this->assertEqualsWithDelta(273700000000, $c['brands'][1]['target'], 1);
        $this->assertEqualsWithDelta(810e9, $c['base'], 1);
        $this->assertEqualsWithDelta(922020000000, $c['target'], 1);
        $this->assertEqualsWithDelta(0.138296, $c['growth'], 1e-6);

        // Baris 33: target per channel → target unit SOC/TTC/ECO/OFD/PTN.
        $harapan = ['SOC' => 368800000000, 'TTC' => 284600000000, 'ECO' => 150500000000, 'OFD' => 87610000000, 'PTN' => 30510000000];
        foreach ($harapan as $ch => $nilai) {
            $this->assertEqualsWithDelta($nilai, $c['channel_target'][$ch], 1, "Target channel {$ch} berbeda dari workbook.");
        }
    }

    public function test_part_d_to_f_match_the_workbook(): void
    {
        $ev = RevenueForecast::ansoffExpectedValue(self::ANSOFF);
        $this->assertEqualsWithDelta(58.5e9, $ev, 1);

        $cagr = 810e9 * (1 + RevenueForecast::cagr($this->deret()));
        $f = RevenueForecast::reconciliation(810e9, $cagr, RevenueForecast::linearForecast($this->deret(), 2027), 922020000000, $ev, 0.0);

        $this->assertEqualsWithDelta(810e9, $f['runrate']['value'], 1);
        $this->assertEqualsWithDelta(980520000000, $f['ambitious']['value'], 1);
        $this->assertEqualsWithDelta(911022046991, $f['average']['value'], 1);
        // Selisih terhadap target disahkan Rp 900 M.
        $this->assertEqualsWithDelta(0.024467, $f['bottomup']['value'] / 900e9 - 1, 1e-6);
        $this->assertEqualsWithDelta(-0.1, $f['runrate']['value'] / 900e9 - 1, 1e-12);
    }

    public function test_the_swot_adjustment_scales_the_ambitious_method(): void
    {
        $f = RevenueForecast::reconciliation(null, null, null, 922020000000, 58.5e9, -0.03);

        $this->assertEqualsWithDelta(980520000000 * 0.97, $f['ambitious']['value'], 1);
        $this->assertEqualsWithDelta(922020000000, $f['average']['value'], 1); // hanya metode 3 yang ada
    }

    public function test_part_g_seasonal_phasing_matches_the_workbook(): void
    {
        $indeks = RevenueForecast::seasonalIndex(self::REALISASI_2026);

        $this->assertEqualsWithDelta(0.077778, $indeks['01'], 1e-6);
        $this->assertEqualsWithDelta(0.087654, $indeks['08'], 1e-6);
        $this->assertEqualsWithDelta(1 / 12, $indeks['09'], 1e-9);
        $this->assertEqualsWithDelta(1.0, array_sum($indeks), 1e-9);

        $fasing = RevenueForecast::phase(900e9, $indeks);
        $harapan = [
            '01' => 70000000000, '02' => 72222222222, '03' => 75555555556, '04' => 73333333333,
            '05' => 78888888889, '06' => 74444444444, '07' => 76666666667, '08' => 78888888889,
            '09' => 75000000000, '10' => 75000000000, '11' => 75000000000, '12' => 75000000000,
        ];
        foreach ($harapan as $b => $nilai) {
            $this->assertEqualsWithDelta($nilai, $fasing[$b], 1, "Fasing bulan {$b} berbeda dari workbook.");
        }
        $this->assertEqualsWithDelta(900e9, array_sum($fasing), 0.01);
    }

    public function test_missing_data_gives_no_number_instead_of_zero(): void
    {
        $this->assertNull(RevenueForecast::yearEndEstimate(null, 8));
        $this->assertNull(RevenueForecast::cagr([2026 => 810e9]));
        $this->assertNull(RevenueForecast::linearForecast([2026 => 810e9], 2027));
        $this->assertNull(RevenueForecast::seasonalIndex(array_fill_keys(['01', '02'], null)));
    }
}
