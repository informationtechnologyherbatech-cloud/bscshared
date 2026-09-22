<?php

namespace App\Support\Bsc;

/**
 * Sheet "L1 Target Revenue" bagian A–G: rumus murni tanpa basis data, supaya
 * bisa diuji langsung terhadap angka workbook.
 *
 *   A  estimasi akhir tahun dasar = YTD × 12 ÷ n (run-rate)
 *   B  CAGR 3 tahun & regresi linear (FORECAST) atas 4 titik
 *   C  bottom-up brand × channel: Σ sel × (1 + growth brand)
 *   D  Ansoff: Σ estimasi × probabilitas (expected value)
 *   E  koreksi SWOT (+/− %)
 *   F  rekonsiliasi enam angka terhadap target disahkan
 *   G  indeks musiman dari realisasi tahun dasar → fasing bulanan
 */
class RevenueForecast
{
    /** Σ basis bottom-up boleh berbeda dari estimasi bagian A paling banyak 2%. */
    public const TOLERANSI_BASIS = 0.02;

    public const ANSOFF = [
        'penetrasi' => 'Penetrasi pasar (produk lama, pasar lama)',
        'pasar' => 'Pengembangan pasar (produk lama, pasar baru)',
        'produk' => 'Pengembangan produk (produk baru, pasar lama)',
        'diversifikasi' => 'Diversifikasi (produk baru, pasar baru)',
    ];

    /** Bagian A. Null bila belum ada YTD atau n tidak sah. */
    public static function yearEndEstimate(?float $ytd, int $bulan): ?float
    {
        if ($ytd === null || $bulan < 1 || $bulan > 12) {
            return null;
        }

        return $ytd * 12 / $bulan;
    }

    /**
     * Bagian B — CAGR dari titik pertama ke terakhir.
     *
     * @param  array<int, float>  $deret  tahun => nilai, urut naik
     */
    public static function cagr(array $deret): ?float
    {
        ksort($deret);
        $tahun = array_keys($deret);

        if (count($deret) < 2) {
            return null;
        }

        $awal = $deret[$tahun[0]];
        $akhir = $deret[end($tahun)];
        $rentang = end($tahun) - $tahun[0];

        if ($awal <= 0 || $akhir <= 0 || $rentang <= 0) {
            return null;
        }

        return ($akhir / $awal) ** (1 / $rentang) - 1;
    }

    /**
     * Bagian B — regresi linear kuadrat terkecil, setara FORECAST Excel.
     *
     * @param  array<int, float>  $deret  tahun => nilai
     */
    public static function linearForecast(array $deret, int $tahunTarget): ?float
    {
        $n = count($deret);

        if ($n < 2) {
            return null;
        }

        $rataX = array_sum(array_keys($deret)) / $n;
        $rataY = array_sum($deret) / $n;
        $sxy = 0.0;
        $sxx = 0.0;

        foreach ($deret as $x => $y) {
            $sxy += ($x - $rataX) * ($y - $rataY);
            $sxx += ($x - $rataX) ** 2;
        }

        if ($sxx == 0.0) {
            return null;
        }

        return $rataY + ($sxy / $sxx) * ($tahunTarget - $rataX);
    }

    /**
     * Pertumbuhan tahun ke tahun.
     *
     * @param  array<int, float>  $deret
     * @return array<int, float|null>
     */
    public static function yoy(array $deret): array
    {
        ksort($deret);
        $hasil = [];
        $sebelum = null;

        foreach ($deret as $tahun => $nilai) {
            $hasil[$tahun] = $sebelum === null || $sebelum == 0.0 ? null : $nilai / $sebelum - 1;
            $sebelum = $nilai;
        }

        return $hasil;
    }

    /**
     * Bagian C.
     *
     * @param  array<int, string>  $channels
     * @param  array<int, array{name: string, cells: array<string, float|null>, growth: float|null}>  $brands
     * @return array{
     *     brands: array<int, array{name: string, base: float, growth: float, target: float}>,
     *     channel_base: array<string, float>, channel_target: array<string, float>,
     *     base: float, target: float, growth: float|null
     * }
     */
    public static function bottomUp(array $channels, array $brands): array
    {
        $perBrand = [];
        $basisChannel = array_fill_keys($channels, 0.0);
        $targetChannel = array_fill_keys($channels, 0.0);

        foreach ($brands as $b) {
            $growth = (float) ($b['growth'] ?? 0);
            $basis = 0.0;

            foreach ($channels as $c) {
                $sel = (float) ($b['cells'][$c] ?? 0);
                $basis += $sel;
                $basisChannel[$c] += $sel;
                $targetChannel[$c] += $sel * (1 + $growth);
            }

            $perBrand[] = ['name' => $b['name'], 'base' => $basis, 'growth' => $growth, 'target' => $basis * (1 + $growth)];
        }

        $basis = array_sum($basisChannel);
        $target = array_sum($targetChannel);

        return [
            'brands' => $perBrand,
            'channel_base' => $basisChannel,
            'channel_target' => $targetChannel,
            'base' => $basis,
            'target' => $target,
            'growth' => $basis > 0 ? $target / $basis - 1 : null,
        ];
    }

