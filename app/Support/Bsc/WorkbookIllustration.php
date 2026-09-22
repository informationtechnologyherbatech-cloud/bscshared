<?php

namespace App\Support\Bsc;

/**
 * Angka ILUSTRASI dari workbook Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx —
 * satu sumber untuk data demo (BSC_SEED_DEMO) dan Uji Mandiri metode.
 * Bukan data perusahaan; jangan dipakai sebagai angka sungguhan.
 */
final class WorkbookIllustration
{
    /** Bulan berjalan tahun dasar (Jan–Agu 2026). */
    public const BULAN = 8;

    /** Sheet Asumsi bagian G — [nilai YTD / saldo akhir, saldo awal]. */
    public const POS_AKUN = [
        'PA01' => [540e9, null], 'PA02' => [351e9, null], 'PA03' => [135e9, null], 'PA04' => [81e9, null],
        'PA05' => [117e9, 108e9], 'PA06' => [99e9, 90e9], 'PA07' => [67.5e9, 63e9], 'PA08' => [58.5e9, 54e9],
        'PA09' => [333e9, 315e9], 'PA10' => [189e9, 180e9], 'PA11' => [756e9, 720e9], 'PA12' => [324e9, 315e9],
        'PA13' => [432e9, 405e9], 'PA14' => [270e9, 270e9], 'PA15' => [320, null], 'PA16' => [450000, null],
    ];

    /** Sheet L2 kolom Target (persen ditulis dalam persen). */
    public const TARGET_RASIO = [
        'P1' => 37, 'P2' => 11, 'P3' => 12, 'P4' => 20,
        'A1' => 6, 'A2' => 1.2, 'A3' => 10, 'A4' => 60, 'A5' => 36, 'A6' => 45,
        'D1' => 2.8e9, 'D2' => 1.3e6, 'D3' => 7, 'D4' => 0.7,
        'L1' => 1.8, 'L2' => 1.2, 'L3' => 0.35,
        'S1' => 0.7, 'S2' => 0.4,
    ];

    /** Sheet L1 bagian B — realisasi 2023–2025 (2026 = estimasi bagian A). */
    public const REVENUE_HISTORIS = [2023 => 560e9, 2024 => 640e9, 2025 => 730e9];

    /** Sheet L1 bagian G — realisasi 2026 Jan–Agu. */
    public const REALISASI_BULANAN = [
        '01' => 63e9, '02' => 65e9, '03' => 68e9, '04' => 66e9, '05' => 71e9, '06' => 67e9,
        '07' => 69e9, '08' => 71e9, '09' => null, '10' => null, '11' => null, '12' => null,
    ];

    /** Sheet L1 bagian C — channel & basis 2026E per brand (Rp), growth 2027. */
    public const CHANNELS = ['SOC', 'TTC', 'ECO', 'OFD', 'PTN'];

    public const BRANDS = [
        ['name' => 'Eyebost', 'cells' => [190e9, 145e9, 75e9, 48e9, 18e9], 'growth' => 0.12],
        ['name' => 'Brand 2', 'cells' => [96e9, 76e9, 38e9, 19e9, 9e9], 'growth' => 0.15],
        ['name' => 'Brand 3', 'cells' => [38e9, 29e9, 19e9, 10e9, 0], 'growth' => 0.20],
    ];

    /** Sheet L1 bagian D — inisiatif Ansoff (revenue tambahan, probabilitas). */
    public const ANSOFF = [
        ['quadrant' => 'penetrasi', 'initiative' => 'Perluasan coverage outlet Modern Trade', 'revenue' => 25e9, 'probability' => 0.7],
        ['quadrant' => 'pasar', 'initiative' => 'Masuk 1 marketplace / negara baru', 'revenue' => 40e9, 'probability' => 0.4],
        ['quadrant' => 'produk', 'initiative' => 'Peluncuran 3 SKU baru hasil PDV', 'revenue' => 50e9, 'probability' => 0.5],
    ];

    /** Sheet L1 bagian E — SWOT. */
    public const SWOT = [
        's' => 'Brand Eyebost sudah dikenal di TikTok',
        'w' => 'Ketergantungan pada satu manufacture',
        'o' => 'Pertumbuhan live commerce',
        't' => 'Perubahan algoritma / komisi platform',
    ];

    /** @return array<string, array{amount: float|null, opening: float|null}> */
    public static function inputs(): array
    {
        return array_map(
            fn ($v) => ['amount' => (float) $v[0], 'opening' => $v[1] === null ? null : (float) $v[1]],
            self::POS_AKUN
        );
    }
}
