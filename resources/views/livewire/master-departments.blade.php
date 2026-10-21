<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-building text-teal mr-2"></i> Master Departemen
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Master Departemen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card card-outline card-teal shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-sitemap mr-1"></i> Daftar Unit Kerja & Departemen Herbatech
                    </h3>
                    <div class="card-tools d-flex flex-wrap align-items-center">
                        <div class="input-group input-group-sm mr-2 mb-1 mb-md-0" style="width: 220px;">
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari kode/nama/manager...">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                        @if(auth()->user()?->can('manage settings') || auth()->user()?->can('manage users') || auth()->user()?->can('can_manage_users'))
                            <button wire:click="openCreateModal" class="btn btn-sm btn-teal font-weight-bold shadow-sm">
                                <i class="fas fa-plus-circle mr-1"></i> Tambah Departemen
                            </button>
                        @endif
                    </div>
                </div>

                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 5%;" class="text-center">No</th>
                                <th style="width: 10%;">Kode</th>
                                <th style="width: 22%;">Nama Departemen</th>
                                <th style="width: 20%;">Jabatan Pimpinan</th>
                                <th style="width: 12%;" class="text-center">Tema Warna</th>
                                <th style="width: 10%;" class="text-center">Sasaran Mutu</th>
                                <th style="width: 9%;" class="text-center">Status</th>
                                <th style="width: 12%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $index => $dept)
                                <tr>
                                    <td class="text-center">{{ $departments->firstItem() + $index }}</td>
                                    <td>
                                        <span class="badge badge-dark px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                            {{ $dept->code }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark">{{ $dept->name }}</div>
                                        @if($dept->description)
                                            <small class="text-muted d-block">{{ Str::limit($dept->description, 60) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-secondary"><i class="fas fa-user-tie mr-1 text-teal"></i> {{ $dept->manager_title ?: '-' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center">
                                            <span class="d-inline-block rounded-circle mr-2 border shadow-xs" style="width: 18px; height: 18px; background-color: {{ $dept->color }};"></span>
                                            <code>{{ $dept->color }}</code>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('department-objectives', ['selectedDept' => $dept->code]) }}" class="badge badge-info px-2 py-1" title="Lihat Sasaran Mutu">
                                            {{ $dept->objectives_count }} KPI
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        @if(auth()->user()?->can('manage settings') || auth()->user()?->can('manage users') || auth()->user()?->can('can_manage_users'))
                                            <button wire:click="toggleActive({{ $dept->id }})" class="btn btn-xs {{ $dept->is_active ? 'btn-success' : 'btn-secondary' }}" title="Klik untuk ubah status">
                                                <i class="fas {{ $dept->is_active ? 'fa-check' : 'fa-times' }} mr-1"></i>
                                                {{ $dept->is_active ? 'Aktif' : 'Non-Aktif' }}
                                            </button>
                                        @else
                                            <span class="badge {{ $dept->is_active ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $dept->is_active ? 'Aktif' : 'Non-Aktif' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(auth()->user()?->can('manage settings') || auth()->user()?->can('manage users') || auth()->user()?->can('can_manage_users'))
                                            <button wire:click="openEditModal({{ $dept->id }})" class="btn btn-xs btn-primary shadow-xs" title="Edit Departemen">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button wire:click="deleteDepartment({{ $dept->id }})" wire:confirm="Hapus departemen '{{ $dept->code }} - {{ $dept->name }}'?" class="btn btn-xs btn-danger shadow-xs ml-1" title="Hapus Departemen">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @else
                                            <span class="text-muted small"><i class="fas fa-lock"></i> View Only</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Tidak ada data departemen yang ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($departments->hasPages())
                    <div class="card-footer py-2">
                        <div class="float-right">
                            {{ $departments->links() }}
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </section>

    <!-- MODAL FORM TAMBAH / UBAH DEPARTEMEN -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-teal shadow-lg">
                    <div class="modal-header bg-teal text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas {{ $isEditMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                            {{ $isEditMode ? 'Ubah Data Departemen' : 'Tambah Departemen Baru' }}
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeModal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form wire:submit.prevent="saveDepartment">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Kode Dept <span class="text-danger">*</span></label>
                                        <input type="text" wire:model="code" class="form-control text-uppercase @error('code') is-invalid @enderror" placeholder="cth: OPS, MKT" maxlength="30" {{ $isEditMode ? 'readonly' : '' }}>
                                        @error('code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Nama Departemen <span class="text-danger">*</span></label>
                                        <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="cth: Operations & Supply Chain">
                                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">Jabatan Pimpinan (Manager)</label>
                                <input type="text" wire:model="manager_title" class="form-control @error('manager_title') is-invalid @enderror" placeholder="cth: Operation Manager">
                                @error('manager_title') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold">Deskripsi / Ruang Lingkup</label>
                                <textarea wire:model="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="Keterangan tugas dan fungsi departemen..."></textarea>
                                @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Warna Aksen (Wiring Diagram)</label>
                                        <div class="input-group">
                                            <input type="color" wire:model="color" class="form-control form-control-color" style="max-width: 50px; height: 38px; padding: 2px;">
                                            <input type="text" wire:model="color" class="form-control font-weight-bold" placeholder="#0d9488">
                                        </div>
                                        @error('color') <span class="text-danger small">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Urutan</label>
                                        <input type="number" wire:model="sort_order" class="form-control @error('sort_order') is-invalid @enderror" min="0">
                                        @error('sort_order') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="font-weight-bold">Status</label>
                                        <div class="custom-control custom-switch mt-2">
                                            <input type="checkbox" wire:model="is_active" class="custom-control-input" id="activeSwitch">
                                            <label class="custom-control-label" for="activeSwitch">{{ $is_active ? 'Aktif' : 'Non-Aktif' }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Batal</button>
                            <button type="submit" class="btn btn-teal font-weight-bold">
                                <i class="fas fa-save mr-1"></i> {{ $isEditMode ? 'Simpan Perubahan' : 'Tambah Departemen' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
