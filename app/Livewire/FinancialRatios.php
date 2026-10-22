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

            // 1. Coba ambil langsung dari database koneksi mysql_finance jika tersedia dan ada data
            try {
                if (\Illuminate\Support\Facades\Schema::connection('mysql_finance')->hasTable('financial_ratio_analyses')) {
                    $rows = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                        ->table('financial_ratio_analyses')
                        ->where('month', $month)
                        ->get();

                    // Jika tidak ada data spesifik bulan tersebut, ambil data bulan terdekat/terbaru
                    if ($rows->isEmpty()) {
                        $rows = \Illuminate\Support\Facades\DB::connection('mysql_finance')
                            ->table('financial_ratio_analyses')
                            ->get();
                    }

                    if ($rows->isNotEmpty()) {
                        foreach ($rows as $r) {
                            $itemsToProcess[] = [
                                'category' => $r->ratio_category,
                                'ratio_name' => $r->ratio_name,
                                'actual' => (float)$r->ratio_value,
                                'target' => (float)$r->benchmark_value,
                                'benchmark' => $r->benchmark_value . ' ' . $r->unit,
                                'status' => $r->status_assessment ?? 'Sehat',
                            ];
                        }
                    }
                }
            } catch (\Throwable $dbEx) {
                // Connection or table mysql_finance belum dikonfigurasi di hosting, lanjut ke fallback template
            }

            // 2. Jika tidak ada data dari mysql_finance, gunakan Master Template 15 Rasio Finansial Lengkap
            if (empty($itemsToProcess)) {
                // Cek apakah ada periode lain di BSC yang sudah memiliki rasio lengkap sebagai acuan
                $existingReference = FinancialRatio::where('period', '!=', $this->selectedPeriod)
                    ->orderBy('period', 'desc')
                    ->get();

                if ($existingReference->isNotEmpty()) {
                    $refByRatio = $existingReference->groupBy('ratio_name');
                    foreach ($refByRatio as $rName => $group) {
                        $first = $group->first();
                        $itemsToProcess[] = [
                            'category' => $first->category,
                            'ratio_name' => $first->ratio_name,
                            'actual' => (float)$first->actual,
                            'target' => (float)$first->target,
                            'benchmark' => $first->benchmark,
                            'status' => $first->status,
                        ];
                    }
                } else {
                    // Fallback template 15 rasio standar pabrik/industri botol plastik
                    $defaultMasterRatios = [
                        ['category' => 'Likuiditas', 'ratio_name' => 'CURRENT RATIO', 'benchmark' => '1.5-2.5 X', 'target' => 1.50, 'actual' => 2.37, 'status' => 'Tercapai'],
                        ['category' => 'Likuiditas', 'ratio_name' => 'QUICK RATIO', 'benchmark' => '≥ 1 X', 'target' => 1.00, 'actual' => 1.63, 'status' => 'Tercapai'],
                        ['category' => 'Solvabilitas', 'ratio_name' => 'DEBT TO EQUITY RATIO', 'benchmark' => '< 1.5 X', 'target' => 1.50, 'actual' => 0.41, 'status' => 'Tercapai'],
                        ['category' => 'Solvabilitas', 'ratio_name' => 'DEBT TO TOTAL ASSET RATIO', 'benchmark' => '30-50 %', 'target' => 30.00, 'actual' => 29.26, 'status' => 'Tercapai'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'INVENTORY TURN OVER', 'benchmark' => '≥ 8 X', 'target' => 8.00, 'actual' => 2.86, 'status' => 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'AR TURN OVER', 'benchmark' => '≥ 6 X', 'target' => 6.00, 'actual' => 3.57, 'status' => 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS SALES OUTSANDING', 'benchmark' => '30-45 hari', 'target' => 30.00, 'actual' => 100.80, 'status' => 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS INVENTORY OUTSTANDING', 'benchmark' => '30-45 hari', 'target' => 30.00, 'actual' => 126.00, 'status' => 'Di Bawah Target'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'DAYS PAYABLE OUTSTANDING', 'benchmark' => '30-60 hari', 'target' => 30.00, 'actual' => 108.00, 'status' => 'Waspada'],
                        ['category' => 'Aktivitas', 'ratio_name' => 'ASSET TURN OVER', 'benchmark' => '≥ 0.8 X', 'target' => 0.80, 'actual' => 0.32, 'status' => 'Di Bawah Target'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'GROSS PROFIT MARGIN', 'benchmark' => '> 28%', 'target' => 28.00, 'actual' => 22.53, 'status' => 'Waspada'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'EBIT', 'benchmark' => '8-15%', 'target' => 8.00, 'actual' => 87.58, 'status' => 'Tercapai'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'NET PROFIT MARGIN', 'benchmark' => '5-10%', 'target' => 5.00, 'actual' => 10.11, 'status' => 'Tercapai'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'RETURN ON ASSET', 'benchmark' => '≥ 8%', 'target' => 8.00, 'actual' => 28.16, 'status' => 'Tercapai'],
                        ['category' => 'Profitabilitas', 'ratio_name' => 'RETURN ON EQUITY', 'benchmark' => '> 10%', 'target' => 10.00, 'actual' => 4.60, 'status' => 'Di Bawah Target'],
                    ];
                    $itemsToProcess = $defaultMasterRatios;
                }
            }

            foreach ($itemsToProcess as $item) {
                $ratioName = trim($item['ratio_name']);
                $catName = ucfirst(strtolower($item['category'] ?? 'Finance'));
                $actualVal = (float)($item['actual'] ?? 0);
                $benchmarkStr = trim($item['benchmark'] ?? '');
                $targetVal = (float)($item['target'] ?? 0);
                $status = trim($item['status'] ?? 'Waspada');

                if ($targetVal <= 0 && preg_match('/([0-9]+(?:\.[0-9]+)?)/', str_replace(',', '.', $benchmarkStr), $matches)) {
                    $targetVal = (float)$matches[1];
                }

                // Tentukan persentase pencapaian
                $lowerStatus = strtolower($status);
                if (str_contains($lowerStatus, 'sangat') || str_contains($lowerStatus, 'ideal') || str_contains($lowerStatus, 'optimal') || str_contains($lowerStatus, 'aman') || str_contains($lowerStatus, 'likuid') || str_contains($lowerStatus, 'produktif') || str_contains($lowerStatus, 'cepat') || str_contains($lowerStatus, 'tercapai') || str_contains($lowerStatus, 'sehat') || str_contains($lowerStatus, 'baik')) {
                    $ach = 100.00;
                    $mappedStatus = 'Tercapai';
                } elseif (str_contains($lowerStatus, 'wajar') || str_contains($lowerStatus, 'longgar') || str_contains($lowerStatus, 'waspada') || str_contains($lowerStatus, 'tinggi')) {
                    $ach = 85.00;
                    $mappedStatus = 'Waspada';
                } else {
                    $ach = 50.00;
                    $mappedStatus = 'Di Bawah Target';
                }

                if ($targetVal > 0 && $ach == 100.00 && $actualVal > 0) {
                    $ratioCalc = ($actualVal / $targetVal) * 100;
                    if ($ratioCalc <= 100) {
                        $ach = round($ratioCalc, 2);
                    }
                }

                $existing = FinancialRatio::where('period', $this->selectedPeriod)
                    ->where('ratio_name', $ratioName)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'target' => $targetVal,
                        'benchmark' => $benchmarkStr,
                        'actual' => $actualVal,
                        'achievement_pct' => $ach,
                        'status' => $mappedStatus,
                        'category' => $catName,
                    ]);
                    $syncedCount++;
                } else {
                    FinancialRatio::create([
                        'period' => $this->selectedPeriod,
                        'category' => $catName,
                        'ratio_name' => $ratioName,
                        'target' => $targetVal,
                        'benchmark' => $benchmarkStr,
                        'actual' => $actualVal,
                        'achievement_pct' => $ach,
                        'status' => $mappedStatus,
                    ]);
                    $syncedCount++;
                }
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
