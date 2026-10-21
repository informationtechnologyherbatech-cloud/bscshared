<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\DepartmentObjective;
use App\Models\Department;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class DepartmentObjectives extends Component
{
    public $selectedDept = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'view')]
    public $viewMode = 'table'; // 'table' (Tabel Grup), 'tabs' (Tab Departemen)

    public function setViewMode($mode)
    {
        $this->viewMode = in_array($mode, ['table', 'tabs']) ? $mode : 'table';
    }

    public function selectTabDept($code)
    {
        $this->selectedDept = $code;
    }

    public $editingObjId = null;
    public $editTarget = 0;
    public $editActual = 0;

    // CRUD Modal State
    public $showModal = false;
    public $isEditMode = false;
    public $objId = null;
    public $dept_code = '';
    public $kpi_code = '';
    public $kpi_name = '';
    public $source_type = 'manual'; // 'manual' atau 'odoo_expense'
    public $odoo_account_code = '';
    public $odooAccountSearch = '';
    public $showOdooDropdown = false;
    public $unit = '';
    public $polarity = 'Naik';
    public $target = 0;
    public $actual = 0;

    protected $rules = [
        'dept_code' => 'required|string|max:30',
        'kpi_code' => 'required|string|max:50',
        'kpi_name' => 'required|string|max:255',
        'source_type' => 'required|in:manual,odoo_expense',
        'odoo_account_code' => 'nullable|string|max:50',
        'unit' => 'nullable|string|max:30',
        'polarity' => 'required|in:Naik,Turun',
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

    public function updatedOdooAccountCode($val)
    {
        if ($this->source_type === 'odoo_expense' && $val) {
            try {
                $cat = DB::connection('mysql_finance')->table('budget_categories')->where('code', $val)->first();
                if ($cat) {
                    if (empty($this->kpi_name) || str_starts_with($this->kpi_name, 'Pengendalian')) {
                        $this->kpi_name = 'Pengendalian ' . $cat->name;
                    }
                    if (empty($this->kpi_code)) {
                        $this->kpi_code = 'OD-' . substr($val, -4);
                    }
                }
            } catch (\Exception $e) {}

            $liveAmount = $this->fetchOdooExpenseAmount($val);
            $this->actual = $liveAmount;
            $this->polarity = 'Turun';
            if (empty($this->unit)) {
                $this->unit = 'Rp';
            }
        }
    }

    public function updatedSourceType($val)
    {
        if ($val === 'odoo_expense') {
            $this->polarity = 'Turun';
            if (empty($this->unit)) {
                $this->unit = 'Rp';
            }
            if ($this->odoo_account_code) {
                $this->actual = $this->fetchOdooExpenseAmount($this->odoo_account_code);
            }
        }
    }

    public function fetchOdooExpenseAmount($accountCode, $period = null)
    {
        $period = $period ?: $this->selectedPeriod;
        $parts = explode('-', $period);
        $year = (int) ($parts[0] ?? date('Y'));
        $month = isset($parts[1]) ? (int) $parts[1] : (int) date('m');

        try {
            $sum = DB::connection('mysql_finance')
                ->table('expenses')
                ->join('budget_categories', 'budget_categories.id', '=', 'expenses.budget_category_id')
                ->where('budget_categories.code', $accountCode)
                ->whereYear('expenses.date', $year)
                ->whereMonth('expenses.date', $month)
                ->sum('expenses.amount');
            return (float) $sum;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function syncFromOdoo($id = null)
    {
        $query = DepartmentObjective::where('period', $this->selectedPeriod)
            ->where('source_type', 'odoo_expense')
            ->whereNotNull('odoo_account_code');

        if ($id) {
            $query->where('id', $id);
        }

        $objs = $query->get();

        if ($objs->isEmpty()) {
            session()->flash('error', 'Tidak ada sasaran mutu terhubung Odoo pada periode ' . $this->selectedPeriod . '.');
            return;
        }

        $count = 0;
        foreach ($objs as $obj) {
            $liveAmount = $this->fetchOdooExpenseAmount($obj->odoo_account_code, $this->selectedPeriod);

            $t = floatval($obj->target);
            $a = floatval($liveAmount);

            if ($obj->polarity === 'Turun') {
                if ($a <= $t) {
                    $ach = 100.00;
                    $status = 'Tercapai';
                } else {
                    $ach = $a > 0 ? round(($t / $a) * 100, 2) : 100.00;
                    $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
                }
            } else {
                $ach = $t > 0 ? round(($a / $t) * 100, 2) : 100.00;
                if ($ach > 100) $ach = 100.00;

                $status = 'Waspada';
                if ($ach >= 100) {
                    $status = 'Tercapai';
                } elseif ($ach < 80) {
                    $status = 'Di Bawah Target';
                }
            }

            $obj->update([
                'actual' => $liveAmount,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
            $count++;
        }

        session()->flash('message', "Berhasil menarik & menyinkronkan $count sasaran mutu langsung dari data realisasi ERP Odoo!");
    }

    public function openCreateModal()
    {
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: Anda tidak berwenang mengelola sasaran mutu.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat ditambah.');
            return;
        }

        $firstDept = Department::where('is_active', true)->orderBy('sort_order', 'asc')->first()?->code ?? 'FAT';

        $this->reset(['objId', 'kpi_code', 'kpi_name', 'target', 'actual', 'odoo_account_code', 'odooAccountSearch', 'showOdooDropdown', 'unit']);
        $this->dept_code = $this->selectedDept ?: $firstDept;
        $this->source_type = 'manual';
        $this->polarity = 'Naik';
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: Anda tidak berwenang mengelola sasaran mutu.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            return;
        }

        $obj = DepartmentObjective::findOrFail($id);
        $this->objId = $obj->id;
        $this->dept_code = $obj->dept_code;
        $this->kpi_code = $obj->kpi_code;
        $this->kpi_name = $obj->kpi_name;
        $this->source_type = $obj->source_type ?: 'manual';
        $this->odoo_account_code = $obj->odoo_account_code ?: '';
        $this->odooAccountSearch = '';
        $this->showOdooDropdown = false;
        $this->unit = $obj->unit ?: '';
        $this->polarity = $obj->polarity;
        $this->target = $obj->target;
        $this->actual = $obj->actual;
        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function toggleOdooDropdown()
    {
        $this->showOdooDropdown = !$this->showOdooDropdown;
    }

    public function closeOdooDropdown()
    {
        $this->showOdooDropdown = false;
    }

    public function selectOdooAccount($code)
    {
        $this->odoo_account_code = $code;
        $this->odooAccountSearch = '';
        $this->showOdooDropdown = false;
    }

    public function clearOdooAccount()
    {
        $this->odoo_account_code = '';
        $this->odooAccountSearch = '';
        $this->showOdooDropdown = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showOdooDropdown = false;
        $this->resetValidation();
    }

    public function saveObjective()
    {
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: Anda tidak berwenang mengelola sasaran mutu.');
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

        // Jika terhubung ke Odoo, selalu ambil realisasi terbaru dari Odoo
        if ($this->source_type === 'odoo_expense' && $this->odoo_account_code) {
            $this->actual = $this->fetchOdooExpenseAmount($this->odoo_account_code);
        }

        $t = floatval($this->target);
        $a = floatval($this->actual);

        if ($this->polarity === 'Turun') {
            if ($a <= $t) {
                $ach = 100.00;
                $status = 'Tercapai';
            } else {
                $ach = $a > 0 ? round(($t / $a) * 100, 2) : 100.00;
                $status = $ach >= 80 ? 'Waspada' : 'Di Bawah Target';
            }
        } else {
            $ach = $t > 0 ? round(($a / $t) * 100, 2) : 100.00;
            if ($ach > 100) $ach = 100.00;

            $status = 'Waspada';
            if ($ach >= 100) {
                $status = 'Tercapai';
            } elseif ($ach < 80) {
                $status = 'Di Bawah Target';
            }
        }

        if ($this->isEditMode && $this->objId) {
            $obj = DepartmentObjective::findOrFail($this->objId);
            $obj->update([
                'dept_code' => strtoupper(trim($this->dept_code)),
                'kpi_code' => strtoupper(trim($this->kpi_code)),
                'kpi_name' => $this->kpi_name,
                'source_type' => $this->source_type,
                'odoo_account_code' => $this->source_type === 'odoo_expense' ? $this->odoo_account_code : null,
                'unit' => $this->unit,
                'polarity' => $this->polarity,
                'target' => $t,
                'actual' => $a,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
            session()->flash('message', 'Sasaran mutu [' . $this->kpi_code . '] berhasil diperbarui!');
        } else {
            DepartmentObjective::create([
                'period' => $this->selectedPeriod,
                'dept_code' => strtoupper(trim($this->dept_code)),
                'kpi_code' => strtoupper(trim($this->kpi_code)),
                'kpi_name' => $this->kpi_name,
                'source_type' => $this->source_type,
                'odoo_account_code' => $this->source_type === 'odoo_expense' ? $this->odoo_account_code : null,
                'unit' => $this->unit,
                'polarity' => $this->polarity,
                'target' => $t,
                'actual' => $a,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
            session()->flash('message', 'Sasaran mutu baru [' . $this->kpi_code . '] berhasil ditambahkan!');
        }

        $this->closeModal();
    }

    public function deleteObjective($id)
    {
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang menghapus sasaran mutu.');
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat dihapus.');
            return;
        }

        $obj = DepartmentObjective::findOrFail($id);
        $name = $obj->kpi_name;
        $code = $obj->kpi_code;
        $obj->delete();

        session()->flash('message', 'Sasaran mutu [' . $code . '] ' . $name . ' berhasil dihapus!');
    }

    public function editObjective($id)
    {
        $this->openEditModal($id);
    }

    public function cancelEdit()
    {
        $this->editingObjId = null;
    }

    public function render()
    {
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        $query = DepartmentObjective::where('period', $this->selectedPeriod);

        if ($this->selectedDept) {
            $query->where('dept_code', $this->selectedDept);
        }

        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['Waspada', 'Di Bawah Target', 'Off-Target']);
            } else {
                $query->where('status', $this->selectedStatus);
            }
        }

        $objectives = $query->get();

        // Ambil data lengkap master departemen
        $masterDepts = Department::where('is_active', true)->orderBy('sort_order', 'asc')->get();
        $deptMap = $masterDepts->keyBy('code');

        $dbDeptCodes = $masterDepts->pluck('code')->toArray();
        $existingObjDepts = DepartmentObjective::where('period', $this->selectedPeriod)->distinct()->pluck('dept_code')->toArray();
        $departments = array_values(array_unique(array_merge($dbDeptCodes, $existingObjDepts)));

        // Ringkasan KPI Global periode terpilih
        $allPeriodObjs = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        $totalKpi = $allPeriodObjs->count();
        $achievedKpi = $allPeriodObjs->where('status', 'Tercapai')->count();
        $warningKpi = $allPeriodObjs->where('status', 'Waspada')->count();
        $dangerKpi = $allPeriodObjs->whereIn('status', ['Di Bawah Target', 'Off-Target'])->count();
        $avgAchievement = $totalKpi > 0 ? round($allPeriodObjs->avg('achievement_pct'), 1) : 0;
        
        $periods = Period::pluck('period')->toArray();

        // Ambil daftar akun beban Odoo untuk opsi linking
        $odooAccounts = collect([]);
        try {
            $odooAccounts = DB::connection('mysql_finance')
                ->table('budget_categories')
                ->select('code', 'name')
                ->orderBy('code')
                ->get();
        } catch (\Exception $e) {
            $odooAccounts = collect([]);
        }

        $odooAccountMap = $odooAccounts->keyBy('code');

        // Filter akun berdasarkan keyword pencarian
        $filteredOdooAccounts = $odooAccounts;
        if (!empty(trim($this->odooAccountSearch))) {
            $kw = strtolower(trim($this->odooAccountSearch));
            $filteredOdooAccounts = $odooAccounts->filter(function ($item) use ($kw) {
                return str_contains(strtolower($item->code), $kw) || str_contains(strtolower($item->name), $kw);
            });
        }

        return view('livewire.department-objectives', [
            'objectives' => $objectives,
            'allPeriodObjectives' => $allPeriodObjs,
            'departments' => $departments,
            'deptMap' => $deptMap,
            'odooAccounts' => $odooAccounts,
            'odooAccountMap' => $odooAccountMap,
            'filteredOdooAccounts' => $filteredOdooAccounts,
            'totalKpi' => $totalKpi,
            'achievedKpi' => $achievedKpi,
            'warningKpi' => $warningKpi,
            'dangerKpi' => $dangerKpi,
            'avgAchievement' => $avgAchievement,
            'periods' => $periods,
            'isClosed' => $isClosed,
        ])->layout('layouts.app', ['title' => 'Objective Departemen']);
    }
}
