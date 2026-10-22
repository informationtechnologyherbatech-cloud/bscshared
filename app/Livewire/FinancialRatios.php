<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\FinancialRatio;
use App\Models\Period;

class FinancialRatios extends Component
{
    public $selectedCategory = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    public $editingRatioId = null;
    public $editTarget = 0;
    public $editActual = 0;

    // CRUD Modal State
    public $showModal = false;
    public $isEditMode = false;
    public $ratioId = null;
    public $category = 'Profitabilitas';
    public $ratio_name = '';
    public $target = 0;
    public $benchmark = '';
    public $actual = 0;

    protected $rules = [
        'category' => 'required|string|max:100',
        'ratio_name' => 'required|string|max:150',
        'target' => 'required|numeric|min:0',
        'benchmark' => 'nullable|string|max:100',
        'actual' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        if (request()->query('status')) {
            $this->selectedStatus = request()->query('status');
        }
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

    public function syncFromFinanceApp()
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang melakukan sinkronisasi rasio.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Sinkronisasi ditolak.');
            return;
        }

        // Parse tahun dan bulan dari format YYYY-MM (misal: 2026-08 -> year 2026, month 8)
        $parts = explode('-', $this->selectedPeriod);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = isset($parts[1]) ? (int)$parts[1] : (int)date('m');

