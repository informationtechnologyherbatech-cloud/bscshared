<?php

namespace App\Support\Bsc;

use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\FinancialRatio;
use App\Models\KpiCascade;
use App\Models\RevenueTarget;
use App\Models\WorkUnit;
use App\Support\ScoreStatus;
use Illuminate\Support\Collection;

/**
 * Menyusun EntitySummary dari basis data yang SEDANG aktif, untuk entitas yang
 * sedang aktif (EntityContext). Dipakai tiga tempat:
 *   - holding membaca entitas di database yang sama (sumber "lokal"),
 *   - holding membaca database entitas yang terpisah (sumber "database"),
 *   - aplikasi entitas menjawab permintaan API holding (sumber "api" di sisi holding).
 */
class EntitySummaryBuilder
{
    public function forEntity(Entity $entitas, string $period, string $source = EntitySummary::SUMBER_LOKAL): EntitySummary
    {
        $tahun = substr($period, 0, 4);
        $skor = Scorecard::forPeriod($period);

        $revenue = RevenueTarget::where('period', '>=', $tahun.'-01')
            ->where('period', '<=', $period)
            ->get(['target', 'actual']);

        $objektif = DepartmentObjective::where('period', $period)->get(['dept_code', 'achievement_pct']);
        $kpi = KpiCascade::where('year', $tahun)->get(['validation_status']);

        return new EntitySummary(
            code: (string) $entitas->code,
            name: (string) $entitas->name,
            legalName: (string) $entitas->legal_name,
            industry: (string) $entitas->industryLabel(),
            period: $period,
            f1: $skor['revenue'],
            f2: $skor['ratios'],
            apex: $skor['apex'],
            revenueTarget: (float) $revenue->sum('target'),
            revenueActual: (float) $revenue->sum(fn ($r) => (float) ($r->actual ?? 0)),
            objectives: $objektif->count(),
            objectiveScore: $objektif->isNotEmpty() ? round((float) $objektif->avg('achievement_pct'), 2) : null,
            kpiTotal: $kpi->count(),
            kpiApproved: $kpi->where('validation_status', KpiCascade::LOLOS)->count(),
            ratios: $this->ratios($period),
            units: $this->units($objektif),
            source: $source,
            fetchedAt: now()->toIso8601String(),
        );
    }

    /**
     * 19 rasio periode itu — angka hasil hitungan, bukan pos akun pembentuknya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ratios(string $period): array
    {
        return FinancialRatio::where('period', $period)
            ->orderBy('id')
            ->get(['ratio_code', 'ratio_name', 'category', 'unit', 'target', 'actual', 'achievement_pct', 'status'])
            ->map(fn (FinancialRatio $r) => [
                'code' => $r->ratio_code,
                'name' => $r->ratio_name,
                'category' => $r->category,
                'unit' => $r->unit,
                'target' => $r->target === null ? null : (float) $r->target,
                'actual' => $r->actual === null ? null : (float) $r->actual,
                'achievement' => $r->achievement_pct === null ? null : (float) $r->achievement_pct,
                'status' => $r->status,
            ])->all();
    }

    /**
     * Ringkasan per unit kerja: berapa sasaran dan rata-rata capaiannya — tanpa
     * isi sasarannya.
     *
     * @param  Collection<int, DepartmentObjective>  $objektif
     * @return array<int, array<string, mixed>>
     */
    private function units($objektif): array
    {
        $nama = WorkUnit::query()->pluck('name', 'code');

        return $objektif->groupBy('dept_code')
            ->map(function ($baris, $kode) use ($nama) {
                $skor = round((float) $baris->avg('achievement_pct'), 2);

                return [
                    'code' => (string) $kode,
                    'name' => (string) ($nama[$kode] ?? $kode),
                    'objectives' => $baris->count(),
                    'score' => $skor,
                    'status' => ScoreStatus::for($skor),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }
}
