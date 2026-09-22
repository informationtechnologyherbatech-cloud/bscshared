<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use Livewire\Component;
use App\Models\StagingLog;
use App\Models\DepartmentObjective;
use App\Models\Period;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StagingLogs extends Component
{
    use AuthorizesWrites;

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
        $logs = StagingLog::latest()->get();

        // Dynamic 4-Hop Telemetry & Reconciliation Audits (PRD G-04 Requirement)
        $totalLogsCount = $logs->count();
        $scoredCount = $logs->where('status', 'SCORED')->count();
        $controlTotalMatch = $totalLogsCount > 0 ? round(($scoredCount / $totalLogsCount) * 100, 1) : 100.0;

        $uniqueKeysCount = $logs->pluck('idempotency_key')->unique()->count();
        $hashIntegrity = $totalLogsCount > 0 ? ($uniqueKeysCount === $totalLogsCount ? '100% (LULUS)' : 'KONFLIK (GANDA)') : '100% (LULUS)';

        $periode = Period::currentPeriod();
        $objs = DepartmentObjective::where('period', $periode)->get();
        $filledObjs = $objs->where('actual', '>', 0)->count();
        $periodCompleteness = $objs->count() > 0 ? round(($filledObjs / $objs->count()) * 100, 1) . '%' : '100%';

        $latestLog = $logs->first();
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
            'completeness' => $periodCompleteness . ' (' . $filledObjs . '/' . $objs->count() . ' KPI terisi, periode ' . $periode . ')',
            'freshness' => $dataFreshnessStatus,
        ];

        return view('livewire.staging-logs', [
            'logs' => $logs,
            'reconciliationMetrics' => $reconciliationMetrics,
            'units' => \App\Models\WorkUnit::active()->get(),
        ])->layout('layouts.app', ['title' => 'Staging & Audit Log']);
    }
}
