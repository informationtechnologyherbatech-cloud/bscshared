<?php

namespace App\Support\Bsc;

/**
 * Pustaka 19 rumus rasio keuangan — sheet "Asumsi" bagian D dan sheet "L2
 * Rasio Keuangan" pada Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx.
 *
 * Tiap entitas memilih rasio mana yang dipakai, bobotnya, dan targetnya lewat
 * menu Katalog Rasio; rumusnya sendiri tetap di sini karena tersusun dari 16
 * pos akun yang sama (lihat AccountPosts).
 *
 * Satuan: rasio bertanda "%" disimpan dalam persen (35 = 35%), selebihnya
 * sebagai angka apa adanya (kali, hari, rupiah).
 */
class RatioLibrary
{
    public const NAIK = 'Naik';

    public const TURUN = 'Turun';

    public const RENTANG = 'Rentang';

    /**
     * @return array<string, array{name: string, group: string, formula: string, unit: string, polarity: string, weight: float}>
     */
    public static function all(): array
    {
        return [
            'P1' => ['name' => 'Gross Profit Margin', 'group' => 'Profitabilitas', 'formula' => 'Laba kotor ÷ Penjualan × 100', 'unit' => '%', 'polarity' => self::NAIK, 'weight' => 10],
            'P2' => ['name' => 'Net Profit Margin', 'group' => 'Profitabilitas', 'formula' => 'Laba bersih ÷ Penjualan × 100', 'unit' => '%', 'polarity' => self::NAIK, 'weight' => 8],
            'P3' => ['name' => 'Return on Asset', 'group' => 'Profitabilitas', 'formula' => 'Laba bersih disetahunkan ÷ Total aset × 100', 'unit' => '%', 'polarity' => self::NAIK, 'weight' => 6],
            'P4' => ['name' => 'Return on Equity', 'group' => 'Profitabilitas', 'formula' => 'Laba bersih disetahunkan ÷ Ekuitas × 100', 'unit' => '%', 'polarity' => self::NAIK, 'weight' => 6],
            'A1' => ['name' => 'Inventory Turnover', 'group' => 'Aktivitas', 'formula' => 'HPP disetahunkan ÷ Persediaan rata-rata', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 6],
            'A2' => ['name' => 'Asset Turnover', 'group' => 'Aktivitas', 'formula' => 'Penjualan disetahunkan ÷ Total aset', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 5],
            'A3' => ['name' => 'AR Turnover', 'group' => 'Aktivitas', 'formula' => 'Penjualan disetahunkan ÷ Piutang usaha rata-rata', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 5],
            'A4' => ['name' => 'Days Inventory Outstanding', 'group' => 'Aktivitas', 'formula' => '365 ÷ Inventory Turnover', 'unit' => 'hari', 'polarity' => self::TURUN, 'weight' => 3],
            'A5' => ['name' => 'Days Sales Outstanding', 'group' => 'Aktivitas', 'formula' => '365 ÷ AR Turnover', 'unit' => 'hari', 'polarity' => self::TURUN, 'weight' => 3],
            'A6' => ['name' => 'Days Payable Outstanding', 'group' => 'Aktivitas', 'formula' => '365 ÷ (HPP disetahunkan ÷ Utang usaha rata-rata)', 'unit' => 'hari', 'polarity' => self::RENTANG, 'weight' => 3],
            'D1' => ['name' => 'Produktivitas per tenaga kerja', 'group' => 'Produktivitas', 'formula' => 'Penjualan ÷ Jumlah karyawan', 'unit' => 'Rp', 'polarity' => self::NAIK, 'weight' => 7],
            'D2' => ['name' => 'Produktivitas per jam kerja', 'group' => 'Produktivitas', 'formula' => 'Penjualan ÷ Total jam kerja', 'unit' => 'Rp', 'polarity' => self::NAIK, 'weight' => 5],
            'D3' => ['name' => 'Produktivitas per biaya TK', 'group' => 'Produktivitas', 'formula' => 'Penjualan ÷ Beban tenaga kerja', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 5],
            'D4' => ['name' => 'Produktivitas Capital (CPR)', 'group' => 'Produktivitas', 'formula' => '(Laba kotor − Beban tenaga kerja) ÷ Modal', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 3],
            'L1' => ['name' => 'Current Ratio', 'group' => 'Likuiditas', 'formula' => 'Aset lancar ÷ Liabilitas lancar', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 6],
            'L2' => ['name' => 'Quick Ratio', 'group' => 'Likuiditas', 'formula' => '(Aset lancar − Persediaan) ÷ Liabilitas lancar', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 4],
            'L3' => ['name' => 'Cash Ratio', 'group' => 'Likuiditas', 'formula' => 'Kas & setara kas ÷ Liabilitas lancar', 'unit' => 'x', 'polarity' => self::NAIK, 'weight' => 5],
            'S1' => ['name' => 'Debt to Equity', 'group' => 'Solvabilitas', 'formula' => 'Total liabilitas ÷ Ekuitas', 'unit' => 'x', 'polarity' => self::TURUN, 'weight' => 6],
            'S2' => ['name' => 'Debt to Asset', 'group' => 'Solvabilitas', 'formula' => 'Total liabilitas ÷ Total aset', 'unit' => 'x', 'polarity' => self::TURUN, 'weight' => 4],
        ];
    }

