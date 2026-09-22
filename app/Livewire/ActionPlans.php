<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\WorkUnit;

class ActionPlans extends Component
{
    public $title = '';
    public $ownerDept = '';
    public $objectiveId = null;

    #[Url(as: 'status')]
    public $selectedStatus = '';

    public $editingPlanId = null;
    public $editProgress = 0;

    public function mount()
    {
        if (request()->query('status')) {
            $this->selectedStatus = request()->query('status');
        }
    }

    public function createPlan()
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh manage actionplans (HRIS, FAT, Kadep, Operator, Super Admin). Viewer baca-saja.');
            return;
        }
        $this->validate([
            'title' => 'required|min:5|max:255',
            // Harus salah satu unit kerja aktif entitas ini — sebelumnya teks bebas,
            // sehingga salah ketik membuat departemen "baru" yang tidak ada.
            'ownerDept' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::in(WorkUnit::active()->pluck('code')->all())],
        ]);

        ActionPlan::create([
            'department_objective_id' => $this->objectiveId ?: null,
            'title' => $this->title,
            'owner_dept' => strtoupper($this->ownerDept),
            'progress_pct' => 0,
            'status' => 'Off-Target',
        ]);

        $this->reset(['title', 'ownerDept', 'objectiveId']);
        session()->flash('message', 'Program kerja baru berhasil ditambahkan!');
    }

    public function editProgressModal($id)
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh manage actionplans untuk ubah progres.');
            return;
        }
        $plan = ActionPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->editProgress = $plan->progress_pct;
    }

    public function updateProgress()
    {
        if (!$this->editingPlanId) return;
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang.');
            $this->editingPlanId = null;
            return;
        }

        $plan = ActionPlan::findOrFail($this->editingPlanId);
        $prog = intval($this->editProgress);
        if ($prog > 100) $prog = 100;
        if ($prog < 0) $prog = 0;

        $status = $prog >= 100 ? 'Completed' : ($prog > 0 ? 'On Progress' : 'Off-Target');

        $plan->update([
            'progress_pct' => $prog,
            'status' => $status,
        ]);

        $this->editingPlanId = null;
        session()->flash('message', 'Progres program kerja ' . $plan->title . ' diperbarui menjadi ' . $prog . '%!');
    }

    public function cancelEdit()
    {
        $this->editingPlanId = null;
    }

    public function render()
    {
        $query = ActionPlan::with('objective');

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

        $actionPlans = $query->get();
        $offTargetObjectives = DepartmentObjective::where('status', '!=', 'Tercapai')->get();

        return view('livewire.action-plans', [
            'actionPlans' => $actionPlans,
            'offTargetObjectives' => $offTargetObjectives,
            'units' => WorkUnit::active()->get(),
        ])->layout('layouts.app', ['title' => 'Program Kerja']);
    }
}
