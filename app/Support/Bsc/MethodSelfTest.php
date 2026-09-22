<?php

namespace App\Support\Bsc;

use App\Models\RatioDefinition;

/**
 * Uji mandiri metode skoring (menu Dokumentasi Metode): 12 pemeriksaan yang
 * menjalankan mesin aplikasi yang SAMA dengan angka ilustrasi workbook dan
 * membandingkannya dengan hasil Excel. Tidak membaca maupun menulis data
 * perusahaan — katalog rasio yang dipakai adalah katalog baku.
 */
class MethodSelfTest
{
    /**
     * @return array<int, array{no: int, name: string, rule: string, expected: string, actual: string, ok: bool}>
     */
    public static function run(): array
    {
        $hasil = [];
        $cek = function (string $nama, string $aturan, $harap, $nyata, float $toleransi = 1e-6) use (&$hasil) {
            $ok = is_bool($harap) ? $harap === $nyata
                : ($nyata !== null && abs((float) $harap - (float) $nyata) <= $toleransi * max(1.0, abs((float) $harap)));
            $tampil = fn ($v) => is_bool($v) ? ($v ? 'ya' : 'tidak') : ($v === null ? '—' : rtrim(rtrim(number_format((float) $v, 6, ',', '.'), '0'), ','));
            $hasil[] = ['no' => count($hasil) + 1, 'name' => $nama, 'rule' => $aturan,
                'expected' => $tampil($harap), 'actual' => $tampil($nyata), 'ok' => $ok];
        };

        $dipakai = AccountPosts::usedValues(WorkbookIllustration::inputs(), WorkbookIllustration::BULAN);
        $katalog = collect(RatioLibrary::all())->map(fn ($r, $kode) => new RatioDefinition([
            'code' => $kode, 'weight' => $r['weight'], 'is_active' => true,
        ]))->values();
        $evaluasi = app(RatioEngine::class)->evaluateUsed($dipakai, WorkbookIllustration::TARGET_RASIO, $katalog);
        $rasio = collect($evaluasi['rows'])->keyBy('code');

        // 1–2. Konvensi nilai dipakai (sheet Asumsi G).
        $cek('Pos aliran disetahunkan', 'Penjualan YTD 540 M × 12 ÷ 8', 810e9, $dipakai['PA01']);
        $cek('Pos neraca dirata-rata', 'Total aset (720 M + 756 M) ÷ 2', 738e9, $dipakai['PA11']);

        // 3. Contoh rasio.
        $cek('Rasio dihitung dari pos akun', 'Inventory Turnover = HPP disetahunkan ÷ persediaan rata-rata', 4.68, $rasio['A1']['actual']);

        // 4–7. Polaritas & batas 100%.
        $cek('Polaritas Naik', 'GPM 35% vs target 37% → aktual ÷ target', 94.59, RatioLibrary::achievement(35, 37, RatioLibrary::NAIK)); // capaian dibulatkan 2 desimal
        $cek('Polaritas Turun', 'DSO 42,58 hari vs target 36 → target ÷ aktual', 84.54, RatioLibrary::achievement(42.58333333, 36, RatioLibrary::TURUN), 1e-3);
        $cek('Polaritas Rentang', 'DPO 45,24 hari vs target 45 → 1 − |selisih| ÷ target', 99.48, RatioLibrary::achievement(45.23504274, 45, RatioLibrary::RENTANG), 1e-3);
        $cek('Pencapaian dibatasi 100%', 'Melebihi target tidak menambah nilai', 100.0, RatioLibrary::achievement(50, 37, RatioLibrary::NAIK));

        // 8. Rubrik pada batas-batasnya.
        // Pasangan [capaian, skor] — kunci array pecahan akan dibulatkan PHP.
        $rubrik = [[90, 100], [89.99, 80], [80, 80], [75, 70], [65, 60], [64.99, 50]];
        $rubrikOk = collect($rubrik)->every(fn ($p) => RatioLibrary::rubric((float) $p[0]) === (float) $p[1]);
        $cek('Rubrik 5 tingkat', '≥90→100 · ≥80→80 · ≥75→70 · ≥65→60 · <65→50', true, $rubrikOk);

        // 9–10. F2 & skor puncak.
        $cek('Skor Tingkat 2 (F2)', 'Σ rubrik × bobot ÷ 100 atas 19 rasio', 94.1, $evaluasi['f2']);
        $bobot = config('bsc.apex_weights');
        $cek('Skor puncak', '0,45 × F1 (90) + 0,55 × F2', 92.255, $bobot['revenue'] * 90 + $bobot['ratios'] * $evaluasi['f2']);

        // 11. Proyeksi revenue (sheet L1 B).
        $deret = WorkbookIllustration::REVENUE_HISTORIS + [2026 => $dipakai['PA01']];
        $cek('Regresi linear target revenue', 'FORECAST 2027 atas 2023–2026E', 895e9, RevenueForecast::linearForecast($deret, 2027));

        // 12. Fasing musiman (sheet L1 G).
        $fasing = RevenueForecast::phase(900e9, RevenueForecast::seasonalIndex(WorkbookIllustration::REALISASI_BULANAN));
        $cek('Fasing musiman target', 'Target Jan = 900 M × (63 M ÷ 810 M)', 70e9, $fasing['01']);

        return $hasil;
    }
}
