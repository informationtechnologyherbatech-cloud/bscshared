<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\DepartmentObjective;
use App\Models\Department;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class UjiIndikatorDepartemen extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'dept')]
    public $selectedDept = '';

    #[Url(as: 'search')]
    public $searchKpi = '';

    #[Url(as: 'sort')]
    public $sortBy = 'default'; // 'default', 'gap_desc', 'gap_asc', 'kpi_code'

    // Simulasi penyesuaian nilai realisasi KPI (key = string kpi_id, value = simulated_actual)
    public $simulatedActuals = [];

    // Filter status gap
    public $filterGapOnly = false;

    // Modal konfirmasi simpan ke database resmi
    public $showApplyModal = false;

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        session(['active_period' => $this->selectedPeriod]);
        $this->initSimulatedValues();
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
        $this->initSimulatedValues();
    }

    public function updatedSelectedDept()
    {
        // Tetap pertahankan nilai simulasi saat berpindah departemen
    }

    public function initSimulatedValues()
    {
        $objs = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        $this->simulatedActuals = [];
        foreach ($objs as $o) {
            $this->simulatedActuals[(string) $o->id] = (float) $o->actual;
        }
    }

    public function updateSimulatedActual($kpiId, $value)
    {
        $cleanVal = str_replace(',', '.', trim($value));
        $this->simulatedActuals[(string) $kpiId] = is_numeric($cleanVal) ? (float) $cleanVal : 0;
    }

    /**
     * Step adjustment nilai simulasi (+5% atau -5%, dst)
     */
    public function adjustSimulatedPercent($kpiId, $percent)
    {
        $key = (string) $kpiId;
        $curr = isset($this->simulatedActuals[$key]) ? (float) $this->simulatedActuals[$key] : 0;
        $multiplier = 1 + ($percent / 100);
        $newVal = round($curr * $multiplier, 2);
        if ($newVal < 0) $newVal = 0;
        $this->simulatedActuals[$key] = $newVal;
    }

    public function resetSimulation()
    {
        $this->initSimulatedValues();
        session()->flash('message', 'Nilai uji simulasi indikator berhasil di-reset kembali ke data aktual asli!');
    }

    /**
     * Terapkan skenario cepat
     */
    public function applyStressTest($scenario)
    {
        $objs = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        foreach ($objs as $o) {
            $t = (float) $o->target;
            $a = (float) $o->actual;

            if ($scenario === 'all_target') {
                // Semua KPI diasumsikan mencapai target 100%
                $this->simulatedActuals[(string) $o->id] = $t;
            } elseif ($scenario === 'stress_15') {
                // Skenario krisis: performa drop 15%
                if ($o->polarity === 'Naik') {
                    $this->simulatedActuals[(string) $o->id] = round($a * 0.85, 2);
                } else {
                    $this->simulatedActuals[(string) $o->id] = round($a * 1.15, 2);
                }
            } elseif ($scenario === 'optimis_10') {
                // Skenario optimis: performa meningkat 10%
                if ($o->polarity === 'Naik') {
                    $this->simulatedActuals[(string) $o->id] = round($a * 1.10, 2);
                } else {
                    $this->simulatedActuals[(string) $o->id] = round($a * 0.90, 2);
                }
            }
        }
        session()->flash('message', "Skenario [$scenario] berhasil diterapkan ke seluruh indikator uji!");
    }

    /**
     * Terapkan nilai hasil simulasi What-If langsung ke database Sasaran Mutu resmi
     */
    public function applySimulationToLive()
    {
        $objs = DepartmentObjective::where('period', $this->selectedPeriod)->get();
        $updatedCount = 0;

        foreach ($objs as $obj) {
            $key = (string) $obj->id;
            if (isset($this->simulatedActuals[$key])) {
                $simVal = (float) $this->simulatedActuals[$key];
                $simAch = $this->calculateAchievement($obj->target, $simVal, $obj->polarity);
                $simStatus = $this->determineStatus($simAch);

                $obj->update([
                    'actual' => $simVal,
                    'achievement_pct' => $simAch,
                    'status' => $simStatus,
                ]);
                $updatedCount++;
            }
        }

        $this->showApplyModal = false;
        session()->flash('message', "Sukses! $updatedCount nilai hasil simulasi telah resmi diterapkan ke Sasaran Mutu periode {$this->selectedPeriod}.");
    }

    /**
     * Ambil master departemen resmi
     */
    public function getOdooDepartments()
    {
        return Department::where('is_active', 1)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Ambil seluruh akun Odoo & 130 Pos Anggaran Odoo dari database mysql_finance
     */
    public function getAllOdooAccountsAndCOA()
    {
        try {
            $bcRows = DB::connection('mysql_finance')
                ->table('budget_categories')
                ->leftJoin('departments', 'budget_categories.department_id', '=', 'departments.id')
                ->select(
                    'budget_categories.code as code',
                    'budget_categories.name as name',
                    'departments.name as dept_name',
                    'departments.code as dept_code'
                )
                ->get()
                ->keyBy('code');

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
                ->get();

            $accountMap = [];
            foreach ($coaRows as $c) {
                $isBudget = isset($bcRows[$c->code]);
                $accountMap[$c->code] = (object) [
                    'code' => $c->code,
                    'name' => $c->name,
                    'type' => $c->type ?? 'COA',
                    'is_budget_pos' => $isBudget,
                    'dept_name' => $c->dept_name ?: ($isBudget ? $bcRows[$c->code]->dept_name : null),
                    'dept_code' => $c->dept_code ?: ($isBudget ? $bcRows[$c->code]->dept_code : null),
                ];
            }

            foreach ($bcRows as $code => $bc) {
                if (!isset($accountMap[$code])) {
                    $prefix = substr($code, 0, 1);
                    $type = ($prefix === '5') ? 'COGS' : 'Expense';
                    $accountMap[$code] = (object) [
                        'code' => $code,
                        'name' => $bc->name,
                        'type' => $type,
                        'is_budget_pos' => true,
                        'dept_name' => $bc->dept_name,
                        'dept_code' => $bc->dept_code,
                    ];
                }
            }

            return $accountMap;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function get12CorePos()
    {
        return [
            ['code' => 'PA01', 'name' => 'Penjualan Botol', 'ratio' => 'Sales & Growth', 'driver' => 'Revenue'],
            ['code' => 'PA02', 'name' => 'Bahan Baku Resin (PET/HDPE)', 'ratio' => 'GPM & COGS', 'driver' => 'HPP Bahan Baku'],
            ['code' => 'PA03', 'name' => 'Listrik & Energi Mesin', 'ratio' => 'GPM & Utilitas', 'driver' => 'Beban Utilitas Listrik'],
            ['code' => 'PA04', 'name' => 'Tenaga Kerja Langsung', 'ratio' => 'COGS & SDM', 'driver' => 'Beban Tenaga Kerja Pabrik'],
            ['code' => 'PA05', 'name' => 'Maintenance & Moulding', 'ratio' => 'TATO & Mesin', 'driver' => 'Downtime & Sparepart'],
            ['code' => 'PA06', 'name' => 'Persediaan Botol & Resin', 'ratio' => 'ITO & DIO', 'driver' => 'Perputaran Stok Botol'],
            ['code' => 'PA07', 'name' => 'Piutang Usaha Pelanggan', 'ratio' => 'DSO & Kas', 'driver' => 'Kecepatan Penagihan Piutang'],
            ['code' => 'PA08', 'name' => 'Utang Usaha Supplier', 'ratio' => 'DPO & Pembelian', 'driver' => 'Kelancaran Pengadaan Bahan'],
            ['code' => 'PA09', 'name' => 'Kas & Rekening Operasional', 'ratio' => 'Current Ratio', 'driver' => 'Ketahanan Kas Tunai'],
            ['code' => 'PA10', 'name' => 'Biaya Pengiriman & Armada', 'ratio' => 'OPEX Logistik', 'driver' => 'Kelancaran Pengiriman (OTIF)'],
            ['code' => 'PA11', 'name' => 'Aset Mesin & Bangunan', 'ratio' => 'ROA & TATO', 'driver' => 'Utilisasi Aset Mesin'],
            ['code' => 'PA12', 'name' => 'Beban Umum & Administrasi', 'ratio' => 'NPM & OPEX', 'driver' => 'Efisiensi Overhead Pabrik'],
        ];
    }

    private function calculateAchievement($target, $actual, $polarity)
    {
        $t = (float) $target;
        $a = (float) $actual;

        if ($polarity === 'Turun') {
            if ($a <= $t) return 100.0;
            return $a > 0 ? round(($t / $a) * 100, 2) : 100.0;
        } else {
            if ($a >= $t) return 100.0;
            return $t > 0 ? round(($a / $t) * 100, 2) : 100.0;
        }
    }

    private function determineStatus($ach)
    {
        if ($ach >= 100.0) return 'Tercapai';
        if ($ach >= 80.0) return 'Waspada';
        return 'Di Bawah Target';
    }

    public function render()
    {
        $periods = Period::pluck('period')->toArray();
        $departments = $this->getOdooDepartments();
        $corePos = $this->get12CorePos();
        $posMap = collect($corePos)->keyBy('code');
        $allAccountsMap = $this->getAllOdooAccountsAndCOA();

        $query = DepartmentObjective::where('period', $this->selectedPeriod);
        if ($this->selectedDept) {
            $query->where('dept_code', $this->selectedDept);
        }

        if (!empty($this->searchKpi)) {
            $q = trim($this->searchKpi);
            $query->where(function($sub) use ($q) {
                $sub->where('kpi_code', 'like', "%$q%")
                    ->orWhere('kpi_name', 'like', "%$q%")
                    ->orWhere('dept_code', 'like', "%$q%")
                    ->orWhere('odoo_account_code', 'like', "%$q%")
                    ->orWhere('pos_anggaran', 'like', "%$q%");
            });
        }

        $allObjectives = $query->orderBy('dept_code')->orderBy('kpi_code')->get();

        // Olah data simulasi per KPI
        $kpiSimulations = [];
        $posSimulations = [];
        foreach ($corePos as $cp) {
            $posSimulations[$cp['code']] = [
                'pos' => $cp,
                'kpis' => [],
                'tercapai_count' => 0,
                'waspada_count' => 0,
                'dibawah_count' => 0,
                'overall_health' => 'Terkendali',
            ];
        }

        $totalSimTercapai = 0;
        $totalSimWaspada = 0;
        $totalSimDiBawah = 0;

        foreach ($allObjectives as $obj) {
            $keyStr = (string) $obj->id;
            if (array_key_exists($keyStr, $this->simulatedActuals)) {
                $simVal = (float) $this->simulatedActuals[$keyStr];
            } else {
                $simVal = (float) $obj->actual;
            }

            $simAch = $this->calculateAchievement($obj->target, $simVal, $obj->polarity);
            $simStatus = $this->determineStatus($simAch);

            if ($simStatus === 'Tercapai') $totalSimTercapai++;
            elseif ($simStatus === 'Waspada') $totalSimWaspada++;
            else $totalSimDiBawah++;

            // 1. Parse Pos Anggaran (130 Pos / PA)
            $rawPos = trim($obj->pos_anggaran ?? '');
            if (!$rawPos && trim($obj->odoo_account_code ?? '')) {
                if (str_contains($obj->odoo_account_code, 'PA')) {
                    $rawPos = trim($obj->odoo_account_code);
                }
            }
            $budgetCodes = [];
            if ($rawPos) {
                if (str_starts_with($rawPos, '[') && str_ends_with($rawPos, ']')) {
                    $decoded = json_decode($rawPos, true);
                    $budgetCodes = is_array($decoded) ? $decoded : [];
                } else {
                    $budgetCodes = array_values(array_filter(array_map('trim', explode(',', $rawPos))));
                }
            }

            // 2. Parse Akun Odoo COA
            $rawOdoo = trim($obj->odoo_account_code ?? '');
            $odooCodes = [];
            if ($rawOdoo && !str_contains($rawOdoo, 'PA')) {
                $odooCodes = array_values(array_filter(array_map('trim', explode(',', $rawOdoo))));
            }

            // 3. Hitung dampak otomatis dinamis berdasarkan nilai uji terkini
            $autoNote = DepartmentObjectives::getAutoImpactNote(
                $obj->kpi_name,
                $obj->kpi_code,
                $obj->dept_code,
                $simStatus,
                $obj->polarity,
                $budgetCodes,
                $obj->odoo_account_code
            );

            $autoImpacts = DepartmentObjectives::getAutoImpactSummary($budgetCodes, $simStatus, $obj->polarity);

            $deptName = DepartmentObjectives::getDepartmentNameByCode($obj->dept_code, $departments);

            $kpiData = [
                'id' => $obj->id,
                'dept_code' => $obj->dept_code,
                'dept_name' => $deptName,
                'kpi_code' => $obj->kpi_code,
                'kpi_name' => $obj->kpi_name,
                'polarity' => $obj->polarity,
                'unit' => $obj->unit,
                'target' => (float) $obj->target,
                'actual' => (float) $obj->actual,
                'base_ach' => (float) $obj->achievement_pct,
                'base_status' => $obj->status,
                'sim_actual' => $simVal,
                'sim_ach' => $simAch,
                'sim_status' => $simStatus,
                'diff_ach' => round($simAch - (float) $obj->achievement_pct, 1),
                'diff_val' => round($simVal - (float) $obj->actual, 2),
                'budget_codes' => $budgetCodes,
                'odoo_codes' => $odooCodes,
                'impact_note' => $obj->impact_note,
                'auto_note' => $autoNote,
                'auto_impacts' => $autoImpacts,
            ];

            if (!$this->filterGapOnly || $simStatus !== 'Tercapai') {
                $kpiSimulations[] = $kpiData;
            }

            // Petakan ke 12 pos
            foreach ($budgetCodes as $c) {
                if (isset($posSimulations[$c])) {
                    $posSimulations[$c]['kpis'][] = $kpiData;
                    if ($simStatus === 'Tercapai') $posSimulations[$c]['tercapai_count']++;
                    elseif ($simStatus === 'Waspada') $posSimulations[$c]['waspada_count']++;
                    else $posSimulations[$c]['dibawah_count']++;
                }
            }
        }

        // Sorting
        if ($this->sortBy === 'gap_desc') {
            // Urutkan capaian terkecil (GAP paling besar) di posisi teratas
            usort($kpiSimulations, fn($a, $b) => $a['sim_ach'] <=> $b['sim_ach']);
        } elseif ($this->sortBy === 'gap_asc') {
            // Capaian tertinggi di atas
            usort($kpiSimulations, fn($a, $b) => $b['sim_ach'] <=> $a['sim_ach']);
        } elseif ($this->sortBy === 'kpi_code') {
            usort($kpiSimulations, fn($a, $b) => strcmp($a['kpi_code'], $b['kpi_code']));
        }

        // Tentukan kesehatan driver pos
        foreach ($posSimulations as $c => &$pData) {
            if ($pData['dibawah_count'] > 0) {
                $pData['overall_health'] = 'Kritis (Gap Tinggi)';
            } elseif ($pData['waspada_count'] > 0) {
                $pData['overall_health'] = 'Waspada';
            } elseif (count($pData['kpis']) > 0) {
                $pData['overall_health'] = 'Sehat & Prima';
            } else {
                $pData['overall_health'] = 'Netral';
            }
        }
        unset($pData);

        return view('livewire.uji-indikator-departemen', [
            'periods' => $periods,
            'departments' => $departments,
            'corePos' => $corePos,
            'posMap' => $posMap,
            'allAccountsMap' => $allAccountsMap,
            'kpiSimulations' => $kpiSimulations,
            'posSimulations' => $posSimulations,
            'totalKpi' => count($allObjectives),
            'totalSimTercapai' => $totalSimTercapai,
            'totalSimWaspada' => $totalSimWaspada,
            'totalSimDiBawah' => $totalSimDiBawah,
        ])->layout('layouts.app', ['title' => 'Uji Indikator Departemen (Level 3)']);
    }
}
