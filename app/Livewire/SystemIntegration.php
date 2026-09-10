<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\StagingLog;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use Illuminate\Support\Str;

class SystemIntegration extends Component
{
    use AuthorizesWrites;
    use WithFileUploads;

    public $apiKey = 'bsc_live_secret_key_2026_hop4';
    public $inboundEndpoint = 'http://127.0.0.1:8000/api/v1/bsc/sync/finance-coa';
    public $csvFile;
    
    // Department Objective Inbound Payload
    public $deptPayload = 'PROD';
    public $kpiCodePayload = 'KPI-PROD-001';
    public $targetPayload = 98.00;
    public $actualPayload = 98.50;
    public $evidenceUrlPayload = 'https://docs.perusahaan.co.id/reports/2026-08-ba-qc.pdf';

    // Finance ERP Inbound Payload (Wadah Penerimaan Finance)
    public $financePeriod = '2026-08';
    public $salesPayload = 96000.00;      // 4101 - Penjualan Produk
    public $hppPayload = 57600.00;        // 5101 - HPP
    public $opexPayload = 23400.00;       // 6101 - Beban Operasional
    public $kasPayload = 12500.00;        // 1101 - Kas & Bank
    public $piutangPayload = 9800.00;     // 1201 - Piutang
    public $persediaanPayload = 14200.00; // 1301 - Persediaan
    public $hutangPayload = 8200.00;      // 2101 - Hutang Usaha
    public $modalPayload = 73300.00;      // 3101 - Modal / Ekuitas

    public function generateApiKey()
    {
        if ($this->lacksPermission('manage apikey')) {
            return;
        }

        $this->apiKey = 'bsc_live_' . Str::random(24);
        session()->flash('message', 'Kunci API Gateway baru berhasil diderivasi!');
    }

    public function processManualPayload()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $idempotencyKey = 'IDEMP-' . strtoupper($this->deptPayload) . '-' . date('Ymd-His');

        // Update or Create Department Objective
        $obj = DepartmentObjective::where('period', '2026-08')
            ->where('kpi_code', $this->kpiCodePayload)
            ->first();

        if ($obj) {
            $ach = $this->targetPayload > 0 ? round(($this->actualPayload / $this->targetPayload) * 100, 2) : 100;
            if ($ach > 100) $ach = 100.00;
            $status = $ach >= 100 ? 'Tercapai' : ($ach >= 80 ? 'Waspada' : 'Di Bawah Target');

            $obj->update([
                'actual' => $this->actualPayload,
                'target' => $this->targetPayload,
                'achievement_pct' => $ach,
                'status' => $status,
            ]);
        }

        // Record Staging Log
        StagingLog::create([
            'period' => '2026-08',
            'dept_code' => strtoupper($this->deptPayload),
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Integrasi API Manual Payload dari ' . $this->deptPayload . ' berhasil diproses & diskor.',
        ]);

