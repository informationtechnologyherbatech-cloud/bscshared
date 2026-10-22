<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use App\Models\Department;
use App\Models\DepartmentObjective;
use Illuminate\Support\Facades\DB;

class PetaPosAkun extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    // Filter Kategori Akun & Pencarian Pos
    public $accountTypeFilter = ''; // '', 'BUDGET_130', 'Asset', 'Liability', 'Equity', 'Revenue', 'COGS', 'Expense'
    public $searchPos = '';

    // Data Matriks Hubungan: matrix[dept_code][account_code] = 1 (Terkait / Ceklis) atau 0 (Tidak Terkait)
    public $matrix = [];

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);

        $this->loadMatrix();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->loadMatrix();
    }

    /**
     * Muat matriks hubungan dari app_settings
     */
    public function loadMatrix()
    {
        $key = 'peta_pos_matrix_accounts_' . $this->selectedPeriod;
        $saved = DB::table('app_settings')->where('key', $key)->value('value');

        if ($saved) {
            $this->matrix = json_decode($saved, true) ?: [];
        } else {
            // Migrasi awal dari matriks lama atau inisialisasi default berdasarkan departemen PIC akun
            $this->matrix = $this->getDefaultMatrix();
        }
    }

    /**
     * Dapatkan matriks default berdasarkan PIC Departemen akun Odoo
     */
    private function getDefaultMatrix()
    {
        // Cek apakah ada matriks 12 pos lama untuk dimigrasikan mapping-nya
        $oldKey = 'peta_pos_matrix_12_' . $this->selectedPeriod;
        $oldSaved = DB::table('app_settings')->where('key', $oldKey)->value('value');
        $oldMatrix = $oldSaved ? json_decode($oldSaved, true) : [];

        $allAccounts = $this->getAllOdooAccountsAndCOA();
        $matrix = [];

        foreach ($allAccounts as $acc) {
            $code = $acc['code'];
            $deptCode = $acc['dept_code'];

            if ($deptCode) {
                $matrix[$deptCode][$code] = 1;
            }

            // Hubungkan juga berdasarkan pos finansial inti jika akun masuk kelompok PA01-PA12
            $prefix = substr($code, 0, 1);
            if ($prefix === '4') { // Penjualan (PA01)
                $matrix['SM'][$code] = 1;
                $matrix['MKT'][$code] = 1;
                $matrix['SALES'][$code] = 1;
            } elseif ($prefix === '5') { // COGS (PA02/PA04/PA05)
                $matrix['PRD'][$code] = 1;
                $matrix['PROD'][$code] = 1;
                $matrix['ACCTAX'][$code] = 1;
                $matrix['FAT'][$code] = 1;
            } elseif (str_starts_with($code, '113')) { // Persediaan (PA06)
                $matrix['WH'][$code] = 1;
                $matrix['PPIC'][$code] = 1;
                $matrix['PRD'][$code] = 1;
            } elseif (str_starts_with($code, '112')) { // Piutang (PA07)
                $matrix['FIN'][$code] = 1;
                $matrix['FAT'][$code] = 1;
                $matrix['SM'][$code] = 1;
            } elseif (str_starts_with($code, '211')) { // Utang (PA08)
                $matrix['FIN'][$code] = 1;
                $matrix['FAT'][$code] = 1;
                $matrix['PCH&IT'][$code] = 1;
                $matrix['PROC'][$code] = 1;
            } elseif (str_starts_with($code, '111')) { // Kas (PA09)
                $matrix['FIN'][$code] = 1;
                $matrix['FAT'][$code] = 1;
            }
        }

        return $matrix;
    }

    /**
     * Dapatkan 130 Pos Akun Anggaran Odoo ditambah seluruh Chart of Accounts (COA) Odoo ERP
     */
    public function getAllOdooAccountsAndCOA()
    {
        try {
            // 1. Ambil 130 Pos Anggaran (budget_categories) dengan Departemen PIC terkait
            $bcRows = DB::connection('mysql_finance')
                ->table('budget_categories')
                ->leftJoin('departments', 'budget_categories.department_id', '=', 'departments.id')
                ->select(
                    'budget_categories.code',
                    'budget_categories.name',
                    'departments.name as dept_name',
                    'departments.code as dept_code'
                )
                ->orderBy('budget_categories.code')
                ->get()
                ->keyBy('code');

            // 2. Ambil seluruh COA Odoo aktif dari coa_mappings
            $coaRows = DB::connection('mysql_finance')
                ->table('coa_mappings')
                ->where('coa_mappings.is_active', 1)
                ->leftJoin('departments', 'coa_mappings.department_id', '=', 'departments.id')
                ->select(
                    'coa_mappings.account_code as code',
                    'coa_mappings.account_name as name',
                    'coa_mappings.account_type as type',
                    'departments.name as dept_name',
                    'departments.code as dept_code'
                )
                ->orderBy('coa_mappings.account_code')
                ->get();

            $list = collect([]);

            // Jika coaRows kosong, gunakan minimal budget_categories
            if ($coaRows->isEmpty()) {
                foreach ($bcRows as $code => $bc) {
                    $prefix = substr($code, 0, 1);
                    $type = ($prefix === '5') ? 'COGS' : 'Expense';
                    $list->push([
                        'code' => $code,
                        'name' => $bc->name,
                        'type' => $type,
                        'is_budget_pos' => true,
                        'dept_name' => $bc->dept_name,
                        'dept_code' => $bc->dept_code,
                    ]);
                }
                return $list;
            }

            // Gabungkan COA dan tandai apakah termasuk pos anggaran Odoo (130 pos)
            foreach ($coaRows as $c) {
                $code = $c->code;
                $isBudget = isset($bcRows[$code]);
                $deptName = $c->dept_name ?: ($isBudget ? $bcRows[$code]->dept_name : null);
                $deptCode = $c->dept_code ?: ($isBudget ? $bcRows[$code]->dept_code : null);

                $list->push([
                    'code' => $code,
                    'name' => $c->name,
                    'type' => $c->type,
                    'is_budget_pos' => $isBudget,
                    'dept_name' => $deptName,
                    'dept_code' => $deptCode,
                ]);
            }

            // Tambahkan pos anggaran jika ada di budget_categories tapi belum ada di coa_mappings
            $existingCodes = $coaRows->pluck('code')->toArray();
            foreach ($bcRows as $code => $bc) {
                if (!in_array($code, $existingCodes)) {
                    $prefix = substr($code, 0, 1);
                    $type = ($prefix === '5') ? 'COGS' : 'Expense';
                    $list->push([
                        'code' => $code,
                        'name' => $bc->name,
                        'type' => $type,
                        'is_budget_pos' => true,
                        'dept_name' => $bc->dept_name,
                        'dept_code' => $bc->dept_code,
                    ]);
                }
            }

            // Urutkan: Pos Akun Anggaran (130) didahulukan, lalu berdasarkan kode akun
            return $list->sortByDesc('is_budget_pos')->values();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Toggle status ceklis (1 atau 0)
     */
    public function toggleCell($deptCode, $accountCode)
    {
        $current = isset($this->matrix[$deptCode][$accountCode]) && (int)$this->matrix[$deptCode][$accountCode] === 1 ? 1 : 0;
        $newVal = $current === 1 ? 0 : 1;

        if ($newVal === 0) {
            unset($this->matrix[$deptCode][$accountCode]);
        } else {
            $this->matrix[$deptCode][$accountCode] = 1;
        }

        // Simpan ke database app_settings secara instan
        $key = 'peta_pos_matrix_accounts_' . $this->selectedPeriod;
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($this->matrix), 'updated_at' => now()]
        );
    }

    /**
     * Reset matriks ke konfigurasi awal
     */
    public function resetMatrix()
    {
        $this->matrix = $this->getDefaultMatrix();
        $key = 'peta_pos_matrix_accounts_' . $this->selectedPeriod;
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($this->matrix), 'updated_at' => now()]
        );

        session()->flash('message', 'Matriks hubungan pos akun berhasil di-reset ke konfigurasi awal!');
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();

        // Ambil Daftar Departemen dari Master Departemen (Tabel departments)
        $departments = Department::where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        if ($departments->isEmpty()) {
            try {
                $departments = DB::connection('mysql_finance')
                    ->table('departments')
                    ->select('id', 'name', 'code')
                    ->orderBy('id', 'asc')
                    ->get();
            } catch (\Exception $e) {
                $departments = collect([]);
            }
        }

        // Ambil 130 Pos Anggaran Odoo ditambah seluruh Chart of Accounts (COA) Odoo ERP
        $allAccounts = $this->getAllOdooAccountsAndCOA();
        $totalBudgetPosCount = $allAccounts->where('is_budget_pos', true)->count();
        $totalAccountsCount = $allAccounts->count();

        // Terapkan filter kategori akun
        $filteredAccounts = $allAccounts;
        if ($this->accountTypeFilter) {
            if ($this->accountTypeFilter === 'BUDGET_130') {
                $filteredAccounts = $filteredAccounts->where('is_budget_pos', true);
            } else {
                $filteredAccounts = $filteredAccounts->where('type', $this->accountTypeFilter);
            }
        }

        // Terapkan filter pencarian akun
        if (!empty(trim($this->searchPos))) {
            $kw = strtolower(trim($this->searchPos));
            $filteredAccounts = $filteredAccounts->filter(function($item) use ($kw) {
                return str_contains(strtolower($item['code']), $kw)
                    || str_contains(strtolower($item['name']), $kw)
                    || str_contains(strtolower($item['dept_name'] ?? ''), $kw);
            });
        }

        // Hitung total ceklis terhubung
        $totalChecked = 0;
        foreach ($this->matrix as $dCode => $row) {
            foreach ($row as $accCode => $val) {
                if ((int)$val === 1) {
                    $totalChecked++;
                }
            }
        }

        // Ambil sasaran mutu terkait per departemen untuk popover / tooltip
        $deptObjectives = DepartmentObjective::where('period', $this->selectedPeriod)->get()->groupBy('dept_code');

        return view('livewire.peta-pos-akun', [
            'periods' => $periods,
            'departments' => $departments,
            'accounts' => $filteredAccounts,
            'allAccounts' => $allAccounts,
            'totalDepts' => $departments->count(),
            'totalAccountsCount' => $totalAccountsCount,
            'totalBudgetPosCount' => $totalBudgetPosCount,
            'totalChecked' => $totalChecked,
            'deptObjectives' => $deptObjectives,
        ])->layout('layouts.app', ['title' => 'Peta Pos Akun — Matriks 130 Pos Anggaran & COA Odoo']);
    }
}