    /**
     * Bagian D.
     *
     * @param  array<int, array{revenue: float|null, probability: float|null}>  $inisiatif
     */
    public static function ansoffExpectedValue(array $inisiatif): float
    {
        return array_sum(array_map(fn ($i) => (float) ($i['revenue'] ?? 0) * (float) ($i['probability'] ?? 0), $inisiatif));
    }

    /**
     * Bagian F — enam angka pembanding. Metode tanpa data bernilai null.
     *
     * @return array<string, array{label: string, value: float|null, nature: string}>
     */
    public static function reconciliation(?float $runRate, ?float $cagr, ?float $regresi, ?float $bottomUp, float $ansoffEv, float $koreksiSwot): array
    {
        $tiga = array_filter([$cagr, $regresi, $bottomUp], fn ($v) => $v !== null);

        return [
            'runrate' => ['label' => 'Metode 1 — Run-rate tahun dasar tanpa pertumbuhan (batas bawah)', 'value' => $runRate, 'nature' => 'Konservatif: kalau tahun depan = tahun ini'],
            'cagr' => ['label' => 'Metode 2a — CAGR 3 tahun', 'value' => $cagr, 'nature' => 'Tren majemuk masa lalu'],
            'regression' => ['label' => 'Metode 2b — Regresi linear', 'value' => $regresi, 'nature' => 'Tren garis lurus masa lalu'],
            'bottomup' => ['label' => 'Metode 3 — Bottom-up brand × channel', 'value' => $bottomUp, 'nature' => 'Komitmen PGM per brand'],
            'ambitious' => [
                'label' => 'Metode 3 + Ansoff EV, dikoreksi SWOT',
                'value' => $bottomUp === null ? null : ($bottomUp + $ansoffEv) * (1 + $koreksiSwot),
                'nature' => 'Ambisius: termasuk inisiatif baru',
            ],
            'average' => [
                'label' => 'Rata-rata metode 2a, 2b, 3',
                'value' => $tiga === [] ? null : array_sum($tiga) / count($tiga),
                'nature' => 'Titik tengah',
            ],
        ];
    }

    /**
     * Bagian G — indeks musiman: realisasi bulan ÷ estimasi akhir tahun dasar;
     * bulan tanpa realisasi berbagi rata sisa indeks.
     *
     * @param  array<string, float|null>  $realisasi  '01'..'12' => nilai (null = belum ada)
     * @return array<string, float>|null null bila belum ada realisasi sama sekali
     */
    public static function seasonalIndex(array $realisasi, ?float $estimasi = null): ?array
    {
        $bulan = array_map(fn ($b) => str_pad((string) $b, 2, '0', STR_PAD_LEFT), range(1, 12));
        $ada = array_filter($realisasi, fn ($v) => $v !== null);

        if ($ada === []) {
            return null;
        }

        $estimasi ??= array_sum($ada) * 12 / count($ada);

        if ($estimasi <= 0) {
            return null;
        }

        $kosong = 12 - count($ada);
        // Estimasi yang tidak melebihi realisasi tercatat (mis. YTD manual lebih kecil)
        // akan memberi bulan sisa indeks ≤ 0 → target negatif/nol. Pakai run-rate.
        if ($kosong > 0 && $estimasi <= array_sum($ada)) {
            $estimasi = array_sum($ada) * 12 / count($ada);
        }
        $sisa = $kosong > 0 ? (1 - array_sum($ada) / $estimasi) / $kosong : 0.0;

        $indeks = [];
        foreach ($bulan as $b) {
            $indeks[$b] = ($realisasi[$b] ?? null) !== null ? $realisasi[$b] / $estimasi : $sisa;
        }

        // Jumlah indeks selalu 1 agar fasing membagi habis target setahun.
        $total = array_sum($indeks);

        return $total > 0 ? array_map(fn ($i) => $i / $total, $indeks) : $indeks;
    }

    /**
     * Fasing target setahun memakai indeks musiman. Pembulatan rupiah; selisih
     * pembulatan ditaruh di Desember agar jumlahnya tepat.
     *
     * @param  array<string, float>  $indeks
     * @return array<string, float>
     */
    public static function phase(float $targetSetahun, array $indeks): array
    {
        $hasil = [];
        $terpakai = 0.0;

        foreach ($indeks as $b => $i) {
            if ((string) $b === '12') { // kunci '12' menjadi int di array PHP
                $hasil[$b] = round($targetSetahun - $terpakai, 2);

                continue;
            }
            $hasil[$b] = round($targetSetahun * $i, 2);
            $terpakai += $hasil[$b];
        }

        return $hasil;
    }
}
