<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><i class="fas fa-users-cog mr-2"></i>Manage User</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Administrasi / Manage User</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif

            <div class="card card-primary card-outline">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <input type="text" wire:model.live="search" class="form-control" placeholder="Cari nama / email...">
                                <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select wire:model.live="filterRole" class="form-control form-control-sm">
                                <option value="">Semua Role</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select wire:model.live="filterStatus" class="form-control form-control-sm">
                                <option value="">Semua Status</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-right">
                            <button wire:click="openCreate" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i> Tambah User</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th><th>Nama</th><th>Email</th><th>Role</th><th>Entitas</th><th>Dept</th><th>Status</th><th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $u)
                                    <tr>
                                        <td>{{ $loop->iteration + ($users->currentPage()-1)*$users->perPage() }}</td>
                                        <td><strong>{{ $u->name }}</strong></td>
                                        <td>{{ $u->email }}</td>
                                        <td>
                                            @foreach($u->roles as $rl)
                                                <span class="badge 
                                                    @if($rl->name=='Super Admin') badge-danger
                                                    @elseif($rl->name=='Admin FAT') badge-info
                                                    @elseif($rl->name=='Admin HRIS') badge-success
                                                    @elseif($rl->name=='Kepala Departemen') badge-warning
                                                    @elseif($rl->name=='Operator') badge-secondary
                                                    @else badge-light border @endif
                                                ">{{ $rl->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @if($u->entity)
                                                <span class="badge badge-info">{{ $u->entity->name }}</span>
                                            @else
                                                <span class="badge badge-light border" title="Dapat berpindah antarentitas">Holding</span>
                                            @endif
                                        </td>
                                        <td>{{ $u->dept_code ?? '-' }}</td>
                                        <td>
                                            @if($u->is_active)
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-danger">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button wire:click="openEdit({{ $u->id }})" class="btn btn-outline-primary" title="Ubah"><i class="fas fa-edit"></i></button>
                                                <button wire:click="toggleActive({{ $u->id }})" class="btn btn-outline-warning" title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    <i class="fas {{ $u->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                                </button>
                                                <button wire:click="confirmDelete({{ $u->id }})" class="btn btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada pengguna.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($users->hasPages())
                <div class="card-footer clearfix">
                    {{ $users->links() }}
                </div>
                @endif
            </div>

            <div class="callout callout-info">
                <h6><i class="fas fa-shield-alt mr-1"></i> Pengaman</h6>
                <p class="mb-0 small">Super Admin terakhir tidak dapat dihapus atau dinonaktifkan. Nonaktifkan = soft disable (tidak dapat login), bukan hard delete audit.</p>
            </div>
        </div>
    </section>

    {{-- Create/Edit Modal --}}
    @if($showModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title">{{ $isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna Baru' }}</h5>
                    <button type="button" class="close text-white" wire:click="closeModal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama *</label>
                        <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" wire:model="email" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group" x-data="{ show: false, nilai: '' }">
                        <label>Password {{ $isEdit ? '(kosongkan jika tidak diubah)' : '*' }}</label>
                        <div class="input-group">
                            {{-- wire:model tetap dipakai agar pengikatan Livewire tidak berubah;
                                 Alpine hanya mengatur tampil/sembunyi dan daftar syarat. --}}
                            <input :type="show ? 'text' : 'password'"
                                   wire:model="password"
                                   x-on:input="nilai = $event.target.value"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password"
                                   placeholder="{{ $isEdit ? 'Biarkan kosong bila tidak diganti' : 'Password baru' }}">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary"
                                        @click="show = ! show"
                                        :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                        :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                        :aria-pressed="show ? 'true' : 'false'">
                                    <i class="fas" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>
                        @error('password') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror

                        {{-- Daftar syarat, tercentang saat terpenuhi. --}}
                        <ul class="list-unstyled small mt-2 mb-0" x-show="nilai.length > 0" x-cloak>
                            @foreach($passwordChecklist as $syarat)
                                <li x-data="{ lolos: false }"
                                    x-effect="lolos = new RegExp(@js($syarat['regex'])).test(nilai)"
                                    :class="lolos ? 'text-success' : 'text-muted'">
                                    <i class="fas" :class="lolos ? 'fa-check-circle' : 'fa-circle-notch'"></i>
                                    {{ $syarat['label'] }}
                                </li>
                            @endforeach
                        </ul>
                        <small class="text-muted d-block mt-1" x-show="nilai.length === 0">{{ $passwordHint }}</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Role *</label>
                            <select wire:model="role" class="form-control @error('role') is-invalid @enderror">
                                @foreach($roles as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                            @error('role') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Entitas</label>
                            <select wire:model.live="entity_id" class="form-control @error('entity_id') is-invalid @enderror">
                                @if($holdingMode)
                                    <option value="">Semua entitas (level holding)</option>
                                @endif
                                @foreach($entities as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }} — {{ $e->legal_name }}</option>
                                @endforeach
                            </select>
                            @error('entity_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="text-muted">
                                @if($holdingMode)
                                    Pengguna level holding dapat berpindah antarentitas; selainnya hanya melihat entitasnya sendiri.
                                @else
                                    Instalasi ini khusus satu entitas (BSC_HOLDING_MODE=false).
                                @endif
                            </small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Unit kerja</label>
                            <select wire:model="dept_code" class="form-control @error('dept_code') is-invalid @enderror" @disabled(! $entity_id)>
                                <option value="">{{ $entity_id ? '— Pilih unit kerja —' : 'Pilih entitas lebih dulu' }}</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->code }}">{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                            @error('dept_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" wire:model.live="is_active" class="custom-control-input" id="isActiveSwitch">
                            <label class="custom-control-label" for="isActiveSwitch">{{ $is_active ? 'Aktif' : 'Nonaktif' }}</label>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" wire:model="must_change_password" class="custom-control-input" id="mustChangePasswordSwitch">
                            <label class="custom-control-label" for="mustChangePasswordSwitch">
                                Wajib ganti password saat login berikutnya
                            </label>
                        </div>
                        <small class="text-muted">
                            Disarankan menyala bila kata sandi di atas Anda tentukan sendiri, sehingga hanya
                            pemilik akun yang mengetahui kata sandi sebenarnya.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button wire:click="closeModal" class="btn btn-secondary">Batal</button>
                    <button wire:click="saveUser" class="btn btn-primary">{{ $isEdit ? 'Perbarui' : 'Simpan' }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Delete Confirm --}}
    @if($showDeleteModal)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="close text-white" wire:click="cancelDelete"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Yakin ingin menghapus pengguna ini? Aksi tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button wire:click="cancelDelete" class="btn btn-secondary">Batal</button>
                    <button wire:click="deleteUser" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
