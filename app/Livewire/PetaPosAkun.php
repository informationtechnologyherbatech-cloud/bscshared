<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class PetaPosAkun extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    public $search = '';
    public $selectedDept = 'all';
    public $selectedType = 'all'; // all, cogs, opex, other
    public $activeTab = 'matrix'; // matrix, tree, summary

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $baseRev = (float) ($periodObj ? $periodObj->actual_revenue : 115.20); // dalam Miliar
        $baseTargetRev = (float) ($periodObj ? $periodObj->target_revenue : 120.00);

        // Parse periode
        $pParts = explode('-', $this->selectedPeriod);
        $pYear = (int) ($pParts[0] ?? date('Y'));
        $pMonth = isset($pParts[1]) ? (int) $pParts[1] : (int) date('m');

        // Ambil data realisasi bulanan dan YTD dari tabel expenses di mysql_finance
        $actualExpensesMonthly = [];
        $actualExpensesYtd = [];
        $hasFinanceDb = false;
        $departments = [];
        $budgetItems = [];

        try {
            // Test koneksi & ambil departemen
            $deptRows = DB::connection('mysql_finance')
                ->table('departments')
                ->select('id', 'name', 'code')
                ->orderBy('name', 'asc')
                ->get();
            $departments = $deptRows->toArray();

            // Realisasi Bulanan MTD
            $mRows = DB::connection('mysql_finance')
                ->table('expenses')
                ->join('budget_categories', 'budget_categories.id', '=', 'expenses.budget_category_id')
                ->whereYear('expenses.date', $pYear)
                ->whereMonth('expenses.date', $pMonth)
                ->select('budget_categories.code', DB::raw('SUM(expenses.amount) as total_amt'), DB::raw('COUNT(expenses.id) as trx_count'))
                ->groupBy('budget_categories.code')
                ->get();
            foreach ($mRows as $r) {
                $actualExpensesMonthly[$r->code] = [
                    'amount' => (float) $r->total_amt,
                    'count' => (int) $r->trx_count,
                ];
            }

            // Realisasi Akumulasi YTD
            $yRows = DB::connection('mysql_finance')
                ->table('expenses')
                ->join('budget_categories', 'budget_categories.id', '=', 'expenses.budget_category_id')
                ->whereYear('expenses.date', $pYear)
                ->whereMonth('expenses.date', '<=', $pMonth)
                ->select('budget_categories.code', DB::raw('SUM(expenses.amount) as total_amt'))
                ->groupBy('budget_categories.code')
                ->get();
            foreach ($yRows as $r) {
                $actualExpensesYtd[$r->code] = (float) $r->total_amt;
            }

            // Ambil seluruh pos anggaran beserta relasi departemen
            $catRows = DB::connection('mysql_finance')
                ->table('budget_categories as bc')
                ->leftJoin('departments as d', 'bc.department_id', '=', 'd.id')
                ->select(
                    'bc.id',
                    'bc.code',
                    'bc.name',
                    'bc.budget_ratio_percent',
                    'bc.description',
                    'd.name as dept_name',
                    'd.code as dept_code'
                )
                ->where('bc.is_active', 1)
                ->orderBy('bc.code', 'asc')
                ->get();

            foreach ($catRows as $c) {
                $ratio = (float) $c->budget_ratio_percent;
                $annualBudgetRaw = round(($baseRev * 1000000000) * ($ratio / 100), 0);
                $monthlyBudgetRaw = round($annualBudgetRaw / 12, 0);

                $monthlyActual = $actualExpensesMonthly[$c->code]['amount'] ?? 0;
                $monthlyTrx = $actualExpensesMonthly[$c->code]['count'] ?? 0;
                $ytdActual = $actualExpensesYtd[$c->code] ?? 0;

                // Tipe akun
                $type = 'other';
                $groupName = 'Beban Operasional (OPEX)';
                if (str_starts_with($c->code, '5')) {
                    $type = 'cogs';
                    $groupName = 'Beban Pokok Penjualan (HPP)';
                } elseif (str_starts_with($c->code, '6')) {
                    $type = 'opex';
                    $groupName = 'Beban Operasional (OPEX)';
                } elseif (str_starts_with($c->code, '4')) {
                    $type = 'income';
                    $groupName = 'Pendapatan';
                }

                $budgetItems[] = [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'dept' => $c->dept_name ?? 'UMUM',
                    'dept_code' => $c->dept_code ?? 'GEN',
                    'type' => $type,
                    'group' => $groupName,
                    'ratio' => $ratio,
                    'annual_budget' => $annualBudgetRaw,
                    'monthly_budget' => $monthlyBudgetRaw,
                    'monthly_actual' => $monthlyActual,
                    'monthly_trx' => $monthlyTrx,
                    'ytd_actual' => $ytdActual,
                    'serapan_monthly' => $monthlyBudgetRaw > 0 ? round(($monthlyActual / $monthlyBudgetRaw) * 100, 1) : 0,
                    'serapan_annual' => $annualBudgetRaw > 0 ? round(($ytdActual / $annualBudgetRaw) * 100, 1) : 0,
                ];
            }

            $hasFinanceDb = true;
        } catch (\Exception $e) {
            $hasFinanceDb = false;
        }

        // Filter data berdasarkan search, dept, dan type
        $itemsCollection = collect($budgetItems);

        if ($this->selectedDept !== 'all') {
            $itemsCollection = $itemsCollection->where('dept', $this->selectedDept);
        }

        if ($this->selectedType !== 'all') {
            $itemsCollection = $itemsCollection->where('type', $this->selectedType);
        }

        if ($this->search) {
            $s = strtolower(trim($this->search));
            $itemsCollection = $itemsCollection->filter(function($i) use ($s) {
                return str_contains(strtolower($i['code']), $s) ||
                       str_contains(strtolower($i['name']), $s) ||
                       str_contains(strtolower($i['dept']), $s);
            });
        }

        $filteredItems = $itemsCollection->values()->toArray();

        // Agregasi Departemen untuk Tab Ringkasan Departemen
        $deptSummary = collect($budgetItems)->groupBy('dept')->map(function($items, $deptName) {
            $totalRatio = $items->sum('ratio');
            $totalAnnualBudget = $items->sum('annual_budget');
            $totalMonthlyBudget = $items->sum('monthly_budget');
            $totalMonthlyActual = $items->sum('monthly_actual');
            $totalYtdActual = $items->sum('ytd_actual');
            $totalAccounts = $items->count();

            return [
                'dept' => $deptName,
                'total_accounts' => $totalAccounts,
                'total_ratio' => $totalRatio,
                'annual_budget' => $totalAnnualBudget,
                'monthly_budget' => $totalMonthlyBudget,
                'monthly_actual' => $totalMonthlyActual,
                'ytd_actual' => $totalYtdActual,
                'serapan' => $totalMonthlyBudget > 0 ? round(($totalMonthlyActual / $totalMonthlyBudget) * 100, 1) : 0,
            ];
        })->sortByDesc('total_ratio')->values()->toArray();

        return view('livewire.peta-pos-akun', [
            'periods' => $periods,
            'departments' => $departments,
            'items' => $filteredItems,
            'deptSummary' => $deptSummary,
            'hasFinanceDb' => $hasFinanceDb,
            'totalAccounts' => count($budgetItems),
            'totalRatioAll' => collect($budgetItems)->sum('ratio'),
            'totalMonthlyBudget' => collect($budgetItems)->sum('monthly_budget'),
            'totalMonthlyActual' => collect($budgetItems)->sum('monthly_actual'),
        ])->layout('layouts.app', ['title' => 'Peta Pos Akun Anggaran & Departemen']);
    }
}
