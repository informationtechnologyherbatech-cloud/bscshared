<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-coins text-success mr-2"></i> Rasio Keuangan (Level 2)
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="form-inline float-right">
                        <label for="periodSelR" class="mr-2 font-weight-bold">Periode:</label>
                        <select wire:model.live="selectedPeriod" id="periodSelR" class="form-control form-control-sm border-success mr-2">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        <span class="badge {{ $isClosed ? 'badge-secondary' : 'badge-success' }} p-2">
                            <i class="fas {{ $isClosed ? 'fa-lock' : 'fa-lock-open' }} mr-1"></i> {{ $isClosed ? 'CLOSED (Terkunci)' : 'OPEN' }}
                        </span>
                    </div>
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

            @if($isClosed)
                <div class="alert alert-secondary py-2 border-dark" role="alert">
                    <i class="fas fa-lock mr-2"></i> <strong>Periode {{ $selectedPeriod }} DITUTUP (CLOSED):</strong> Nilai rasio keuangan terkunci untuk pengubahan langsung.
                </div>
            @endif

            <div class="card card-outline card-success">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-list mr-1"></i> Daftar 7 Rasio Keuangan Utama (5 Kategori)
                    </h3>
                    <div class="card-tools d-flex flex-wrap align-items-center">
                        <select wire:model.live="selectedCategory" class="form-control form-control-sm mr-2 mb-1 mb-md-0" style="width: 150px;">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="selectedStatus" class="form-control form-control-sm mr-2 mb-1 mb-md-0" style="width: 160px;">
                            <option value="">Semua Status</option>
                            <option value="bermasalah">⚠️ Hanya Bermasalah</option>
                            <option value="Tercapai">Tercapai (&ge;100%)</option>
                            <option value="Waspada">Waspada (80-99%)</option>
                            <option value="Di Bawah Target">Di Bawah Target (&lt;80%)</option>
                        </select>
                        @can('manage ratios')
                            <button wire:click="syncFromFinanceApp" 
                                    wire:loading.attr="disabled"
                                    class="btn btn-sm btn-outline-success mr-2 mb-1 mb-md-0 {{ $isClosed ? 'disabled' : '' }}" 
                                    title="Tarik data otomatis dari Matriks Analisa Rasio Aplikasi Finance"
                                    {{ $isClosed ? 'disabled' : '' }}>
                                <span wire:loading.remove wire:target="syncFromFinanceApp">
                                    <i class="fas fa-sync-alt mr-1"></i> Tarik Data Finance
                                </span>
                                <span wire:loading wire:target="syncFromFinanceApp">
                                    <i class="fas fa-spinner fa-spin mr-1"></i> Menyinkronkan...
                                </span>
                            </button>
                            <button wire:click="openCreateModal" class="btn btn-sm btn-success mb-1 mb-md-0 {{ $isClosed ? 'disabled' : '' }}" {{ $isClosed ? 'disabled' : '' }}>
                                <i class="fas fa-plus-circle mr-1"></i> Tambah Rasio
                            </button>
                        @endcan
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered m-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 35%;">Nama Rasio</th>
                                <th class="text-center" style="width: 15%;">Benchmark / Target</th>
                                <th class="text-center" style="width: 15%;">Nilai Riil (Actual)</th>
                                <th class="text-center" style="width: 12%;">Capaian (%)</th>
                                <th class="text-center" style="width: 12%;">Status</th>
                                <th class="text-center" style="width: 11%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $groupedRatios = $ratios->groupBy('category');
                            @endphp

                            @forelse($groupedRatios as $categoryName => $catRatios)
                                <!-- Baris Pemisah Header Kategori Warna Kuning Sesuai Gambar -->
                                <tr style="background-color: #ffff00;">
                                    <td colspan="6" class="py-2 px-3 font-weight-bold text-dark text-uppercase border-top border-bottom border-dark" style="font-size: 15px; letter-spacing: 0.5px;">
                                        <strong>{{ $categoryName }}</strong>
                                    </td>
                                </tr>

                                @foreach($catRatios as $r)
                                    <tr>
                                        <td class="font-weight-bold pl-4 text-dark">
                                            {{ $r->ratio_name }}
                                        </td>
                                        <td class="text-center font-weight-bold text-muted">
                                            {{ $r->benchmark_display }}
                                        </td>
                                        <td class="text-center font-weight-bold text-dark">
                                            {{ number_format($r->actual, 2) }}{{ $r->unit }}
                                        </td>
                                        <td class="text-center font-weight-bold text-teal">
                                            {{ number_format($r->achievement_pct, 1) }}%
                                        </td>
                                        <td class="text-center">
                                            @if($r->status === 'Tercapai')
                                                <span class="badge badge-tercapai px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                            @elseif($r->status === 'Waspada')
                                                <span class="badge badge-waspada px-2 py-1"><i class="fas fa-exclamation-circle"></i> Waspada</span>
                                            @else
                                                <span class="badge badge-dibawah px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @can('manage ratios')
                                                <button wire:click="openEditModal({{ $r->id }})" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-primary' }}" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Edit' }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="deleteRatio({{ $r->id }})" wire:confirm="Apakah Anda yakin ingin menghapus rasio '{{ $r->ratio_name }}'?" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-danger' }} ml-1" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Hapus' }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            @else
                                                <span class="text-muted small"><i class="fas fa-lock"></i> View Only</span>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Tidak ada data rasio keuangan untuk kategori ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>

    <!-- MODAL FORM TAMBAH / UBAH RASIO KEUANGAN -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-success shadow-lg">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas {{ $isEditMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                            {{ $isEditMode ? 'Ubah Rasio Keuangan' : 'Tambah Rasio Keuangan Baru' }}
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeModal">&times;</button>
                    </div>
                    <form wire:submit.prevent="saveRatio">
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="font-weight-bold text-dark">Kategori Rasio:</label>
                                <select wire:model="category" class="form-control @error('category') is-invalid @enderror">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                                @error('category') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold text-dark">Nama Rasio:</label>
                                <input type="text" wire:model="ratio_name" class="form-control @error('ratio_name') is-invalid @enderror" placeholder="Contoh: Net Profit Margin (%)">
                                @error('ratio_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Target Numerik:</label>
                                    <input type="number" step="0.01" wire:model="target" class="form-control @error('target') is-invalid @enderror" placeholder="0.00">
                                    @error('target') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Benchmark / Standar:</label>
                                    <input type="text" wire:model="benchmark" class="form-control @error('benchmark') is-invalid @enderror" placeholder="Contoh: 1.5-2.5 X, ≥ 8 X">
                                    @error('benchmark') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Realisasi (Actual):</label>
                                    <input type="number" step="0.01" wire:model="actual" class="form-control @error('actual') is-invalid @enderror" placeholder="0.00">
                                    @error('actual') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="closeModal">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm font-weight-bold">
                                <i class="fas fa-save mr-1"></i> {{ $isEditMode ? 'Simpan Perubahan' : 'Tambah Rasio' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
