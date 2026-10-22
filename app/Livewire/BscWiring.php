<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\FinancialRatio;
use App\Models\DepartmentObjective;
use App\Models\ActionPlan;
use App\Models\Department;
use App\Models\Period;

class BscWiring extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    public $revenueBaseline = 120.00; // Miliar IDR
    public $revenueActual = 115.20;   // Miliar IDR
    
    public $activeTab = 'flow'; // 'flow' (Diagram Alur Bezier Makro) or 'cascade' (Wiring Sasaran Mutu)
    public $cascadeValue = 120.00; // Value for Uji Kaskade Revenue slider (Rp Miliar IDR)
    public $selectedUnit = 'all'; // Unit kerja filter
    public $hanyaBergeser = false; // Checkbox filter

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);
        $this->loadPeriodRevenue();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->loadPeriodRevenue();
    }

    public function loadPeriodRevenue()
    {
        $p = Period::where('period', $this->selectedPeriod)->first();
        if ($p) {
            $this->revenueBaseline = (float) ($p->target_revenue ?? 120.00);
            $this->revenueActual = (float) ($p->actual_revenue ?? 115.20);
            $this->cascadeValue = $this->revenueBaseline;
        }
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $p = Period::where('period', $this->selectedPeriod)->first();
        if ($p) {
            $this->revenueBaseline = (float) ($p->target_revenue ?? 120.00);
            $this->revenueActual = (float) ($p->actual_revenue ?? 115.20);
        }
        $revenueDelta = $this->revenueBaseline > 0 ? round((($this->cascadeValue - $this->revenueBaseline) / $this->revenueBaseline) * 100, 2) : 0;

        $ratios = FinancialRatio::where('period', $this->selectedPeriod)->get();
        $objectives = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        $actionPlans = ActionPlan::with('objective')->get();

        // Group objectives by department
        $objectivesByDept = $objectives->groupBy('dept_code');

        // Dynamic perspective calculation from DB ratios (15 Rasio Botol Plastik & 5 Pilar BSC)
        $perspectiveDefinitions = [
            [
                'id' => 'profitability',
                'name' => 'Profitabilitas',
                'cat' => 'Profitabilitas',
                'ratios' => ['GROSS PROFIT MARGIN', 'EBIT', 'NET PROFIT MARGIN', 'RETURN ON ASSET', 'RETURN ON EQUITY', 'Return on Equity / ROE (%)', 'Net Profit Margin (%)', 'ROE', 'NPM'],
                'color' => '#10b981', 'bg' => '#ecfdf5', 'border' => '#a7f3d0',
            ],
            [
                'id' => 'revenue',
                'name' => 'Revenue / Pertumbuhan',
                'cat' => 'Profitabilitas',
                'ratios' => ['GROSS PROFIT MARGIN', 'EBIT', 'NET PROFIT MARGIN', 'Sales Growth'],
                'color' => '#10b981', 'bg' => '#ecfdf5', 'border' => '#a7f3d0',
            ],
            [
                'id' => 'activity',
                'name' => 'Aktivitas',
                'cat' => 'Aktivitas',
                'ratios' => ['INVENTORY TURN OVER', 'AR TURN OVER', 'DAYS SALES OUTSANDING', 'DAYS INVENTORY OUTSTANDING', 'DAYS PAYABLE OUTSTANDING', 'ASSET TURN OVER', 'Inventory Turnover'],
                'color' => '#f59e0b', 'bg' => '#fffbeb', 'border' => '#fde68a',
            ],
            [
                'id' => 'productivity',
                'name' => 'Produktivitas',
                'cat' => 'Produktivitas',
                'ratios' => ['Revenue per Employee (Juta IDR)', 'Labor Productivity'],
                'color' => '#0284c7', 'bg' => '#f0f9ff', 'border' => '#bae6fd',
            ],
            [
                'id' => 'liquidity',
                'name' => 'Likuiditas',
                'cat' => 'Likuiditas',
                'ratios' => ['CURRENT RATIO', 'QUICK RATIO', 'Current Ratio', 'Quick Ratio'],
                'color' => '#0d9488', 'bg' => '#f0fdf4', 'border' => '#99f6e4',
            ],
            [
                'id' => 'solvency',
                'name' => 'Solvabilitas',
                'cat' => 'Solvabilitas',
                'ratios' => ['DEBT TO EQUITY RATIO', 'DEBT TO TOTAL ASSET RATIO', 'Debt to Equity Ratio'],
                'color' => '#8b5cf6', 'bg' => '#f5f3ff', 'border' => '#ddd6fe',
            ],
        ];

        $perspectives = [];
        foreach ($perspectiveDefinitions as $def) {
            $catRatios = $ratios->filter(function($r) use ($def) {
                $rNameUpper = strtoupper(trim($r->ratio_name));
                $rCatLower = strtolower(trim($r->category));
                $targetCatLower = strtolower(trim($def['cat']));

                if ($def['id'] === 'revenue') {
                    return in_array($rNameUpper, ['GROSS PROFIT MARGIN', 'EBIT', 'NET PROFIT MARGIN', 'SALES GROWTH']);
                }

                if ($rCatLower === $targetCatLower) {
                    return true;
                }

                foreach ($def['ratios'] as $expected) {
                    if (strtoupper(trim($expected)) === $rNameUpper) {
                        return true;
                    }
                }
                return false;
            });

            $avgPct = $catRatios->count() > 0 ? $catRatios->avg('achievement_pct') : 100.0;
            $scoreFormatted = number_format($avgPct / 100, 3, ',', '.');
            
            $perspectives[] = [
                'id' => $def['id'],
                'name' => $def['name'],
                'detail' => 'e ' . number_format($avgPct / 100, 2, ',', '.') . ' · ' . $catRatios->count() . ' rasio',
                'score' => $scoreFormatted,
                'color' => $avgPct >= 100 ? '#10b981' : ($avgPct >= 80 ? '#f59e0b' : '#e11d48'),
                'bg' => $def['bg'],
                'border' => $def['border'],
                'ratios' => $catRatios->pluck('ratio_name')->toArray(),
            ];
        }

        // Dynamic Department list from database
        $dbDepartments = Department::where('is_active', true)->orderBy('sort_order', 'asc')->get();

        $departments = [];
        $deptCards = [];

        foreach ($dbDepartments as $deptModel) {
            $code = $deptModel->code;
            $name = $deptModel->name;
            $deptColor = $deptModel->color ?: '#0d9488';
            $managerTitle = $deptModel->manager_title ?: ($name . ' Manager');

            $deptObjs = $objectivesByDept->get($code, collect());
            $sasaranCount = $deptObjs->count();
            $bergeserCount = $deptObjs->where('status', '!=', 'Tercapai')->count();

            // Status dots based on real DB KPIs
            $dots = [];
            if ($deptObjs->count() > 0) {
                foreach ($deptObjs as $o) {
                    if ($o->status === 'Tercapai') $dots[] = '#10b981';
                    elseif ($o->status === 'Waspada') $dots[] = '#f59e0b';
                    else $dots[] = '#e11d48';
                }
            } else {
                $dots = ['#94a3b8'];
            }

            $departments[] = [
                'code' => $code,
                'name' => $name,
                'sasaran' => $sasaranCount > 0 ? $sasaranCount : 0,
                'bergeser' => $bergeserCount,
                'dots' => array_slice($dots, 0, 4),
            ];

            // Build deptCards dynamically for Cascade tab
            $cardKpis = [];
            if ($deptObjs->count() > 0) {
                foreach ($deptObjs as $o) {
                    $unitStr = $o->unit ?: '%';
                    $valStr = is_numeric($o->actual) ? number_format((float)$o->actual, 1, ',', '.') : $o->actual;
                    $cardKpis[] = [
                        'code' => $o->kpi_code,
                        'name' => $o->kpi_name,
                        'elasticity' => $o->polarity === 'Turun' ? 'e 0,30' : 'e 1,00',
                        'elasticity_type' => $o->status === 'Tercapai' ? 'green' : ($o->status === 'Waspada' ? 'yellow' : 'red'),
                        'result' => $valStr . ' ' . $unitStr,
                        'result_highlight' => $o->achievement_pct >= 100,
                        'left_accent' => $o->status === 'Tercapai' ? '#10b981' : ($o->status === 'Waspada' ? '#f59e0b' : '#e11d48'),
                    ];
                }
            } else {
                // If no specific KPI in DB yet, show notice placeholder
                $cardKpis[] = [
                    'code' => $code . '-01',
                    'name' => 'Sasaran Mutu & Program Kerja ' . $name,
                    'elasticity' => 'standar',
                    'elasticity_type' => 'locked',
                    'result' => 'Belum Diinput',
                    'result_highlight' => false,
                    'left_accent' => '#94a3b8',
                ];
            }

            $deptCards[] = [
                'title' => $managerTitle,
                'code' => $code,
                'sasaran_count' => $sasaranCount . ' sasaran',
                'header_bg' => $deptColor,
                'badge_bg' => '#ffffff',
                'badge_color' => $deptColor,
                'kpis' => $cardKpis,
            ];
        }

        // Filter departments if unit kerja filter active or hanyaBergeser checkbox
        $filteredDepartments = collect($departments)->filter(function($d) {
            $matchUnit = $this->selectedUnit === 'all' || $d['code'] === $this->selectedUnit || $d['name'] === $this->selectedUnit;
            $matchBergeser = !$this->hanyaBergeser || $d['bergeser'] > 0;
            return $matchUnit && $matchBergeser;
        })->values()->toArray();

        // Filter deptCards based on selectedUnit
        $filteredDeptCards = collect($deptCards)->filter(function($c) {
            return $this->selectedUnit === 'all' || $c['code'] === $this->selectedUnit;
        })->values()->toArray();

        return view('livewire.bsc-wiring', [
            'periods' => $periods,
            'revenueDelta' => $revenueDelta,
            'ratios' => $ratios,
            'objectives' => $objectives,
            'objectivesByDept' => $objectivesByDept,
            'actionPlans' => $actionPlans,
            'perspectives' => $perspectives,
            'departments' => $departments,
            'filteredDepartments' => $filteredDepartments,
            'deptCards' => $deptCards,
            'filteredDeptCards' => $filteredDeptCards,
        ])->layout('layouts.app', ['title' => 'Wiring / Peta Hubungan']);
    }
}

