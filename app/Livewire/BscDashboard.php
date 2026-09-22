<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\MonitoringSync;
use App\Support\Bsc\Scorecard;
use App\Support\ScoreStatus;
use App\Models\FinancialRatio;
use App\Models\DepartmentObjective;
use App\Models\ActionPlan;
use Carbon\Carbon;

class BscDashboard extends Component
{
    use AuthorizesWrites;

    #[Url]
    public $selectedPeriod = '';

    public $periods = [];

    #[Url]
    public $activeLevel = 1; // 1: Apex, 2: Rasio, 3: Objectives, 4: Action Plans

    #[Url]
    public $statusFilter = 'all'; // all, Tercapai, Waspada, Di Bawah Target, bermasalah

    public $searchQuery = '';
    public $selectedItemDetail = null;
    public $showModal = false;

    // Period management state
    public $showCreatePeriodModal = false;
    public $newPeriodInput = '';

    public function mount()
    {
        // Terbaru lebih dulu; tanpa periode sama sekali dipakai bulan berjalan.
        $this->periods = Period::list() ?: [Period::currentPeriod()];
        if (!in_array($this->selectedPeriod, $this->periods)) {
            $this->selectedPeriod = $this->periods[0];
        }
    }

    public function selectLevel($level)
    {
        $this->activeLevel = (int) $level;
        $this->searchQuery = '';

        // Langsung gulir ke panel Telusur Detail — tidak perlu scroll manual.
        $this->dispatch('telusur-detail');
    }

    public function filterStatus($status)
    {
        $this->statusFilter = $status;
    }

    public function togglePeriodStatus()
    {
        if ($this->lacksPermission('can_override')) {
            return;
        }

        $period = Period::where('period', $this->selectedPeriod)->first();
        if ($period) {
            $newStatus = $period->status === 'OPEN' ? 'CLOSED' : 'OPEN';
            $period->update(['status' => $newStatus]);
            session()->flash('message', 'Status periode ' . $this->selectedPeriod . ' berhasil diubah menjadi ' . $newStatus . '!');
        }
    }

