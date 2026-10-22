<div>
    <!-- Content Header -->
    <div class="content-header pb-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 22px;">
                        <i class="fas fa-bullseye text-warning mr-2"></i> Objective & Sasaran Mutu Departemen (Level 3)
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        Penerjemahan sasaran strategis BSC ke KPI operasional 10 departemen manufaktur botol plastik dan keterkaitannya dengan pos akun finansial.
                    </p>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <a href="{{ route('uji-indikator') }}" class="btn btn-sm btn-outline-warning text-dark font-weight-bold mr-2" title="Simulasi What-If Uji Indikator">
                        <i class="fas fa-flask mr-1"></i> Uji Indikator
                    </a>
                    @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                        <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-warning font-weight-bold text-dark mr-2">
                            <i class="fas fa-plus-circle mr-1"></i> Tambah KPI
                        </button>
                    @endif
                    <div class="d-inline-flex align-items-center bg-white px-3 py-1 rounded shadow-xs border">
                        <label class="small font-weight-bold mr-2 mb-0 text-muted">Periode:</label>
                        <select wire:model.live="selectedPeriod" class="form-control form-control-sm border-0 font-weight-bold text-warning" style="width: auto; background: transparent;">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <!-- 1. METRIC STATUS CARDS -->
            <div class="row mb-3">
                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-info text-white rounded"><i class="fas fa-tasks"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">TOTAL SASARAN MUTU</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalKpi }} KPI</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-success text-white rounded"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">TERCAPAI (&ge; 100%)</span>
                            <span class="info-box-number text-success font-weight-bold" style="font-size: 20px;">{{ $tercapai }} KPI</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-warning text-dark rounded"><i class="fas fa-exclamation-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">WASPADA (80% - 99%)</span>
                            <span class="info-box-number text-warning font-weight-bold" style="font-size: 20px;">{{ $waspada }} KPI</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-danger text-white rounded"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">DI BAWAH TARGET (&lt; 80%)</span>
                            <span class="info-box-number text-danger font-weight-bold" style="font-size: 20px;">{{ $diBawah }} KPI</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. MAIN CARD DENGAN TABS LEVEL 3 -->
            <div class="card card-outline card-warning shadow-xs mb-4">
                <div class="card-header bg-white p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-pills card-header-pills">
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 {{ $activeTab === 'kpi' ? 'active bg-warning text-dark font-weight-bold' : 'text-dark' }}" 
                               wire:click.prevent="$set('activeTab', 'kpi')" href="#">
                                <i class="fas fa-list-check mr-1"></i> Sasaran Mutu Operasional
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 {{ $activeTab === 'pos_link' ? 'active bg-warning text-dark font-weight-bold' : 'text-dark' }}" 
                               wire:click.prevent="$set('activeTab', 'pos_link')" href="#">
                                <i class="fas fa-th mr-1"></i> Keterkaitan 12 Pos Finansial
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 {{ $activeTab === 'action' ? 'active bg-warning text-dark font-weight-bold' : 'text-dark' }}" 
                               wire:click.prevent="$set('activeTab', 'action')" href="#">
                                <i class="fas fa-tasks mr-1"></i> Trigger Program Kerja (Level 4)
                                @if($diBawah > 0)
                                    <span class="badge badge-danger ml-1">{{ $diBawah }} Gap</span>
                                @endif
                            </a>
                        </li>
                    </ul>

                    <!-- Controls Search & Filter -->
                    <div class="d-flex align-items-center flex-wrap mt-2 mt-md-0" style="gap: 8px;">
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari KPI / departemen...">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                        </div>

                        <select wire:model.live="selectedDept" class="form-control form-control-sm" style="width: 190px;">
                            <option value="">Semua Departemen</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->code }}">{{ $d->name }} ({{ $d->code }})</option>
                            @endforeach
                        </select>

                        <select wire:model.live="selectedStatus" class="form-control form-control-sm" style="width: 150px;">
                            <option value="">Semua Status</option>
                            <option value="bermasalah">⚠️ Butuh Perhatian</option>
                            <option value="Tercapai">Tercapai (&ge;100%)</option>
                            <option value="Waspada">Waspada (80-99%)</option>
                            <option value="Di Bawah Target">Di Bawah Target (&lt;80%)</option>
                        </select>
                    </div>
                </div>

                <div class="card-body p-0">
                    <!-- ========================================== -->
                    <!-- TAB 1: SASARAN MUTU OPERASIONAL (KPI)      -->
                    <!-- ========================================== -->
                    @if($activeTab === 'kpi')
                        <div class="table-responsive" style="max-height: 620px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-bordered m-0 align-middle" style="font-size: 13px;">
                                <thead class="bg-light position-sticky" style="top: 0; z-index: 2;">
                                    <tr>
                                        <th style="width: 9%;">Kode KPI</th>
                                        <th style="width: 25%;">Sasaran Mutu (Nama KPI)</th>
                                        <th style="width: 12%;">Unit Kerja</th>
                                        <th style="width: 20%;">Dampak Finansial & Operasional</th>
                                        <th class="text-center" style="width: 6%;">Arah</th>
                                        <th class="text-right" style="width: 8%;">Target</th>
                                        <th class="text-right" style="width: 8%;">Realisasi</th>
                                        <th class="text-center" style="width: 12%;">Status</th>
                                        @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                                            <th class="text-center" style="width: 7%;">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $grouped = $objectives->groupBy('dept_code');
                                    @endphp

                                    @forelse($grouped as $dCode => $deptKpis)
                                        @php
                                            $deptObj = collect($departments)->firstWhere('code', $dCode);
                                            $deptName = $deptObj ? $deptObj->name : $dCode;
                                        @endphp
                                        <tr class="bg-light">
                                            <td colspan="{{ (auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi')) ? 9 : 8 }}" class="py-2 px-3 font-weight-bold text-dark border-top border-bottom">
                                                <i class="fas fa-building text-warning mr-1"></i>
                                                <strong>{{ $deptName }} ({{ $dCode }})</strong>
                                                <span class="badge badge-secondary ml-2 font-weight-normal" style="font-size: 11px;">
                                                     {{ $deptKpis->count() }} Sasaran Mutu
                                                </span>
                                            </td>
                                        </tr>

                                        @foreach($deptKpis as $obj)
                                            <tr>
                                                 <td class="font-weight-bold">
                                                     <code>{{ $obj->kpi_code }}</code>
                                                 </td>
                                                 <td>
                                                     <div class="font-weight-bold text-dark">{{ $obj->kpi_name }}</div>
                                                 </td>
                                                 <td>
                                                     <span class="badge badge-light border text-dark font-weight-bold px-2 py-1">
                                                         {{ $deptName }}
                                                     </span>
                                                 </td>
                                                 <td>
                                                     @php
                                                         $rawPos = trim($obj->pos_anggaran ?? '');
                                                         if (!$rawPos && trim($obj->odoo_account_code ?? '')) {
                                                             if (str_contains($obj->odoo_account_code, 'PA')) {
                                                                 $rawPos = trim($obj->odoo_account_code);
                                                             }
                                                         }
                                                         $codes = [];
                                                         if ($rawPos) {
                                                             if (str_starts_with($rawPos, '[') && str_ends_with($rawPos, ']')) {
                                                                 $decoded = json_decode($rawPos, true);
                                                                 $codes = is_array($decoded) ? $decoded : [];
                                                             } else {
                                                                 $codes = array_values(array_filter(array_map('trim', explode(',', $rawPos))));
                                                             }
                                                         }
                                                         $autoImpacts = \App\Livewire\DepartmentObjectives::getAutoImpactSummary($codes, $obj->status, $obj->polarity);
                                                     @endphp

                                                     <!-- 1. Akun Odoo Terpilih (Jika Dikaitkan - Mendukung Multi Akun) -->
                                                     @if(!empty($obj->odoo_account_code) && !str_contains($obj->odoo_account_code, 'PA'))
                                                         @php
                                                             $linkedOdoos = array_values(array_filter(array_map('trim', explode(',', $obj->odoo_account_code))));
                                                         @endphp
                                                         <div class="mb-1 d-flex flex-wrap" style="gap: 4px;">
                                                             @foreach($linkedOdoos as $lOdoo)
                                                                 <span class="badge badge-warning text-dark font-weight-normal px-2 py-1 shadow-sm" style="font-size: 11px;">
                                                                     <i class="fas fa-coins text-dark mr-1"></i><strong>{{ $lOdoo }}</strong>
                                                                     @if(isset($odooAccountMap[$lOdoo]))
                                                                         - {{ $odooAccountMap[$lOdoo]->name }}
                                                                     @endif
                                                                 </span>
                                                             @endforeach
                                                         </div>
                                                     @endif

                                                     <!-- 2. Dampak Otomatis (Dari Pos Anggaran Terpilih & Status Capaian) -->
                                                     @if(!empty($autoImpacts))
                                                         <div class="mb-1">
                                                             @foreach($autoImpacts as $ai)
                                                                 <div class="small d-flex align-items-baseline mb-1" style="line-height: 1.25;">
                                                                     <span class="badge {{ $ai['is_ok'] ? 'badge-success' : 'badge-danger' }} mr-1 flex-shrink-0" style="font-size: 10px;">
                                                                         <i class="fas {{ $ai['is_ok'] ? 'fa-check' : 'fa-exclamation-triangle' }} mr-1"></i>{{ $ai['code'] }}
                                                                     </span>
                                                                     <span class="{{ $ai['is_ok'] ? 'text-success' : 'text-danger font-weight-bold' }}" style="font-size: 11.5px;">
                                                                         {{ $ai['desc'] }}
                                                                     </span>
                                                                 </div>
                                                             @endforeach
                                                         </div>
                                                     @endif

                                                     <!-- 3. Catatan Tambahan Kustom (Jika Diisi di Form) -->
                                                     @if(!empty($obj->impact_note))
                                                         <div class="p-1 px-2 rounded bg-light border text-dark font-italic small mt-1" style="font-size: 11px; line-height: 1.2;">
                                                             <i class="fas fa-pencil-alt text-warning mr-1"></i><strong>Catatan:</strong> {{ $obj->impact_note }}
                                                         </div>
                                                     @elseif(empty($autoImpacts) && empty($obj->odoo_account_code))
                                                         <span class="text-muted small font-italic">- Tautkan ke pos anggaran / akun Odoo untuk melihat dampak -</span>
                                                     @endif
                                                 </td>
                                                <td class="text-center">
                                                    @if($obj->polarity === 'Naik')
                                                        <span class="badge badge-success px-2 py-1" title="Semakin tinggi semakin baik">
                                                            <i class="fas fa-arrow-up mr-1"></i> Naik
                                                        </span>
                                                    @else
                                                        <span class="badge badge-info px-2 py-1" title="Semakin rendah semakin baik (Efisiensi Biaya / Cacat)">
                                                            <i class="fas fa-arrow-down mr-1"></i> Turun
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-right font-weight-normal text-muted">
                                                    {{ number_format($obj->target, 2, ',', '.') }} {{ $obj->unit }}
                                                </td>
                                                <td class="text-right font-weight-bold text-dark">
                                                    {{ number_format($obj->actual, 2, ',', '.') }} {{ $obj->unit }}
                                                </td>
                                                <td class="text-center">
                                                    @if($obj->status === 'Tercapai')
                                                        <span class="badge badge-success px-2 py-1 font-weight-bold">
                                                            <i class="fas fa-check-circle mr-1"></i> {{ number_format($obj->achievement_pct, 1) }}%
                                                        </span>
                                                    @elseif($obj->status === 'Waspada')
                                                        <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold">
                                                            <i class="fas fa-exclamation-circle mr-1"></i> {{ number_format($obj->achievement_pct, 1) }}%
                                                        </span>
                                                    @else
                                                        <span class="badge badge-danger px-2 py-1 font-weight-bold">
                                                            <i class="fas fa-times-circle mr-1"></i> {{ number_format($obj->achievement_pct, 1) }}%
                                                        </span>
                                                    @endif
                                                </td>
                                                @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                                                    <td class="text-center">
                                                        <div class="btn-group btn-group-xs">
                                                            <button wire:click="openEditModal({{ $obj->id }})" class="btn btn-xs btn-outline-info" title="Ubah KPI">
                                                                <i class="fas fa-pencil-alt"></i>
                                                            </button>
                                                            <button wire:click="deleteObjective({{ $obj->id }})" wire:confirm="Hapus sasaran mutu [{{ $obj->kpi_code }}]?" class="btn btn-xs btn-outline-danger" title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fas fa-bullseye fa-3x mb-2 d-block text-secondary"></i>
                                                Belum ada Sasaran Mutu untuk periode {{ $selectedPeriod }}.<br>
                                                @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                                                    <button type="button" wire:click="openCreateModal" class="btn btn-sm btn-warning font-weight-bold text-dark mt-2">
                                                        <i class="fas fa-plus-circle mr-1"></i> Tambah Sasaran Mutu Baru
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: KETERKAITAN 12 POS FINANSIAL        -->
                    <!-- ========================================== -->
                    @elseif($activeTab === 'pos_link')
                        <div class="p-3">
                            <div class="alert alert-light border d-flex align-items-center mb-3">
                                <i class="fas fa-info-circle fa-2x text-warning mr-3"></i>
                                <div>
                                    <strong class="text-dark">Integrasi Level 3 &rarr; Tingkat 2 (Rasio Keuangan):</strong><br>
                                    Tabel di bawah menghubungkan setiap Sasaran Mutu departemen langsung dengan 12 Pos Finansial Inti dan rasio keuangan BSC yang dipengaruhinya.
                                </div>
                                <a href="{{ route('peta-pos-akun') }}" class="btn btn-sm btn-outline-teal ml-auto text-nowrap">
                                    <i class="fas fa-th mr-1"></i> Buka Matriks Lengkap
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle" style="font-size: 13px;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 14%;">Pos Finansial</th>
                                            <th style="width: 18%;">Rasio Terkait</th>
                                            <th style="width: 38%;">Sasaran Mutu Terhubung (KPI)</th>
                                            <th style="width: 18%;">Departemen PIC</th>
                                            <th class="text-center" style="width: 12%;">Status Driver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($corePos as $cp)
                                            @php
                                                $linkedKpis = $allObjectives->filter(function($item) use ($cp) {
                                                    $raw = trim($item->pos_anggaran ?? $item->odoo_account_code ?? '');
                                                    if (!$raw) return false;
                                                    $arr = array_map('trim', explode(',', str_replace(['[', ']', '"'], '', $raw)));
                                                    return in_array($cp['code'], $arr);
                                                });
                                            @endphp
                                            <tr>
                                                <td class="font-weight-bold text-teal">
                                                    <span class="badge badge-teal mr-1">{{ $cp['code'] }}</span>
                                                    {{ $cp['name'] }}
                                                </td>
                                                <td>
                                                    <span class="badge badge-light border text-dark font-weight-bold">
                                                        {{ $cp['ratio'] }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($linkedKpis->count() > 0)
                                                        @foreach($linkedKpis as $lk)
                                                            <div class="mb-1">
                                                                <i class="fas fa-bullseye text-warning mr-1"></i>
                                                                <strong>[{{ $lk->kpi_code }}]</strong> {{ $lk->kpi_name }}
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted font-italic">Belum ada KPI khusus yang ditautkan</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $deptsLinked = [];
                                                        foreach($matrix as $dCode => $row) {
                                                            if (($row[$cp['code']] ?? '') === 'K') {
                                                                $dObj = collect($departments)->firstWhere('code', $dCode);
                                                                $deptsLinked[] = $dObj ? $dObj->name : $dCode;
                                                            }
                                                        }
                                                    @endphp
                                                    @if(count($deptsLinked) > 0)
                                                        @foreach($deptsLinked as $dl)
                                                            <span class="badge badge-light border mr-1 mb-1">{{ $dl }}</span>
                                                        @endforeach
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    @if($linkedKpis->where('status', 'Di Bawah Target')->count() > 0)
                                                        <span class="badge badge-danger px-2 py-1">LEMAH (GAP)</span>
                                                    @elseif($linkedKpis->count() > 0)
                                                        <span class="badge badge-success px-2 py-1">KUAT</span>
                                                    @else
                                                        <span class="badge badge-light border text-muted">TERKENDALI</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: TRIGGER PROGRAM KERJA (LEVEL 4)     -->
                    <!-- ========================================== -->
                    @elseif($activeTab === 'action')
                        <div class="p-3">
                            <div class="alert alert-warning border d-flex align-items-center mb-3">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning mr-3"></i>
                                <div>
                                    <strong class="text-dark">Alur Eskalasi Tingkat 3 &rarr; Tingkat 4 (Action Plan):</strong><br>
                                    Sasaran mutu di bawah ini mengalami gap pencapaian (Status <strong>Di Bawah Target</strong> atau <strong>Waspada</strong>) dan membutuhkan inisiatif program kerja konkret.
                                </div>
                            </div>

                            @php
                                $problematic = $allObjectives->whereIn('status', ['Waspada', 'Di Bawah Target']);
                            @endphp

                            @if($problematic->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle" style="font-size: 13px;">
                                        <thead class="bg-light">
                                            <tr>
                                                <th style="width: 12%;">Kode KPI</th>
                                                <th style="width: 32%;">Sasaran Mutu Bermasalah</th>
                                                <th style="width: 16%;">Departemen</th>
                                                <th class="text-center" style="width: 14%;">Target vs Aktual</th>
                                                <th class="text-center" style="width: 12%;">Gap Capaian</th>
                                                <th class="text-center" style="width: 14%;">Tindakan (Level 4)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($problematic as $prob)
                                                <tr>
                                                    <td><code>{{ $prob->kpi_code }}</code></td>
                                                    <td>
                                                        <strong class="text-dark">{{ $prob->kpi_name }}</strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-light border text-dark font-weight-bold">
                                                            {{ $prob->dept_code }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center font-weight-bold text-muted">
                                                        {{ number_format($prob->target, 2, ',', '.') }} vs <span class="text-danger">{{ number_format($prob->actual, 2, ',', '.') }}</span> {{ $prob->unit }}
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-danger px-2 py-1 font-weight-bold">
                                                            Gap {{ number_format(100 - $prob->achievement_pct, 1) }}%
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="{{ route('action-plans') }}" class="btn btn-xs btn-primary font-weight-bold">
                                                            <i class="fas fa-plus mr-1"></i> Buat Action Plan
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x text-success mb-2 d-block"></i>
                                    <strong>Semua Sasaran Mutu Tercapai dengan Baik!</strong><br>
                                    Tidak ada indikator operasional yang membutuhkan eskalasi darurat ke Program Kerja (Level 4).
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-sitemap text-warning mr-1"></i> Terhubung langsung ke 10 Departemen Odoo ERP dan 12 Pos Finansial Inti Pabrik Botol Plastik.
                    </div>
                    <div>
                        Periode: <strong>{{ $selectedPeriod }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MODAL FORM TAMBAH / UBAH SASARAN MUTU -->
    @if($showModal)
        <div class="modal fade show" style="display: block; background: rgba(0,0,0,0.5); z-index: 1050; overflow-y: auto;" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable my-3">
                <div class="modal-content border-0 shadow-lg" style="max-height: 90vh;">
                    <div class="modal-header bg-warning py-2 text-dark">
                        <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                            <i class="fas fa-bullseye mr-2"></i> {{ $isEditMode ? 'Ubah Sasaran Mutu KPI' : 'Tambah Sasaran Mutu KPI Baru' }}
                        </h5>
                        <button type="button" class="close text-dark" wire:click="$set('showModal', false)">&times;</button>
                    </div>
                    <form wire:submit.prevent="saveObjective" class="d-flex flex-column" style="overflow: hidden; height: 100%;">
                        <div class="modal-body p-3" style="overflow-y: auto; max-height: calc(90vh - 110px);">
                            <div class="row">
                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Departemen PIC (Odoo):</label>
                                    <select wire:model="dept_code" class="form-control form-control-sm">
                                        @foreach($departments as $d)
                                            <option value="{{ $d->code }}">{{ $d->name }} ({{ $d->code }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Kode KPI:</label>
                                    <input type="text" wire:model="kpi_code" class="form-control form-control-sm" placeholder="Contoh: PRD-01">
                                    @error('kpi_code') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-12 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Nama Sasaran Mutu (Objective KPI):</label>
                                    <input type="text" wire:model="kpi_name" class="form-control form-control-sm" placeholder="Contoh: OEE Mesin Cetak Botol Plastik">
                                    @error('kpi_name') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <!-- BIDANG: AKUN ODOO ERP (CHART OF ACCOUNTS LENGKAP: ASSET, PIUTANG, COGS, OPEX, DLL.) -->
                                <div class="col-md-12 form-group mb-2">
                                    <div class="p-2.5 rounded border bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="font-weight-bold text-dark small mb-0">
                                                <i class="fas fa-coins text-warning mr-1"></i> Akun Odoo ERP (Chart of Accounts: Piutang, Kas, HPP, Beban):
                                            </label>
                                            <div class="d-flex align-items-center" style="gap: 8px;">
                                                <span class="badge badge-primary px-2 py-1" style="font-size: 11px;">
                                                    Dipilih: <strong>{{ count($selected_odoo_accounts) }}</strong> akun
                                                </span>
                                                @if(count($selected_odoo_accounts) > 0)
                                                    <a href="javascript:void(0)" wire:click="clearOdooAccounts" class="text-danger small font-weight-normal" title="Reset semua pilihan">
                                                        <i class="fas fa-times-circle mr-1"></i> Kosongkan
                                                    </a>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Tag Pill Akun yang Terpilih -->
                                        @if(count($selected_odoo_accounts) > 0)
                                            <div class="p-2 mb-2 bg-white rounded border d-flex flex-wrap" style="gap: 5px; max-height: 80px; overflow-y: auto;">
                                                @foreach($selected_odoo_accounts as $sCode)
                                                    <span class="badge badge-light border border-primary text-dark py-1 px-2 d-inline-flex align-items-center" style="font-size: 11px;">
                                                        <i class="fas fa-check-circle text-primary mr-1"></i>
                                                        <strong>{{ $sCode }}</strong>
                                                        @if(isset($odooAccountMap[$sCode]))
                                                            <span class="badge badge-secondary ml-1 py-0 px-1" style="font-size: 9.5px;">{{ $odooAccountMap[$sCode]->type ?? 'COA' }}</span>
                                                            <span class="text-muted ml-1" style="max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                                - {{ $odooAccountMap[$sCode]->name }}
                                                            </span>
                                                        @endif
                                                        <a href="javascript:void(0)" wire:click="removeOdooAccount('{{ $sCode }}')" class="ml-1 text-danger font-weight-bold" style="text-decoration: none;">&times;</a>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <!-- Search Box Filter Akun -->
                                        <div class="input-group input-group-sm mb-2">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-white border-right-0 text-muted">
                                                    <i class="fas fa-search"></i>
                                                </span>
                                            </div>
                                            <input type="text" 
                                                   wire:model.live.debounce.200ms="odooAccountSearch" 
                                                   class="form-control form-control-sm border-left-0" 
                                                   placeholder="Cari Piutang, Kas, Bank, HPP, Biaya, atau ketik kode akun (misal: 11210010)...">
                                            @if($odooAccountSearch)
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('odooAccountSearch', '')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Multi-Select List Akun Odoo (Scrollable) -->
                                        <div class="border rounded bg-white p-2" style="max-height: 150px; overflow-y: auto;">
                                            <div class="row">
                                                @forelse($filteredOdooAccounts as $acc)
                                                    <div class="col-md-6 mb-1">
                                                        <label class="d-flex align-items-center p-1 px-2 rounded border mb-0 {{ in_array($acc->code, $selected_odoo_accounts) ? 'border-primary bg-light' : '' }}" 
                                                               style="font-size: 11.5px; cursor: pointer; transition: all 0.15s; line-height: 1.25;"
                                                               onmouseover="this.classList.add('border-warning')"
                                                               onmouseout="this.classList.remove('border-warning')">
                                                            <input type="checkbox" 
                                                                   value="{{ $acc->code }}" 
                                                                   wire:click="toggleOdooAccount('{{ $acc->code }}')"
                                                                   {{ in_array($acc->code, $selected_odoo_accounts) ? 'checked' : '' }}
                                                                   style="width: 14px; height: 14px; cursor: pointer; flex-shrink: 0;" class="mr-2">
                                                            <div class="text-truncate flex-grow-1">
                                                                <strong class="{{ in_array($acc->code, $selected_odoo_accounts) ? 'text-primary' : 'text-dark' }}">{{ $acc->code }}</strong>
                                                                @if(!empty($acc->type))
                                                                    <span class="badge {{ $acc->type === 'Asset' ? 'badge-info' : ($acc->type === 'COGS' ? 'badge-warning' : 'badge-light border') }} py-0 px-1 ml-1" style="font-size: 9.5px;">{{ $acc->type }}</span>
                                                                @endif
                                                                <span class="text-muted ml-1">- {{ $acc->name }}</span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @empty
                                                    <div class="col-12 text-center py-2 text-muted small">
                                                        Tidak ada akun COA yang cocok dengan kata kunci "{{ $odooAccountSearch }}".
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                        <small class="form-text text-muted mt-1" style="font-size: 11px;">
                                            <i class="fas fa-info-circle text-info mr-1"></i> Mencakup seluruh Chart of Accounts Odoo ERP: <strong>Piutang Usaha (11210010)</strong>, Kas & Bank, HPP/Bahan Baku, Aset Tetap, dan Biaya Operasional.
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Satuan Unit:</label>
                                    <input type="text" wire:model="unit" class="form-control form-control-sm" placeholder="Contoh: %, Jam, Pcs, Hari, Rp, kWh/kg, Miliar">
                                </div>

                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Polaritas Capaian:</label>
                                    <select wire:model="polarity" class="form-control form-control-sm">
                                        <option value="Naik">Naik (Semakin tinggi semakin bagus)</option>
                                        <option value="Turun">Turun (Semakin rendah semakin bagus / Efisiensi)</option>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Target:</label>
                                    <input type="number" step="any" wire:model="target" class="form-control form-control-sm" placeholder="Contoh: 85 atau 100">
                                    @error('target') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-6 form-group mb-2">
                                    <label class="font-weight-bold text-dark small mb-1">Realisasi (Actual):</label>
                                    <input type="number" step="any" wire:model="actual" class="form-control form-control-sm" placeholder="Contoh: 82.5 atau 95">
                                    @error('actual') <span class="text-danger small">{{ $message }}</span> @enderror
                                </div>

                                <div class="col-md-12 form-group mb-0">
                                    <label class="font-weight-bold text-dark small mb-1 d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-magic text-warning mr-1"></i> Catatan Tambahan Dampak (Opsional):</span>
                                        <span class="text-muted font-weight-normal" style="font-size: 11px;">
                                            <i class="fas fa-check-circle text-success mr-1"></i>Dampak utama otomatis dihasilkan dari Pos Finansial
                                        </span>
                                    </label>
                                    <textarea wire:model="impact_note" rows="2" class="form-control form-control-sm" 
                                              placeholder="Tuliskan catatan dampak operasional khusus jika ada (opsional). Boleh dikosongkan."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light py-2 border-top">
                            <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('showModal', false)">Batal</button>
                            <button type="submit" class="btn btn-sm btn-warning font-weight-bold text-dark">
                                <i class="fas fa-save mr-1"></i> Simpan Sasaran Mutu
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