    /** Kode tambahan untuk KPI yang menggerakkan Target Revenue (Tingkat 1). */
    public const REVENUE = 'REV';

    /**
     * Peta bagian 1 — pos akun pembentuk tiap rasio. P = pembilang, Y = penyebut,
     * P+ / P− = bagian pembilang gabungan yang menambah / mengurangi. DIO, DSO, DPO
     * (365 ÷ rasio lain) dibaca terbalik, persis seperti di workbook.
     *
     * @return array<string, array<string, string>>
     */
    public static function posts(): array
    {
        return [
            'P1' => ['PA01' => 'P+,Y', 'PA02' => 'P−'],
            'P2' => ['PA01' => 'P+,Y', 'PA02' => 'P−', 'PA03' => 'P−', 'PA04' => 'P−'],
            'P3' => ['PA01' => 'P+', 'PA02' => 'P−', 'PA03' => 'P−', 'PA11' => 'Y'],
            'P4' => ['PA01' => 'P+', 'PA02' => 'P−', 'PA03' => 'P−', 'PA13' => 'Y'],
            'A1' => ['PA02' => 'P', 'PA05' => 'Y'],
            'A2' => ['PA01' => 'P', 'PA11' => 'Y'],
            'A3' => ['PA01' => 'P', 'PA06' => 'Y'],
            'A4' => ['PA02' => 'Y', 'PA05' => 'P'],
            'A5' => ['PA01' => 'Y', 'PA06' => 'P'],
            'A6' => ['PA02' => 'Y', 'PA07' => 'P'],
            'D1' => ['PA01' => 'P', 'PA15' => 'Y'],
            'D2' => ['PA01' => 'P', 'PA16' => 'Y'],
            'D3' => ['PA01' => 'P', 'PA04' => 'Y'],
            'D4' => ['PA01' => 'P+', 'PA02' => 'P−', 'PA04' => 'P−', 'PA14' => 'Y'],
            'L1' => ['PA09' => 'P', 'PA10' => 'Y'],
            'L2' => ['PA05' => 'P−', 'PA09' => 'P+', 'PA10' => 'Y'],
            'L3' => ['PA08' => 'P', 'PA10' => 'Y'],
            'S1' => ['PA12' => 'P', 'PA13' => 'Y'],
            'S2' => ['PA11' => 'Y', 'PA12' => 'P'],
        ];
    }

    /**
     * Pos akun yang membentuk satu kode dampak. REV (Target Revenue) hanya
     * digerakkan lewat Penjualan.
     *
     * @return array<int, string>
     */
    public static function postsOf(string $code): array
    {
        if ($code === self::REVENUE) {
            return ['PA01'];
        }

        return array_keys(self::posts()[$code] ?? []);
    }

    /** Nama untuk kode dampak (rasio atau REV). */
    public static function impactName(string $code): ?string
    {
        if ($code === self::REVENUE) {
            return 'Target Revenue (Tingkat 1)';
        }

        return self::all()[$code]['name'] ?? null;
    }

    /** Lima kelompok & bobotnya (sheet Asumsi bagian B, sudah disepakati di BSC). */
    public static function groups(): array
    {
        return config('bsc.ratio_groups', [
            'Profitabilitas' => 30,
            'Aktivitas' => 25,
            'Produktivitas' => 20,
            'Likuiditas' => 15,
            'Solvabilitas' => 10,
        ]);
    }

