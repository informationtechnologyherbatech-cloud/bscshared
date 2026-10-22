<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\ActionPlan;
use App\Models\DepartmentObjective;
use App\Models\Department;
use App\Models\Period;

class ActionPlans extends Component
{
    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    #[Url(as: 'dept')]
    public $filterDept = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    public $search = '';

    // Form modal state
    public $showModal = false;
    public $isEditMode = false;
    public $planId = null;

    // Form fields
    public $title = '';
    public $ownerDept = 'PRD';
    public $objectiveId = null;
    public $picName = '';
    public $dueDate = '';
    public $priority = 'Medium';
    public $budgetEstimate = 0;
    public $actualCost = 0;
    public $progressPct = 0;
    public $status = 'Off-Target';
    public $notes = '';

    // Quick progress update modal
    public $showProgressModal = false;
    public $editingPlanId = null;
    public $editProgress = 0;

    public function mount()
    {
        $reqPeriod = request()->query('period') ?? session('active_period');
        if ($reqPeriod) {
            $this->selectedPeriod = $reqPeriod;
        }
        if (request()->query('status')) {
            $this->selectedStatus = request()->query('status');
        }
        if (request()->query('dept')) {
            $this->filterDept = request()->query('dept');
        }
    }

    public function updatedSelectedPeriod($val)
    {
        session(['active_period' => $val]);
    }

