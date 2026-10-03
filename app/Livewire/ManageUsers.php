<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesWrites;
use App\Models\Entity;
use App\Models\User;
use App\Models\WorkUnit;
use App\Support\EntityContext;
use App\Support\PasswordPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class ManageUsers extends Component
{
    use AuthorizesWrites;
    use WithFileUploads;
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

    /** Berkas foto yang baru dipilih; null = foto tidak diubah. */
    public $photoUpload = null;

    /** Foto yang tersimpan saat ini, untuk pratinjau di formulir. */
    public ?string $currentPhotoUrl = null;

    /** Dicentang = foto dilepas, kembali memakai inisial nama. */
    public bool $removePhoto = false;

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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'role' => 'required|exists:roles,name',
            'entity_id' => ['nullable', 'integer', Rule::in($this->selectableEntities()->pluck('id')->all())],
            // Bila entitas dipilih, departemen harus salah satu unit kerjanya.
            // Pengguna level holding tidak terikat unit entitas mana pun, jadi
            // kode lamanya dibiarkan apa adanya.
            'dept_code' => $this->entity_id
                ? ['nullable', 'string', 'max:30', Rule::in($this->unitsForSelectedEntity()->pluck('code')->all())]
                : ['nullable', 'string', 'max:30'],
            // Foto: gambar kecil saja — navbar hanya menampilkan lingkaran 32 px.
            'photoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
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

    /**
     * Periksa fotonya begitu dipilih, bukan menunggu tombol Simpan — pengguna
     * langsung tahu berkasnya ditolak dan mengapa.
     */
    public function updatedPhotoUpload(): void
    {
        $this->validateOnly('photoUpload');
    }

    /** Nama kolom yang enak dibaca pada pesan validasi. */
    protected function validationAttributes(): array
    {
        return [
            'photoUpload' => 'foto',
            'dept_code' => 'departemen',
            'entity_id' => 'entitas',
            'is_active' => 'status aktif',
            'must_change_password' => 'wajib ganti kata sandi',
        ];
    }

    public function openCreate()
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $user = $this->manageableUsers()->findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->getRoleNames()->first() ?? 'Viewer';
        $this->dept_code = $user->dept_code ?? '';
        $this->entity_id = $user->entity_id ?? $this->installationDefault();
        $this->is_active = (bool) $user->is_active;
        $this->must_change_password = (bool) $user->must_change_password;
        $this->currentPhotoUrl = $user->photoUrl();
        $this->photoUpload = null;
        $this->removePhoto = false;
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
        $this->entity_id = $this->installationDefault();
        $this->is_active = true;
        $this->must_change_password = true;
        $this->photoUpload = null;
        $this->currentPhotoUrl = null;
        $this->removePhoto = false;
        $this->resetErrorBag();
    }

    public function saveUser()
    {
        if ($this->lacksPermission('manage users', 'can_manage_users')) {
            return;
        }

        if ($entitasSaya = auth()->user()?->entity_id) {
            $this->entity_id = $entitasSaya; // sebelum validasi: aturan unit ikut entitas ini
        }

        $this->validate();

        // Guard: last Super Admin cannot be deactivated via edit
        if ($this->isEdit && $this->role !== 'Super Admin') {
            $user = $this->manageableUsers()->find($this->userId);
            if ($user && $user->hasRole('Super Admin')) {
                if ($this->isLastSuperAdmin($user->id)) {
                    session()->flash('error', 'Super Admin terakhir tidak dapat diubah role-nya atau dinonaktifkan!');

                    return;
                }
            }
        }
        if ($this->isEdit && ! $this->is_active) {
            $user = $this->manageableUsers()->find($this->userId);
            if ($user && $user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
                session()->flash('error', 'Super Admin terakhir tidak dapat dinonaktifkan!');

                return;
            }
        }

        if ($this->isEdit) {
            $user = $this->manageableUsers()->findOrFail($this->userId);
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'dept_code' => $this->dept_code ?: null,
                'entity_id' => $this->entity_id ?: null,
                'is_active' => $this->is_active,
                'must_change_password' => $this->must_change_password,
            ];
            if (! empty($this->password)) {
                $data['password'] = Hash::make($this->password);
                $data['password_changed_at'] = now();
            }
            $user->update($data);
            $user->syncRoles([$this->role]);
            session()->flash('message', 'Pengguna '.$user->name.' berhasil diperbarui!');
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
            session()->flash('message', 'Pengguna '.$user->name.' berhasil ditambahkan!');
        }

        $this->applyPhoto($user);

        $this->closeModal();
    }

    /**
     * Simpan / lepas foto pengguna.
     *
     * Berkas lama selalu dihapus saat diganti maupun dilepas, supaya disk tidak
     * menumpuk foto yang tidak lagi dirujuk siapa pun.
     */
    private function applyPhoto(User $user): void
    {
        if (! $this->photoUpload && ! $this->removePhoto) {
            return;
        }

        $lama = $user->photo_path;

        $user->update([
            'photo_path' => $this->photoUpload
                ? $this->photoUpload->store('avatar', 'public')
                : null,
        ]);

        if ($lama && $lama !== $user->photo_path) {
            Storage::disk('public')->delete($lama);
        }

        $this->photoUpload = null;
        $this->removePhoto = false;
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

        if (! $this->confirmDeleteId) {
            return;
        }
        $user = $this->manageableUsers()->findOrFail($this->confirmDeleteId);

        if ($user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
            session()->flash('error', 'Super Admin terakhir tidak dapat dihapus!');
            $this->cancelDelete();

            return;
        }

        $name = $user->name;
        $foto = $user->photo_path;
        $user->delete();

        // Fotonya ikut dihapus; tanpa ini berkasnya tertinggal selamanya di disk.
        if ($foto) {
            Storage::disk('public')->delete($foto);
        }

        session()->flash('message', 'Pengguna '.$name.' berhasil dihapus!');
        $this->cancelDelete();
    }

    public function toggleActive($id)
    {
        if ($this->lacksPermission('manage users', 'can_manage_users')) {
            return;
        }

        $user = $this->manageableUsers()->findOrFail($id);
        if (! $user->is_active) {
            $user->update(['is_active' => true]);
            session()->flash('message', 'Pengguna '.$user->name.' diaktifkan kembali!');

            return;
        }
        // Deactivating
        if ($user->hasRole('Super Admin') && $this->isLastSuperAdmin($user->id)) {
            session()->flash('error', 'Super Admin terakhir tidak dapat dinonaktifkan!');

            return;
        }
        $user->update(['is_active' => false]);
        session()->flash('message', 'Pengguna '.$user->name.' dinonaktifkan!');
    }

    private function isLastSuperAdmin($excludeUserId = null): bool
    {
        $query = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->where('is_active', true);
        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->count() === 0;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterRole()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    /**
     * Entitas yang boleh dipilih di formulir: semua entitas aktif di instalasi
     * holding, hanya entitas instalasi di instalasi satu entitas.
     */
    /**
     * Pengguna yang boleh dikelola: admin level holding (tanpa entitas) mengelola
     * semua; admin yang terikat satu entitas hanya pengguna entitasnya sendiri —
     * tidak dapat mengubah pengguna entitas lain atau menjadikan siapa pun level holding.
     */
    private function manageableUsers(): Builder
    {
        $query = User::query();

        if ($entitasSaya = auth()->user()?->entity_id) {
            $query->where('entity_id', $entitasSaya);
        }

        return $query;
    }

    private function selectableEntities(): Collection
    {
        if ($entitasSaya = auth()->user()?->entity_id) {
            return Entity::whereKey($entitasSaya)->get();
        }

        $konteks = app(EntityContext::class);

        if (! $konteks->isHoldingMode() && ($id = $konteks->installationEntityId())) {
            return Entity::whereKey($id)->get();
        }

        return Entity::active()->get();
    }

    /** Instalasi satu entitas: pengguna baru langsung tertaut ke entitas itu. */
    private function installationDefault(): string
    {
        if ($entitasSaya = auth()->user()?->entity_id) {
            return (string) $entitasSaya;
        }

        $konteks = app(EntityContext::class);

        return $konteks->isHoldingMode() ? '' : (string) ($konteks->installationEntityId() ?? '');
    }

    /** Ganti entitas → departemen lama belum tentu ada di entitas baru. */
    public function updatedEntityId(): void
    {
        $this->dept_code = '';
    }

    /**
     * Unit kerja aktif milik entitas yang dipilih pada formulir — bukan entitas
     * yang sedang dibuka Super Admin, karena ia dapat mengatur pengguna entitas lain.
     */
    private function unitsForSelectedEntity(): Collection
    {
        if (! $this->entity_id) {
            return collect();
        }

        return WorkUnit::withoutGlobalScopes()
            ->where('entity_id', $this->entity_id)
            ->where('is_active', true)
            ->orderBy('sort')
            ->get();
    }

    public function render()
    {
        $query = $this->manageableUsers()->with(['roles', 'entity']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }
        if ($this->filterRole) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $this->filterRole));
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
            'entities' => $this->selectableEntities(),
            // Admin yang terikat satu entitas tidak dapat membuat pengguna level holding.
            'holdingMode' => app(EntityContext::class)->isHoldingMode() && ! auth()->user()?->entity_id,
            'units' => $this->unitsForSelectedEntity(),
        ])->layout('layouts.app', ['title' => 'Manage User']);
    }
}
