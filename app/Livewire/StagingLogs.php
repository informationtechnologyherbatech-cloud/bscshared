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

    public $simulatedDept = 'QC';

    public function simulateInbound()
    {
        if ($this->lacksPermission('manage integration')) {
            return;
        }

        $idempotencyKey = 'IDEMP-' . strtoupper($this->simulatedDept) . '-' . date('Ymd') . '-' . Str::random(4);

        StagingLog::create([
            'period' => '2026-08',
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

        $objs = DepartmentObjective::where('period', '2026-08')->get();
        $filledObjs = $objs->where('actual', '>', 0)->count();
        $periodCompleteness = $objs->count() > 0 ? round(($filledObjs / $objs->count()) * 100, 1) . '%' : '100%';

        $latestLog = $logs->first();
        $hoursSinceLatest = $latestLog ? Carbon::parse($latestLog->created_at)->diffInHours(Carbon::now()) : 0;
        $dataFreshnessStatus = $hoursSinceLatest < 26 ? 'LUKAS / TERKINI (' . $hoursSinceLatest . ' jam yang lalu)' : 'TERAMPAU (WAWASAN >26 JAM)';

        $reconciliationMetrics = [
            'control_total' => $controlTotalMatch . '% Match (' . $scoredCount . '/' . $totalLogsCount . ' Scored)',
            'hash_integrity' => $hashIntegrity,
            'completeness' => $periodCompleteness . ' (' . $filledObjs . '/' . $objs->count() . ' KPI Terisi)',
            'freshness' => $dataFreshnessStatus,
        ];

        return view('livewire.staging-logs', [
            'logs' => $logs,
            'reconciliationMetrics' => $reconciliationMetrics,
        ])->layout('layouts.app', ['title' => 'Staging & Audit Log']);
    }
}
