<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-bullseye text-warning mr-2"></i> Objective Departemen (Level 3)
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="form-inline float-right">
                        <label for="periodSel" class="mr-2 font-weight-bold">Periode:</label>
                        <select wire:model.live="selectedPeriod" id="periodSel" class="form-control form-control-sm border-warning mr-2">
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
                    <i class="fas fa-lock mr-2"></i> <strong>Periode {{ $selectedPeriod }} DITUTUP (CLOSED):</strong> Nilai target dan aktual Sasaran Mutu terkunci untuk pengubahan langsung.
                </div>
            @endif

            <div class="card card-outline card-warning">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-list-check mr-1"></i> Daftar Sasaran Mutu Departemen
                    </h3>
                    <div class="card-tools d-flex flex-wrap align-items-center">
                        @can('manage units')
                            <a href="{{ route('work-units') }}" class="btn btn-outline-secondary btn-sm mr-2 mb-1 mb-md-0" title="Tambah atau ubah daftar departemen">
                                <i class="fas fa-sitemap mr-1"></i> Kelola unit kerja
                            </a>
                        @endcan
                        <!-- Filter Dept -->
                        <select wire:model.live="selectedDept" class="form-control form-control-sm mr-2 mb-1 mb-md-0" style="width: 220px;">
                            <option value="">Semua Departemen</option>
                            @foreach($departments as $kode => $nama)
                                <option value="{{ $kode }}">{{ $kode }}{{ $nama !== $kode ? ' — '.$nama : '' }}</option>
                            @endforeach
                        </select>
                        <!-- Filter Status -->
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
                    <table class="table table-hover table-bordered m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 8%;">Dept</th>
                                <th style="width: 14%;">Kode KPI</th>
                                <th style="width: 30%;">Sasaran Mutu (KPI Name)</th>
                                <th class="text-center" style="width: 10%;">Polaritas</th>
                                <th class="text-center" style="width: 10%;">Target</th>
                                <th class="text-center" style="width: 10%;">Actual</th>
                                <th class="text-center" style="width: 9%;">Capaian</th>
                                <th class="text-center" style="width: 12%;">Status</th>
                                <th class="text-center" style="width: 7%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($objectives as $obj)
                                <tr>
                                    <td><span class="badge badge-dark" title="{{ $departments[$obj->dept_code] ?? $obj->dept_code }}">{{ $obj->dept_code }}</span></td>
                                    <td>
                                        <code>{{ $obj->kpi_code }}</code>
                                        @if (! $obj->kpi_cascade_id)
                                            <small class="d-block text-warning" title="Belum tertaut ke Cascade KPI — tautkan lewat tombol Ambil dari Objective Departemen"><i class="fas fa-unlink"></i> belum di cascade</small>
                                        @endif
                                    </td>
                                    <td class="font-weight-normal">{{ $obj->kpi_name }}</td>
                                    <td class="text-center">
                                        @if($obj->polarity === 'Turun')
                                            <span class="badge badge-info"><i class="fas fa-arrow-down mr-1"></i> Turun Baik</span>
                                        @else
                                            <span class="badge badge-primary"><i class="fas fa-arrow-up mr-1"></i> Naik Baik</span>
                                        @endif
                                    </td>

                                    @if($editingObjId === $obj->id)
                                        <td class="text-center">
                                            <input type="number" step="0.01" wire:model="editTarget" class="form-control form-control-sm text-center">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" step="0.01" wire:model="editActual" class="form-control form-control-sm text-center">
                                        </td>
                                        <td class="text-center align-middle">-</td>
                                        <td class="text-center align-middle">-</td>
                                        <td class="text-center align-middle">
                                            <button wire:click="updateObjective" class="btn btn-xs btn-success mr-1" title="Simpan"><i class="fas fa-save"></i></button>
                                            <button wire:click="cancelEdit" class="btn btn-xs btn-secondary" title="Batal"><i class="fas fa-times"></i></button>
                                        </td>
                                    @else
                                        <td class="text-center">{{ number_format($obj->target, 2) }}</td>
                                        <td class="text-center font-weight-bold">{{ number_format($obj->actual, 2) }}</td>
                                        <td class="text-center font-weight-bold text-teal">
                                            {{ number_format($obj->achievement_pct, 1) }}%
                                        </td>
                                        <td class="text-center">
                                            <x-status-badge :status="$obj->status" />
                                        </td>
                                        <td class="text-center">
                                            <button wire:click="editObjective({{ $obj->id }})" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-primary' }}" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Edit' }}">
                                                <i class="fas {{ $isClosed ? 'fa-lock' : 'fa-edit' }}"></i>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">Tidak ada sasaran mutu yang sesuai filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>
