<?php

namespace App\Support\Bsc;

use App\Models\FinancialRatio;
use App\Models\RevenueTarget;

/**
 * Skor piramida satu periode untuk entitas aktif — satu sumber untuk
 * dashboard entitas dan konsolidasi holding, supaya angkanya tidak pernah
 * berbeda antara kedua layar.
 */
class Scorecard
{
    /**
     * F2. Bila periode punya rasio hasil hitungan pos akun: Σ(rubrik × bobot) ÷
     * Σ bobot seperti sheet L2 (null bila belum ada yang bertarget). Periode lama
     * yang rasionya diisi manual memakai rata-rata pencapaian. Null = belum ada data.
     */
    public static function ratioScore(string $period): ?float
    {
        $rasio = FinancialRatio::where('period', $period)->get(['achievement_pct', 'source']);

        if ($rasio->contains(fn ($r) => $r->isComputed())) {
            return RatioEngine::storedScore($period);
        }

        return $rasio->isNotEmpty() ? round((float) $rasio->avg('achievement_pct'), 2) : null;
    }

    /**
     * Rata-rata terbobot skor tiap tingkat (config bsc.apex_weights). Tingkat
     * tanpa data (null) dikeluarkan dan bobotnya dibagikan ke yang tersedia.
     *
     * @param  array<string, float|null>  $tierScores
     */
    public static function apex(array $tierScores): float
    {
        $weights = config('bsc.apex_weights', []);
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($tierScores as $tier => $score) {
            $weight = (float) ($weights[$tier] ?? 0);
            if ($score === null || $weight <= 0) {
                continue;
            }
            $weightedSum += $weight * (float) $score;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0.0;
    }

    /**
     * F1, F2, dan skor puncak entitas aktif.
     *
     * @return array{revenue: float|null, ratios: float|null, apex: float|null}
     */
    public static function forPeriod(string $period): array
    {
        $tingkat = [
            'revenue' => RevenueTarget::cumulativeAchievement($period),
            'ratios' => self::ratioScore($period),
        ];

        return $tingkat + [
            'apex' => $tingkat['revenue'] === null && $tingkat['ratios'] === null ? null : self::apex($tingkat),
        ];
    }
}