    public function createNewPeriod()
    {
        if ($this->lacksPermission('can_override')) {
            return;
        }

        $this->validate([
            'newPeriodInput' => 'required|regex:/^\d{4}-\d{2}$/',
        ], [
            'newPeriodInput.required' => 'Format periode harus YYYY-MM.',
            'newPeriodInput.regex' => 'Format periode harus YYYY-MM (misal: 2026-09).',
        ]);

        $periodStr = $this->newPeriodInput;

        $existing = Period::where('period', $periodStr)->first();
        if ($existing) {
            session()->flash('error', 'Periode ' . $periodStr . ' sudah ada!');
            return;
        }

        // Templat = periode terakhir sebelum periode baru (sebelumnya selalu
        // 2026-08, sehingga entitas lain atau tahun berikutnya menyalin data keliru).
        $sumber = Period::where('period', '<', $periodStr)->orderByDesc('period')->value('period')
            ?? Period::orderByDesc('period')->value('period');
        $tahunSama = $sumber && substr($sumber, 0, 4) === substr($periodStr, 0, 4);

        // Create new period (G-05: Actuals initialized to 0.00, NOT copied from target)
        Period::create([
            'period' => $periodStr,
            'status' => 'OPEN',
            'apex_score' => 0.00,
        ]);

        // Salin sasaran periode sumber dengan realisasi 0. Sasaran yang tertaut ke
        // KPI cascade hanya disalin dalam tahun yang sama — KPI cascade berlaku per
        // tahun; untuk tahun baru, sasarannya datang dari KPI Lolos tahun itu.
        $baseObjectives = $sumber ? DepartmentObjective::where('period', $sumber)->get() : collect();

        foreach ($baseObjectives as $base) {
            if ($base->kpi_cascade_id && ! $tahunSama) {
                continue;
            }

            DepartmentObjective::create([
                'kpi_cascade_id' => $base->kpi_cascade_id,
                'period' => $periodStr,
                'dept_code' => $base->dept_code,
                'kpi_code' => $base->kpi_code,
                'kpi_name' => $base->kpi_name,
                'polarity' => $base->polarity,
                'target' => $base->target,
                'actual' => 0.00, // PRD G-05 Requirement: NOT auto-populated with target!
                'achievement_pct' => 0.00,
                'status' => 'Di Bawah Target',
            ]);
        }

        // Copy template of financial ratios with 0 actuals
        // Rasio hasil hitungan tidak disalin — periode baru mendapatkannya
        // saat pos akunnya diisi di menu Pos Akun.
        $baseRatios = $sumber
            ? FinancialRatio::where('period', $sumber)->where('source', '!=', RatioEngine::SOURCE_COMPUTED)->get()
            : collect();
        foreach ($baseRatios as $baseR) {
            FinancialRatio::create([
                'period' => $periodStr,
                'category' => $baseR->category,
                'ratio_name' => $baseR->ratio_name,
                'target' => $baseR->target,
                'actual' => 0.00,
                'achievement_pct' => 0.00,
                'status' => 'Di Bawah Target',
            ]);
        }

        // KPI cascade berstatus Lolos tahun itu yang belum ada ikut dimasukkan,
        // dengan target disesuaikan faktor revisi revenue.
        $sinkron = MonitoringSync::syncPeriod($periodStr);

        $this->periods = Period::list();
        $this->selectedPeriod = $periodStr;
        $this->newPeriodInput = '';
        $this->showCreatePeriodModal = false;

        session()->flash('message', 'Periode baru ' . $periodStr . ' berhasil dibuat'
            . ($sumber ? ' dari templat ' . $sumber : '')
            . ($sinkron['created'] ? ', ' . $sinkron['created'] . ' KPI Lolos dari Cascade KPI ditambahkan' : '')
            . ' (realisasi diinisialisasi 0 per aturan PRD G-05).');
    }

