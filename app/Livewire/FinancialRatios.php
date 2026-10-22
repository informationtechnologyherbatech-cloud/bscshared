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
            // Panggil langsung FinancialRatioController Finance App secara dinamis
            $bridgePath = app_path('Services/FinanceRatioBridge.php');
            $jsonStr = '';

            if (function_exists('exec')) {
                $output = [];
                $returnVar = 0;
                @exec("php {$bridgePath} {$month} 2>&1", $output, $returnVar);
                $jsonStr = implode('', $output);
            } elseif (function_exists('shell_exec')) {
                $jsonStr = @shell_exec("php {$bridgePath} {$month} 2>&1") ?: '';
            } else {
                throw new \Exception("Fungsi shell/exec dinonaktifkan di konfigurasi PHP server hosting (disable_functions). Silakan gunakan form Tambah/Ubah Rasio secara manual atau hubungi administrator untuk mengizinkan sinkronisasi background.");
            }

            $ratioGroups = json_decode($jsonStr, true);

            if (!is_array($ratioGroups) || empty($ratioGroups)) {
                throw new \Exception("Gagal mengambil data dari Controller Finance: " . substr($jsonStr ?: 'Tidak ada respon dari proses background.', 0, 200));
            }

            $syncedCount = 0;
            foreach ($ratioGroups as $categoryTitle => $items) {
                // Normalisasi nama kategori (misal: LIKUIDITAS -> Likuiditas)
                $catName = ucfirst(strtolower($categoryTitle));

                foreach ($items as $item) {
                    $ratioName = trim($item['name']);
                    $actualVal = (float)($item['value'] ?? 0);
                    $benchmarkStr = trim($item['benchmark'] ?? '');
                    $status = trim($item['assessment'] ?? 'Waspada');

                    // Ekstrak target numerik dari string benchmark untuk kalkulasi persentase pencapaian
                    $targetVal = 0.0;
                    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', str_replace(',', '.', $benchmarkStr), $matches)) {
                        $targetVal = (float)$matches[1];
                    }

                    // Tentukan achievement percentage berdasarkan status riil dari finance
                    if (str_contains(strtolower($status), 'sangat') || str_contains(strtolower($status), 'ideal') || str_contains(strtolower($status), 'optimal') || str_contains(strtolower($status), 'aman') || str_contains(strtolower($status), 'likuid') || str_contains(strtolower($status), 'produktif') || str_contains(strtolower($status), 'cepat') || str_contains(strtolower($status), 'tercapai')) {
                        $ach = 100.00;
                        $mappedStatus = 'Tercapai';
                    } elseif (str_contains(strtolower($status), 'wajar') || str_contains(strtolower($status), 'longgar') || str_contains(strtolower($status), 'waspada') || str_contains(strtolower($status), 'tinggi')) {
                        $ach = 85.00;
                        $mappedStatus = 'Waspada';
                    } else {
                        $ach = 50.00;
                        $mappedStatus = 'Di Bawah Target';
                    }

                    // Khusus jika ada kalkulasi persentase eksak
                    if ($targetVal > 0 && $ach == 100.00) {
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
            }

            session()->flash('message', "Sukses! {$syncedCount} rasio keuangan berhasil disinkronkan langsung dari Finance App ({$this->selectedPeriod}).");
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menyambung ke data Finance: ' . $e->getMessage());
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