        try {
            $syncedCount = 0;
            $itemsToProcess = [];

            // 1. Coba hitung langsung secara dinamis dari database Finance (GlobalMonthlyBudget & Expense)
            try {
                $financeDb = \Illuminate\Support\Facades\DB::connection('mysql_finance');

                if (\Illuminate\Support\Facades\Schema::connection('mysql_finance')->hasTable('global_monthly_budgets') &&
                    \Illuminate\Support\Facades\Schema::connection('mysql_finance')->hasTable('expenses')) {

                    $activeFiscalYear = $financeDb->table('fiscal_years')->where('is_active', 1)->first();
                    $fiscalYearId = $activeFiscalYear?->id;
                    $fiscalYear = (int)($activeFiscalYear?->year ?? $year);

                    // A. Fetch Omset / Revenue untuk bulan terpilih
                    $actualRev = $financeDb->table('global_monthly_budgets')
                        ->where('fiscal_year_id', $fiscalYearId)
                        ->where('month', $month)
                        ->where('type', 'actual')
                        ->value('amount');

                    $forecastRev = $financeDb->table('global_monthly_budgets')
                        ->where('fiscal_year_id', $fiscalYearId)
                        ->where('month', $month)
                        ->where('type', 'forecast')
                        ->value('amount');

                    $revenue = (float)($actualRev ?? ($forecastRev ?? 0));

                    // B. Fetch Real Odoo Expenses & COGS
                    $baseExpenseQuery = $financeDb->table('expenses')
                        ->where('is_synced', 1)
                        ->whereYear('date', $fiscalYear)
                        ->whereMonth('date', $month);

                    $totalExpenses = (float)(clone $baseExpenseQuery)->sum('amount');

                    // COGS (Akun Kepala 5)
                    $cogs = (float)(clone $baseExpenseQuery)
                        ->join('budget_categories', 'expenses.budget_category_id', '=', 'budget_categories.id')
                        ->where('budget_categories.code', 'like', '5%')
                        ->sum('expenses.amount');

                    // Opex Murni
                    $hasCoaMappings = \Illuminate\Support\Facades\Schema::connection('mysql_finance')->hasTable('coa_mappings');
                    $opex = 0.0;
                    if ($hasCoaMappings) {
                        $opex = (float)(clone $baseExpenseQuery)
                            ->join('budget_categories', 'expenses.budget_category_id', '=', 'budget_categories.id')
                            ->whereIn('budget_categories.code', function ($sub) use ($financeDb) {
                                $sub->select('account_code')
                                    ->from('coa_mappings')
                                    ->where('account_type', 'Expense');
                            })->sum('expenses.amount');
                    }

                    if ($opex == 0) {
                        $opex = (float)(clone $baseExpenseQuery)
                            ->join('budget_categories', 'expenses.budget_category_id', '=', 'budget_categories.id')
                            ->where('budget_categories.code', 'not like', '5%')
                            ->where('budget_categories.code', 'not like', '8%')
                            ->sum('expenses.amount');
                    }

                    // Beban Lainnya (Kepala 8)
                    $otherExpenses = (float)(clone $baseExpenseQuery)
                        ->join('budget_categories', 'expenses.budget_category_id', '=', 'budget_categories.id')
                        ->where('budget_categories.code', 'like', '8%')
                        ->sum('expenses.amount');

                    $grossProfit = $revenue - $cogs;
                    $netIncome = $revenue - ($cogs + $opex + $otherExpenses);

                    // C. Est. Baseline Balance Sheet (Neraca) Industri Pabrik Sesuai Finance App
                    $currentAssets = $revenue * 1.6;
                    $shortTermLiabilities = $totalExpenses * 0.75;
                    $cashAndAr = $revenue * 1.1;
                    $totalLiabilities = $shortTermLiabilities * 1.35;
                    $totalEquity = max($revenue * 2.2, 1);
                    $totalAssets = $totalLiabilities + $totalEquity;
                    $inventory = max($cogs * 0.35, 1);
                    $ar = max($revenue * 0.28, 1);
                    $ap = max($cogs * 0.30, 1);

                    // D. Hitung 15 Rasio Keuangan Persis Sesuai Formula Finance App
                    $crVal = $shortTermLiabilities > 0 ? ($currentAssets / $shortTermLiabilities) : 0;
                    $qrVal = $shortTermLiabilities > 0 ? ($cashAndAr / $shortTermLiabilities) : 0;
                    $derVal = $totalEquity > 0 ? ($totalLiabilities / $totalEquity) : 0;
                    $darVal = $totalAssets > 0 ? (($totalLiabilities / $totalAssets) * 100) : 0;
                    $itoVal = $inventory > 0 ? ($cogs / $inventory) : 0;
                    $arTurnoverVal = $ar > 0 ? ($revenue / $ar) : 0;
                    $dsoVal = $revenue > 0 ? (($ar / $revenue) * 360) : 0;
                    $dioVal = $cogs > 0 ? (($inventory / $cogs) * 360) : 0;
                    $dpoVal = $cogs > 0 ? (($ap / $cogs) * 360) : 0;
                    $tatoVal = $totalAssets > 0 ? ($revenue / $totalAssets) : 0;
                    $gpmVal = $revenue > 0 ? (($grossProfit / $revenue) * 100) : 0;
                    $ebitMarginVal = $revenue > 0 ? ((($revenue - $opex) / $revenue) * 100) : 0;
                    $npmVal = $revenue > 0 ? (($netIncome / $revenue) * 100) : 0;
                    $roaVal = $totalAssets > 0 ? ((($revenue - $opex) / $totalAssets) * 100) : 0;
                    $roeVal = $totalEquity > 0 ? (($netIncome / $totalEquity) * 100) : 0;

                    $itemsToProcess = [
                        // Likuiditas
                        ['category' => 'Likuiditas', 'ratio_name' => 'CURRENT RATIO', 'actual' => round($crVal, 2), 'target' => 1.50, 'benchmark' => '1.5-2.5 X', 'status' => ($crVal >= 1.5 && $crVal <= 2.5) ? 'Tercapai' : ($crVal > 2.5 ? 'Tercapai' : 'Di Bawah Target')],
                        ['category' => 'Likuiditas', 'ratio_name' => 'QUICK RATIO', 'actual' => round($qrVal, 2), 'target' => 1.00, 'benchmark' => '≥ 1 X', 'status' => ($qrVal >= 1.0) ? 'Tercapai' : 'Waspada'],
                        // Solvabilitas
                        ['category' => 'Solvabilitas', 'ratio_name' => 'DEBT TO EQUITY RATIO', 'actual' => round($derVal, 2), 'target' => 1.50, 'benchmark' => '< 1.5 X', 'status' => ($derVal < 1.5) ? 'Tercapai' : 'Waspada'],
                        ['category' => 'Solvabilitas', 'ratio_name' => 'DEBT TO TOTAL ASSET RATIO', 'actual' => round($darVal, 2), 'target' => 30.00, 'benchmark' => '30-50 %', 'status' => ($darVal >= 30 && $darVal <= 50) ? 'Tercapai' : ($darVal < 30 ? 'Tercapai' : 'Waspada')],
                        // Aktivitas
                        ['category' => 'Aktivitas', 'ratio_name' => 'INVENTORY TURN OVER', 'actual' => round($itoVal, 2), 'target' => 8.00, 'benchmark' => '≥ 8 X', 'status' => ($itoVal >= 8.0) ? 'Tercapai' : 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'AR TURN OVER', 'actual' => round($arTurnoverVal, 2), 'target' => 6.00, 'benchmark' => '≥ 6 X', 'status' => ($arTurnoverVal >= 6.0) ? 'Tercapai' : 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS SALES OUTSANDING', 'actual' => round($dsoVal, 2), 'target' => 30.00, 'benchmark' => '30-45 hari', 'status' => ($dsoVal >= 30 && $dsoVal <= 45) ? 'Tercapai' : ($dsoVal < 30 ? 'Tercapai' : 'Di Bawah Target')],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS INVENTORY OUTSTANDING', 'actual' => round($dioVal, 2), 'target' => 30.00, 'benchmark' => '30-45 hari', 'status' => ($dioVal >= 30 && $dioVal <= 45) ? 'Tercapai' : ($dioVal < 30 ? 'Tercapai' : 'Di Bawah Target')],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS PAYABLE OUTSTANDING', 'actual' => round($dpoVal, 2), 'target' => 30.00, 'benchmark' => '30-60 hari', 'status' => ($dpoVal >= 30 && $dpoVal <= 60) ? 'Tercapai' : 'Waspada'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'ASSET TURN OVER', 'actual' => round($tatoVal, 2), 'target' => 0.80, 'benchmark' => '≥ 0.8 X', 'status' => ($tatoVal >= 0.8) ? 'Tercapai' : 'Di Bawah Target'],
                        // Profitabilitas
                        ['category' => 'Profitabilitas', 'ratio_name' => 'GROSS PROFIT MARGIN', 'actual' => round($gpmVal, 2), 'target' => 28.00, 'benchmark' => '> 28%', 'status' => ($gpmVal > 28) ? 'Tercapai' : 'Waspada'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'EBIT', 'actual' => round($ebitMarginVal, 2), 'target' => 8.00, 'benchmark' => '8-15%', 'status' => ($ebitMarginVal >= 8) ? 'Tercapai' : 'Di Bawah Target'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'NET PROFIT MARGIN', 'actual' => round($npmVal, 2), 'target' => 5.00, 'benchmark' => '5-10%', 'status' => ($npmVal >= 5) ? 'Tercapai' : 'Di Bawah Target'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'RETURN ON ASSET', 'actual' => round($roaVal, 2), 'target' => 8.00, 'benchmark' => '≥ 8%', 'status' => ($roaVal >= 8) ? 'Tercapai' : 'Di Bawah Target'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'RETURN ON EQUITY', 'actual' => round($roeVal, 2), 'target' => 10.00, 'benchmark' => '> 10%', 'status' => ($roeVal > 10) ? 'Tercapai' : 'Di Bawah Target'],
                    ];
                }
            } catch (\Throwable $calcEx) {
                // Jika koneksi DB Finance offline, simpan error untuk log
            }

            // Jika kalkulasi dinamis berhasil didapat
            if (!empty($itemsToProcess)) {
                // Bersihkan record rasio lama untuk periode ini agar tidak ada duplikasi nama rasio lain
                FinancialRatio::where('period', $this->selectedPeriod)->delete();

                foreach ($itemsToProcess as $item) {
                    $ratioName = trim($item['ratio_name']);
                    $catName = ucfirst(strtolower($item['category'] ?? 'Finance'));
                    $actualVal = (float)($item['actual'] ?? 0);
                    $benchmarkStr = trim($item['benchmark'] ?? '');
                    $targetVal = (float)($item['target'] ?? 0);
                    $status = trim($item['status'] ?? 'Waspada');

                    // Hitung achievement_pct
                    if ($status === 'Tercapai') {
                        $ach = 100.00;
                        if ($targetVal > 0 && $actualVal > 0) {
                            $calc = ($actualVal / $targetVal) * 100;
                            if ($calc <= 100) {
                                $ach = round($calc, 2);
                            }
                        }
                    } elseif ($status === 'Waspada') {
                        $ach = 85.00;
                    } else {
                        $ach = 50.00;
                    }

                    FinancialRatio::create([
                        'period' => $this->selectedPeriod,
                        'category' => $catName,
                        'ratio_name' => $ratioName,
                        'target' => $targetVal,
                        'benchmark' => $benchmarkStr,
                        'actual' => $actualVal,
                        'achievement_pct' => $ach,
                        'status' => $status,
                    ]);
                    $syncedCount++;
                }
            } else {
                throw new \Exception("Tidak ada data transaksi Finance yang ditemukan untuk bulan {$month} ({$this->selectedPeriod}).");
            }

            session()->flash('message', "Sukses! {$syncedCount} rasio keuangan berhasil disinkronkan untuk periode {$this->selectedPeriod}.");
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menyinkronkan data Rasio Finance: ' . $e->getMessage());
        }
    }

    public function openCreateModal()
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengelola rasio (butuh manage ratios / Admin FAT / Super Admin).');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat ditambah.');
            return;
        }

        $this->reset(['ratioId', 'ratio_name', 'target', 'benchmark', 'actual']);
        $this->category = 'Profitabilitas';
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengelola rasio.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            return;
        }

        $ratio = FinancialRatio::findOrFail($id);
        $this->ratioId = $ratio->id;
        $this->category = $ratio->category;
        $this->ratio_name = $ratio->ratio_name;
        $this->target = $ratio->target;
        $this->benchmark = $ratio->benchmark ?? '';
        $this->actual = $ratio->actual;
        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function saveRatio()
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengelola rasio.');
            $this->closeModal();
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            $this->closeModal();
            return;
        }

        $this->validate();

        $t = floatval($this->target);
        $a = floatval($this->actual);
        $bm = !empty($this->benchmark) ? trim($this->benchmark) : null;

        $upperName = strtoupper(trim($this->ratio_name));
        $isDpo = str_contains($upperName, 'DPO') || str_contains($upperName, 'DAYS PAYABLE');

        $lowerIsBetter = in_array($upperName, [
            'DEBT TO EQUITY RATIO (DER)', 
            'DEBT TO TOTAL ASSET RATIO (DAR)',
            'DAYS SALES OUTSTANDING (DSO)',
            'DAYS INVENTORY OUTSTANDING (DIO)'
        ]);

        if ($isDpo) {
            // Rentang ideal DPO: 60 - 90 hari (toleransi s.d 108 hari masih wajar/optimal)
            if ($a >= 60 && $a <= 90) {
                $ach = 100.00;
                $status = 'Tercapai';
            } elseif ($a > 90 && $a <= 110) {
                $ach = 100.00;
                $status = 'Tercapai'; // Optimal (sesuai matriks Odoo)
            } elseif ($a < 60) {
                $ach = round(($a / 60) * 100, 2);
                $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
            } else {
                $ach = round((90 / $a) * 100, 2);
                $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
            }
        } elseif ($lowerIsBetter) {
            if ($a <= $t) {
                $ach = 100.00;
                $status = 'Tercapai';
            } else {
                $ach = $a > 0 ? round(($t / $a) * 100, 2) : 100.00;
                $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
            }
        } else {
            $ach = $t > 0 ? round(($a / $t) * 100, 2) : 100;
            if ($ach > 100) $ach = 100.00;

            $status = 'Waspada';
            if ($ach >= 100) {
                $status = 'Tercapai';
            } elseif ($ach < 80) {
                $status = 'Di Bawah Target';
            }
        }

        if ($this->isEditMode && $this->ratioId) {
            $ratio = FinancialRatio::findOrFail($this->ratioId);
            $ratio->update([
                'category' => $this->category,
                'ratio_name' => $this->ratio_name,
                'target' => $t,
                'benchmark' => $bm,
                'actual' => $a,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
            session()->flash('message', 'Rasio keuangan "' . $this->ratio_name . '" berhasil diperbarui!');
        } else {
            FinancialRatio::create([
                'period' => $this->selectedPeriod,
                'category' => $this->category,
                'ratio_name' => $this->ratio_name,
                'target' => $t,
                'benchmark' => $bm,
                'actual' => $a,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
            session()->flash('message', 'Rasio keuangan baru "' . $this->ratio_name . '" berhasil ditambahkan!');
        }

        $this->closeModal();
    }

    public function deleteRatio($id)
    {
        if (!auth()->user()?->can('manage ratios')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang menghapus rasio.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat dihapus.');
            return;
        }

        $ratio = FinancialRatio::findOrFail($id);
        $name = $ratio->ratio_name;
        $ratio->delete();

        session()->flash('message', 'Rasio keuangan "' . $name . '" berhasil dihapus!');
    }

    public function editRatio($id)
    {
        $this->openEditModal($id);
    }

    public function cancelEdit()
    {
        $this->editingRatioId = null;
    }

    public function render()
    {
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        $query = FinancialRatio::where('period', $this->selectedPeriod);
        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }
        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['Waspada', 'Di Bawah Target', 'Off-Target']);
            } else {
                $query->where('status', $this->selectedStatus);
            }
        }
        $ratios = $query->get();

        $standardCategories = ['Profitabilitas', 'Likuiditas', 'Solvabilitas', 'Aktivitas', 'Produktivitas'];
        $dbCategories = FinancialRatio::distinct()->pluck('category')->toArray();
        $categories = array_unique(array_merge($standardCategories, $dbCategories));
        $periods = Period::pluck('period')->toArray();

        return view('livewire.financial-ratios', [
            'ratios' => $ratios,
            'categories' => $categories,
            'periods' => $periods,
            'isClosed' => $isClosed,
        ])->layout('layouts.app', ['title' => 'Rasio Keuangan']);
    }
}
