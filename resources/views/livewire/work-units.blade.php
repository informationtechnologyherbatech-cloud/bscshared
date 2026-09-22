<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-building-user mr-2"></i>Unit Kerja</h1>
                    <small class="text-muted">
                        Struktur unit kerja
                        <strong>{{ $entity?->legal_name ?? 'entitas aktif' }}</strong>
                        @if($entity) · {{ $entity->industryLabel() }} @endif
                    </small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Konfigurasi / Unit Kerja</li>
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

            <div class="callout callout-info py-2 small">
                <i class="fas fa-info-circle mr-1"></i>
                Unit kerja di sini menjadi pilihan departemen pada <strong>Objective Departemen</strong> dan
                <strong>Program Kerja</strong>. Tiap entitas punya daftarnya sendiri.
                Kode unit yang sudah dipakai data tidak dapat diubah — ubah namanya saja, atau nonaktifkan
                bila unit tidak lagi digunakan.
            </div>

            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-list mr-1"></i> Daftar Unit Kerja
                        <span class="badge badge-secondary ml-1">{{ $totalAktif }} aktif</span>
                    </h3>
                    <div class="card-tools d-flex flex-wrap align-items-center">
                        <div class="input-group input-group-sm mr-2 mb-1 mb-md-0" style="width: 220px;">
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari kode / nama / stream...">
                            <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        </div>
                        <div class="custom-control custom-switch mr-3 mb-1 mb-md-0">
                            <input type="checkbox" wire:model.live="showInactive" class="custom-control-input" id="showInactive">
                            <label class="custom-control-label small" for="showInactive">Tampilkan nonaktif</label>
                        </div>
                        @can('manage units')
                            <button wire:click="openCreate" class="btn btn-teal btn-sm" style="background:#17a2b8;border-color:#17a2b8;color:#fff;">
                                <i class="fas fa-plus mr-1"></i> Tambah Unit
                            </button>
                        @endcan
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped m-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:90px">Kode</th>
                                <th>Nama unit kerja</th>
                                <th>Stream</th>
                                <th>Lapor ke</th>
                                <th class="text-center">Sasaran mutu</th>
                                <th class="text-center">Status</th>
                                @can('manage units')
                                    <th class="text-right" style="width:140px">Aksi</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($units as $unit)
                                <tr class="{{ $unit->is_active ? '' : 'text-muted' }}">
                                    <td><span class="badge badge-dark">{{ $unit->code }}</span></td>
                                    <td>
                                        <div class="font-weight-bold">{{ $unit->name }}</div>
                                        @if($unit->scope)
                                            <small class="text-muted">{{ $unit->scope }}</small>
                                        @endif
                                    </td>
                                    <td class="small">{{ $unit->stream ?: '—' }}</td>
                                    <td class="small">{{ $unit->reports_to ?: '—' }}</td>
                                    <td class="text-center">{{ $pemakaian[$unit->code] ?? 0 }}</td>
                                    <td class="text-center">
                                        @if($unit->is_active)
                                            <span class="badge badge-success">Aktif</span>
                                        @else
                                            <span class="badge badge-secondary">Nonaktif</span>
                                        @endif
                                    </td>
                                    @can('manage units')
                                        <td class="text-right">
                                            <div class="btn-group btn-group-sm">
                                                <button wire:click="openEdit({{ $unit->id }})" class="btn btn-outline-primary" title="Ubah">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="toggleActive({{ $unit->id }})" class="btn btn-outline-warning"
                                                        title="{{ $unit->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    <i class="fas {{ $unit->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                                </button>
                                                <button wire:click="confirmDelete({{ $unit->id }})" class="btn btn-outline-danger" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                        Belum ada unit kerja{{ $search !== '' ? ' yang cocok dengan pencarian' : '' }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- Formulir tambah / ubah --}}
    @if($showModal)
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas {{ $unitId ? 'fa-pen-to-square' : 'fa-building-user' }}"></i></span>
                        <div>
                            <h5 class="modal-title">{{ $unitId ? 'Ubah Unit Kerja' : 'Tambah Unit Kerja' }}</h5>
                            <small>Kode unit dipakai di seluruh aplikasi</small>
                        </div>
                        <button type="button" class="modal-close" wire:click="closeModal" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Kode *</label>
                                <input type="text" wire:model.blur="code" maxlength="20"
                                       class="form-control text-uppercase @error('code') is-invalid @enderror"
                                       placeholder="mis. SCM" @disabled($codeLocked)>
                                @error('code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                @if($codeLocked)
                                    <small class="text-muted"><i class="fas fa-lock mr-1"></i>Sudah dipakai data, tidak dapat diubah.</small>
                                @endif
                            </div>
                            <div class="col-md-9 form-group">
                                <label>Nama unit kerja *</label>
                                <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="mis. Supply Chain">
                                @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Stream / kelompok</label>
                                <input type="text" wire:model="stream" list="daftar-stream"
                                       class="form-control @error('stream') is-invalid @enderror"
                                       placeholder="mis. Midstream EICK / Business Support">
                                <datalist id="daftar-stream">
                                    @foreach($streams as $s)
                                        <option value="{{ $s }}">
                                    @endforeach
                                </datalist>
                                @error('stream') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Lapor ke</label>
                                <input type="text" wire:model="reports_to" class="form-control @error('reports_to') is-invalid @enderror"
                                       placeholder="mis. GM EICK / CFO">
                                @error('reports_to') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-2 form-group">
                                <label>Urutan</label>
                                <input type="number" wire:model="sort" min="0" class="form-control @error('sort') is-invalid @enderror">
                                @error('sort') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Lingkup tugas</label>
                            <textarea wire:model="scope" rows="2" class="form-control @error('scope') is-invalid @enderror"
                                      placeholder="Ringkasan tanggung jawab unit"></textarea>
                            @error('scope') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" wire:model="is_active" class="custom-control-input" id="unitActive">
                            <label class="custom-control-label" for="unitActive">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-ft">
                        <button wire:click="closeModal" class="btn btn-ghost">Batal</button>
                        <button wire:click="save" class="btn btn-teal"><i class="fas fa-save mr-1"></i> Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Konfirmasi hapus --}}
    @if($confirmDeleteId)
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-confirm">
                    <button type="button" class="modal-close modal-close--float" wire:click="cancelDelete" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    <div class="modal-body">
                        <div class="modal-confirm-icon"><i class="fas fa-trash-can"></i></div>
                        <h5>Hapus unit kerja ini?</h5>
                        <p>Unit kerja yang masih dipakai sasaran mutu atau program kerja tidak dapat dihapus — nonaktifkan saja agar riwayatnya tetap utuh.</p>
                    </div>
                    <div class="modal-ft">
                        <button type="button" wire:click="cancelDelete" class="btn btn-ghost">Batal</button>
                        <button type="button" wire:click="delete" class="btn btn-danger"><i class="fas fa-trash-can mr-1"></i> Ya, hapus</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
