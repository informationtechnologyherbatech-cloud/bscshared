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

            <!-- 1. TOP SUMMARY METRICS (Statistik KPI Keseluruhan) -->
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="info-box bg-white shadow-sm border mb-0">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-tasks"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Total Sasaran Mutu</span>
                            <span class="info-box-number text-dark" style="font-size: 20px;">{{ $totalKpi }} KPI</span>
                            <span class="progress-description text-muted small">Rata-rata Capaian: {{ $avgAchievement }}%</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="info-box bg-white shadow-sm border mb-0">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Tercapai (&ge;100%)</span>
                            <span class="info-box-number text-success" style="font-size: 20px;">{{ $achievedKpi }} KPI</span>
                            <span class="progress-description text-muted small">Target Terpenuhi</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="info-box bg-white shadow-sm border mb-0">
                        <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Waspada (80-99%)</span>
                            <span class="info-box-number text-warning" style="font-size: 20px;">{{ $warningKpi }} KPI</span>
                            <span class="progress-description text-muted small">Mendekati Batas</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="info-box bg-white shadow-sm border mb-0">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Off-Target (&lt;80%)</span>
                            <span class="info-box-number text-danger" style="font-size: 20px;">{{ $dangerKpi }} KPI</span>
                            <span class="progress-description text-muted small">Perlu Tindakan Perbaikan</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. PILIHAN MODE TAMPILAN & CONTROL BAR -->
            <div class="card bg-white shadow-sm border mb-3">
                <div class="card-body p-2 d-flex flex-wrap align-items-center justify-content-between">
                    <!-- Mode Switcher Buttons -->
                    <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                        <div class="btn-group btn-group-sm shadow-xs" role="group">
                            <button type="button" 
                                    wire:click="setViewMode('table')" 
                                    class="btn {{ $viewMode === 'table' ? 'btn-warning font-weight-bold text-dark' : 'btn-outline-secondary' }}">
                                <i class="fas fa-table mr-1"></i> Tabel Grup
                            </button>
                            <button type="button" 
                                    wire:click="setViewMode('tabs')" 
                                    class="btn {{ $viewMode === 'tabs' ? 'btn-warning font-weight-bold text-dark' : 'btn-outline-secondary' }}">
                                <i class="fas fa-folder mr-1"></i> Tab Departemen
                            </button>
                        </div>
                    </div>

                    <!-- Filter Status & Tambah Button -->
                    <!-- Filter Status & Tambah Button -->
                    <div class="d-flex align-items-center flex-wrap">
                        <select wire:model.live="selectedStatus" class="form-control form-control-sm mr-2" style="width: 170px;">
                            <option value="">Semua Status</option>
                            <option value="bermasalah">⚠️ Hanya Bermasalah</option>
                            <option value="Tercapai">Tercapai (&ge;100%)</option>
                            <option value="Waspada">Waspada (80-99%)</option>
                            <option value="Di Bawah Target">Di Bawah Target (&lt;80%)</option>
                        </select>
                        @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                            <button wire:click="openCreateModal" class="btn btn-sm btn-warning font-weight-bold text-dark {{ $isClosed ? 'disabled' : '' }}" {{ $isClosed ? 'disabled' : '' }}>
                                <i class="fas fa-plus-circle mr-1"></i> Tambah KPI
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAMPILAN TABEL DIKELOMPOKKAN PER DEPARTEMEN (HEADER KUNING)                -->
            <!-- ========================================================================= -->
            @if($viewMode === 'table')
                <div class="card card-outline card-warning shadow-sm">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title font-weight-bold">
                            <i class="fas fa-table mr-1 text-warning"></i> Sasaran Mutu Dikelompokkan per Departemen
                        </h3>
                        <div class="card-tools">
                            <select wire:model.live="selectedDept" class="form-control form-control-sm" style="width: 200px;">
                                <option value="">Semua Departemen</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d }}">{{ $deptMap[$d]->name ?? $d }} ({{ $d }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-bordered m-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 12%;">Kode KPI</th>
                                    <th style="width: 34%;">Sasaran Mutu (Nama KPI)</th>
                                    <th class="text-center" style="width: 10%;">Polaritas</th>
                                    <th class="text-center" style="width: 12%;">Target</th>
                                    <th class="text-center" style="width: 12%;">Actual</th>
                                    <th class="text-center" style="width: 10%;">Capaian</th>
                                    <th class="text-center" style="width: 10%;">Status</th>
                                    <th class="text-center" style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $groupedByDept = $objectives->groupBy('dept_code');
                                @endphp

                                @forelse($groupedByDept as $dCode => $deptObjs)
                                    <!-- Header Kategori Baris Kuning (#ffff00) Sesuai Standar -->
                                    <tr style="background-color: #ffff00;">
                                        <td colspan="8" class="py-2 px-3 font-weight-bold text-dark text-uppercase border-top border-bottom border-dark" style="font-size: 14px; letter-spacing: 0.5px;">
                                            <i class="fas fa-building mr-1"></i>
                                            <strong>{{ $deptMap[$dCode]->name ?? $dCode }} ({{ $dCode }})</strong>
                                            <span class="badge badge-dark ml-2 font-weight-normal" style="font-size: 11px;">
                                                {{ $deptObjs->count() }} Sasaran Mutu
                                            </span>
                                        </td>
                                    </tr>

                                    @foreach($deptObjs as $obj)
                                        <tr>
                                            <td class="pl-4"><code>{{ $obj->kpi_code }}</code></td>
                                            <td class="font-weight-normal text-dark">
                                                <div class="font-weight-bold">{{ $obj->kpi_name }}</div>
                                                @if($obj->odoo_account_code && isset($odooAccountMap[$obj->odoo_account_code]))
                                                    <div class="mt-1">
                                                        <span class="badge badge-light border text-dark font-weight-normal" style="font-size: 11px;" title="KPI ini berdampak pada pos anggaran Odoo">
                                                            <i class="fas fa-coins text-warning mr-1"></i> Dampak: <strong>{{ $odooAccountMap[$obj->odoo_account_code]->name }}</strong> ({{ $obj->odoo_account_code }})
                                                        </span>
                                                    </div>
                                                @elseif($obj->odoo_account_code)
                                                    <div class="mt-1">
                                                        <span class="badge badge-light border text-dark font-weight-normal" style="font-size: 11px;">
                                                            <i class="fas fa-coins text-warning mr-1"></i> Dampak: <strong>{{ $obj->odoo_account_code }}</strong>
                                                        </span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($obj->polarity === 'Turun')
                                                    <span class="badge badge-info"><i class="fas fa-arrow-down mr-1"></i> Turun Baik</span>
                                                @else
                                                    <span class="badge badge-primary"><i class="fas fa-arrow-up mr-1"></i> Naik Baik</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ number_format($obj->target, 2) }}
                                                @if($obj->unit)<small class="text-muted ml-1">{{ $obj->unit }}</small>@endif
                                            </td>
                                            <td class="text-center font-weight-bold">
                                                {{ number_format($obj->actual, 2) }}
                                                @if($obj->unit)<small class="text-muted ml-1">{{ $obj->unit }}</small>@endif
                                            </td>
                                            <td class="text-center font-weight-bold text-teal">
                                                {{ number_format($obj->achievement_pct, 1) }}%
                                            </td>
                                            <td class="text-center">
                                                @if($obj->status === 'Tercapai')
                                                    <span class="badge badge-tercapai px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                                @elseif($obj->status === 'Waspada')
                                                    <span class="badge badge-waspada px-2 py-1"><i class="fas fa-exclamation-circle"></i> Waspada</span>
                                                @else
                                                    <span class="badge badge-dibawah px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                                                    <button wire:click="openEditModal({{ $obj->id }})" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-primary' }}" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Edit' }}">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button wire:click="deleteObjective({{ $obj->id }})" wire:confirm="Apakah Anda yakin ingin menghapus KPI '{{ $obj->kpi_code }} - {{ $obj->kpi_name }}'?" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-danger' }} ml-1" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Hapus' }}">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                @else
                                                    <span class="text-muted small"><i class="fas fa-lock"></i> View Only</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            Belum ada sasaran mutu KPI yang tersimpan untuk periode {{ $selectedPeriod }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- ========================================================================= -->
            <!-- TAMPILAN TAB FILTER DEPARTEMEN                                            -->
            <!-- ========================================================================= -->
            @if($viewMode === 'tabs')
                <div class="card card-outline card-warning shadow-sm">
                    <!-- Nav tabs departemen -->
                    <div class="card-header p-2 bg-light">
                        <ul class="nav nav-pills flex-wrap">
                            <li class="nav-item mr-1 mb-1">
                                <a class="nav-link btn-sm {{ empty($selectedDept) ? 'active bg-warning font-weight-bold text-dark' : 'text-dark' }}" 
                                   href="javascript:void(0)" 
                                   wire:click="selectTabDept('')">
                                    <i class="fas fa-th mr-1"></i> Semua Departemen
                                </a>
                            </li>
                            @foreach($departments as $d)
                                <li class="nav-item mr-1 mb-1">
                                    <a class="nav-link btn-sm {{ $selectedDept === $d ? 'active bg-warning font-weight-bold text-dark' : 'text-dark' }}" 
                                       href="javascript:void(0)" 
                                       wire:click="selectTabDept('{{ $d }}')">
                                        {{ $d }}
                                        <span class="badge badge-secondary ml-1">
                                            {{ $allPeriodObjectives->where('dept_code', $d)->count() }}
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover table-striped table-bordered m-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 10%;">Dept</th>
                                    <th style="width: 12%;">Kode KPI</th>
                                    <th style="width: 34%;">Sasaran Mutu (KPI Name)</th>
                                    <th class="text-center" style="width: 10%;">Polaritas</th>
                                    <th class="text-center" style="width: 12%;">Target</th>
                                    <th class="text-center" style="width: 12%;">Actual</th>
                                    <th class="text-center" style="width: 10%;">Capaian</th>
                                    <th class="text-center" style="width: 10%;">Status</th>
                                    <th class="text-center" style="width: 10%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($objectives as $obj)
                                    <tr>
                                        <td><span class="badge badge-dark">{{ $obj->dept_code }}</span></td>
                                        <td><code>{{ $obj->kpi_code }}</code></td>
                                        <td class="font-weight-normal text-dark">
                                            <div class="font-weight-bold">{{ $obj->kpi_name }}</div>
                                            @if($obj->odoo_account_code && isset($odooAccountMap[$obj->odoo_account_code]))
                                                <div class="mt-1">
                                                    <span class="badge badge-light border text-dark font-weight-normal" style="font-size: 11px;" title="KPI ini berdampak pada pos anggaran Odoo">
                                                        <i class="fas fa-coins text-warning mr-1"></i> Dampak: <strong>{{ $odooAccountMap[$obj->odoo_account_code]->name }}</strong> ({{ $obj->odoo_account_code }})
                                                    </span>
                                                </div>
                                            @elseif($obj->odoo_account_code)
                                                <div class="mt-1">
                                                    <span class="badge badge-light border text-dark font-weight-normal" style="font-size: 11px;">
                                                        <i class="fas fa-coins text-warning mr-1"></i> Dampak: <strong>{{ $obj->odoo_account_code }}</strong>
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($obj->polarity === 'Turun')
                                                <span class="badge badge-info"><i class="fas fa-arrow-down mr-1"></i> Turun Baik</span>
                                            @else
                                                <span class="badge badge-primary"><i class="fas fa-arrow-up mr-1"></i> Naik Baik</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{ number_format($obj->target, 2) }}
                                            @if($obj->unit)<small class="text-muted ml-1">{{ $obj->unit }}</small>@endif
                                        </td>
                                        <td class="text-center font-weight-bold">
                                            {{ number_format($obj->actual, 2) }}
                                            @if($obj->unit)<small class="text-muted ml-1">{{ $obj->unit }}</small>@endif
                                        </td>
                                        <td class="text-center font-weight-bold text-teal">
                                            {{ number_format($obj->achievement_pct, 1) }}%
                                        </td>
                                        <td class="text-center">
                                            @if($obj->status === 'Tercapai')
                                                <span class="badge badge-tercapai px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                            @elseif($obj->status === 'Waspada')
                                                <span class="badge badge-waspada px-2 py-1"><i class="fas fa-exclamation-circle"></i> Waspada</span>
                                            @else
                                                <span class="badge badge-dibawah px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                                                <button wire:click="openEditModal({{ $obj->id }})" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-primary' }}" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Edit' }}">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="deleteObjective({{ $obj->id }})" wire:confirm="Apakah Anda yakin ingin menghapus KPI '{{ $obj->kpi_code }} - {{ $obj->kpi_name }}'?" class="btn btn-xs {{ $isClosed ? 'btn-secondary' : 'btn-danger' }} ml-1" {{ $isClosed ? 'disabled' : '' }} title="{{ $isClosed ? 'Terkunci' : 'Hapus' }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            @else
                                                <span class="text-muted small"><i class="fas fa-lock"></i> View Only</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                            Tidak ada sasaran mutu pada departemen ini untuk periode {{ $selectedPeriod }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </section>

    <!-- MODAL FORM TAMBAH / UBAH SASARAN MUTU KPI -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);" role="dialog">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content border-warning shadow-lg">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas {{ $isEditMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                            {{ $isEditMode ? 'Ubah Sasaran Mutu (KPI)' : 'Tambah Sasaran Mutu Baru' }}
                        </h5>
                        <button type="button" class="close text-dark" wire:click="closeModal">&times;</button>
                    </div>
                    <form wire:submit.prevent="saveObjective">
                        <div class="modal-body">
                            <!-- Searchable Combobox Mempengaruhi Akun / Anggaran Odoo (Livewire Native) -->
                            <div class="form-group bg-light p-2.5 rounded border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold text-dark mb-0">
                                        <i class="fas fa-coins text-warning mr-1"></i> Akun / Pos Anggaran yang Dipengaruhi (Odoo ERP):
                                    </label>
                                    @if($odoo_account_code)
                                        <button type="button" wire:click="clearOdooAccount" class="btn btn-outline-danger btn-xs py-0 px-2" style="font-size: 0.75rem;">
                                            <i class="fas fa-times mr-1"></i> Lepas Kaitan
                                        </button>
                                    @endif
                                </div>

                                <!-- Box Single Combobox / Searchable Dropdown -->
                                <div class="position-relative">
                                    <!-- Trigger Display Field -->
                                    <div class="input-group input-group-sm" style="cursor: pointer;" wire:click="toggleOdooDropdown">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-white border-right-0 text-muted">
                                                <i class="fas fa-search text-warning"></i>
                                            </span>
                                        </div>
                                        @php
                                            $selectedAccountLabel = $odoo_account_code 
                                                ? $odoo_account_code . ' - ' . ($odooAccountMap[$odoo_account_code]->name ?? '') 
                                                : '-- Klik untuk Cari & Pilih Akun Anggaran Odoo --';
                                        @endphp
                                        <input type="text" 
                                               class="form-control bg-white" 
                                               style="cursor: pointer;"
                                               readonly 
                                               value="{{ $selectedAccountLabel }}">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary bg-white border-left-0">
                                                <i class="fas {{ $showOdooDropdown ? 'fa-chevron-up text-warning' : 'fa-chevron-down text-muted' }}"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Floating Dropdown Panel (Hanya Muncul Jika Diklik) -->
                                    @if($showOdooDropdown)
                                        <div style="position: absolute; top: 100%; left: 0; right: 0; z-index: 1060; max-height: 310px; box-shadow: 0 10px 28px rgba(0,0,0,0.25);"
                                             class="bg-white rounded-bottom border border-top-0 mt-1 d-flex flex-column">
                                            
                                            <!-- Search input inside dropdown header -->
                                            <div class="p-2 border-bottom bg-light">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text bg-white border-right-0 py-1">
                                                            <i class="fas fa-search text-muted small"></i>
                                                        </span>
                                                    </div>
                                                    <input type="text" 
                                                           wire:model.live.debounce.150ms="odooAccountSearch" 
                                                           class="form-control form-control-sm border-left-0" 
                                                           placeholder="Ketik kode / nama akun (cth: 5100, cogs, gaji, mesin)..."
                                                           autofocus>
                                                    <div class="input-group-append">
                                                        @if(!empty($odooAccountSearch))
                                                            <button type="button" class="btn btn-light border border-left-0 py-0 px-2 text-muted" wire:click="$set('odooAccountSearch', '')" title="Bersihkan">
                                                                <i class="fas fa-times small"></i>
                                                            </button>
                                                        @endif
                                                        <button type="button" class="btn btn-warning border py-0 px-2 text-dark font-weight-bold" wire:click="closeOdooDropdown" title="Tutup">
                                                            <i class="fas fa-times mr-1"></i> Tutup
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Item List (Scrollable) -->
                                            <div class="overflow-auto py-1" style="max-height: 210px;">
                                                <!-- Option None -->
                                                <div class="px-3 py-2 cursor-pointer border-bottom d-flex align-items-center justify-content-between account-option-item {{ empty($odoo_account_code) ? 'bg-warning text-dark font-weight-bold' : 'text-muted' }}"
                                                     style="cursor: pointer; font-size: 0.85rem;"
                                                     wire:click="clearOdooAccount">
                                                    <span><i class="fas fa-ban mr-1.5 text-secondary"></i> Tidak Terkait Akun Anggaran (Operasional Umum)</span>
                                                    @if(empty($odoo_account_code))
                                                        <i class="fas fa-check text-dark"></i>
                                                    @endif
                                                </div>

                                                <!-- Filtered Accounts -->
                                                @forelse($filteredOdooAccounts as $acc)
                                                    <div class="px-3 py-2 cursor-pointer d-flex align-items-center justify-content-between border-bottom account-option-item {{ $odoo_account_code === $acc->code ? 'bg-warning text-dark font-weight-bold' : 'text-dark' }}"
                                                         style="cursor: pointer; font-size: 0.85rem;"
                                                         wire:click="selectOdooAccount('{{ $acc->code }}')">
                                                        <div>
                                                            <span class="badge {{ $odoo_account_code === $acc->code ? 'badge-dark' : 'badge-secondary' }} mr-1.5 font-weight-normal">{{ $acc->code }}</span>
                                                            <span>{{ $acc->name }}</span>
                                                        </div>
                                                        @if($odoo_account_code === $acc->code)
                                                            <i class="fas fa-check text-dark ml-2"></i>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <div class="p-3 text-center text-muted small">
                                                        <i class="fas fa-search-minus mr-1"></i> Tidak ada akun yang cocok dengan kata kunci "{{ $odooAccountSearch }}"
                                                    </div>
                                                @endforelse
                                            </div>

                                            <!-- Dropdown Footer count & close button -->
                                            <div class="px-2 py-1.5 bg-light border-top d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                                                <span class="text-muted">
                                                    Ditemukan <strong>{{ $filteredOdooAccounts->count() }}</strong> dari {{ $odooAccounts->count() }} akun
                                                </span>
                                                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" wire:click="closeOdooDropdown">
                                                    <i class="fas fa-check mr-1"></i> Selesai
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <small class="text-muted d-block mt-1.5">
                                    <i class="fas fa-info-circle mr-1 text-info"></i> Contoh: KPI <em>Produksi Botol PET</em> mempengaruhi pos <strong>51000010 - Cost of Goods Sold (HPP)</strong>.
                                </small>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Departemen:</label>
                                    <select wire:model.live="dept_code" class="form-control @error('dept_code') is-invalid @enderror">
                                        @foreach($departments as $d)
                                            <option value="{{ $d }}">{{ $deptMap[$d]->name ?? $d }} ({{ $d }})</option>
                                        @endforeach
                                    </select>
                                    @error('dept_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Kode KPI:</label>
                                    <input type="text" wire:model="kpi_code" class="form-control @error('kpi_code') is-invalid @enderror" placeholder="Contoh: PRD-01">
                                    @error('kpi_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Polaritas Target:</label>
                                    <select wire:model="polarity" class="form-control @error('polarity') is-invalid @enderror">
                                        <option value="Naik">Naik Baik (≥ Target)</option>
                                        <option value="Turun">Turun Baik (≤ Target)</option>
                                    </select>
                                    @error('polarity') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold text-dark">Nama Sasaran Mutu (KPI Name):</label>
                                <textarea wire:model="kpi_name" rows="2" class="form-control @error('kpi_name') is-invalid @enderror" placeholder="Contoh: Efisiensi Produksi Botol PET atau Tingkat Reject Botol"></textarea>
                                @error('kpi_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Target:</label>
                                    <input type="number" step="0.01" wire:model="target" class="form-control @error('target') is-invalid @enderror" placeholder="Contoh: 100">
                                    @error('target') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Realisasi (Actual):</label>
                                    <input type="number" step="0.01" wire:model="actual" class="form-control @error('actual') is-invalid @enderror" placeholder="Contoh: 95">
                                    @error('actual') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="font-weight-bold text-dark">Satuan Unit:</label>
                                    <input type="text" wire:model="unit" class="form-control @error('unit') is-invalid @enderror" placeholder="Contoh: Botol, %, Pcs, Hari, Batch">
                                    @error('unit') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="closeModal">Batal</button>
                            <button type="submit" class="btn btn-warning btn-sm font-weight-bold">
                                <i class="fas fa-save mr-1"></i> {{ $isEditMode ? 'Simpan Perubahan' : 'Tambah Sasaran Mutu' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <style>
        .account-option-item {
            transition: background-color 0.15s ease-in-out;
        }
        .account-option-item:hover {
            background-color: #f1f5f9 !important;
        }
    </style>
</div>
