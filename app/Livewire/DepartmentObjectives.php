<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\DepartmentObjective;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class DepartmentObjectives extends Component
{
    #[Url(as: 'dept')]
    public $selectedDept = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'tab')]
    public $activeTab = 'kpi'; // 'kpi' (Sasaran Mutu), 'pos_link' (Keterkaitan Pos Akun), 'action' (Inisiatif Perbaikan)

    public $search = '';

    // Filter Pos Akun (Tab 2: 130 Pos Anggaran & COA)
    public $posAccountTypeFilter = '';
    public $posSearch = '';

    // CRUD Modal State
    public $showModal = false;
    public $isEditMode = false;
    public $objId = null;
    public $dept_code = '';
    public $kpi_code = '';
    public $kpi_name = '';
    public $source_type = 'manual'; // 'manual' atau 'odoo_expense'
    public $selected_pos_codes = []; // Array of PA codes, contoh: ['PA01', 'PA02'] untuk Pos Anggaran
    public $odoo_account_code = ''; // Akun Beban/COA Odoo ERP string (comma-separated jika multi)
    public $selected_odoo_accounts = []; // Array of Odoo account codes, contoh: ['51000010', '61100330']
    public $odooAccountSearch = ''; // Keyword pencarian akun odoo
    public $impact_note = ''; // Catatan dampak spesifik operasional/finansial
    public $unit = '';
    public $polarity = 'Naik';
    public $target = '';
    public $actual = '';

    // Quick inline edit
    public $editingObjId = null;
    public $editTarget = 0;
    public $editActual = 0;

    protected $rules = [
        'dept_code' => 'required|string|max:50',
        'kpi_code' => 'required|string|max:50',
        'kpi_name' => 'required|string|max:255',
        'source_type' => 'required|in:manual,odoo_expense',
        'selected_pos_codes' => 'nullable|array',
        'selected_pos_codes.*' => 'string|max:20',
        'selected_odoo_accounts' => 'nullable|array',
        'selected_odoo_accounts.*' => 'string|max:50',
        'odoo_account_code' => 'nullable|string|max:255',
        'impact_note' => 'nullable|string|max:1000',
        'unit' => 'nullable|string|max:30',
        'polarity' => 'required|in:Naik,Turun',
        'target' => 'required|numeric|min:0',
        'actual' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);

        if (request()->query('status')) {
            $this->selectedStatus = request()->query('status');
        }
        if (request()->query('dept')) {
            $this->selectedDept = request()->query('dept');
        }
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
    }

    /**
     * Dapatkan Daftar Departemen dari Master Departemen (Tabel departments)
     */
    public function getOdooDepartments()
    {
        return \App\Models\Department::where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * 12 Pos Finansial Inti Pabrik Botol Plastik
     */
    public function get12CorePos()
    {
        return [
            ['code' => 'PA01', 'name' => 'Penjualan Botol', 'ratio' => 'Sales & Growth'],
            ['code' => 'PA02', 'name' => 'Bahan Baku Resin (PET/HDPE)', 'ratio' => 'GPM & COGS'],
            ['code' => 'PA03', 'name' => 'Listrik & Energi Mesin', 'ratio' => 'GPM & Utilitas'],
            ['code' => 'PA04', 'name' => 'Tenaga Kerja Langsung', 'ratio' => 'COGS & SDM'],
            ['code' => 'PA05', 'name' => 'Maintenance & Moulding', 'ratio' => 'TATO & Mesin'],
            ['code' => 'PA06', 'name' => 'Persediaan Botol & Resin', 'ratio' => 'ITO & DIO'],
            ['code' => 'PA07', 'name' => 'Piutang Usaha Pelanggan', 'ratio' => 'DSO & Kas'],
            ['code' => 'PA08', 'name' => 'Utang Usaha Supplier', 'ratio' => 'DPO & Pembelian'],
            ['code' => 'PA09', 'name' => 'Kas & Rekening Operasional', 'ratio' => 'Current Ratio'],
            ['code' => 'PA10', 'name' => 'Biaya Pengiriman & Armada', 'ratio' => 'OPEX Logistik'],
            ['code' => 'PA11', 'name' => 'Aset Mesin & Bangunan', 'ratio' => 'ROA & TATO'],
            ['code' => 'PA12', 'name' => 'Beban Umum & Administrasi', 'ratio' => 'NPM & OPEX'],
        ];
    }

    /**
     * Helper menghasilkan Dampak Finansial & Operasional Otomatis Berdasarkan Pos & Status Capaian
     */
    public static function getAutoImpactSummary($posCodes, $status, $polarity)
    {
        if (empty($posCodes)) {
            return null;
        }

        $impacts = [
            'PA01' => [
                'pos' => 'Penjualan Botol',
                'ok' => 'Mendorong Gross Profit & pemenuhan utilisasi kapasitas mesin cetak',
                'gap' => 'Penurunan omset penjualan botol & risiko idle capacity mesin pabrik',
            ],
            'PA02' => [
                'pos' => 'HPP Bahan Baku Resin (PET/HDPE)',
                'ok' => 'Efisiensi HPP biji plastik dan optimasi yield produksi botol',
                'gap' => 'Pembengkakan HPP botol akibat pemborosan/scrap resin yang tinggi',
            ],
            'PA03' => [
                'pos' => 'Beban Listrik Pabrik',
                'ok' => 'Penghematan konsumsi daya pemanas heater & kompresor blow moulding',
                'gap' => 'Pemborosan tarif listrik kWh pabrik melebihi pagu anggaran operasional',
            ],
            'PA04' => [
                'pos' => 'Beban Tenaga Kerja Langsung',
                'ok' => 'Produktivitas operator optimal tanpa biaya lembur berlebih',
                'gap' => 'Risiko pembengkakan biaya lembur operator pabrik akibat downtime/rework',
            ],
            'PA05' => [
                'pos' => 'Maintenance Mesin & Moulding',
                'ok' => 'Kesiapan mesin cetak prima (OEE tinggi) & keawetan moulding terjaga',
                'gap' => 'Frekuensi breakdown mesin injeksi/blow meningkat dan biaya sparepart membengkak',
            ],
            'PA06' => [
                'pos' => 'Persediaan Botol & Resin',
                'ok' => 'Perputaran inventory (ITO) sehat dan akurasi stok fisik ERP presisi',
                'gap' => 'Penumpukan stok botol mati (slow-moving) atau risiko dead stock bahan baku',
            ],
            'PA07' => [
                'pos' => 'Piutang Usaha Pelanggan',
                'ok' => 'Penagihan piutang distributor lancar (DSO sehat) memperkuat kas masuk',
                'gap' => 'Penumpukan piutang macet/tertunda yang menekan likuiditas kas operasional',
            ],
            'PA08' => [
                'pos' => 'Utang Usaha Supplier',
                'ok' => 'Hubungan supplier biji plastik aman dengan jadwal pembayaran DPO disiplin',
                'gap' => 'Risiko penalti atau terhambatnya pengiriman bahan baku dari supplier',
            ],
            'PA09' => [
                'pos' => 'Kas & Rekening Operasional',
                'ok' => 'Cash runway pabrik sehat untuk kelancaran operasional harian',
                'gap' => 'Defisit arus kas tunai yang dapat mengganggu belanja operasional pabrik',
            ],
            'PA10' => [
                'pos' => 'Biaya Pengiriman & Armada',
                'ok' => 'Biaya logistik efisien dan pengiriman tepat waktu (OTIF) tercapai',
                'gap' => 'Biaya sewa armada ekstra meningkat dan risiko retur botol dari pelanggan',
            ],
            'PA11' => [
                'pos' => 'Aset Mesin & Bangunan',
                'ok' => 'Utilisasi aset mesin pabrik maksimal (TATO & ROA tinggi)',
                'gap' => 'Peralatan pabrik under-utilized atau aus sebelum umur ekonomisnya',
            ],
            'PA12' => [
                'pos' => 'Beban Umum & Administrasi',
                'ok' => 'Beban operasional umum (OPEX) terkendali di bawah pagu anggaran',
                'gap' => 'Overhead pabrik membengkak menekan Net Profit Margin (NPM)',
            ],
        ];

        $lines = [];
        $isOk = ($status === 'Tercapai');

        foreach ((array) $posCodes as $code) {
            if (isset($impacts[$code])) {
                $item = $impacts[$code];
                $desc = $isOk ? $item['ok'] : $item['gap'];
                $lines[] = [
                    'code' => $code,
                    'pos_name' => $item['pos'],
                    'desc' => $desc,
                    'is_ok' => $isOk,
                ];
            }
        }

        return $lines;
    }

    private function calculateAchievement($target, $actual, $polarity)
    {
        $t = (float) $target;
        $a = (float) $actual;

        if ($polarity === 'Turun') {
            if ($a <= $t) return 100.0;
            return $a > 0 ? round(($t / $a) * 100, 2) : 100.0;
        } else {
            return $t > 0 ? round(($a / $t) * 100, 2) : 100.0;
        }
    }

    private function determineStatus($ach)
    {
        if ($ach >= 100.0) return 'Tercapai';
        if ($ach >= 80.0) return 'Waspada';
        return 'Di Bawah Target';
    }

    public function openCreateModal()
    {
        $this->reset(['objId', 'kpi_code', 'kpi_name', 'target', 'actual', 'selected_pos_codes', 'selected_odoo_accounts', 'impact_note', 'unit']);
        $this->selected_pos_codes = [];
        $this->selected_odoo_accounts = [];
        $firstDept = \App\Models\Department::where('is_active', 1)->orderBy('sort_order')->value('code') ?: 'FAT';
        $this->dept_code = $this->selectedDept ?: $firstDept;
        $this->source_type = 'manual';
        $this->polarity = 'Naik';
        $this->unit = '';
        $this->target = '';
        $this->actual = '';
        $this->impact_note = '';
        $this->odoo_account_code = '';
        $this->odooAccountSearch = '';
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function selectOdooAccount($code)
    {
        if (!in_array($code, $this->selected_odoo_accounts)) {
            $this->selected_odoo_accounts[] = $code;
        }
    }

    public function toggleOdooAccount($code)
    {
        if (in_array($code, $this->selected_odoo_accounts)) {
            $this->selected_odoo_accounts = array_values(array_diff($this->selected_odoo_accounts, [$code]));
        } else {
            $this->selected_odoo_accounts[] = $code;
        }
    }

    public function removeOdooAccount($code)
    {
        $this->selected_odoo_accounts = array_values(array_diff($this->selected_odoo_accounts, [$code]));
    }

    public function clearOdooAccounts()
    {
        $this->selected_odoo_accounts = [];
        $this->odoo_account_code = '';
    }

    public function getOdooAccounts()
    {
        try {
            // Ambil dari seluruh Chart of Accounts (COA) Odoo ERP (Asset termasuk Piutang & Kas, Liabilitas, Ekuitas, Pendapatan, HPP, Beban)
            $coaList = DB::connection('mysql_finance')
                ->table('coa_mappings')
                ->where('is_active', 1)
                ->select('account_code as code', 'account_name as name', 'account_type as type')
                ->orderBy('account_code')
                ->get();

            if ($coaList->count() > 0) {
                return $coaList;
            }

            return DB::connection('mysql_finance')
                ->table('budget_categories')
                ->select('code', 'name', DB::raw("'Expense' as type"))
                ->orderBy('code')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
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
                        'ratio' => $this->determineRatioByAccount($code, $type, $bc->name),
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
                    'ratio' => $this->determineRatioByAccount($code, $c->type, $c->name),
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
                        'ratio' => $this->determineRatioByAccount($code, $type, $bc->name),
                        'dept_name' => $bc->dept_name,
                        'dept_code' => $bc->dept_code,
                    ]);
                }
            }

            // Urutkan: Pos Akun Anggaran (130) didahulukan, lalu berdasarkan kode akun
            return $list->sortByDesc('is_budget_pos')->values();
        } catch (\Exception $e) {
            // Fallback ke 12 pos inti jika koneksi error
            return collect($this->get12CorePos())->map(function($cp) {
                return [
                    'code' => $cp['code'],
                    'name' => $cp['name'],
                    'type' => 'Pos Finansial',
                    'is_budget_pos' => true,
                    'ratio' => $cp['ratio'],
                    'dept_name' => 'Operasional',
                    'dept_code' => 'ALL',
                ];
            });
        }
    }

    /**
     * Helper menentukan Rasio Keuangan / Parameter Kinerja BSC Berdasarkan Akun
     */
    private function determineRatioByAccount($code, $type, $name)
    {
        $prefix = substr($code, 0, 1);
        $nameLower = strtolower($name);

        if ($type === 'Revenue' || $prefix === '4') {
            return 'Sales Growth & Revenue';
        }
        if ($type === 'COGS' || $prefix === '5') {
            if (str_contains($nameLower, 'gaji') || str_contains($nameLower, 'tenaga kerja')) {
                return 'COGS & SDM Produksi';
            }
            if (str_contains($nameLower, 'penyusutan')) {
                return 'COGS & Utilisasi Mesin (TATO)';
            }
            return 'Gross Profit Margin (GPM) & COGS';
        }
        if ($type === 'Expense' || in_array($prefix, ['6', '8', '9'])) {
            if (str_contains($nameLower, 'listrik') || str_contains($nameLower, 'pln')) {
                return 'OPEX Energi & Utilitas Pabrik';
            }
            if (str_contains($nameLower, 'armada') || str_contains($nameLower, 'ekspedisi') || str_contains($nameLower, 'kirim')) {
                return 'OPEX Logistik & Distribusi';
            }
            if (str_contains($nameLower, 'pemeliharaan') || str_contains($nameLower, 'perawatan') || str_contains($nameLower, 'sparepart')) {
                return 'TATO & Maintenance Mould/Mesin';
            }
            if (str_contains($nameLower, 'gaji') || str_contains($nameLower, 'tunjangan') || str_contains($nameLower, 'lembur')) {
                return 'OPEX Payroll & Human Capital';
            }
            return 'Net Profit Margin (NPM) & OPEX';
        }
        if ($type === 'Asset' || $prefix === '1') {
            if (str_contains($nameLower, 'piutang') || str_starts_with($code, '112')) {
                return 'DSO & Piutang Pelanggan (AR)';
            }
            if (str_contains($nameLower, 'kas') || str_contains($nameLower, 'bank') || str_starts_with($code, '111')) {
                return 'Cash Runway & Quick Ratio';
            }
            if (str_contains($nameLower, 'persediaan') || str_starts_with($code, '113')) {
                return 'ITO & DIO Persediaan (Resin/Botol)';
            }
            if (str_starts_with($code, '12')) {
                return 'ROA & Utilisasi Aset Tetap (TATO)';
            }
            return 'Current Ratio & Likuiditas Aset';
        }
        if ($type === 'Liability' || $prefix === '2') {
            if (str_contains($nameLower, 'hutang usaha') || str_starts_with($code, '211')) {
                return 'DPO & Utang Usaha Supplier';
            }
            return 'Solvabilitas & Debt to Equity (DER)';
        }
        if ($type === 'Equity' || $prefix === '3') {
            return 'ROE & Return on Equity';
        }

        return 'Finansial & Operasional';
    }

    public function openEditModal($id)
    {
        $obj = DepartmentObjective::findOrFail($id);
        $this->objId = $obj->id;
        $this->dept_code = $obj->dept_code;
        $this->kpi_code = $obj->kpi_code;
        $this->kpi_name = $obj->kpi_name;
        $this->source_type = $obj->source_type ?: 'manual';
        $this->odooAccountSearch = '';

        // Parse odoo accounts (bisa koma atau satu akun)
        $rawOdoo = trim($obj->odoo_account_code ?: '');
        if ($rawOdoo && !str_contains($rawOdoo, 'PA')) {
            $this->selected_odoo_accounts = array_values(array_filter(array_map('trim', explode(',', $rawOdoo))));
        } else {
            $this->selected_odoo_accounts = [];
        }

        // Parse pos_anggaran
        $rawPos = trim($obj->pos_anggaran ?? '');
        if ($rawPos) {
            if (str_starts_with($rawPos, '[') && str_ends_with($rawPos, ']')) {
                $decoded = json_decode($rawPos, true);
                $this->selected_pos_codes = is_array($decoded) ? $decoded : [];
            } else {
                $this->selected_pos_codes = array_values(array_filter(array_map('trim', explode(',', $rawPos))));
            }
        } else {
            $this->selected_pos_codes = [];
        }

        $this->impact_note = $obj->impact_note ?: '';
        $this->unit = $obj->unit ?: '%';
        $this->polarity = $obj->polarity;
        $this->target = $obj->target;
        $this->actual = $obj->actual;
        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function saveObjective()
    {
        $this->validate();

        $ach = $this->calculateAchievement($this->target, $this->actual, $this->polarity);
        $status = $this->determineStatus($ach);

        // Gabungkan selected Odoo accounts menjadi comma-separated string
        $cleanOdoo = array_values(array_filter(array_unique((array) $this->selected_odoo_accounts)));
        $odooString = count($cleanOdoo) > 0 ? implode(',', $cleanOdoo) : null;

        DepartmentObjective::updateOrCreate(
            ['id' => $this->objId],
            [
                'period' => $this->selectedPeriod,
                'dept_code' => $this->dept_code,
                'kpi_code' => $this->kpi_code,
                'kpi_name' => $this->kpi_name,
                'polarity' => $this->polarity,
                'unit' => $this->unit,
                'target' => $this->target,
                'actual' => $this->actual,
                'achievement_pct' => $ach,
                'status' => $status,
                'source_type' => $this->source_type,
                'odoo_account_code' => $odooString,
                'pos_anggaran' => count($this->selected_pos_codes) > 0 ? implode(',', $this->selected_pos_codes) : null,
                'impact_note' => $this->impact_note ?: null,
            ]
        );

        $this->showModal = false;
        session()->flash('message', 'Sasaran mutu [' . $this->kpi_code . '] berhasil disimpan!');
    }

    public function deleteObjective($id)
    {
        $obj = DepartmentObjective::findOrFail($id);
        $code = $obj->kpi_code;
        $obj->delete();
        session()->flash('message', "Sasaran mutu [$code] berhasil dihapus!");
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $departments = $this->getOdooDepartments();
        $corePos = $this->get12CorePos();
        $posMap = collect($corePos)->keyBy('code');

        // Ambil akun Odoo dari database mysql_finance
        $odooAccounts = $this->getOdooAccounts();
        $odooAccountMap = $odooAccounts->keyBy('code');

        $filteredOdooAccounts = $odooAccounts;
        if (!empty(trim($this->odooAccountSearch))) {
            $kw = strtolower(trim($this->odooAccountSearch));
            $filteredOdooAccounts = $odooAccounts->filter(function ($item) use ($kw) {
                return str_contains(strtolower($item->code), $kw) || str_contains(strtolower($item->name), $kw);
            });
        }

        $query = DepartmentObjective::where('period', $this->selectedPeriod);

        if ($this->selectedDept) {
            $query->where('dept_code', $this->selectedDept);
        }

        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['Waspada', 'Di Bawah Target']);
            } else {
                $query->where('status', $this->selectedStatus);
            }
        }

        if ($this->search) {
            $s = strtolower(trim($this->search));
            $query->where(function($q) use ($s) {
                $q->where('kpi_code', 'like', "%$s%")
                  ->orWhere('kpi_name', 'like', "%$s%")
                  ->orWhere('dept_code', 'like', "%$s%");
            });
        }

        $allPeriodObjs = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        $filteredObjectives = $query->orderBy('dept_code')->orderBy('kpi_code')->get();

        // Statistik
        $totalKpi = $allPeriodObjs->count();
        $tercapai = $allPeriodObjs->where('status', 'Tercapai')->count();
        $waspada = $allPeriodObjs->where('status', 'Waspada')->count();
        $diBawah = $allPeriodObjs->where('status', 'Di Bawah Target')->count();

        // Ambil matriks peta pos akun tersimpan untuk integrasi Tab 2
        $key = 'peta_pos_matrix_12_' . $this->selectedPeriod;
        $matrixJson = DB::table('app_settings')->where('key', $key)->value('value');
        $matrix = $matrixJson ? json_decode($matrixJson, true) : [];

        // Ambil 130 Pos Anggaran Odoo ditambah seluruh Chart of Accounts (COA) Odoo ERP untuk Tab 2
        $allPosAccounts = $this->getAllOdooAccountsAndCOA();

        // Filter untuk Tab 2 (Kategori Akun & Keyword Pencarian Pos)
        $filteredPosAccounts = $allPosAccounts;
        if ($this->posAccountTypeFilter) {
            if ($this->posAccountTypeFilter === 'BUDGET_130') {
                $filteredPosAccounts = $filteredPosAccounts->where('is_budget_pos', true);
            } else {
                $filteredPosAccounts = $filteredPosAccounts->where('type', $this->posAccountTypeFilter);
            }
        }
        if (!empty(trim($this->posSearch))) {
            $pKw = strtolower(trim($this->posSearch));
            $filteredPosAccounts = $filteredPosAccounts->filter(function($item) use ($pKw) {
                return str_contains(strtolower($item['code']), $pKw)
                    || str_contains(strtolower($item['name']), $pKw)
                    || str_contains(strtolower($item['dept_name'] ?? ''), $pKw)
                    || str_contains(strtolower($item['ratio'] ?? ''), $pKw);
            });
        }

        // Hitung statistik pos akun untuk badge header
        $totalBudgetPosCount = $allPosAccounts->where('is_budget_pos', true)->count();
        $totalCoaCount = $allPosAccounts->count();

        return view('livewire.department-objectives', [
            'periods' => $periods,
            'departments' => $departments,
            'objectives' => $filteredObjectives,
            'allObjectives' => $allPeriodObjs,
            'corePos' => $corePos,
            'posMap' => $posMap,
            'odooAccounts' => $odooAccounts,
            'odooAccountMap' => $odooAccountMap,
            'filteredOdooAccounts' => $filteredOdooAccounts,
            'posAccountsList' => $filteredPosAccounts,
            'allPosAccounts' => $allPosAccounts,
            'totalBudgetPosCount' => $totalBudgetPosCount,
            'totalCoaCount' => $totalCoaCount,
            'matrix' => $matrix,
            'selectedPeriod' => $this->selectedPeriod,
            'selectedDept' => $this->selectedDept,
            'selectedStatus' => $this->selectedStatus,
            'search' => $this->search,
            'posAccountTypeFilter' => $this->posAccountTypeFilter,
            'posSearch' => $this->posSearch,
            'activeTab' => $this->activeTab,
            'showModal' => $this->showModal,
            'isEditMode' => $this->isEditMode,
            'selected_pos_codes' => $this->selected_pos_codes,
            'selected_odoo_accounts' => $this->selected_odoo_accounts,
            'odoo_account_code' => $this->odoo_account_code,
            'odooAccountSearch' => $this->odooAccountSearch,
            'totalKpi' => $totalKpi,
            'tercapai' => $tercapai,
            'waspada' => $waspada,
            'diBawah' => $diBawah,
        ])->layout('layouts.app', ['title' => 'Objective Departemen (Level 3)']);
    }
}
