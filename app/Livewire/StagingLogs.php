<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StagingLog;
use App\Models\DepartmentObjective;
use App\Models\Period;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StagingLogs extends Component
{
    use AuthorizesWrites;
    use WithPagination;

    public $simulatedDept = '';

    public function mount()
    {
        // Unit kerja pertama entitas aktif (sebelumnya dipatok "QC" milik Herbatech).
        $this->simulatedDept = (string) (\App\Models\WorkUnit::active()->value('code') ?? '');
    }

    public function simulateInbound()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $this->validate([
            'simulatedDept' => ['required', \Illuminate\Validation\Rule::in(\App\Models\WorkUnit::active()->pluck('code')->all())],
        ], [], ['simulatedDept' => 'unit kerja']);

        $idempotencyKey = 'IDEMP-' . strtoupper($this->simulatedDept) . '-' . date('Ymd') . '-' . Str::random(4);

        StagingLog::create([
            'period' => Period::currentPeriod(),
            'dept_code' => strtoupper($this->simulatedDept),
            'idempotency_key' => $idempotencyKey,
            'status' => 'SCORED',
            'source_version' => 1,
            'message' => 'Simulasi payload inbound dari ' . $this->simulatedDept . ' diterima dan berhasil dihitung.',
        ]);

        session()->flash('message', 'Payload inbound berhasil disimulasikan! (Idempotency Key: ' . $idempotencyKey . ')');
    }

    public function render()
    {
        // Daftar per halaman; angka rekonsiliasi dihitung di database — log terus
        // bertambah, jadi tidak lagi memuat seluruh baris ke memori.
        $logs = StagingLog::latest()->latest('id')->paginate(25);

        // Dynamic 4-Hop Telemetry & Reconciliation Audits (PRD G-04 Requirement)
        $totalLogsCount = StagingLog::count();
        $scoredCount = StagingLog::where('status', 'SCORED')->count();
        $controlTotalMatch = $totalLogsCount > 0 ? round(($scoredCount / $totalLogsCount) * 100, 1) : 100.0;

        $uniqueKeysCount = StagingLog::distinct()->count('idempotency_key');
        $hashIntegrity = $totalLogsCount > 0 ? ($uniqueKeysCount === $totalLogsCount ? '100% (LULUS)' : 'KONFLIK (GANDA)') : '100% (LULUS)';

        $periode = Period::currentPeriod();
        $jumlahObjs = DepartmentObjective::where('period', $periode)->count();
        $filledObjs = DepartmentObjective::where('period', $periode)->where('actual', '>', 0)->count();
        $periodCompleteness = $jumlahObjs > 0 ? round(($filledObjs / $jumlahObjs) * 100, 1) . '%' : '100%';

        $latestLog = StagingLog::latest()->latest('id')->first();
        // diffInHours() Carbon 3 bertanda; ambil nilai mutlak & ambang dari config.
        $hoursSinceLatest = $latestLog ? (int) abs(Carbon::parse($latestLog->created_at)->diffInHours(Carbon::now())) : 0;
        $ambang = (int) config('bsc.stale_after_hours', 26);
        $dataFreshnessStatus = ! $latestLog
            ? 'BELUM ADA DATA'
            : ($hoursSinceLatest < $ambang
                ? 'TERKINI (' . $hoursSinceLatest . ' jam yang lalu)'
                : 'BASI (lebih dari ' . $ambang . ' jam — ' . $hoursSinceLatest . ' jam yang lalu)');

        $reconciliationMetrics = [
            'control_total' => $controlTotalMatch . '% Match (' . $scoredCount . '/' . $totalLogsCount . ' Scored)',
            'hash_integrity' => $hashIntegrity,
            'completeness' => $periodCompleteness . ' (' . $filledObjs . '/' . $jumlahObjs . ' KPI terisi, periode ' . $periode . ')',
            'freshness' => $dataFreshnessStatus,
        ];

        return view('livewire.staging-logs', [
            'logs' => $logs,
            'reconciliationMetrics' => $reconciliationMetrics,
            'units' => \App\Models\WorkUnit::active()->get(),
        ])->layout('layouts.app', ['title' => 'Staging & Audit Log']);
    }
}
