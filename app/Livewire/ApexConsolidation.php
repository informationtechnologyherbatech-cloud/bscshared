<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use App\Models\FinancialRatio;

class ApexConsolidation extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'tab')]
    public $activeTab = 'input'; // 'input', 'dupont', 'sensitivity', 'weights'

    // Input form state
    public $target_revenue = 120.00;
    public $actual_revenue = 115.20;
    public $weight_revenue = 45.00;
    public $weight_ratios = 55.00;

    // Simulation state
    public $simulatedRevenueDev = 0; // % slider

    // Pillar Weights State (Dinamis dari AppSetting)
    public $pillarWeights = [
        'Profitabilitas' => 35,
        'Likuiditas' => 20,
        'Aktivitas' => 15,
        'Produktivitas' => 15,
        'Solvabilitas' => 15,
    ];

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);
        $this->loadPeriodData();
        $this->loadPillarWeights();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->loadPeriodData();
    }

    public function loadPillarWeights()
    {
        $saved = \App\Models\AppSetting::getValue('bsc_pillar_weights');
        if ($saved) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded)) {
                $this->pillarWeights = array_merge($this->pillarWeights, $decoded);
            }
        }
    }

    public function savePillarWeights()
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: Hanya peran Admin FAT atau Super Admin yang dapat mengubah bobot 5 pilar.');
            return;
        }

        $total = array_sum(array_map('floatval', $this->pillarWeights));
        if (round($total, 1) != 100.0) {
            session()->flash('error', 'Total bobot 5 Pilar harus tepat 100% (Saat ini: ' . $total . '%). Silakan sesuaikan.');
            return;
        }

        \App\Models\AppSetting::setValue('bsc_pillar_weights', json_encode($this->pillarWeights));
        session()->flash('message', 'Bobot 5 Pilar Finansial berhasil diperbarui dan disimpan!');
    }

    public function loadPeriodData()
    {
        $period = Period::where('period', $this->selectedPeriod)->first();
        if ($period) {
            $this->target_revenue = $period->target_revenue ?? 120.00;
            $this->actual_revenue = $period->actual_revenue ?? 115.20;
            $this->weight_revenue = $period->weight_revenue ?? 45.00;
            $this->weight_ratios = $period->weight_ratios ?? 55.00;
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function saveApexInput()
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: Hanya peran Admin FAT atau Super Admin yang dapat menyimpan data Apex Tingkat 1.');
            return;
        }

        $period = Period::where('period', $this->selectedPeriod)->first();
        if ($period && $period->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data terkunci dan tidak dapat diubah.');
            return;
        }

        $this->validate([
            'target_revenue' => 'required|numeric|min:0.01',
            'actual_revenue' => 'required|numeric|min:0',
            'weight_revenue' => 'required|numeric|min:0|max:100',
            'weight_ratios' => 'required|numeric|min:0|max:100',
        ]);

        if (round($this->weight_revenue + $this->weight_ratios, 1) != 100.0) {
            session()->flash('error', 'Total bobot harus tepat 100% (Saat ini: ' . ($this->weight_revenue + $this->weight_ratios) . '%).');
            return;
        }

        // Recalculate Apex Score
        $ratios = FinancialRatio::where('period', $this->selectedPeriod)->get();
        $avgRatioScore = $ratios->count() > 0 ? $ratios->avg('achievement_pct') : 100.0;
        $revenueAchievementPct = round(($this->actual_revenue / $this->target_revenue) * 100, 2);

        $apexScore = round((($this->weight_revenue / 100) * $revenueAchievementPct) + (($this->weight_ratios / 100) * $avgRatioScore), 2);

        if ($period) {
            $period->update([
                'target_revenue' => $this->target_revenue,
                'actual_revenue' => $this->actual_revenue,
                'weight_revenue' => $this->weight_revenue,
                'weight_ratios' => $this->weight_ratios,
                'apex_score' => $apexScore,
            ]);
        }

        session()->flash('message', 'Data Tingkat 1 Apex & Target Revenue untuk periode ' . $this->selectedPeriod . ' berhasil disimpan dan dikonsolidasikan!');
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        $ratios = FinancialRatio::where('period', $this->selectedPeriod)->get();
        $avgRatioScore = $ratios->count() > 0 ? round($ratios->avg('achievement_pct'), 2) : 100.0;

        // Current calculation
        $targetRev = $this->target_revenue > 0 ? $this->target_revenue : 120.00;
        $actualRev = $this->actual_revenue;
        $revPct = round(($actualRev / $targetRev) * 100, 2);
        $wRev = $this->weight_revenue;
        $wRat = $this->weight_ratios;
        $apexScore = round((($wRev / 100) * $revPct) + (($wRat / 100) * $avgRatioScore), 2);

        // Simulation
        $simulatedRevenue = round($actualRev * (1 + ($this->simulatedRevenueDev / 100)), 2);
        $simulatedRevenuePct = round(($simulatedRevenue / $targetRev) * 100, 2);
        $simulatedApexScore = round((($wRev / 100) * $simulatedRevenuePct) + (($wRat / 100) * $avgRatioScore), 2);
        $apexDelta = round($simulatedApexScore - $apexScore, 2);

        // DuPont Decomposition
        $npmRatio = $ratios->firstWhere('ratio_name', 'Net Profit Margin (%)');
        $roeRatio = $ratios->firstWhere('ratio_name', 'Return on Equity / ROE (%)');
        $derRatio = $ratios->firstWhere('ratio_name', 'Debt to Equity Ratio');
        $invRatio = $ratios->firstWhere('ratio_name', 'Inventory Turnover');

        $dupontData = [
            'roe' => [
                'actual' => $roeRatio ? $roeRatio->actual : 18.5,
                'target' => $roeRatio ? $roeRatio->target : 18.0,
                'achievement' => $roeRatio ? $roeRatio->achievement_pct : 100.0,
                'status' => $roeRatio ? $roeRatio->status : 'Tercapai',
            ],
            'npm' => [
                'actual' => $npmRatio ? $npmRatio->actual : 14.8,
                'target' => $npmRatio ? $npmRatio->target : 15.0,
                'achievement' => $npmRatio ? $npmRatio->achievement_pct : 98.67,
                'status' => $npmRatio ? $npmRatio->status : 'Waspada',
            ],
            'asset_turnover' => [
                'actual' => $invRatio ? round($invRatio->actual / 4.5, 2) : 1.28,
                'target' => 1.25,
                'achievement' => 102.4,
                'status' => 'Tercapai',
            ],
            'leverage' => [
                'actual' => $derRatio ? $derRatio->actual : 0.75,
                'target' => $derRatio ? $derRatio->target : 0.80,
                'achievement' => 100.0,
                'status' => 'Tercapai',
            ],
        ];

        // Pillar breakdown (menggunakan bobot dinamis)
        $pillarCategories = [
            'Profitabilitas' => ['weight' => floatval($this->pillarWeights['Profitabilitas'] ?? 35), 'color' => '#10b981', 'icon' => 'fa-chart-pie'],
            'Likuiditas' => ['weight' => floatval($this->pillarWeights['Likuiditas'] ?? 20), 'color' => '#0d9488', 'icon' => 'fa-water'],
            'Aktivitas' => ['weight' => floatval($this->pillarWeights['Aktivitas'] ?? 15), 'color' => '#f59e0b', 'icon' => 'fa-sync-alt'],
            'Produktivitas' => ['weight' => floatval($this->pillarWeights['Produktivitas'] ?? 15), 'color' => '#0284c7', 'icon' => 'fa-users-cog'],
            'Solvabilitas' => ['weight' => floatval($this->pillarWeights['Solvabilitas'] ?? 15), 'color' => '#8b5cf6', 'icon' => 'fa-balance-scale'],
        ];

        $pillarContributions = [];
        foreach ($pillarCategories as $catName => $meta) {
            $catRatios = $ratios->where('category', $catName);
            $catScore = $catRatios->count() > 0 ? $catRatios->avg('achievement_pct') : 100.0;
            $contributedScore = round(($catScore * $meta['weight']) / 100, 2);
            $pillarContributions[] = [
                'name' => $catName,
                'weight' => $meta['weight'],
                'score' => round($catScore, 2),
                'contrib' => $contributedScore,
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'status' => $catScore >= 100 ? 'Tercapai' : ($catScore >= 85 ? 'Waspada' : 'Kritis'),
            ];
        }

        // 1. Red Flags Alert (Rasio dengan status Off-Target atau Waspada)
        $redFlags = $ratios->whereIn('status', ['Waspada', 'Di Bawah Target', 'Off-Target'])->values();

        // 2. Historical Trend Antar Periode
        $allPeriods = Period::orderBy('period', 'asc')->get();
        $historicalTrends = [];
        foreach ($allPeriods as $p) {
            $pScore = $p->apex_score > 0 ? $p->apex_score : 0;
            $historicalTrends[] = [
                'period' => $p->period,
                'status' => $p->status,
                'apex_score' => $pScore,
                'revenue' => $p->actual_revenue,
            ];
        }

        return view('livewire.apex-consolidation', [
            'periods' => $periods,
            'periodObj' => $periodObj,
            'isClosed' => $isClosed,
            'ratios' => $ratios,
            'avgRatioScore' => $avgRatioScore,
            'revenueAchievementPct' => $revPct,
            'apexScore' => $apexScore,
            'simulatedRevenue' => $simulatedRevenue,
            'simulatedRevenuePct' => $simulatedRevenuePct,
            'simulatedApexScore' => $simulatedApexScore,
            'apexDelta' => $apexDelta,
            'dupontData' => $dupontData,
            'pillarContributions' => $pillarContributions,
            'redFlags' => $redFlags,
            'historicalTrends' => $historicalTrends,
        ])->layout('layouts.app', ['title' => 'Tingkat 1: Apex & Konsolidasi Hop 4']);
    }
}