    public function openCreateModal()
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh wewenang manage actionplans.');
            return;
        }

        $this->resetForm();
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengedit program kerja.');
            return;
        }

        $plan = ActionPlan::findOrFail($id);
        $this->planId = $plan->id;
        $this->title = $plan->title;
        $this->ownerDept = $plan->owner_dept;
        $this->objectiveId = $plan->department_objective_id;
        $this->picName = $plan->pic_name ?? '';
        $this->dueDate = $plan->due_date ? date('Y-m-d', strtotime($plan->due_date)) : '';
        $this->priority = $plan->priority ?? 'Medium';
        $this->budgetEstimate = (float) $plan->budget_estimate;
        $this->actualCost = (float) $plan->actual_cost;
        $this->progressPct = (int) $plan->progress_pct;
        $this->status = $plan->status;
        $this->notes = $plan->notes ?? '';

        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function savePlan()
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak.');
            return;
        }

        $this->validate([
            'title' => 'required|min:5|max:255',
            'ownerDept' => 'required|string|max:30',
            'priority' => 'required|in:High,Medium,Low',
            'progressPct' => 'required|numeric|min:0|max:100',
            'budgetEstimate' => 'nullable|numeric|min:0',
            'actualCost' => 'nullable|numeric|min:0',
            'dueDate' => 'nullable|date',
        ]);

        $prog = (int) $this->progressPct;
        $computedStatus = $prog >= 100 ? 'Completed' : ($prog >= 60 ? 'On Progress' : 'Off-Target');

        $data = [
            'period' => $this->selectedPeriod,
            'department_objective_id' => $this->objectiveId ?: null,
            'title' => $this->title,
            'owner_dept' => strtoupper($this->ownerDept),
            'pic_name' => $this->picName,
            'due_date' => $this->dueDate ?: null,
            'priority' => $this->priority,
            'budget_estimate' => (float) $this->budgetEstimate,
            'actual_cost' => (float) $this->actualCost,
            'progress_pct' => $prog,
            'status' => $computedStatus,
            'notes' => $this->notes,
        ];

        if ($this->isEditMode && $this->planId) {
            $plan = ActionPlan::findOrFail($this->planId);
            $plan->update($data);
            session()->flash('message', "Program kerja '{$this->title}' berhasil diperbarui!");
        } else {
            ActionPlan::create($data);
            session()->flash('message', "Program kerja '{$this->title}' berhasil ditambahkan!");
        }

        $this->closeModal();
    }

    public function deletePlan($id)
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang menghapus.');
            return;
        }

        $plan = ActionPlan::findOrFail($id);
        $title = $plan->title;
        $plan->delete();
        session()->flash('message', "Program kerja '{$title}' berhasil dihapus.");
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->reset([
            'planId',
            'title',
            'objectiveId',
            'picName',
            'dueDate',
            'notes',
        ]);
        $this->ownerDept = 'PRD';
        $this->priority = 'Medium';
        $this->budgetEstimate = 0;
        $this->actualCost = 0;
        $this->progressPct = 0;
        $this->status = 'Off-Target';
        $this->isEditMode = false;
    }

    public function editProgressModal($id)
    {
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak: butuh manage actionplans untuk ubah progres.');
            return;
        }
        $plan = ActionPlan::findOrFail($id);
        $this->editingPlanId = $plan->id;
        $this->editProgress = (int) $plan->progress_pct;
        $this->showProgressModal = true;
    }

    public function updateProgress()
    {
        if (!$this->editingPlanId) return;
        if (!auth()->user()?->can('manage actionplans')) {
            session()->flash('error', 'Akses ditolak.');
            $this->editingPlanId = null;
            $this->showProgressModal = false;
            return;
        }

        $plan = ActionPlan::findOrFail($this->editingPlanId);
        $prog = max(0, min(100, (int) $this->editProgress));
        $status = $prog >= 100 ? 'Completed' : ($prog >= 60 ? 'On Progress' : 'Off-Target');

        $plan->update([
            'progress_pct' => $prog,
            'status' => $status,
        ]);

        $this->editingPlanId = null;
        $this->showProgressModal = false;
        session()->flash('message', "Progres program kerja '{$plan->title}' diperbarui menjadi {$prog}% ({$status})!");
    }

    public function closeProgressModal()
    {
        $this->editingPlanId = null;
        $this->showProgressModal = false;
    }

    public function render()
    {
        $query = ActionPlan::with('objective');

        if ($this->selectedPeriod) {
            $query->where(function ($q) {
                $q->where('period', $this->selectedPeriod)
                  ->orWhereNull('period');
            });
        }

        if ($this->filterDept) {
            $query->where('owner_dept', strtoupper($this->filterDept));
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

        if ($this->search) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('pic_name', 'like', "%{$s}%")
                  ->orWhere('owner_dept', 'like', "%{$s}%");
            });
        }

        $actionPlans = $query->orderBy('priority', 'asc')->orderBy('created_at', 'desc')->get();

        // Rekapitulasi Statistik
        $totalPlans = $actionPlans->count();
        $completedPlans = $actionPlans->where('status', 'Completed')->count();
        $inProgressPlans = $actionPlans->where('status', 'On Progress')->count();
        $offTargetPlans = $actionPlans->where('status', 'Off-Target')->count();
        $avgProgress = $totalPlans > 0 ? round($actionPlans->avg('progress_pct'), 1) : 0;
        $totalBudget = $actionPlans->sum('budget_estimate');
        $totalActualCost = $actionPlans->sum('actual_cost');

        $departments = Department::orderBy('code')->get();
        $offTargetObjectives = DepartmentObjective::where('period', $this->selectedPeriod)
            ->where('status', '!=', 'Tercapai')
            ->get();

        $periods = Period::orderBy('period', 'desc')->get();

        return view('livewire.action-plans', [
            'actionPlans' => $actionPlans,
            'departments' => $departments,
            'offTargetObjectives' => $offTargetObjectives,
            'periods' => $periods,
            'totalPlans' => $totalPlans,
            'completedPlans' => $completedPlans,
            'inProgressPlans' => $inProgressPlans,
            'offTargetPlans' => $offTargetPlans,
            'avgProgress' => $avgProgress,
            'totalBudget' => $totalBudget,
            'totalActualCost' => $totalActualCost,
        ])->layout('layouts.app', ['title' => 'Program Kerja & Inisiatif Strategis']);
    }
}
