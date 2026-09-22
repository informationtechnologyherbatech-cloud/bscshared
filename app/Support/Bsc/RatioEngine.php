<?php

namespace App\Support\Bsc;

use App\Models\AccountBalance;
use App\Models\FinancialRatio;
use App\Models\RatioDefinition;
use App\Models\RatioTarget;
use Illuminate\Support\Facades\DB;

/**
 * Mesin Tingkat 2 — menjalankan rantai sheet "L2 Rasio Keuangan" untuk entitas
 * yang sedang aktif:
 *
 *   pos akun → nilai dipakai (×12/n, rata-rata neraca) → 19 rasio
 *   → pencapaian (sesuai polaritas, maks 100%) → skor rubrik
 *   → skor tertimbang (rubrik × bobot ÷ 100) → Σ = F2
 *
 * Semua kueri memakai model berentitas, sehingga otomatis terbatas pada
 * entitas aktif.
 */
class RatioEngine
{
    public const SOURCE_COMPUTED = 'computed';

    /** Status bila rasio sudah terhitung tetapi targetnya belum diisi. */
    public const TANPA_TARGET = 'Belum Ada Target';

    /**
     * Isian pos akun satu periode.
     *
     * @return array<string, array{amount: float|null, opening: float|null}>
     */
    public function inputs(string $period): array
    {
        $tersimpan = AccountBalance::where('period', $period)->get()->keyBy('code');
        $hasil = [];

        foreach (array_keys(AccountPosts::all()) as $kode) {
            $baris = $tersimpan->get($kode);
            $hasil[$kode] = [
                'amount' => $baris?->amount,
                'opening' => $baris?->opening,
            ];
        }

        return $hasil;
    }

    public static function monthOf(string $period): int
    {
        return (int) substr($period, 5, 2);
    }

    /**
     * Evaluasi lengkap satu periode.
     *
     * @return array{
     *     used: array<string, float|null>,
     *     rows: array<int, array<string, mixed>>,
     *     groups: array<string, array{weight: float, weighted: float, scored_weight: float, achievement: float|null}>,
     *     f2: float|null,
     *     scored: int,
     *     active: int
     * }
     */
    public function evaluate(string $period): array
    {
        return $this->evaluateWith(
            $this->inputs($period),
            self::monthOf($period),
            $this->targetsFor(substr($period, 0, 4))
        );
    }

    /**
     * Evaluasi dari isian mentah — dipisah agar dapat diuji dan dipakai untuk
     * pratinjau sebelum disimpan.
     *
     * @param  array<string, array{amount: float|null, opening: float|null}>  $inputs
     * @param  array<string, float|null>  $targets
     */
    public function evaluateWith(array $inputs, int $bulan, array $targets): array
    {
        return $this->evaluateUsed(AccountPosts::usedValues($inputs, $bulan), $targets);
    }

