<?php

namespace App\Support\Bsc;

use App\Models\DepartmentObjective;
use App\Models\Entity;
use App\Models\IntercompanySale;
use App\Models\KpiCascade;
use App\Models\RevenueTarget;
use App\Support\EntityContext;
use Illuminate\Support\Collection;

/**
 * Tampilan holding atas keempat entitas: input tiap entitas boleh berbeda,
 * tetapi keluarannya seragam — F1, F2, dan skor puncak berskala 0–100.
 *
 * Revenue grup = Σ revenue entitas − penjualan antarentitas (eliminasi), agar
 * produk Herbatech yang dijual lewat Erdigma tidak terhitung dua kali.
 *
 *   F1 grup = (Σ realisasi − eliminasi realisasi) ÷ (Σ target − eliminasi rencana),
 *             kumulatif Jan s.d. periode, maks 100.
 *   F2 grup = rata-rata F2 entitas, dibobot target revenue YTD masing-masing
 *             (rasio laporan konsolidasi butuh pos akun konsolidasi, yang belum
 *             dicatat; pembobotan revenue membuat entitas besar berpengaruh besar).
 *   Skor puncak grup = 0,45 × F1 grup + 0,55 × F2 grup.
 */
class Consolidation
{
    public function __construct(private EntityContext $context) {}

    /**
     * @return array{
     *     entities: array<int, array<string, mixed>>,
     *     group: array<string, mixed>,
     *     eliminations: Collection<int, IntercompanySale>
     * }
     */
    public function forPeriod(string $period): array
    {
        $tahun = substr($period, 0, 4);
        $baris = [];

        foreach (Entity::active()->get() as $entitas) {
            $baris[] = $this->context->runAs($entitas->id, function () use ($entitas, $period, $tahun) {
                $skor = Scorecard::forPeriod($period);
                $revenue = RevenueTarget::where('period', '>=', $tahun.'-01')->where('period', '<=', $period)->get(['target', 'actual']);
                $objektif = DepartmentObjective::where('period', $period)->get(['achievement_pct']);
                $kpi = KpiCascade::where('year', $tahun)->get(['validation_status']);

                return [
                    'entity' => $entitas,
                    'revenue_target' => (float) $revenue->sum('target'),
                    'revenue_actual' => (float) $revenue->sum(fn ($r) => (float) ($r->actual ?? 0)),
                    'f1' => $skor['revenue'],
                    'f2' => $skor['ratios'],
                    'apex' => $skor['apex'],
                    'objectives' => $objektif->count(),
                    'objective_score' => $objektif->isNotEmpty() ? round((float) $objektif->avg('achievement_pct'), 2) : null,
                    'kpi_total' => $kpi->count(),
                    'kpi_approved' => $kpi->where('validation_status', KpiCascade::LOLOS)->count(),
                ];
            });
        }

        $eliminasi = IntercompanySale::with(['seller', 'buyer'])
            ->where('period', '>=', $tahun.'-01')->where('period', '<=', $period)
            ->orderBy('period')->get();

        $targetKotor = array_sum(array_column($baris, 'revenue_target'));
        $realisasiKotor = array_sum(array_column($baris, 'revenue_actual'));
        $elimRencana = (float) $eliminasi->sum(fn ($e) => (float) ($e->planned_amount ?? 0));
        $elimRealisasi = (float) $eliminasi->sum(fn ($e) => (float) ($e->actual_amount ?? 0));
        $targetBersih = $targetKotor - $elimRencana;
        $realisasiBersih = $realisasiKotor - $elimRealisasi;

        $f1 = $targetBersih > 0 ? round(min(100.0, max(0.0, $realisasiBersih / $targetBersih * 100)), 2) : null;

        // F2 grup: dibobot target revenue YTD; bila belum ada target sama sekali,
        // rata-rata sederhana entitas yang punya F2.
        $punyaF2 = array_filter($baris, fn ($b) => $b['f2'] !== null);
        $bobot = array_sum(array_map(fn ($b) => $b['revenue_target'], $punyaF2));
        $f2 = match (true) {
            $punyaF2 === [] => null,
            $bobot > 0 => round(array_sum(array_map(fn ($b) => $b['f2'] * $b['revenue_target'], $punyaF2)) / $bobot, 2),
            default => round(array_sum(array_column($punyaF2, 'f2')) / count($punyaF2), 2),
        };

        return [
            'entities' => $baris,
            'group' => [
                'revenue_target_gross' => $targetKotor,
                'revenue_actual_gross' => $realisasiKotor,
                'elimination_planned' => $elimRencana,
                'elimination_actual' => $elimRealisasi,
                'revenue_target_net' => $targetBersih,
                'revenue_actual_net' => $realisasiBersih,
                'f1' => $f1,
                'f2' => $f2,
                'f2_weighting' => $bobot > 0 ? 'revenue' : 'equal',
                'apex' => $f1 === null && $f2 === null ? null : Scorecard::apex(['revenue' => $f1, 'ratios' => $f2]),
            ],
            'eliminations' => $eliminasi,
        ];
    }
}
