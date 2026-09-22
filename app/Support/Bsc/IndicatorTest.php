<?php

namespace App\Support\Bsc;

use App\Models\KpiCascade;

/**
 * Sheet "L4 Uji Indikator".
 *
 * Uji A — checklist logika sebab-akibat, 8 pertanyaan Ya/Tidak. Q3, Q4, Q7, Q8
 * dapat dijawab dari data (Peta & Cascade), sisanya dijawab Keuangan.
 *
 * Uji B — simulasi numerik: KPI membaik x% → tiap pos akun bergerak
 * x% × koefisien transmisi → 19 rasio & skor dinilai ulang dengan mesin yang
 * sama seperti Tingkat 2. Rasio yang diklaim harus bergerak ke arah baik.
 */
class IndicatorTest
{
    public const QUESTIONS = [
        1 => 'Berdampak langsung pada SATU pos akun yang jelas?',
        2 => 'Arah pengaruh ke pos akun terdefinisi?',
        3 => 'Pos akun muncul dalam rumus rasio yang diklaim? (Peta bagian 1)',
        4 => 'Unit adalah Pemilik/Kontributor pos akun itu? (Peta bagian 2)',
        5 => 'Data tersedia bulanan & tertelusur ke record?',
        6 => 'Tidak menghitung ganda (rasio cermin / KPI lain)?',
        7 => 'Jenis ukuran sesuai level (Head=Lag, Spv=Lead, Staff=Output)?',
        8 => 'Bobot ditetapkan & Σ per jabatan = 100%?',
    ];

    /** Pertanyaan yang jawabannya dihitung dari data. */
    public const AUTO = [3, 4, 7, 8];

    /** Guardrail cukup lolos pertanyaan ini. */
    public const GUARDRAIL_REQUIRED = [5, 7, 8];

    public const LOLOS = 'LOLOS';

    public const LOLOS_GUARDRAIL = 'LOLOS (guardrail)';

    public const REVISI_MINOR = 'REVISI MINOR';

    public const REVISI = 'REVISI';

    /** Batas "bergerak" — setara 0,00001 pada rasio pecahan di workbook. */
    private const EPS = 1e-6;

    /**
     * Jawaban Q3, Q4, Q7, Q8 dari hasil CascadeChecks satu KPI.
     *
     * @param  array<string, mixed>  $cek  CascadeChecks::forRow()
     * @return array<int, bool>
     */
    public static function autoAnswers(KpiCascade $kpi, array $cek): array
    {
        $posDiRumus = $kpi->ratio_code && $kpi->post_code
            && in_array($kpi->post_code, RatioLibrary::postsOf($kpi->ratio_code), true);

        return [
            3 => $posDiRumus,
            4 => in_array($cek['role'], ['Pemilik', 'Kontributor'], true),
            7 => (bool) $cek['measure_ok'],
            8 => (bool) $cek['weight_ok'],
        ];
    }

    /**
     * Kolom "Hasil" Uji A. Null bila ada pertanyaan yang wajib belum dijawab.
     *
     * @param  array<int, bool|null>  $jawaban  1..8
     */
    public static function ujiAResult(bool $guardrail, array $jawaban): ?string
    {
        if ($guardrail) {
            foreach (self::GUARDRAIL_REQUIRED as $q) {
                if (($jawaban[$q] ?? null) === null) {
                    return null;
                }
            }

            return collect(self::GUARDRAIL_REQUIRED)->every(fn ($q) => $jawaban[$q] === true)
                ? self::LOLOS_GUARDRAIL
                : self::REVISI;
        }

        foreach (array_keys(self::QUESTIONS) as $q) {
            if (($jawaban[$q] ?? null) === null) {
                return null;
            }
        }

        $ya = collect($jawaban)->filter(fn ($j) => $j === true)->count();

        return match (true) {
            $ya === 8 => self::LOLOS,
            $ya === 7 => self::REVISI_MINOR,
            default => self::REVISI,
        };
    }

