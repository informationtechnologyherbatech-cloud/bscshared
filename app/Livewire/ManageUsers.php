<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Support\PasswordPolicy;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ManageUsers extends Component
{
    use AuthorizesWrites;
    use WithPagination;

    public $search = '';
    public $filterRole = '';
    public $filterStatus = ''; // active, inactive, all

    // Form fields
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $role = 'Viewer';
    public $dept_code = '';

    /** Kosong = pengguna level holding yang dapat berpindah antarentitas. */
    public $entity_id = '';
    public $is_active = true;

    /** Paksa pengguna mengganti kata sandi pada login berikutnya. */
    public $must_change_password = true;

    public $showModal = false;
    public $isEdit = false;

    public $confirmDeleteId = null;
    public $showDeleteModal = false;

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|min:3|max:255',
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($this->userId)],
            'role' => 'required|exists:roles,name',
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            // Bila entitas dipilih, departemen harus salah satu unit kerjanya.
            // Pengguna level holding tidak terikat unit entitas mana pun, jadi
            // kode lamanya dibiarkan apa adanya.
            'dept_code' => $this->entity_id
                ? ['nullable', 'string', 'max:30', Rule::in($this->unitsForSelectedEntity()->pluck('code')->all())]
                : ['nullable', 'string', 'max:30'],
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
        // Kata sandi lama tidak dipaksa berubah, tetapi setiap kali diisi harus
        // memenuhi syarat kekuatan pada config/security.php.
        $rules['password'] = [
            $this->isEdit ? 'nullable' : 'required',
            'string',
            'max:255',
            PasswordPolicy::rule(),
        ];

        return $rules;
    }

    public function openCreate()
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->getRoleNames()->first() ?? 'Viewer';
        $this->dept_code = $user->dept_code ?? '';
        $this->entity_id = $user->entity_id ?? '';
        $this->is_active = (bool) $user->is_active;
        $this->must_change_password = (bool) $user->must_change_password;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'Viewer';
        $this->dept_code = '';
        $this->entity_id = '';
        $this->is_active = true;
        $this->must_change_password = true;
        $this->resetErrorBag();
    }

    public function saveUser()
    {
        if ($this->lacksPermission('manage users', 'can_manage_users')) {
            return;
        }

        $this->validate();

        // Guard: last Super Admin cannot be deactivated via edit
        if ($this->isEdit && $this->role !== 'Super Admin') {
            $user = User::find($this->userId);
            if ($user && $user->hasRole('Super Admin')) {
                if ($this->isLastSuperAdmin($user->id)) {
                    session()->flash('error', 'Super Admin terakhir tidak dapat diubah role-nya atau dinonaktifkan!');
                    return;
                }
            }
        }
        if ($this->isEdit && !$this->is_active) {
            $user = User::find($this->userId);
            if ($user && $user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
                session()->flash('error', 'Super Admin terakhir tidak dapat dinonaktifkan!');
                return;
            }
        }

        if ($this->isEdit) {
            $user = User::findOrFail($this->userId);
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'dept_code' => $this->dept_code ?: null,
                'entity_id' => $this->entity_id ?: null,
                'is_active' => $this->is_active,
                'must_change_password' => $this->must_change_password,
            ];
            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
                $data['password_changed_at'] = now();
            }
            $user->update($data);
            $user->syncRoles([$this->role]);
            session()->flash('message', 'Pengguna ' . $user->name . ' berhasil diperbarui!');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'dept_code' => $this->dept_code ?: null,
                'entity_id' => $this->entity_id ?: null,
                'is_active' => $this->is_active,
                'must_change_password' => $this->must_change_password,
                'password_changed_at' => now(),
            ]);
            $user->assignRole($this->role);
            session()->flash('message', 'Pengguna ' . $user->name . ' berhasil ditambahkan!');
        }

        $this->closeModal();
    }

    public function confirmDelete($id)
    {
        $this->confirmDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->confirmDeleteId = null;
        $this->showDeleteModal = false;
    }

    public function deleteUser()
    {
        if ($this->lacksPermission('manage users', 'can_manage_users')) {
            return;
        }

        if (!$this->confirmDeleteId) return;
        $user = User::findOrFail($this->confirmDeleteId);

        if ($user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
            session()->flash('error', 'Super Admin terakhir tidak dapat dihapus!');
            $this->cancelDelete();
            return;
        }

        $name = $user->name;
        $user->delete();
        session()->flash('message', 'Pengguna ' . $name . ' berhasil dihapus!');
        $this->cancelDelete();
    }

    public function toggleActive($id)
    {
        if ($this->lacksPermission('manage users', 'can_manage_users')) {
            return;
        }

        $user = User::findOrFail($id);
        if (!$user->is_active) {
            $user->update(['is_active' => true]);
            session()->flash('message', 'Pengguna ' . $user->name . ' diaktifkan kembali!');
            return;
        }
        // Deactivating
        if ($user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
            session()->flash('error', 'Super Admin terakhir tidak dapat dinonaktifkan!');
            return;
        }
        $user->update(['is_active' => false]);
        session()->flash('message', 'Pengguna ' . $user->name . ' dinonaktifkan!');
    }

    private function isLastSuperAdmin($excludeUserId = null): bool
    {
        $query = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))
            ->where('is_active', true);
        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }
        return $query->count() === 0;
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterRole() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

    /** Ganti entitas → departemen lama belum tentu ada di entitas baru. */
    public function updatedEntityId(): void
    {
        $this->dept_code = '';
    }

    /**
     * Unit kerja aktif milik entitas yang dipilih pada formulir — bukan entitas
     * yang sedang dibuka Super Admin, karena ia dapat mengatur pengguna entitas lain.
     */
    private function unitsForSelectedEntity(): \Illuminate\Support\Collection
    {
        if (! $this->entity_id) {
            return collect();
        }

        return \App\Models\WorkUnit::withoutGlobalScopes()
            ->where('entity_id', $this->entity_id)
            ->where('is_active', true)
            ->orderBy('sort')
            ->get();
    }

    public function render()
    {
        $query = User::with(['roles', 'entity']);

        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }
        if ($this->filterRole) {
            $query->whereHas('roles', fn($q) => $q->where('name', $this->filterRole));
        }
        if ($this->filterStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->latest()->paginate(10);
        $roles = Role::pluck('name')->toArray();

        return view('livewire.manage-users', [
            'passwordChecklist' => PasswordPolicy::checklist(),
            'passwordHint' => PasswordPolicy::hint(),
            'users' => $users,
            'roles' => $roles,
            'entities' => \App\Models\Entity::active()->get(),
            'units' => $this->unitsForSelectedEntity(),
        ])->layout('layouts.app', ['title' => 'Manage User']);
    }
}
