<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-percent text-teal mr-2"></i> Rasio Keuangan (Level 2)
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
                        <i class="fas fa-list mr-1"></i> Daftar Rasio Keuangan
                    </h3>
                    <div class="card-tools d-flex flex-wrap align-items-center">
                        <select wire:model.live="selectedCategory" class="form-control form-control-sm mr-2 mb-1 mb-md-0" style="width: 160px;">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="selectedStatus" class="form-control form-control-sm" style="width: 180px;">
                            <option value="">Semua Status</option>
                            <option value="bermasalah">⚠️ Hanya Bermasalah</option>
                            <option value="Tercapai">Tercapai (&ge;100%)</option>
                            <option value="Waspada">Waspada (80-99%)</option>
                            <option value="Di Bawah Target">Di Bawah Target (&lt;80%)</option>
                        </select>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered m-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 15%;">Kategori</th>
                                <th style="width: 30%;">Nama Rasio</th>
                                <th class="text-center" style="width: 12%;">Target</th>
                                <th class="text-center" style="width: 12%;">Realisasi (Actual)</th>
                                <th class="text-center" style="width: 12%;">Capaian (%)</th>
                                <th class="text-center" style="width: 10%;">Status</th>
                                <th class="text-center" style="width: 9%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ratios as $r)
                                <tr>
                                    <td><span class="badge badge-info">{{ $r->category }}</span></td>
                                    <td class="font-weight-bold">{{ $r->ratio_name }}</td>
                                    
                                    @if($editingRatioId === $r->id)
                                        <td class="text-center">
                                            <input type="number" step="0.01" wire:model="editTarget" class="form-control form-control-sm text-center">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" step="0.01" wire:model="editActual" class="form-control form-control-sm text-center">
                                        </td>
                                        <td class="text-center align-middle">
                                            <small class="text-muted">Kalkulasi...</small>
                                        </td>
                                        <td class="text-center align-middle">-</td>
                                        <td class="text-center align-middle">
                                            <button wire:click="updateRatio" class="btn btn-xs btn-success mr-1" title="Simpan"><i class="fas fa-save"></i></button>
                                            <button wire:click="cancelEdit" class="btn btn-xs btn-secondary" title="Batal"><i class="fas fa-times"></i></button>
                                        </td>
                                    @else
                                        <td class="text-center">{{ $r->display($r->target) }}</td>
                                        <td class="text-center font-weight-bold">{{ $r->display($r->actual) }}</td>
                                        <td class="text-center font-weight-bold text-teal">
                                            {{ number_format($r->achievement_pct, 1) }}%
                                        </td>
                                        <td class="text-center">
                                            <x-status-badge :status="$r->status" />
                                        </td>
                                        <td class="text-center">
                                            @if($r->isComputed())
                                                <a href="{{ route('account-balances', ['period' => $selectedPeriod]) }}" class="btn btn-xs btn-outline-info" title="Dihitung dari pos akun — ubah lewat menu Pos Akun">
                                                    <i class="fas fa-calculator"></i> Otomatis
                                                </a>
                                            @else
                                            <button wire:click="editRatio({{ $r->id }})" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-primary' }}" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Edit' }}">
                                                <i class="fas {{ $isClosed ? 'fa-lock' : 'fa-edit' }}"></i> Edit
                                            </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Tidak ada data rasio keuangan untuk kategori ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>
