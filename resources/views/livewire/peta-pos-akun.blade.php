<div>
    <div class="content-header pb-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 24px;">
                        <i class="fas fa-sitemap text-teal mr-2"></i> Peta Pos Akun & Alokasi Anggaran
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        Pemetaan struktur akun Chart of Accounts (CoA) Odoo ke pos departemen, rasio pagu anggaran, serta realisasi riil.
                    </p>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <div class="d-inline-flex align-items-center bg-white px-3 py-1 rounded shadow-xs border">
                        <label class="small font-weight-bold mr-2 mb-0 text-muted">Periode:</label>
                        <select wire:model.live="selectedPeriod" class="form-control form-control-sm border-0 font-weight-bold text-teal" style="width: auto; background: transparent;">
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
            <!-- Metric Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white border shadow-xs p-3 mb-3">
                        <div class="inner">
                            <span class="text-muted small font-weight-bold text-uppercase">Total Pos Akun Terpetakan</span>
                            <h3 class="font-weight-bold text-dark mb-0 mt-1">{{ $totalAccounts }} <small style="font-size: 14px;">Akun</small></h3>
                            <small class="text-teal font-weight-bold">
                                <i class="fas fa-check-circle mr-1"></i> Terhubung Live Odoo
                            </small>
                        </div>
                        <div class="icon" style="position: absolute; right: 15px; top: 15px; opacity: 0.15; font-size: 45px;">
                            <i class="fas fa-list-ol text-dark"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white border shadow-xs p-3 mb-3">
                        <div class="inner">
                            <span class="text-muted small font-weight-bold text-uppercase">Total Alokasi Pagu Rasio</span>
                            <h3 class="font-weight-bold text-teal mb-0 mt-1">{{ number_format($totalRatioAll, 2, ',', '.') }}%</h3>
                            <small class="text-muted">Dari Target Pendapatan Tahunan</small>
                        </div>
                        <div class="icon" style="position: absolute; right: 15px; top: 15px; opacity: 0.15; font-size: 45px;">
                            <i class="fas fa-percentage text-teal"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white border shadow-xs p-3 mb-3">
                        <div class="inner">
                            <span class="text-muted small font-weight-bold text-uppercase">Pagu Bulanan (MTD)</span>
                            <h3 class="font-weight-bold text-dark mb-0 mt-1">Rp {{ number_format($totalMonthlyBudget, 0, ',', '.') }}</h3>
                            <small class="text-muted">Alokasi anggaran 1 bulan</small>
                        </div>
                        <div class="icon" style="position: absolute; right: 15px; top: 15px; opacity: 0.15; font-size: 45px;">
                            <i class="fas fa-wallet text-dark"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white border shadow-xs p-3 mb-3">
                        <div class="inner">
                            <span class="text-muted small font-weight-bold text-uppercase">Realisasi Beban MTD</span>
                            <h3 class="font-weight-bold {{ $totalMonthlyActual > $totalMonthlyBudget ? 'text-danger' : 'text-success' }} mb-0 mt-1">
                                Rp {{ number_format($totalMonthlyActual, 0, ',', '.') }}
                            </h3>
                            <small class="font-weight-bold {{ $totalMonthlyActual > $totalMonthlyBudget ? 'text-danger' : 'text-success' }}">
                                @php
                                    $overallSerapan = $totalMonthlyBudget > 0 ? round(($totalMonthlyActual / $totalMonthlyBudget) * 100, 1) : 0;
                                @endphp
                                <i class="fas {{ $overallSerapan > 100 ? 'fa-arrow-up' : 'fa-check' }} mr-1"></i> Serapan: {{ $overallSerapan }}%
                            </small>
                        </div>
                        <div class="icon" style="position: absolute; right: 15px; top: 15px; opacity: 0.15; font-size: 45px;">
                            <i class="fas fa-receipt text-success"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="card card-outline card-teal shadow-xs mb-4">
                <div class="card-header bg-white p-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
                    <!-- Nav Tabs -->
                    <ul class="nav nav-pills card-header-pills">
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 {{ $activeTab === 'matrix' ? 'active bg-teal text-white' : 'text-dark' }}" 
                               wire:click.prevent="$set('activeTab', 'matrix')" href="#">
                                <i class="fas fa-table mr-1"></i> Tabel Matriks Akun (CoA)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-3 {{ $activeTab === 'summary' ? 'active bg-teal text-white' : 'text-dark' }}" 
                               wire:click.prevent="$set('activeTab', 'summary')" href="#">
                                <i class="fas fa-chart-pie mr-1"></i> Ringkasan Departemen
                            </a>
                        </li>
                    </ul>

                    <!-- Search & Filter Controls -->
                    <div class="d-flex align-items-center flex-wrap mt-2 mt-md-0" style="gap: 8px;">
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari kode / nama / dept...">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                        </div>

                        <select wire:model.live="selectedDept" class="form-control form-control-sm" style="width: 170px;">
                            <option value="all">Semua Departemen</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->name }}">{{ $d->name }}</option>
                            @endforeach
                        </select>

                        <select wire:model.live="selectedType" class="form-control form-control-sm" style="width: 140px;">
                            <option value="all">Semua Jenis</option>
                            <option value="cogs">HPP (5x)</option>
                            <option value="opex">OPEX (6x)</option>
                        </select>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($activeTab === 'matrix')
                        <!-- TAB MATRIKS AKUN -->
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-bordered m-0 align-middle" style="font-size: 13px;">
                                <thead class="bg-light position-sticky" style="top: 0; z-index: 2;">
                                    <tr>
                                        <th style="width: 12%;">Kode Akun</th>
                                        <th style="width: 26%;">Nama Pos Akun</th>
                                        <th style="width: 16%;">Departemen PIC</th>
                                        <th class="text-center" style="width: 10%;">Pagu Rasio</th>
                                        <th class="text-right" style="width: 18%;">Pagu Bulanan (Rp)</th>
                                        <th class="text-right" style="width: 18%;">Realisasi MTD (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $item)
                                        <tr>
                                            <td class="font-weight-bold">
                                                <code>{{ $item['code'] }}</code>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-dark">{{ $item['name'] }}</div>
                                                <small class="badge badge-light border text-muted" style="font-size: 10px;">
                                                    {{ $item['group'] }}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge badge-teal px-2 py-1" style="font-size: 11px;">
                                                    <i class="fas fa-building mr-1"></i> {{ $item['dept'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @if($item['ratio'] > 0)
                                                    <span class="badge badge-info font-weight-bold" style="font-size: 11px;">
                                                        {{ number_format($item['ratio'], 2, ',', '.') }}%
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-weight-normal text-muted">
                                                <div class="font-weight-bold text-dark">
                                                    Rp {{ number_format($item['monthly_budget'], 0, ',', '.') }}
                                                </div>
                                                <small style="font-size: 10px;">
                                                    Setahun: Rp {{ number_format($item['annual_budget'], 0, ',', '.') }}
                                                </small>
                                            </td>
                                            <td class="text-right font-weight-bold">
                                                <div class="{{ $item['monthly_actual'] > $item['monthly_budget'] && $item['monthly_budget'] > 0 ? 'text-danger' : 'text-dark' }}">
                                                    Rp {{ number_format($item['monthly_actual'], 0, ',', '.') }}
                                                </div>
                                                @if($item['monthly_budget'] > 0)
                                                    <small class="badge {{ $item['serapan_monthly'] > 100 ? 'badge-danger' : 'badge-light border' }}" style="font-size: 10px;">
                                                        Serapan: {{ $item['serapan_monthly'] }}%
                                                    </small>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fas fa-search fa-2x mb-2 d-block text-secondary"></i>
                                                Tidak ditemukan pos akun yang sesuai kriteria pencarian.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <!-- TAB RINGKASAN DEPARTEMEN -->
                        <div class="table-responsive p-3">
                            <table class="table table-bordered table-hover align-middle" style="font-size: 13px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Nama Departemen</th>
                                        <th class="text-center" style="width: 12%;">Jumlah Akun</th>
                                        <th class="text-center" style="width: 14%;">Total Pagu Rasio</th>
                                        <th class="text-right" style="width: 20%;">Pagu Anggaran Bulanan</th>
                                        <th class="text-right" style="width: 20%;">Realisasi MTD</th>
                                        <th class="text-center" style="width: 14%;">Persentase Serapan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deptSummary as $ds)
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <i class="fas fa-folder text-teal mr-2"></i> {{ $ds['dept'] }}
                                            </td>
                                            <td class="text-center font-weight-bold">
                                                <span class="badge badge-light border px-2 py-1">{{ $ds['total_accounts'] }} Akun</span>
                                            </td>
                                            <td class="text-center font-weight-bold text-teal">
                                                {{ number_format($ds['total_ratio'], 2, ',', '.') }}%
                                            </td>
                                            <td class="text-right font-weight-bold text-dark">
                                                Rp {{ number_format($ds['monthly_budget'], 0, ',', '.') }}
                                            </td>
                                            <td class="text-right font-weight-bold {{ $ds['monthly_actual'] > $ds['monthly_budget'] && $ds['monthly_budget'] > 0 ? 'text-danger' : 'text-success' }}">
                                                Rp {{ number_format($ds['monthly_actual'], 0, ',', '.') }}
                                            </td>
                                            <td class="text-center">
                                                <div class="progress progress-xs mb-1" style="height: 6px;">
                                                    <div class="progress-bar {{ $ds['serapan'] > 100 ? 'bg-danger' : 'bg-teal' }}" style="width: {{ min($ds['serapan'], 100) }}%"></div>
                                                </div>
                                                <small class="font-weight-bold {{ $ds['serapan'] > 100 ? 'text-danger' : 'text-dark' }}">
                                                    {{ $ds['serapan'] }}%
                                                </small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle text-teal mr-1"></i> Data diperbarui secara dinamis langsung dari tabel <code>budget_categories</code> dan <code>expenses</code> Odoo ERP.
                    </div>
                    <div>
                        Menampilkan <strong>{{ count($items) }}</strong> dari <strong>{{ $totalAccounts }}</strong> total pos akun.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
