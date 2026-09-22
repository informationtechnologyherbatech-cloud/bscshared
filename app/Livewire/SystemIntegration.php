<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\AccountBalance;
use App\Models\DepartmentObjective;
use App\Models\FinancialRatio;
use App\Models\Period;
use App\Models\StagingLog;
use App\Models\WorkUnit;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SystemIntegration extends Component
{
    use AuthorizesWrites;
    use WithFileUploads;

    /**
     * Pemetaan akun CoA payload → pos akun (satuan payload: juta rupiah).
     * Aliran = nilai YTD; neraca = saldo akhir periode.
     */
    private const COA_KE_POS = [
        'salesPayload' => 'PA01',      // 4101 Penjualan
        'hppPayload' => 'PA02',        // 5101 HPP
        'opexPayload' => 'PA03',       // 6101 Beban operasional
        'persediaanPayload' => 'PA05', // 1301 Persediaan
        'piutangPayload' => 'PA06',    // 1201 Piutang usaha
        'hutangPayload' => 'PA07',     // 2101 Hutang usaha
        'kasPayload' => 'PA08',        // 1101 Kas & bank
        'modalPayload' => 'PA13',      // 3101 Modal / ekuitas
    ];

    public $apiKey = 'bsc_live_secret_key_2026_hop4';
    public $inboundEndpoint = '';
    public $csvFile;

    // Department Objective Inbound Payload
    public $deptPayload = '';
    public $kpiCodePayload = '';
    public $targetPayload = 0;
    public $actualPayload = 0;
    public $evidenceUrlPayload = '';

    // Finance ERP Inbound Payload (Wadah Penerimaan Finance)
    public $financePeriod = '';
    public $salesPayload = 96000.00;      // 4101 - Penjualan Produk
    public $hppPayload = 57600.00;        // 5101 - HPP
    public $opexPayload = 23400.00;       // 6101 - Beban Operasional
    public $kasPayload = 12500.00;        // 1101 - Kas & Bank
    public $piutangPayload = 9800.00;     // 1201 - Piutang
    public $persediaanPayload = 14200.00; // 1301 - Persediaan
    public $hutangPayload = 8200.00;      // 2101 - Hutang Usaha
    public $modalPayload = 73300.00;      // 3101 - Modal / Ekuitas

    public function mount()
    {
        // Periode, departemen, dan KPI bawaan dari data entitas aktif — sebelumnya
        // dipatok 2026-08, PROD, dan KPI-PROD-001 milik Herbatech.
        $this->financePeriod = Period::currentPeriod();
        $this->inboundEndpoint = url('/api/v1/bsc/sync/finance-coa');
        $this->deptPayload = (string) (DepartmentObjective::where('period', $this->financePeriod)->orderBy('dept_code')->value('dept_code')
            ?? WorkUnit::active()->value('code') ?? '');
        $this->updatedDeptPayload();
    }

    /** KPI pertama departemen itu pada periode berjalan. */
    public function updatedDeptPayload()
    {
        $obj = DepartmentObjective::where('period', Period::currentPeriod())
            ->where('dept_code', $this->deptPayload)->orderBy('kpi_code')->first();

        $this->kpiCodePayload = (string) ($obj?->kpi_code ?? '');
        $this->targetPayload = $obj ? (float) $obj->target : 0;
        $this->actualPayload = $obj ? (float) $obj->actual : 0;
    }

    public function updatedKpiCodePayload()
    {
        $obj = DepartmentObjective::where('period', Period::currentPeriod())->where('kpi_code', $this->kpiCodePayload)->first();

        $this->targetPayload = $obj ? (float) $obj->target : 0;
        $this->actualPayload = $obj ? (float) $obj->actual : 0;
    }

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

        $periode = Period::currentPeriod();
        $this->validate([
            'deptPayload' => ['required', Rule::in(WorkUnit::pluck('code')->all())],
            'kpiCodePayload' => ['required', 'string', 'max:50'],
            'targetPayload' => ['required', 'numeric'],
            'actualPayload' => ['required', 'numeric'],
            'evidenceUrlPayload' => ['nullable', 'url:http,https', 'max:500'],
        ], [], ['deptPayload' => 'departemen', 'kpiCodePayload' => 'kode KPI', 'actualPayload' => 'realisasi']);

        $obj = DepartmentObjective::where('period', $periode)
            ->where('dept_code', $this->deptPayload)
            ->where('kpi_code', $this->kpiCodePayload)
            ->first();

        if (! $obj) {
            $this->addError('kpiCodePayload', 'KPI ' . $this->kpiCodePayload . ' tidak ada di departemen ' . $this->deptPayload . ' pada periode ' . $periode . '.');

            return;
        }

        if (Period::where('period', $periode)->first()?->isClosed()) {
            session()->flash('error', 'Periode ' . $periode . ' telah DITUTUP (CLOSED). Payload ditolak.');

            return;
        }

        // Target KPI yang tertaut cascade ditetapkan di Cascade KPI, bukan oleh payload.
        // Capaian mengikuti polaritas (Naik/Turun/Rentang), sama dengan Objective Departemen.
        $target = $obj->kpi_cascade_id ? (float) $obj->target : (float) $this->targetPayload;
        $ach = RatioLibrary::achievement((float) $this->actualPayload, $target, $obj->polarity ?: RatioLibrary::NAIK) ?? 100.0;

        $obj->update([
            'actual' => $this->actualPayload,
            'target' => $target,
            'achievement_pct' => $ach,
            'status' => MonitoringSync::status($ach),
        ]);

        $idempotencyKey = 'IDEMP-' . strtoupper($this->deptPayload) . '-' . date('Ymd-His');

        StagingLog::create([
            'period' => $periode,
            'dept_code' => strtoupper($this->deptPayload),
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Integrasi API Manual Payload dari ' . $this->deptPayload . ' untuk ' . $obj->kpi_code . ' berhasil diproses & diskor.',
        ]);

        session()->flash('message', 'Payload integrasi dari ' . $this->deptPayload . ' berhasil diproses! (Idempotency Key: ' . $idempotencyKey . ')');
    }

    /**
     * Saldo CoA dari ERP masuk ke Pos Akun, lalu 19 rasio dihitung ulang oleh
     * mesin yang sama dengan menu Pos Akun. Sebelumnya rasio dihitung sendiri di
     * sini dengan target tetap dan ditulis langsung ke Rasio Keuangan, sehingga
     * angkanya bisa berbeda dengan piramida.
     */
    public function processFinancePayload()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $aturan = ['financePeriod' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']];
        foreach (array_keys(self::COA_KE_POS) as $kolom) {
            $aturan[$kolom] = ['required', 'numeric'];
        }
        $this->validate($aturan, ['financePeriod.regex' => 'Periode harus YYYY-MM.'], ['financePeriod' => 'periode']);

        if (Period::where('period', $this->financePeriod)->first()?->isClosed()) {
            session()->flash('error', 'Periode ' . $this->financePeriod . ' telah DITUTUP (CLOSED). Data Finance ditolak.');

            return;
        }

        DB::transaction(function () {
            foreach (self::COA_KE_POS as $kolom => $pos) {
                AccountBalance::updateOrCreate(
                    ['period' => $this->financePeriod, 'code' => $pos],
                    ['amount' => (float) $this->{$kolom} * 1_000_000]
                );
            }

            app(RatioEngine::class)->materialize($this->financePeriod);
        });

        $netProfit = (float) $this->salesPayload - (float) $this->hppPayload - (float) $this->opexPayload;
        $idempotencyKey = 'IDEMP-FIN-COA-' . date('Ymd-His');

        StagingLog::create([
            'period' => $this->financePeriod,
            'dept_code' => 'FIN',
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Penerimaan Data Finance ERP ke Pos Akun (Penjualan: Rp ' . number_format($this->salesPayload) . ' JT, HPP: Rp ' . number_format($this->hppPayload) . ' JT, Laba: Rp ' . number_format($netProfit) . ' JT); rasio keuangan dihitung ulang.',
        ]);

        session()->flash('message', 'Data CoA Finance ' . $this->financePeriod . ' masuk ke Pos Akun dan rasio keuangan dihitung ulang. '
            . 'Pos akun lain (beban tenaga kerja, aset & liabilitas lancar, total aset/liabilitas, modal disetor, data HRIS) dilengkapi di menu Pos Akun.');
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
            'period' => Period::currentPeriod(),
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
        $financialRatios = FinancialRatio::where('period', $this->financePeriod)->orderBy('ratio_code')->get();

        $netProfitCalculated = (float)$this->salesPayload - (float)$this->hppPayload - (float)$this->opexPayload;
        $periode = Period::currentPeriod();

        return view('livewire.system-integration', [
            'recentLogs' => $recentLogs,
            'financeLogs' => $financeLogs,
            'financialRatios' => $financialRatios,
            'netProfitCalculated' => $netProfitCalculated,
            'units' => WorkUnit::active()->get(),
            'kpiOptions' => DepartmentObjective::where('period', $periode)->where('dept_code', $this->deptPayload)->orderBy('kpi_code')->get(),
            'currentPeriod' => $periode,
        ])->layout('layouts.app', ['title' => 'Integrasi Sistem & Gateway']);
    }
}