    /**
     * Evaluasi dari nilai pos akun yang sudah "dipakai" (disetahunkan /
     * dirata-rata, termasuk LK & LB). Dipakai juga oleh simulasi Uji B, yang
     * menggeser nilai-nilai ini lalu menilai ulang.
     *
     * @param  array<string, float|null>  $dipakai
     * @param  array<string, float|null>  $targets
     */
    public function evaluateUsed(array $dipakai, array $targets): array
    {
        $baris = [];
        $kelompok = [];

        foreach (RatioLibrary::groups() as $nama => $bobot) {
            $kelompok[$nama] = ['weight' => (float) $bobot, 'weighted' => 0.0, 'scored_weight' => 0.0, 'achievement' => null];
        }

        $totalTertimbang = 0.0;
        $totalBobotTerskor = 0.0;
        $terskor = 0;

        foreach (RatioDefinition::active()->get() as $definisi) {
            $meta = $definisi->meta();
            $aktual = RatioLibrary::compute($definisi->code, $dipakai);
            $target = $targets[$definisi->code] ?? null;
            $capaian = RatioLibrary::achievement($aktual, $target, $meta['polarity']);
            $rubrik = RatioLibrary::rubric($capaian);
            $tertimbang = $rubrik === null ? null : $rubrik * $definisi->weight / 100;

            if ($tertimbang !== null) {
                $terskor++;
                $totalTertimbang += $tertimbang;
                $totalBobotTerskor += $definisi->weight;

                if (isset($kelompok[$meta['group']])) {
                    $kelompok[$meta['group']]['weighted'] += $tertimbang;
                    $kelompok[$meta['group']]['scored_weight'] += $definisi->weight;
                }
            }

            $baris[] = [
                'code' => $definisi->code,
                'name' => $meta['name'],
                'group' => $meta['group'],
                'formula' => $meta['formula'],
                'unit' => $meta['unit'],
                'polarity' => $meta['polarity'],
                'weight' => $definisi->weight,
                'actual' => $aktual,
                'target' => $target,
                'achievement' => $capaian,
                'rubric' => $rubrik,
                'weighted' => $tertimbang === null ? null : round($tertimbang, 4),
                'status' => $this->status($aktual, $capaian),
            ];
        }

        foreach ($kelompok as $nama => $k) {
            $kelompok[$nama]['weighted'] = round($k['weighted'], 4);
            $kelompok[$nama]['achievement'] = $k['scored_weight'] > 0
                ? round($k['weighted'] / $k['scored_weight'], 4)
                : null;
        }

        // F2 skala 0–100. Bila seluruh rasio terskor dan bobotnya berjumlah 100,
        // ini persis Σ skor tertimbang seperti di sheet L2. Rasio yang belum
        // punya data/target dikeluarkan dan bobotnya dinormalisasi.
        $f2 = $totalBobotTerskor > 0 ? round($totalTertimbang / $totalBobotTerskor * 100, 2) : null;

        return [
            'used' => $dipakai,
            'rows' => $baris,
            'groups' => $kelompok,
            'f2' => $f2,
            'scored' => $terskor,
            'active' => count($baris),
        ];
    }

    /**
     * Simpan hasil ke financial_ratios supaya halaman Rasio Keuangan, dashboard,
     * dan riwayat periode memakainya. Baris hasil hitungan sebelumnya untuk
     * rasio yang kini nonaktif atau tidak lagi terhitung dihapus.
     */
    public function materialize(string $period): array
    {
        $hasil = $this->evaluate($period);

        DB::transaction(function () use ($period, $hasil) {
            $disimpan = [];

            foreach ($hasil['rows'] as $r) {
                if ($r['actual'] === null) {
                    continue;
                }

                FinancialRatio::updateOrCreate(
                    ['period' => $period, 'ratio_code' => $r['code']],
                    [
                        'category' => $r['group'],
                        'ratio_name' => $r['name'],
                        'unit' => $r['unit'],
                        'polarity' => $r['polarity'],
                        'target' => $r['target'] ?? 0,
                        'actual' => $r['actual'],
                        'achievement_pct' => $r['achievement'] ?? 0,
                        'weight' => $r['weight'],
                        'rubric_score' => $r['rubric'],
                        'weighted_score' => $r['weighted'],
                        'status' => $r['status'],
                        'source' => self::SOURCE_COMPUTED,
                    ]
                );

                $disimpan[] = $r['code'];
            }

            FinancialRatio::where('period', $period)
                ->where('source', self::SOURCE_COMPUTED)
                ->whereNotIn('ratio_code', $disimpan ?: ['__kosong__'])
                ->delete();
        });

        return $hasil;
    }

