<?php

namespace App\Livewire;

use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Period;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActionPlans extends Component
{
    use WithPagination;

    public $title = '';

    public $ownerDept = '';

    public $objectiveId = null;

    #[Url(as: 'status')]
    public $selectedStatus = '';

    /** Periode yang ditampilkan; 'semua' memperlihatkan seluruh riwayat. */
    #[Url(as: 'periode')]
    public string $selectedPeriod = '';

    public $editingPlanId = null;

    public $editProgress = 0;

    public function mount()
    {
        if (request()->query('status') && request()->query('status') !== 'all') {
            $this->selectedStatus = request()->query('status');
        }
        if ($this->selectedStatus === 'all') {
            $this->selectedStatus = ''; // "Semua" dari dashboard = tanpa saringan
        }

        if ($this->selectedPeriod === '') {
            $this->selectedPeriod = Period::currentPeriod();
        }
    }

    public function updatedSelectedPeriod(): void
    {
        $this->resetPage();
    }

    public function createPlan()
    {
        if (! auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh manage actionplans (HRIS, FAT, Kadep, Operator, Super Admin). Viewer baca-saja.');

            return;
        }
        if ($this->periodIsClosed()) {
            session()->flash('error', 'Periode '.$this->targetPeriod().' telah DITUTUP. Program kerja baru tidak dapat ditambahkan di periode itu.');

            return;
        }

        $this->validate([
            'title' => 'required|min:5|max:255',
            // Harus salah satu unit kerja aktif entitas ini — sebelumnya teks bebas,
            // sehingga salah ketik membuat departemen "baru" yang tidak ada.
            'ownerDept' => ['required', 'string', 'max:30', Rule::in(WorkUnit::active()->pluck('code')->all())],
            // Sasaran harus milik entitas aktif (model berentitas) — id buatan
            // dari entitas lain ditolak.
            'objectiveId' => ['nullable', Rule::exists('department_objectives', 'id')->where('entity_id', app(EntityContext::class)->id())],
        ]);

        $sasaran = $this->objectiveId ? DepartmentObjective::find($this->objectiveId) : null;

        ActionPlan::create([
            'department_objective_id' => $this->objectiveId ?: null,
            // Mengikuti sasaran mutu yang dimitigasi; tanpa kaitan, dipakai
            // periode YANG SEDANG DILIHAT di halaman ini — bukan periode aktif
            // di bilah atas. Keduanya bisa berbeda, dan memakai periode bilah
            // atas membuat program kerja yang baru dibuat langsung hilang dari
            // daftar di depan mata pembuatnya.
            'period' => $sasaran?->period ?: $this->targetPeriod(),
            'title' => $this->title,
            'owner_dept' => strtoupper($this->ownerDept),
            'progress_pct' => 0,
            'status' => 'Off-Target',
        ]);

        $this->reset(['title', 'ownerDept', 'objectiveId']);
        session()->flash('message', 'Program kerja baru berhasil ditambahkan!');
    }

    /**
     * Periode tempat program kerja baru akan disimpan: periode yang sedang
     * dilihat. Saat daftar menampilkan "Semua periode" tidak ada satu periode
     * yang sedang dilihat, jadi dipakai periode aktif.
     */
    public function targetPeriod(): string
    {
        return $this->selectedPeriod === 'semua' || $this->selectedPeriod === ''
            ? Period::currentPeriod()
            : $this->selectedPeriod;
    }

    /**
     * Periode yang sudah ditutup tidak boleh diubah — aturan yang sama dengan
     * Pos Akun dan Target & Realisasi. Tanpa ini, program kerja menjadi satu-
     * satunya menu bulanan yang masih dapat ditulisi setelah buku ditutup.
     */
    public function periodIsClosed(?string $period = null): bool
    {
        $period ??= $this->targetPeriod();

        return (bool) Period::where('period', $period)->first()?->isClosed();
    }

    public function editProgressModal($id)
    {
        if (! auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh manage actionplans untuk ubah progres.');

            return;
        }
        $plan = ActionPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->editProgress = $plan->progress_pct;
    }

    public function updateProgress()
    {
        if (! $this->editingPlanId) {
            return;
        }
        if (! auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang.');
            $this->editingPlanId = null;

            return;
        }

        $plan = ActionPlan::findOrFail($this->editingPlanId);

        if ($this->periodIsClosed($plan->period)) {
            $this->editingPlanId = null;
            session()->flash('error', 'Periode '.$plan->period.' telah DITUTUP. Progres program kerja di periode itu tidak dapat diubah.');

            return;
        }

        $prog = intval($this->editProgress);
        if ($prog > 100) {
            $prog = 100;
        }
        if ($prog < 0) {
            $prog = 0;
        }

        $status = $prog >= 100 ? 'Completed' : ($prog > 0 ? 'On Progress' : 'Off-Target');

        $plan->update([
            'progress_pct' => $prog,
            'status' => $status,
        ]);

        $this->editingPlanId = null;
        session()->flash('message', 'Progres program kerja '.$plan->title.' diperbarui menjadi '.$prog.'%!');
    }

    public function updatedSelectedStatus(): void
    {
        $this->resetPage();
    }

    public function cancelEdit()
    {
        $this->editingPlanId = null;
    }

    public function render()
    {
        $query = ActionPlan::with('objective');

        // Periode mengikuti bilah atas seperti menu bulanan lain, supaya daftar
        // di sini dan Tingkat 4 piramida berisi program kerja yang sama.
        // "Semua periode" tetap tersedia untuk melihat riwayatnya.
        if ($this->selectedPeriod !== 'semua') {
            $query->where('period', $this->selectedPeriod);
        }

        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['On Progress', 'Off-Target', 'Dalam Proses', 'Belum Dimulai', 'Terhambat']);
            } elseif ($this->selectedStatus === 'Tercapai') {
                $query->whereIn('status', ['Completed', 'Selesai']);
            } elseif ($this->selectedStatus === 'Waspada') {
                $query->whereIn('status', ['On Progress', 'Dalam Proses']);
            } elseif ($this->selectedStatus === 'Di Bawah Target') {
                $query->whereIn('status', ['Off-Target', 'Belum Dimulai', 'Terhambat']);
            } else {
                $query->where('status', $this->selectedStatus);
            }
        }

        $actionPlans = $query->latest('id')->paginate(25);
        // Hanya sasaran periode terbaru — sebelumnya semua periode, sehingga kode
        // KPI yang sama muncul berulang kali di pilihan.
        $offTargetObjectives = DepartmentObjective::where('period', $this->targetPeriod())
            ->where('status', '!=', 'Tercapai')
            ->orderBy('dept_code')->orderBy('kpi_code')
            ->get();

        return view('livewire.action-plans', [
            'actionPlans' => $actionPlans,
            'offTargetObjectives' => $offTargetObjectives,
            'units' => WorkUnit::active()->get(),
            // Kontrol tulis disembunyikan bagi yang tidak berhak, bukan dibiarkan
            // tampil lalu ditolak diam-diam saat disimpan.
            'canWrite' => (bool) auth()->user()?->can('manage actionplans'),
            // Periode tempat program kerja baru akan tersimpan — disebut di formulir
            // supaya tidak ada yang mengira isiannya masuk ke periode lain.
            'targetPeriod' => $this->targetPeriod(),
            'periodClosed' => $this->selectedPeriod !== 'semua' && $this->periodIsClosed(),
            'periods' => Period::orderByDesc('period')->pluck('period'),
            // Program kerja yang sedang disunting progresnya — dipakai modal.
            'editingPlan' => $this->editingPlanId ? ActionPlan::with('objective')->find($this->editingPlanId) : null,
            // Program kerja periode lain yang tersembunyi oleh saringan periode —
            // supaya daftar yang "hilang" tidak terasa seperti data yang lenyap.
            'lainPeriode' => $this->selectedPeriod === 'semua'
                ? 0
                : ActionPlan::where('period', '!=', $this->selectedPeriod)->count(),
        ])->layout('layouts.app', ['title' => 'Program Kerja']);
    }
}