    /**
     * Uji B.
     *
     * @param  array<string, float|null>  $dipakai  nilai dipakai baseline (AccountPosts::usedValues)
     * @param  float  $perbaikan  perbaikan KPI, pecahan (0,05 = 5%)
     * @param  array<string, float|null>  $koefisien  PA01..PA16 → koefisien transmisi
     * @param  array<string, float|null>  $targets
     * @return array{
     *     used: array<string, array{baseline: float|null, delta_pct: float, scenario: float|null}>,
     *     rows: array<int, array<string, mixed>>,
     *     f2_baseline: float|null, f2_scenario: float|null, delta_score: float|null,
     *     claimed: string, claimed_polarity: string|null, claimed_delta: float|null,
     *     moved: bool, favourable: bool|null, others_moved: int, result: string
     * }
     */
    public static function simulate(array $dipakai, float $perbaikan, array $koefisien, string $klaim, array $targets): array
    {
        $skenario = [];
        $pos = [];

        foreach (array_keys(AccountPosts::all()) as $kode) {
            $geser = $perbaikan * (float) ($koefisien[$kode] ?? 0);
            $awal = $dipakai[$kode] ?? null;
            $skenario[$kode] = $awal === null ? null : $awal * (1 + $geser);
            $pos[$kode] = ['baseline' => $awal, 'delta_pct' => $geser, 'scenario' => $skenario[$kode]];
        }
        $skenario = AccountPosts::withDerived($skenario);

        $mesin = app(RatioEngine::class);
        $dasar = $mesin->evaluateUsed($dipakai, $targets);
        $sim = $mesin->evaluateUsed($skenario, $targets);
        $simPerKode = collect($sim['rows'])->keyBy('code');

        $baris = [];
        $lainBergerak = 0;

        foreach ($dasar['rows'] as $b) {
            $s = $simPerKode->get($b['code']);
            $delta = $b['actual'] === null || $s['actual'] === null ? null : $s['actual'] - $b['actual'];
            $deltaPct = $delta === null || $b['actual'] == 0.0 ? null : $delta / $b['actual'];
            $bergerak = $delta !== null && abs($delta) > self::EPS * max(1.0, abs($b['actual']));

            if ($bergerak && $b['code'] !== $klaim) {
                $lainBergerak++;
            }

            $baris[] = [
                'code' => $b['code'],
                'name' => $b['name'],
                'unit' => $b['unit'],
                'polarity' => $b['polarity'],
                'weight' => $b['weight'],
                'target' => $b['target'],
                'baseline' => $b['actual'],
                'scenario' => $s['actual'],
                'delta' => $delta,
                'delta_pct' => $deltaPct,
                'achievement_baseline' => $b['achievement'],
                'achievement_scenario' => $s['achievement'],
                'rubric_baseline' => $b['rubric'],
                'rubric_scenario' => $s['rubric'],
                'weighted_baseline' => $b['weighted'],
                'weighted_scenario' => $s['weighted'],
                'claimed' => $b['code'] === $klaim,
            ];
        }

        // Rasio yang diklaim; REV = Target Revenue, digerakkan lewat Penjualan.
        if ($klaim === RatioLibrary::REVENUE) {
            $polaritas = RatioLibrary::NAIK;
            $deltaKlaim = $dipakai['PA01'] === null ? null : $skenario['PA01'] - $dipakai['PA01'];
            $acuan = abs((float) $dipakai['PA01']);
        } else {
            $r = collect($baris)->firstWhere('code', $klaim);
            $polaritas = $r['polarity'] ?? (RatioLibrary::all()[$klaim]['polarity'] ?? null);
            $deltaKlaim = $r['delta'] ?? null;
            $acuan = abs((float) ($r['baseline'] ?? 0));
        }

        $bergerak = $deltaKlaim !== null && abs($deltaKlaim) > self::EPS * max(1.0, $acuan);
        $baik = match ($polaritas) {
            RatioLibrary::NAIK => $bergerak && $deltaKlaim > 0,
            RatioLibrary::TURUN => $bergerak && $deltaKlaim < 0,
            // Rentang: baik bila skenario mendekatkan rasio ke targetnya. Tanpa
            // target, tidak dapat dinilai otomatis (null → REVISI, cek manual).
            default => ! $bergerak ? false : (isset($r['target'], $r['baseline'], $r['scenario'])
                ? abs($r['scenario'] - $r['target']) < abs($r['baseline'] - $r['target'])
                : null),
        };

        return [
            'used' => $pos,
            'rows' => $baris,
            'f2_baseline' => $dasar['f2'],
            'f2_scenario' => $sim['f2'],
            'delta_score' => $dasar['f2'] === null || $sim['f2'] === null ? null : round($sim['f2'] - $dasar['f2'], 2),
            'claimed' => $klaim,
            'claimed_polarity' => $polaritas,
            'claimed_delta' => $deltaKlaim,
            'moved' => $bergerak,
            'favourable' => $baik,
            'others_moved' => $lainBergerak,
            'result' => $bergerak && $baik === true ? self::LOLOS : self::REVISI,
        ];
    }

    /**
     * Status yang dianjurkan untuk kolom "Status validasi keuangan" L3:
     * Guardrail cukup lolos Uji A; Driver harus lolos Uji A dan Uji B.
     */
    public static function recommendedStatus(KpiCascade $kpi, ?string $ujiA, ?string $ujiB): ?string
    {
        if ($kpi->isGuardrail()) {
            return $ujiA === null ? null : ($ujiA === self::LOLOS_GUARDRAIL ? KpiCascade::LOLOS : KpiCascade::REVISI);
        }

        if ($ujiA === null) {
            return null;
        }

        if ($ujiA !== self::LOLOS) {
            return KpiCascade::REVISI;
        }

        return $ujiB === null ? null : ($ujiB === self::LOLOS ? KpiCascade::LOLOS : KpiCascade::REVISI);
    }
}