    public function inspectItem($type, $id)
    {
        if ($type === 'ratio') {
            $ratio = FinancialRatio::find($id);
            if ($ratio) {
                $this->selectedItemDetail = [
                    'type' => 'Rasio Keuangan (Tingkat 2)',
                    'code' => $ratio->category,
                    'name' => $ratio->ratio_name,
                    'target' => $ratio->display($ratio->target),
                    'actual' => $ratio->display($ratio->actual),
                    'achievement' => number_format($ratio->achievement_pct, 1) . '%',
                    'status' => $ratio->status,
                    'upstream' => 'Piramida Tingkat 1 (Apex Score Keuangan)',
                    'downstream' => 'Drive Sasaran Mutu Departemen (Tingkat 3)',
                    'description' => 'Indikator kinerja keuangan utama yang menyokong kesehatan finansial dan apex score perusahaan.',
                ];
                $this->showModal = true;
            }
        } elseif ($type === 'objective') {
            $obj = DepartmentObjective::with('actionPlans')->find($id);
            if ($obj) {
                $this->selectedItemDetail = [
                    'type' => 'Objective Departemen (Tingkat 3)',
                    'code' => $obj->kpi_code . ' [' . $obj->dept_code . ']',
                    'name' => $obj->kpi_name,
                    'target' => number_format($obj->target, 1),
                    'actual' => number_format($obj->actual, 1),
                    'achievement' => number_format($obj->achievement_pct, 1) . '%',
                    'status' => $obj->status,
                    'upstream' => $obj->kpiCascade?->ratio_code
                        ? 'Menggerakkan ' . \App\Support\Bsc\RatioLibrary::impactName($obj->kpiCascade->ratio_code) . ' (Tingkat 2)'
                        : 'Menyokong Rasio Keuangan (Tingkat 2)',
                    'downstream' => $obj->actionPlans->count() . ' Program Kerja Mitigasi (Tingkat 4)',
                    'description' => 'Sasaran mutu operasional departemen ' . $obj->dept_code . ' untuk mencapai target strategic BSC.',
                    'action_plans' => $obj->actionPlans->toArray(),
                ];
                $this->showModal = true;
            }
        } elseif ($type === 'action') {
            $ap = ActionPlan::with('objective')->find($id);
            if ($ap) {
                $this->selectedItemDetail = [
                    'type' => 'Program Kerja / Action Plan (Tingkat 4)',
                    'code' => 'AP-' . $ap->id . ' [' . $ap->owner_dept . ']',
                    'name' => $ap->title,
                    'target' => '100%',
                    'actual' => $ap->progress_pct . '%',
                    'achievement' => $ap->progress_pct . '%',
                    'status' => $ap->status,
                    'upstream' => $ap->objective ? 'Mitigasi KPI: ' . $ap->objective->kpi_code . ' (' . $ap->objective->kpi_name . ')' : 'Inisiatif Perbaikan',
                    'downstream' => 'Eksekusi Lapangan & Pondasi Piramida BSC',
                    'description' => 'Inisiatif perbaikan konkret untuk mengatasi gap pencapaian sasaran mutu departemen.',
                ];
                $this->showModal = true;
            }
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedItemDetail = null;
    }

    /**
     * Rincian bobot yang benar-benar dipakai menghitung Apex Score, untuk
     * ditampilkan sebagai keterangan formula. Tingkat tanpa data dikeluarkan
     * dan bobotnya dinormalisasi, persis seperti pada calculateApexScore().
     *
     * @param  array<string, float|null>  $tierScores
     * @return array<int, array{label: string, weight: float}>
     */
    private function apexBreakdown(array $tierScores): array
    {
        $weights = config('bsc.apex_weights', []);
        $label = [
            'revenue' => 'Revenue',
            'ratios' => 'Rasio Keuangan',
        ];

        $aktif = [];
        $totalWeight = 0.0;

        foreach ($tierScores as $tier => $score) {
            $weight = (float) ($weights[$tier] ?? 0);
            if ($score === null || $weight <= 0) {
                continue;
            }
            $aktif[] = ['label' => $label[$tier] ?? $tier, 'weight' => $weight];
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return [];
        }

        return array_map(
            fn (array $item) => [
                'label' => $item['label'],
                'weight' => round($item['weight'] / $totalWeight * 100),
            ],
            $aktif
        );
    }

    /**
     * Rata-rata terbobot skor tiap tingkat piramida.
     *
     * Tingkat yang belum punya data (nilai null) dikeluarkan dari perhitungan
     * dan bobotnya dibagikan ke tingkat yang tersedia, sehingga periode yang
     * baru terisi sebagian tidak menghasilkan Apex Score yang menyesatkan.
     *
     * @param  array<string, float|null>  $tierScores
     */
    private function calculateApexScore(array $tierScores): float
    {
        return Scorecard::apex($tierScores);
    }

    public function render()
    {
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        // Freshness check. diffInHours() pada Carbon 3 bertanda (negatif bila
        // pembandingnya di masa lalu), jadi selisih diambil sebagai nilai mutlak
        // agar peringatan data basi benar-benar menyala.
        $lastSyncTime = $periodObj && $periodObj->updated_at ? $periodObj->updated_at : Carbon::now()->subHours(2);
        $hoursSinceSync = (int) abs($lastSyncTime->diffInHours(Carbon::now()));
        $isStale = $hoursSinceSync >= (int) config('bsc.stale_after_hours', 26);
        
        $ratiosQuery = FinancialRatio::where('period', $this->selectedPeriod);
        $ratios = $ratiosQuery->get();
        // F2 dari Scorecard — sumber yang sama dengan konsolidasi holding.
        $ratioScore = Scorecard::ratioScore($this->selectedPeriod);
        $hasRatioScore = $ratioScore !== null;
        $avgRatioScore = $ratioScore ?? 0;

        $objectivesQuery = DepartmentObjective::where('period', $this->selectedPeriod);
        $objectives = $objectivesQuery->get();
        $avgObjScore = $objectives->count() > 0 ? round($objectives->avg('achievement_pct'), 2) : 0;

        // Program kerja tidak punya kolom periode; keterkaitannya lewat sasaran mutu
        // yang dimitigasinya. Tanpa penyaringan ini, Tingkat 4 dan Apex Score memakai
        // program kerja dari seluruh periode, dan penanda "data belum lengkap" ikut
        // salah pada periode yang sebenarnya memang belum punya program kerja.
        // Program kerja tanpa sasaran mutu tidak dapat diatribusikan ke periode mana
        // pun, sehingga tidak ikut diskor — daftar lengkapnya tetap ada di menu
        // Program Kerja.
        $actionPlans = ActionPlan::with('objective')
            ->whereHas('objective', fn ($query) => $query->where('period', $this->selectedPeriod))
            ->get();
        // PRD G-03 Requirement: Tier 4 Apex score MUST strictly represent average progress_pct of action plans
        $avgActionProgress = $actionPlans->count() > 0 ? round($actionPlans->avg('progress_pct'), 2) : 0;

        // Apex Score = rata-rata terbobot Tingkat 2 (rasio), Tingkat 3 (sasaran
        // mutu) dan Tingkat 4 (program kerja), seluruhnya dari data nyata.
        // Bobot diatur di config/bsc.php.
        // F1: pencapaian revenue kumulatif (Tingkat 1). Null (dengan alasannya) =
        // belum ada target bulanan atau belum ada realisasi.
        $revenueDetail = RevenueTarget::cumulative($this->selectedPeriod);
        $revenueScore = $revenueDetail['score'];

        // Skor puncak = 0,45 × F1 + 0,55 × F2 (config/bsc.php).
        $tierScores = [
            'revenue' => $revenueScore,
            'ratios' => $hasRatioScore ? $avgRatioScore : null,
        ];
        $apexScore = $this->calculateApexScore($tierScores);
        $apexBreakdown = $this->apexBreakdown($tierScores);

        // Status tiap tingkat piramida. Tingkat tanpa data ditandai "belum lengkap",
        // bukan diberi nilai nol — keduanya berbeda arti.
        $tierStatus = [
            1 => ScoreStatus::for($revenueScore, $revenueScore !== null),
            2 => ScoreStatus::for($avgRatioScore, $hasRatioScore),
            3 => ScoreStatus::for($avgObjScore, $objectives->count() > 0),
            4 => ScoreStatus::for($avgActionProgress, $actionPlans->count() > 0),
        ];

        // Save computed apex score to period model — hanya bila berubah, agar
        // render ulang Livewire tidak menulis berulang. Timestamp sengaja tidak
        // disentuh: periods.updated_at menandai sinkronisasi data terakhir,
        // bukan kalkulasi ulang skor (dipakai penanda data basi di atas).
        if ($periodObj && !$isClosed && (float) $periodObj->apex_score !== $apexScore) {
            $periodObj->timestamps = false;
            $periodObj->update(['apex_score' => $apexScore]);
            $periodObj->timestamps = true;
        }

        $counts = [
            'total_kpi' => $objectives->count(),
            'tercapai' => $objectives->where('status', 'Tercapai')->count(),
            'waspada' => $objectives->where('status', 'Waspada')->count(),
            'dibawah' => $objectives->where('status', 'Di Bawah Target')->count(),
        ];

        // Filtered datasets for active drill-down tier (G-01: Bermasalah filter support)
        $filteredRatios = $ratios->filter(function($r) {
            $matchStatus = $this->statusFilter === 'all' || 
                ($this->statusFilter === 'Tercapai' && $r->status === 'Tercapai') ||
                ($this->statusFilter === 'Waspada' && $r->status === 'Waspada') ||
                ($this->statusFilter === 'Di Bawah Target' && ($r->status === 'Di Bawah Target' || $r->status === 'Off-Target')) ||
                ($this->statusFilter === 'bermasalah' && ($r->status === 'Waspada' || $r->status === 'Di Bawah Target' || $r->status === 'Off-Target'));
            $matchQuery = empty($this->searchQuery) || 
                stripos($r->ratio_name, $this->searchQuery) !== false || 
                stripos($r->category, $this->searchQuery) !== false;
            return $matchStatus && $matchQuery;
        });

        $filteredObjectives = $objectives->filter(function($o) {
            $matchStatus = $this->statusFilter === 'all' || 
                ($this->statusFilter === 'Tercapai' && $o->status === 'Tercapai') ||
                ($this->statusFilter === 'Waspada' && $o->status === 'Waspada') ||
                ($this->statusFilter === 'Di Bawah Target' && ($o->status === 'Di Bawah Target' || $o->status === 'Off-Target')) ||
                ($this->statusFilter === 'bermasalah' && ($o->status === 'Waspada' || $o->status === 'Di Bawah Target' || $o->status === 'Off-Target'));
            $matchQuery = empty($this->searchQuery) || 
                stripos($o->kpi_name, $this->searchQuery) !== false || 
                stripos($o->kpi_code, $this->searchQuery) !== false ||
                stripos($o->dept_code, $this->searchQuery) !== false;
            return $matchStatus && $matchQuery;
        });

        $filteredActionPlans = $actionPlans->filter(function($ap) {
            $matchStatus = $this->statusFilter === 'all' || 
                ($this->statusFilter === 'Tercapai' && ($ap->status === 'Completed' || $ap->status === 'Selesai')) ||
                ($this->statusFilter === 'Waspada' && ($ap->status === 'On Progress' || $ap->status === 'Dalam Proses')) ||
                ($this->statusFilter === 'Di Bawah Target' && ($ap->status === 'Off-Target' || $ap->status === 'Belum Dimulai')) ||
                ($this->statusFilter === 'bermasalah' && ($ap->status === 'On Progress' || $ap->status === 'Off-Target' || $ap->status === 'Dalam Proses' || $ap->status === 'Belum Dimulai'));
            $matchQuery = empty($this->searchQuery) || 
                stripos($ap->title, $this->searchQuery) !== false || 
                stripos($ap->owner_dept, $this->searchQuery) !== false;
            return $matchStatus && $matchQuery;
        });

        return view('livewire.bsc-dashboard', [
            'periodObj' => $periodObj,
            'isClosed' => $isClosed,
            'isStale' => $isStale,
            'hoursSinceSync' => $hoursSinceSync,
            'lastSyncTime' => $lastSyncTime,
            'apexScore' => $apexScore,
            'apexBreakdown' => $apexBreakdown,
            'revenueScore' => $revenueScore,
            'revenueDetail' => $revenueDetail,
            'revenueReason' => RevenueTarget::reasonLabel($revenueDetail['reason']),
            'hasRatioScore' => $hasRatioScore,
            'apexWeights' => config('bsc.apex_weights', []),
            'tierStatus' => $tierStatus,
            'statusLegend' => ScoreStatus::legend(),
            'objectiveCount' => $objectives->count(),
            'actionPlanCount' => $actionPlans->count(),
            'ratioCount' => $ratios->count(),
            'avgRatioScore' => $avgRatioScore,
            'avgObjScore' => $avgObjScore,
            'avgActionProgress' => $avgActionProgress,
            'ratios' => $ratios,
            'objectives' => $objectives,
            'counts' => $counts,
            'filteredRatios' => $filteredRatios,
            'filteredObjectives' => $filteredObjectives,
            'filteredActionPlans' => $filteredActionPlans,
        ])->layout('layouts.app', ['title' => 'Dashboard Piramida BSC']);
    }
}

