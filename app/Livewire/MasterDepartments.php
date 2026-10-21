<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Department;

class MasterDepartments extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $isEditMode = false;
    public $deptId = null;

    // Form fields
    public $code = '';
    public $name = '';
    public $manager_title = '';
    public $description = '';
    public $color = '#0d9488';
    public $sort_order = 0;
    public $is_active = true;

    protected $paginationTheme = 'bootstrap';

    protected function rules()
    {
        return [
            'code' => 'required|string|max:30|unique:departments,code,' . ($this->deptId ?? 'NULL') . ',id',
            'name' => 'required|string|max:150',
            'manager_title' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:500',
            'color' => 'required|string|max:20',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        if (!auth()->user()?->can('manage settings') && !auth()->user()?->can('manage users') && !auth()->user()?->can('can_manage_users')) {
            session()->flash('error', 'Akses ditolak: Anda tidak memiliki wewenang mengelola master departemen.');
            return;
        }

        $this->reset(['deptId', 'code', 'name', 'manager_title', 'description', 'color', 'sort_order', 'is_active']);
        $this->color = '#0d9488';
        $this->sort_order = Department::max('sort_order') + 1;
        $this->is_active = true;
        $this->isEditMode = false;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        if (!auth()->user()?->can('manage settings') && !auth()->user()?->can('manage users') && !auth()->user()?->can('can_manage_users')) {
            session()->flash('error', 'Akses ditolak: Anda tidak memiliki wewenang mengelola master departemen.');
            return;
        }

        $dept = Department::findOrFail($id);
        $this->deptId = $dept->id;
        $this->code = $dept->code;
        $this->name = $dept->name;
        $this->manager_title = $dept->manager_title;
        $this->description = $dept->description;
        $this->color = $dept->color ?: '#0d9488';
        $this->sort_order = $dept->sort_order;
        $this->is_active = (bool) $dept->is_active;

        $this->isEditMode = true;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function saveDepartment()
    {
        if (!auth()->user()?->can('manage settings') && !auth()->user()?->can('manage users') && !auth()->user()?->can('can_manage_users')) {
            session()->flash('error', 'Akses ditolak: Anda tidak berwenang menyimpan departemen.');
            $this->closeModal();
            return;
        }

        $this->validate();

        $data = [
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'manager_title' => trim($this->manager_title),
            'description' => trim($this->description),
            'color' => $this->color,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];

        if ($this->isEditMode && $this->deptId) {
            $dept = Department::findOrFail($this->deptId);
            $dept->update($data);
            session()->flash('message', 'Departemen [' . $dept->code . '] ' . $dept->name . ' berhasil diperbarui!');
        } else {
            Department::create($data);
            session()->flash('message', 'Departemen baru [' . $data['code'] . '] ' . $data['name'] . ' berhasil ditambahkan!');
        }

        $this->closeModal();
    }

    public function deleteDepartment($id)
    {
        if (!auth()->user()?->can('manage settings') && !auth()->user()?->can('manage users') && !auth()->user()?->can('can_manage_users')) {
            session()->flash('error', 'Akses ditolak: Anda tidak berwenang menghapus departemen.');
            return;
        }

        $dept = Department::findOrFail($id);

        // Check if has objectives or action plans
        $objCount = $dept->objectives()->count();
        $planCount = $dept->actionPlans()->count();

        if ($objCount > 0 || $planCount > 0) {
            session()->flash('error', 'Departemen [' . $dept->code . '] tidak dapat dihapus karena memiliki ' . $objCount . ' sasaran mutu dan ' . $planCount . ' program kerja terkait.');
            return;
        }

        $code = $dept->code;
        $name = $dept->name;
        $dept->delete();

        session()->flash('message', 'Departemen [' . $code . '] ' . $name . ' berhasil dihapus!');
    }

    public function toggleActive($id)
    {
        if (!auth()->user()?->can('manage settings') && !auth()->user()?->can('manage users') && !auth()->user()?->can('can_manage_users')) {
            return;
        }

        $dept = Department::findOrFail($id);
        $dept->is_active = !$dept->is_active;
        $dept->save();

        session()->flash('message', 'Status aktif departemen [' . $dept->code . '] berhasil diubah!');
    }

    public function render()
    {
        $query = Department::query()->withCount(['objectives', 'actionPlans']);

        if ($this->search) {
            $s = '%' . trim($this->search) . '%';
            $query->where(function($q) use ($s) {
                $q->where('code', 'like', $s)
                  ->orWhere('name', 'like', $s)
                  ->orWhere('manager_title', 'like', $s);
            });
        }

        $departments = $query->orderBy('sort_order', 'asc')->orderBy('code', 'asc')->paginate(15);

        return view('livewire.master-departments', [
            'departments' => $departments,
        ])->layout('layouts.app', ['title' => 'Master Departemen']);
    }
}