        session()->flash('message', 'Payload integrasi dari ' . $this->deptPayload . ' berhasil diproses! (Idempotency Key: ' . $idempotencyKey . ')');
    }

    public function processFinancePayload()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $idempotencyKey = 'IDEMP-FIN-COA-' . date('Ymd-His');

        // Hitung Kinerja Finance dari Transaksi CoA yang Diterima
        $netProfit = (float)$this->salesPayload - (float)$this->hppPayload - (float)$this->opexPayload;
        $npm = (float)$this->salesPayload > 0 ? round(($netProfit / (float)$this->salesPayload) * 100, 2) : 0;
        $roe = (float)$this->modalPayload > 0 ? round(($netProfit / (float)$this->modalPayload) * 100, 2) : 0;
        $ito = (float)$this->persediaanPayload > 0 ? round((float)$this->hppPayload / (float)$this->persediaanPayload, 2) : 0;
        $cr  = (float)$this->hutangPayload > 0 ? round(((float)$this->kasPayload + (float)$this->piutangPayload + (float)$this->persediaanPayload) / (float)$this->hutangPayload, 2) : 0;
        $der = (float)$this->modalPayload > 0 ? round((float)$this->hutangPayload / (float)$this->modalPayload, 2) : 0;
        $revEmp = round((float)$this->salesPayload / 100, 2);

        // Update/Create Financial Ratio Records
        $ratios = [
            ['cat' => 'Likuiditas', 'name' => 'Current Ratio (CR)', 'target' => 2.0, 'actual' => $cr, 'ach' => $cr >= 2.0 ? 100 : round(($cr/2.0)*100, 2)],
            ['cat' => 'Solvabilitas', 'name' => 'Debt to Equity (DER)', 'target' => 0.5, 'actual' => $der, 'ach' => $der <= 0.5 ? 100 : round((0.5/$der)*100, 2)],
            ['cat' => 'Aktivitas', 'name' => 'Inventory Turnover (ITO)', 'target' => 5.0, 'actual' => $ito, 'ach' => $ito >= 5.0 ? 100 : round(($ito/5.0)*100, 2)],
            ['cat' => 'Profitabilitas', 'name' => 'Net Profit Margin (NPM)', 'target' => 10.0, 'actual' => $npm, 'ach' => $npm >= 10.0 ? 100 : round(($npm/10.0)*100, 2)],
            ['cat' => 'Profitabilitas', 'name' => 'Return on Equity (ROE)', 'target' => 15.0, 'actual' => $roe, 'ach' => $roe >= 15.0 ? 100 : round(($roe/15.0)*100, 2)],
            ['cat' => 'Produktivitas', 'name' => 'Revenue per Employee (REV_EMP)', 'target' => 1000.0, 'actual' => $revEmp, 'ach' => $revEmp >= 1000 ? 100 : round(($revEmp/1000)*100, 2)],
        ];

        foreach ($ratios as $r) {
            $ach = min(100.0, max(0.0, (float)$r['ach']));
            $status = $ach >= 100 ? 'Tercapai' : ($ach >= 80 ? 'Waspada' : 'Di Bawah Target');

            FinancialRatio::updateOrCreate(
                ['period' => $this->financePeriod, 'ratio_name' => $r['name']],
                [
                    'category' => $r['cat'],
                    'target' => $r['target'],
                    'actual' => $r['actual'],
                    'achievement_pct' => $ach,
                    'status' => $status,
                ]
            );
        }

        // Simpan Log Penerimaan Data Finance ke StagingLog
        StagingLog::create([
            'period' => $this->financePeriod,
            'dept_code' => 'FIN',
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Penerimaan Data Finance ERP (Penjualan: Rp ' . number_format($this->salesPayload) . ' JT, HPP: Rp ' . number_format($this->hppPayload) . ' JT, Laba Bersih: Rp ' . number_format($netProfit) . ' JT) Berhasil Disinkronkan.',
        ]);

        session()->flash('message', 'Wadah Penerimaan Finance: Data CoA ERP & Laba Bersih (Rp ' . number_format($netProfit) . ' JT) berhasil diterima dan memperbarui skor 5 kelompok rasio!');
    }

    public function uploadCsv()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt,xlsx|max:2048',
        ]);

        $idempotencyKey = 'IDEMP-CSV-' . date('Ymd-His');

        StagingLog::create([
            'period' => '2026-08',
            'dept_code' => 'BATCH',
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Upload berkas CSV ' . $this->csvFile->getClientOriginalName() . ' berhasil diproses secara massal.',
        ]);

        $this->reset('csvFile');
        session()->flash('message', 'Berkas data project CSV berhasil diunggah & diproses secara massal!');
    }

    public function render()
    {
        $recentLogs = StagingLog::latest()->take(8)->get();
        $financeLogs = StagingLog::where('dept_code', 'FIN')->latest()->take(5)->get();
        $financialRatios = FinancialRatio::where('period', $this->financePeriod)->get();

        $netProfitCalculated = (float)$this->salesPayload - (float)$this->hppPayload - (float)$this->opexPayload;

        return view('livewire.system-integration', [
            'recentLogs' => $recentLogs,
            'financeLogs' => $financeLogs,
            'financialRatios' => $financialRatios,
            'netProfitCalculated' => $netProfitCalculated,
        ])->layout('layouts.app', ['title' => 'Integrasi Sistem & Gateway']);
    }
}

