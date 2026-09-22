<?php

namespace App\Livewire;

use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\RevenuePlan;
use App\Models\RevenueTarget;
use App\Models\WorkUnit;
use App\Support\Bsc\PostMap;
use App\Support\Bsc\RatioLibrary;
use App\Support\EntityContext;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Wiring / peta hubungan transmisi kaskade untuk entitas aktif:
 *
 *   Target revenue → perspektif (Revenue + 5 kelompok rasio) → unit kerja
 *
 * Semua simpul dan garis diturunkan dari data: skor perspektif memakai rumus
 * piramida (rubrik × bobot), garis perspektif → unit berasal dari KPI cascade
 * yang mengklaim rasio/REV, dan — bila diminta — jalur potensial dari Peta
 * Pos Akun. Tab kedua menampilkan sasaran mutu periode itu per unit, dengan
 * simulasi faktor revisi revenue (target × (1 + e × (k − 1))).
 */
class BscWiring extends Component
{
    public const REVENUE = 'revenue';

    /** Warna tiap perspektif. */
    private const WARNA = [
        self::REVENUE => '#6366f1',
        'Profitabilitas' => '#10b981',
        'Aktivitas' => '#f59e0b',
        'Produktivitas' => '#0284c7',
        'Likuiditas' => '#0d9488',
        'Solvabilitas' => '#8b5cf6',
    ];

    #[Url(as: 'period')]
    public string $selectedPeriod = '';

    public string $activeTab = 'flow';

    /** Uji kaskade revenue: revenue revisi dalam persen dari target disahkan. */
    public int $cascadeValue = 100;

    public string $selectedUnit = 'all';

    public bool $hanyaBergeser = false;

    /** Tampilkan jalur potensial dari Peta Pos Akun (garis putus-putus). */
    public bool $showPotential = false;

