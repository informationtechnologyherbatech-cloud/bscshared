<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\DepartmentObjective;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

class UjiIndikatorDepartemen extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'dept')]
    public $selectedDept = '';

    // Simulasi penyesuaian nilai realisasi KPI (key = string kpi_id, value = simulated_actual)
    public $simulatedActuals = [];

    // Filter status gap
    public $filterGapOnly = false;

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

    public function resetSimulation()
    {
        $this->initSimulatedValues();
        session()->flash('message', 'Nilai uji simulasi indikator berhasil di-reset kembali ke data aktual!');
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
                $this->simulatedActuals[$o->id] = $t;
            } elseif ($scenario === 'stress_15') {
                // Skenario krisis: performa drop 15%
                if ($o->polarity === 'Naik') {
                    $this->simulatedActuals[$o->id] = round($a * 0.85, 2);
                } else {
                    $this->simulatedActuals[$o->id] = round($a * 1.15, 2);
                }
            } elseif ($scenario === 'optimis_10') {
                // Skenario optimis: performa meningkat 10%
                if ($o->polarity === 'Naik') {
                    $this->simulatedActuals[$o->id] = round($a * 1.10, 2);
                } else {
                    $this->simulatedActuals[$o->id] = round($a * 0.90, 2);
                }
            }
        }
        session()->flash('message', "Skenario [$scenario] berhasil diterapkan ke seluruh indikator uji!");
    }

    public function getOdooDepartments()
    {
        try {
            return DB::connection('mysql_finance')
                ->table('departments')
                ->select('id', 'name', 'code')
                ->orderBy('id', 'asc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return DB::table('departments')->where('is_active', 1)->orderBy('sort_order')->get()->toArray();
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
            // Polaritas Turun: jika realisasi <= target maka tetap 100.0% (Tercapai)
            if ($a <= $t) return 100.0;
            return $a > 0 ? round(($t / $a) * 100, 2) : 100.0;
        } else {
            // Polaritas Naik: jika realisasi >= target maka tetap 100.0% (Tercapai)
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

        $query = DepartmentObjective::where('period', $this->selectedPeriod);
        if ($this->selectedDept) {
            $query->where('dept_code', $this->selectedDept);
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
            } elseif (array_key_exists($obj->id, $this->simulatedActuals)) {
                $simVal = (float) $this->simulatedActuals[$obj->id];
            } else {
                $simVal = (float) $obj->actual;
            }

            $simAch = $this->calculateAchievement($obj->target, $simVal, $obj->polarity);
            $simStatus = $this->determineStatus($simAch);

            if ($simStatus === 'Tercapai') $totalSimTercapai++;
            elseif ($simStatus === 'Waspada') $totalSimWaspada++;
            else $totalSimDiBawah++;

            // Parse pos codes
            $rawPos = trim($obj->odoo_account_code ?? '');
            $codes = [];
            if ($rawPos) {
                if (str_starts_with($rawPos, '[') && str_ends_with($rawPos, ']')) {
                    $decoded = json_decode($rawPos, true);
                    $codes = is_array($decoded) ? $decoded : [];
                } else {
                    $codes = array_values(array_filter(array_map('trim', explode(',', $rawPos))));
                }
            }

            $kpiData = [
                'id' => $obj->id,
                'dept_code' => $obj->dept_code,
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
                'diff_ach' => round($simAch - $obj->achievement_pct, 1),
                'diff_val' => round($simVal - (float) $obj->actual, 2),
                'pos_codes' => $codes,
                'impact_note' => $obj->impact_note,
            ];

            if (!$this->filterGapOnly || $simStatus !== 'Tercapai') {
                $kpiSimulations[] = $kpiData;
            }

            // Petakan ke 12 pos
            foreach ($codes as $c) {
                if (isset($posSimulations[$c])) {
                    $posSimulations[$c]['kpis'][] = $kpiData;
                    if ($simStatus === 'Tercapai') $posSimulations[$c]['tercapai_count']++;
                    elseif ($simStatus === 'Waspada') $posSimulations[$c]['waspada_count']++;
                    else $posSimulations[$c]['dibawah_count']++;
                }
            }
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
            'kpiSimulations' => $kpiSimulations,
            'posSimulations' => $posSimulations,
            'totalKpi' => count($allObjectives),
            'totalSimTercapai' => $totalSimTercapai,
            'totalSimWaspada' => $totalSimWaspada,
            'totalSimDiBawah' => $totalSimDiBawah,
        ])->layout('layouts.app', ['title' => 'Uji Indikator Departemen (Level 3)']);
    }
}