    /**
     * F2 dari baris yang sudah tersimpan: Σ(rubrik × bobot) ÷ Σ bobot.
     * Null bila periode itu belum punya rasio hasil hitungan.
     */
    public static function storedScore(string $period): ?float
    {
        $baris = FinancialRatio::where('period', $period)
            ->where('source', self::SOURCE_COMPUTED)
            ->whereNotNull('rubric_score')
            ->get(['rubric_score', 'weight']);

        $bobot = (float) $baris->sum('weight');

        if ($bobot <= 0) {
            return null;
        }

        $tertimbang = $baris->sum(fn ($b) => (float) $b->rubric_score * (float) $b->weight);

        return round($tertimbang / $bobot, 2);
    }

    /**
     * Target tahunan entitas aktif.
     *
     * @return array<string, float|null>
     */
    public function targetsFor(string $year): array
    {
        return RatioTarget::where('year', $year)->pluck('target', 'code')
            ->map(fn ($t) => $t === null ? null : (float) $t)
            ->all();
    }

    /**
     * Cek konsistensi target (sheet L2, blok "Cek konsistensi target").
     * Hanya memeriksa pasangan yang kedua targetnya terisi.
     *
     * @param  array<string, float|null>  $t
     * @return array<int, array{label: string, ok: bool|null, note: string}>
     */
    public static function consistencyChecks(array $t, float $totalWeight): array
    {
        $cek = function (?float $a, ?float $b, callable $uji): ?bool {
            return $a === null || $b === null ? null : $uji($a, $b);
        };
        // Toleransi sama dengan workbook: selisih cermin kurang dari 1 hari.
        $hampir = fn (float $a, float $b): bool => abs($a - $b) < 1;

        return [
            ['label' => 'NPM target ≤ GPM target', 'ok' => $cek($t['P2'] ?? null, $t['P1'] ?? null, fn ($a, $b) => $a <= $b), 'note' => 'Laba bersih tidak mungkin melebihi laba kotor.'],
            ['label' => 'ROE target ≥ ROA target', 'ok' => $cek($t['P4'] ?? null, $t['P3'] ?? null, fn ($a, $b) => $a >= $b), 'note' => 'Dengan ada utang, ekuitas lebih kecil dari total aset.'],
            ['label' => 'DIO target = 365 ÷ ITO target', 'ok' => $cek($t['A4'] ?? null, $t['A1'] ?? null, fn ($a, $b) => $b > 0 && $hampir($a, 365 / $b)), 'note' => 'Rasio cermin harus satu angka.'],
            ['label' => 'DSO target = 365 ÷ ART target', 'ok' => $cek($t['A5'] ?? null, $t['A3'] ?? null, fn ($a, $b) => $b > 0 && $hampir($a, 365 / $b)), 'note' => 'Rasio cermin harus satu angka.'],
            ['label' => 'Quick target ≤ Current target', 'ok' => $cek($t['L2'] ?? null, $t['L1'] ?? null, fn ($a, $b) => $a <= $b), 'note' => 'Quick ratio mengeluarkan persediaan.'],
            ['label' => 'Cash target ≤ Quick target', 'ok' => $cek($t['L3'] ?? null, $t['L2'] ?? null, fn ($a, $b) => $a <= $b), 'note' => 'Kas adalah bagian dari aset lancar.'],
            ['label' => 'DAR target ≤ DER target', 'ok' => $cek($t['S2'] ?? null, $t['S1'] ?? null, fn ($a, $b) => $a <= $b), 'note' => 'Total aset selalu ≥ ekuitas.'],
            ['label' => 'Total bobot rasio aktif = 100', 'ok' => abs($totalWeight - 100) < 0.01, 'note' => 'Saat ini '.rtrim(rtrim(number_format($totalWeight, 2, ',', '.'), '0'), ',').'.'],
        ];
    }

    private function status(?float $aktual, ?float $capaian): string
    {
        if ($aktual !== null && $capaian === null) {
            return self::TANPA_TARGET;
        }

        return match (true) {
            $capaian === null => 'Belum Lengkap',
            $capaian >= 100 => 'Tercapai',
            $capaian >= 80 => 'Waspada',
            default => 'Di Bawah Target',
        };
    }
}
