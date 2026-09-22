<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Models\DepartmentObjective;
use App\Models\Period;
use App\Models\WorkUnit;

class DepartmentObjectives extends Component
{
    public $selectedDept = '';

    #[Url(as: 'status')]
    public $selectedStatus = '';

    #[Url(as: 'period')]
    public $selectedPeriod = '2026-08';

    public $editingObjId = null;
    public $editTarget = 0;
    public $editActual = 0;

    public function mount()
    {
        if (request()->query('status')) {
            $this->selectedStatus = request()->query('status');
        }
        if (request()->query('period')) {
            $this->selectedPeriod = request()->query('period');
        }
    }

    public function editObjective($id)
    {
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: butuh manage objectives / can_write_kpi (HRIS, FAT, Kadep, Operator, Super Admin). Viewer tidak dapat mengubah KPI.');
            return;
        }
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            return;
        }

        $obj = DepartmentObjective::findOrFail($id);
        $this->editingObjId = $obj->id;
        $this->editTarget = $obj->target;
        $this->editActual = $obj->actual;
    }

    public function updateObjective()
    {
        if (!$this->editingObjId) return;
        if (!auth()->user()?->can('manage objectives') && !auth()->user()?->can('can_write_kpi')) {
            session()->flash('error', 'Akses ditolak: peran Anda tidak berwenang mengubah KPI.');
            $this->editingObjId = null;
            return;
        }

        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        if ($periodObj && $periodObj->isClosed()) {
            session()->flash('error', 'Periode ' . $this->selectedPeriod . ' telah DITUTUP (CLOSED). Data tidak dapat diubah.');
            $this->editingObjId = null;
            return;
        }

        $obj = DepartmentObjective::findOrFail($this->editingObjId);
        $target = floatval($this->editTarget);
        $actual = floatval($this->editActual);

        // Calculate achievement based on polarity
        if ($obj->polarity === 'Turun') {
            // Lower actual is better (e.g. defect rate, cycle time)
            $ach = $actual > 0 ? round(($target / $actual) * 100, 2) : 100;
        } else {
            // Higher actual is better (Naik)
            $ach = $target > 0 ? round(($actual / $target) * 100, 2) : 100;
        }

        // Cap achievement at 100% per business rule (Cap 100%)
        if ($ach > 100) {
            $ach = 100.00;
        }

        $status = 'Waspada';
        if ($ach >= 100) {
            $status = 'Tercapai';
        } elseif ($ach < 80) {
            $status = 'Di Bawah Target';
        }

        $obj->update([
            'target' => $target,
            'actual' => $actual,
            'achievement_pct' => $ach,
            'status' => $status,
        ]);

        $this->editingObjId = null;
        session()->flash('message', 'KPI ' . $obj->kpi_code . ' (' . $obj->dept_code . ') berhasil diperbarui!');
    }

    public function cancelEdit()
    {
        $this->editingObjId = null;
    }

    public function render()
    {
        $periodObj = Period::where('period', $this->selectedPeriod)->first();
        $isClosed = $periodObj ? $periodObj->isClosed() : false;

        $query = DepartmentObjective::where('period', $this->selectedPeriod);

        if ($this->selectedDept) {
            $query->where('dept_code', $this->selectedDept);
        }

        if ($this->selectedStatus) {
            if ($this->selectedStatus === 'bermasalah') {
                $query->whereIn('status', ['Waspada', 'Di Bawah Target', 'Off-Target']);
            } else {
                $query->where('status', $this->selectedStatus);
            }
        }

        $objectives = $query->get();

        // Daftar departemen dari master Unit Kerja entitas aktif — sebelumnya hanya
        // diturunkan dari DISTINCT dept_code, sehingga unit yang belum punya sasaran
        // mutu tidak pernah muncul dan nama unitnya tidak diketahui.
        $units = WorkUnit::active()->get();
        $departments = $units->pluck('name', 'code')->toArray();

        // Kode yang masih dipakai data lama tetapi unitnya sudah nonaktif/terhapus
        // tetap dapat disaring, supaya datanya tidak "hilang" dari layar.
        foreach ($objectives->pluck('dept_code')->unique() as $kode) {
            $departments[$kode] ??= $kode;
        }
        $periods = Period::pluck('period')->toArray();

        return view('livewire.department-objectives', [
            'objectives' => $objectives,
            'departments' => $departments,
            'periods' => $periods,
            'isClosed' => $isClosed,
        ])->layout('layouts.app', ['title' => 'Objective Departemen']);
    }
}