    /**
     * Nilai rasio dari nilai pos akun yang dipakai. Null bila pos yang
     * dibutuhkan belum diisi atau penyebutnya nol.
     *
     * @param  array<string, float|null>  $p  hasil AccountPosts::usedValues()
     */
    public static function compute(string $code, array $p): ?float
    {
        $bagi = fn (?float $a, ?float $b): ?float => ($a === null || $b === null || $b == 0.0) ? null : $a / $b;
        $persen = fn (?float $x): ?float => $x === null ? null : $x * 100;
        $hari = fn (?float $x): ?float => ($x === null || $x == 0.0) ? null : 365 / $x;

        return match ($code) {
            'P1' => $persen($bagi($p['LK'], $p['PA01'])),
            'P2' => $persen($bagi($p['LB'], $p['PA01'])),
            'P3' => $persen($bagi($p['LB'], $p['PA11'])),
            'P4' => $persen($bagi($p['LB'], $p['PA13'])),
            'A1' => $bagi($p['PA02'], $p['PA05']),
            'A2' => $bagi($p['PA01'], $p['PA11']),
            'A3' => $bagi($p['PA01'], $p['PA06']),
            'A4' => $hari($bagi($p['PA02'], $p['PA05'])),
            'A5' => $hari($bagi($p['PA01'], $p['PA06'])),
            'A6' => $hari($bagi($p['PA02'], $p['PA07'])),
            'D1' => $bagi($p['PA01'], $p['PA15']),
            'D2' => $bagi($p['PA01'], $p['PA16']),
            'D3' => $bagi($p['PA01'], $p['PA04']),
            // "GPM selain gaji" dibaca sebagai Laba kotor − Beban tenaga kerja (catatan d di sheet Panduan).
            'D4' => $bagi($p['LK'] === null || $p['PA04'] === null ? null : $p['LK'] - $p['PA04'], $p['PA14']),
            'L1' => $bagi($p['PA09'], $p['PA10']),
            'L2' => $bagi($p['PA09'] === null || $p['PA05'] === null ? null : $p['PA09'] - $p['PA05'], $p['PA10']),
            'L3' => $bagi($p['PA08'], $p['PA10']),
            'S1' => $bagi($p['PA12'], $p['PA13']),
            'S2' => $bagi($p['PA12'], $p['PA11']),
            default => null,
        };
    }

    /**
     * Pencapaian dalam persen, dibatasi 0–100 (sheet L2 langkah 3):
     *   Naik    = aktual ÷ target
     *   Turun   = target ÷ aktual
     *   Rentang = 1 − |aktual − target| ÷ target
     * Melebihi target tidak menambah nilai — konvensi BSC.
     */
    public static function achievement(?float $actual, ?float $target, string $polarity): ?float
    {
        if ($actual === null || $target === null || $target == 0.0) {
            return null;
        }

        if ($target < 0) {
            // Target negatif (mis. margin rugi yang ditoleransi): rasio biasa terbalik
            // arah, jadi dinilai dari selisih relatif terhadap |target|.
            $selisih = ($actual - $target) / abs($target);
            $nilai = match ($polarity) {
                self::TURUN => 1 - $selisih,
                self::RENTANG => 1 - abs($selisih),
                default => 1 + $selisih,
            };

            return round(max(0.0, min(1.0, $nilai)) * 100, 2);
        }

        $nilai = match ($polarity) {
            // Realisasi 0 = sempurna; negatif (mis. DER dengan ekuitas negatif) bukan
            // "lebih baik" melainkan kondisi buruk → 0.
            self::TURUN => $actual == 0.0 ? 1.0 : ($actual < 0 ? 0.0 : $target / $actual),
            self::RENTANG => 1 - abs($actual - $target) / $target,
            default => $actual / $target,
        };

        return round(max(0.0, min(1.0, $nilai)) * 100, 2);
    }

    /**
     * Capaian sasaran mutu / rasio manual (0–100). Sama dengan achievement(),
     * tetapi target 0 tetap dinilai: tercapai (100) bila realisasi memenuhi arah
     * polaritasnya, selain itu 0. Dipakai monitoring bulanan dan menu edit,
     * sehingga keduanya selalu memberi hasil yang sama.
     */
    public static function objectiveAchievement(float $actual, float $target, ?string $polarity): float
    {
        $polarity = $polarity ?: self::NAIK;

        if ($target == 0.0) {
            $tercapai = match ($polarity) {
                self::TURUN => $actual <= 0.0,
                self::RENTANG => $actual == 0.0,
                default => $actual >= 0.0,
            };

            return $tercapai ? 100.0 : 0.0;
        }

        return self::achievement($actual, $target, $polarity) ?? 0.0;
    }

    /**
     * Skor rubrik (sheet Asumsi bagian C): ≥90% → 100 · ≥80% → 80 ·
     * ≥75% → 70 · ≥65% → 60 · selebihnya 50. Celah 74–75 dan 64–65 pada
     * dokumen sumber dibaca kontinu (catatan e).
     */
    public static function rubric(?float $achievementPct): ?float
    {
        if ($achievementPct === null) {
            return null;
        }

        foreach (config('bsc.rubric', [90 => 100, 80 => 80, 75 => 70, 65 => 60, 0 => 50]) as $minimal => $skor) {
            if ($achievementPct >= $minimal) {
                return (float) $skor;
            }
        }

        return 50.0;
    }

    /** Format nilai rasio sesuai satuannya. */
    public static function format(?float $value, string $unit): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($unit) {
            '%' => number_format($value, 2, ',', '.').'%',
            'hari' => number_format($value, 1, ',', '.').' hari',
            'Rp' => 'Rp '.number_format($value, 0, ',', '.'),
            default => number_format($value, 2, ',', '.').'x',
        };
    }
}