    public function mount(): void
    {
        $periode = Period::orderByDesc('period')->pluck('period');

        if (! $periode->contains($this->selectedPeriod)) {
            $this->selectedPeriod = (string) ($periode->first() ?? now()->format('Y-m'));
        }
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['flow', 'cascade'], true) ? $tab : 'flow';
    }

    public function updatedCascadeValue(): void
    {
        $this->cascadeValue = max(50, min(150, (int) $this->cascadeValue));
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'Tercapai' => '#10b981',
            'Waspada' => '#f59e0b',
            default => '#e11d48',
        };
    }

    /**
     * Skor tiap kelompok rasio periode ini: Σ(rubrik × bobot) ÷ Σ bobot untuk
     * rasio hasil hitungan pos akun; rata-rata pencapaian untuk rasio manual lama.
     *
     * @return array<string, array{score: float|null, count: int}>
     */
    private function groupScores(): array
    {
        $rasio = FinancialRatio::where('period', $this->selectedPeriod)->get();
        $hasil = [];

        foreach (array_keys(RatioLibrary::groups()) as $grup) {
            $baris = $rasio->where('category', $grup);
            $dihitung = $baris->filter(fn ($r) => $r->isComputed() && $r->rubric_score !== null);
            $bobot = (float) $dihitung->sum('weight');

            $hasil[$grup] = [
                'score' => match (true) {
                    $bobot > 0 => round($dihitung->sum(fn ($r) => (float) $r->rubric_score * (float) $r->weight) / $bobot, 1),
                    $baris->isNotEmpty() && ! $baris->contains(fn ($r) => $r->isComputed()) => round((float) $baris->avg('achievement_pct'), 1),
                    default => null,
                },
                'count' => $baris->count(),
            ];
        }

        return $hasil;
    }

    /** Kelompok perspektif untuk satu kode dampak KPI (rasio atau REV). */
    private static function perspectiveOf(?string $kode): ?string
    {
        if ($kode === null || $kode === '') {
            return null;
        }

        return $kode === RatioLibrary::REVENUE ? self::REVENUE : (RatioLibrary::all()[$kode]['group'] ?? null);
    }

    public function render()
    {
        $tahun = substr($this->selectedPeriod, 0, 4);
        $faktor = $this->cascadeValue / 100;

        // ---- Kolom 1: target revenue
        $rencana = RevenuePlan::where('year', $tahun)->first();
        $revenue = RevenueTarget::where('period', '>=', $tahun.'-01')->where('period', '<=', $this->selectedPeriod)->get(['target', 'actual']);
        $targetYtd = (float) $revenue->sum('target');
        $realisasiYtd = (float) $revenue->sum(fn ($r) => (float) ($r->actual ?? 0));
        $f1 = RevenueTarget::cumulativeAchievement($this->selectedPeriod);
        $targetSetahun = $rencana?->approved_target ?? (float) RevenueTarget::where('period', 'like', $tahun.'-%')->sum('target');

        // ---- Kolom 2: perspektif
        $skorGrup = $this->groupScores();
        $perspectives = [[
            'id' => self::REVENUE,
            'name' => 'Revenue (Tingkat 1)',
            'detail' => 'F1 · pencapaian kumulatif',
            'score' => $f1,
            'color' => self::WARNA[self::REVENUE],
        ]];
        foreach (RatioLibrary::groups() as $grup => $bobot) {
            $perspectives[] = [
                'id' => $grup,
                'name' => $grup,
                'detail' => 'bobot '.$bobot.' · '.$skorGrup[$grup]['count'].' rasio',
                'score' => $skorGrup[$grup]['score'],
                'color' => self::WARNA[$grup] ?? '#64748b',
            ];
        }

        // ---- Kolom 3: unit kerja + garis
        $objektif = DepartmentObjective::with('kpiCascade')->where('period', $this->selectedPeriod)->get();
        $kpi = KpiCascade::where('year', $tahun)->get();
        $peta = new PostMap;

        $kodeUnit = WorkUnit::active()->pluck('name', 'code');
        foreach ($objektif->pluck('dept_code')->unique() as $kode) {
            $kodeUnit[$kode] ??= $kode; // unit nonaktif/terhapus yang masih punya sasaran
        }

        $departments = [];
        foreach ($kodeUnit as $kode => $nama) {
            $milik = $objektif->where('dept_code', $kode);
            $kpiUnit = $kpi->where('unit_code', $kode);

            $aktual = $kpiUnit->where('kpi_type', KpiCascade::DRIVER)
                ->map(fn ($k) => self::perspectiveOf($k->ratio_code))->filter()->unique()->values()->all();
            $potensial = collect($peta->claimableRatios($kode))
                ->map(fn ($r) => self::perspectiveOf($r))
                ->merge($peta->canClaim($kode, RatioLibrary::REVENUE) ? [self::REVENUE] : [])
                ->unique()->diff($aktual)->values()->all();

            $departments[] = [
                'code' => $kode,
                'name' => $nama,
                'sasaran' => $milik->count(),
                'kpi' => $kpiUnit->count(),
                'bergeser' => $milik->where('status', '!=', 'Tercapai')->count(),
                'dots' => $milik->map(fn ($o) => self::statusColor($o->status))->take(6)->values()->all(),
                'links' => $aktual,
                'potential' => $potensial,
            ];
        }

        $filteredDepartments = collect($departments)->filter(function ($d) {
            return ($this->selectedUnit === 'all' || $d['code'] === $this->selectedUnit)
                && (! $this->hanyaBergeser || $d['bergeser'] > 0);
        })->values()->all();

        // ---- Tab 2: sasaran mutu per unit dengan simulasi faktor revisi
        $deptCards = [];
        foreach ($objektif->groupBy('dept_code') as $kode => $baris) {
            if ($this->selectedUnit !== 'all' && $kode !== $this->selectedUnit) {
                continue;
            }

            $kpis = $baris->sortBy('kpi_code')->map(function ($o) use ($faktor) {
                $c = $o->kpiCascade;
                $guardrail = $c?->isGuardrail() ?? false;
                $e = $c?->elasticity;
                $disesuaikan = $c && $c->target !== null ? $c->adjustedTarget($faktor) : (float) $o->target;

                return [
                    'code' => $o->kpi_code,
                    'name' => $o->kpi_name,
                    'linked' => (bool) $c,
                    'elasticity' => match (true) {
                        ! $c => 'belum di cascade',
                        $guardrail || (float) $e === 0.0 => 'dikunci',
                        default => 'e '.number_format((float) $e, 2, ',', '.'),
                    },
                    'elasticity_type' => ! $c ? 'locked' : ($guardrail || (float) $e === 0.0 ? 'locked' : ((float) $e >= 0.8 ? 'green' : 'yellow')),
                    'target' => (float) $o->target,
                    'adjusted' => $disesuaikan,
                    'actual' => (float) $o->actual,
                    'unit_label' => $c?->unit_label,
                    'achievement' => (float) $o->achievement_pct,
                    'accent' => self::statusColor($o->status),
                    'bergeser' => $o->status !== 'Tercapai',
                ];
            })->filter(fn ($k) => ! $this->hanyaBergeser || $k['bergeser'])->values()->all();

            if ($kpis === []) {
                continue;
            }

            $deptCards[] = [
                'code' => $kode,
                'title' => $kodeUnit[$kode] ?? $kode,
                'kpis' => $kpis,
            ];
        }

        return view('livewire.bsc-wiring', [
            'periods' => Period::orderByDesc('period')->pluck('period')->all(),
            'entity' => app(EntityContext::class)->entity(),
            'revenue' => [
                'annual' => $targetSetahun > 0 ? $targetSetahun : null,
                'simulated' => $targetSetahun > 0 ? $targetSetahun * $faktor : null,
                'target_ytd' => $targetYtd,
                'actual_ytd' => $realisasiYtd,
                'f1' => $f1,
                'approved' => (bool) $rencana,
            ],
            'factor' => $faktor,
            'perspectives' => $perspectives,
            'departments' => $departments,
            'filteredDepartments' => $filteredDepartments,
            'deptCards' => $deptCards,
            'objectiveCount' => $objektif->count(),
        ])->layout('layouts.app', ['title' => 'Wiring / Peta Hubungan']);
    }
}
