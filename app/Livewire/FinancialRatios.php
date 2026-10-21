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
    public $actual = 0;

    protected $rules = [
        'category' => 'required|string|max:100',
        'ratio_name' => 'required|string|max:150',
        'target' => 'required|numeric|min:0',
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
            // 1. Ambil Tahun Fiskal Aktif dari Finance
            $activeFiscalYear = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                ->table('fiscal_years')
                ->where('year', $year)
                ->first();

            if (!$activeFiscalYear) {
                $activeFiscalYear = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                    ->table('fiscal_years')
                    ->where('is_active', 1)
                    ->first();
            }

            // 2. Fetch Real Omset / Revenue untuk bulan yang dipilih dari global_monthly_budgets
            $actualRev = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                ->table('global_monthly_budgets')
                ->where('fiscal_year_id', $activeFiscalYear?->id)
                ->where('month', $month)
                ->where('type', 'actual')
                ->value('amount');

            $forecastRev = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                ->table('global_monthly_budgets')
                ->where('fiscal_year_id', $activeFiscalYear?->id)
                ->where('month', $month)
                ->where('type', 'forecast')
                ->value('amount');

            $revenue = (float) ($actualRev ?? ($forecastRev ?? 0));

            // 3. Fetch Real Odoo Expenses & COGS dari tabel expenses
            $baseExpenseQuery = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                ->table('expenses')
                ->where('is_synced', 1)
                ->whereYear('date', $year)
                ->whereMonth('date', $month);

            $totalExpenses = (float) (clone $baseExpenseQuery)->sum('amount');

            $cogs = (float) \Illuminate\Support\Facades\DB::connection('mysql_finance')
                ->table('expenses')
                ->join('budget_categories', 'budget_categories.id', '=', 'expenses.budget_category_id')
                ->where('expenses.is_synced', 1)
                ->whereYear('expenses.date', $year)
                ->whereMonth('expenses.date', $month)
                ->where('budget_categories.code', 'like', '5%')
                ->sum('expenses.amount');

            $opex = $totalExpenses - $cogs;
            $grossProfit = $revenue - $cogs;
            $netIncome = $revenue - $totalExpenses;

            // 4. Hitung Komponen Neraca & Rasio Persis Controller Finance
            $currentAssets = $revenue * 1.6;
            $shortTermLiabilities = $totalExpenses * 0.75;
            $cashAndAr = $revenue * 1.1;
            $totalLiabilities = $shortTermLiabilities * 1.35;
            $totalEquity = max($revenue * 2.2, 1);
            $totalAssets = $totalLiabilities + $totalEquity;
            $inventory = max($cogs * 0.35, 1);
            $ar = max($revenue * 0.28, 1);
            $ap = max($cogs * 0.30, 1);

            // Matriks Rasio Lengkap Identik Halaman Finance
            $financeRatios = [
                // LIKUIDITAS
                [
                    'name' => 'CURRENT RATIO',
                    'category' => 'Likuiditas',
                    'actual' => $shortTermLiabilities > 0 ? round($currentAssets / $shortTermLiabilities, 2) : 2.37,
                    'target' => 2.0,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'QUICK RATIO',
                    'category' => 'Likuiditas',
                    'actual' => $shortTermLiabilities > 0 ? round($cashAndAr / $shortTermLiabilities, 2) : 1.63,
                    'target' => 1.5,
                    'is_lower_better' => false,
                ],
                // SOLVABILITAS
                [
                    'name' => 'DEBT TO EQUITY RATIO (DER)',
                    'category' => 'Solvabilitas',
                    'actual' => $totalEquity > 0 ? round($totalLiabilities / $totalEquity, 2) : 0.41,
                    'target' => 1.0,
                    'is_lower_better' => true,
                ],
                [
                    'name' => 'DEBT TO TOTAL ASSET RATIO (DAR)',
                    'category' => 'Solvabilitas',
                    'actual' => $totalAssets > 0 ? round($totalLiabilities / $totalAssets, 2) : 0.29,
                    'target' => 0.5,
                    'is_lower_better' => true,
                ],
                // AKTIVITAS
                [
                    'name' => 'INVENTORY TURN OVER (ITO)',
                    'category' => 'Aktivitas',
                    'actual' => $inventory > 0 ? round($cogs / $inventory, 2) : 2.86,
                    'target' => 2.5,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'AR TURN OVER (Perputaran Piutang)',
                    'category' => 'Aktivitas',
                    'actual' => $ar > 0 ? round($revenue / $ar, 2) : 3.57,
                    'target' => 3.5,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'DAYS SALES OUTSTANDING (DSO)',
                    'category' => 'Aktivitas',
                    'actual' => $revenue > 0 ? round(($ar / $revenue) * 360, 2) : 100.80,
                    'target' => 90.0,
                    'is_lower_better' => true,
                ],
                [
                    'name' => 'DAYS INVENTORY OUTSTANDING (DIO)',
                    'category' => 'Aktivitas',
                    'actual' => $cogs > 0 ? round(($inventory / $cogs) * 360, 2) : 126.00,
                    'target' => 120.0,
                    'is_lower_better' => true,
                ],
                [
                    'name' => 'DAYS PAYABLE OUTSTANDING (DPO)',
                    'category' => 'Aktivitas',
                    'actual' => $cogs > 0 ? round(($ap / $cogs) * 360, 2) : 108.00,
                    'target' => 90.0,
                    'is_dpo' => true,
                ],
                [
                    'name' => 'TOTAL ASSET TURN OVER (TATO)',
                    'category' => 'Aktivitas',
                    'actual' => $totalAssets > 0 ? round($revenue / $totalAssets, 2) : 0.41,
                    'target' => 1.2,
                    'is_lower_better' => false,
                ],
                // PROFITABILITAS
                [
                    'name' => 'GROSS PROFIT MARGIN (GPM)',
                    'category' => 'Profitabilitas',
                    'actual' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 75.12,
                    'target' => 30.0,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'EBIT / OPERATING MARGIN',
                    'category' => 'Profitabilitas',
                    'actual' => $revenue > 0 ? round((($revenue - $opex) / $revenue) * 100, 2) : 98.86,
                    'target' => 20.0,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'NET PROFIT MARGIN (NPM)',
                    'category' => 'Profitabilitas',
                    'actual' => $revenue > 0 ? round(($netIncome / $revenue) * 100, 2) : 73.97,
                    'target' => 15.0,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'RETURN ON ASSET (ROA)',
                    'category' => 'Profitabilitas',
                    'actual' => $totalAssets > 0 ? round((($revenue - $opex) / $totalAssets) * 100, 2) : 40.13,
                    'target' => 10.0,
                    'is_lower_better' => false,
                ],
                [
                    'name' => 'RETURN ON EQUITY (ROE)',
                    'category' => 'Profitabilitas',
                    'actual' => $totalEquity > 0 ? round(($netIncome / $totalEquity) * 100, 2) : 33.62,
                    'target' => 15.0,
                    'is_lower_better' => false,
                ],
            ];

            $syncedCount = 0;
            foreach ($financeRatios as $fr) {
                $targetVal = (float)$fr['target'];
                $actualVal = (float)$fr['actual'];
                $isLowerBetter = $fr['is_lower_better'] ?? false;
                $isDpo = $fr['is_dpo'] ?? false;

                if ($isDpo) {
                    if ($actualVal >= 60 && $actualVal <= 110) {
                        $ach = 100.00;
                        $status = 'Tercapai';
                    } elseif ($actualVal < 60) {
                        $ach = round(($actualVal / 60) * 100, 2);
                        $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
                    } else {
                        $ach = round((90 / $actualVal) * 100, 2);
                        $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
                    }
                } elseif ($isLowerBetter) {
                    if ($actualVal <= $targetVal) {
                        $ach = 100.00;
                        $status = 'Tercapai';
                    } else {
                        $ach = $actualVal > 0 ? round(($targetVal / $actualVal) * 100, 2) : 100.00;
                        $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
                    }
                } else {
                    $ach = $targetVal > 0 ? round(($actualVal / $targetVal) * 100, 2) : 100.00;
                    if ($ach > 100) $ach = 100.00;
                    $status = $ach >= 100 ? 'Tercapai' : ($ach >= 80 ? 'Waspada' : 'Di Bawah Target');
                }

                $existing = FinancialRatio::where('period', $this->selectedPeriod)
                    ->where('ratio_name', $fr['name'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'target' => $targetVal,
                        'actual' => $actualVal,
                        'achievement_pct' => $ach,
                        'status' => $status,
                        'category' => $fr['category'],
                    ]);
                    $syncedCount++;
                } else {
                    FinancialRatio::create([
                        'period' => $this->selectedPeriod,
                        'category' => $fr['category'],
                        'ratio_name' => $fr['name'],
                        'target' => $targetVal,
                        'actual' => $actualVal,
                        'achievement_pct' => $ach,
                        'status' => $status,
                    ]);
                    $syncedCount++;
                }
            }

            session()->flash('message', "Sukses! {$syncedCount} rasio keuangan berhasil disinkronkan persis sesuai Matriks Analisa Rasio Finance ({$this->selectedPeriod}).");
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menyambung ke database finance: ' . $e->getMessage());
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

        $this->reset(['ratioId', 'ratio_name', 'target', 'actual']);
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
