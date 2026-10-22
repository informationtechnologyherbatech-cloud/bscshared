<div>
    <!-- Content Header -->
    <div class="content-header pb-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 22px;">
                        <i class="fas fa-th text-teal mr-2"></i> Peta Pos Akun & Chart of Accounts Odoo (Level 3)
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        Matriks keterkaitan unit kerja terhadap <strong>{{ $totalBudgetPosCount ?? 130 }} Pos Anggaran Odoo</strong> dan seluruh <strong>Chart of Accounts ({{ $totalAccountsCount ?? 301 }} COA)</strong> dengan sistem ceklis terintegrasi.
                    </p>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <button type="button" wire:click="resetMatrix" wire:confirm="Kembalikan matriks keterkaitan ke konfigurasi awal?" class="btn btn-sm btn-outline-secondary mr-2" title="Kembalikan ke Matriks Standar">
                        <i class="fas fa-undo mr-1"></i> Reset Default
                    </button>
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
            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <!-- Metric Cards & Stats -->
            <div class="row mb-3">
                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-teal text-white rounded"><i class="fas fa-building"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">UNIT KERJA (MASTER)</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalDepts }} Departemen</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-warning text-dark rounded"><i class="fas fa-star"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">POS ANGGARAN ODOO</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalBudgetPosCount }} Pos Anggaran</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-info text-white rounded"><i class="fas fa-coins"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">TOTAL CHART OF ACCOUNTS</span>
                            <span class="info-box-number text-dark font-weight-bold" style="font-size: 20px;">{{ $totalAccountsCount }} Akun COA</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="info-box shadow-xs bg-white border p-2 mb-2">
                        <span class="info-box-icon bg-success text-white rounded font-weight-bold"><i class="fas fa-check-double"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small font-weight-bold">TOTAL CEKLIS TERKAIT</span>
                            <span class="info-box-number text-success font-weight-bold" style="font-size: 20px;">{{ $totalChecked }} Hubungan Aktif</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Matriks Table Card -->
            <div class="card card-outline card-teal shadow-xs mb-4">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap" style="gap: 10px;">
                    <!-- Filter Kategori Akun -->
                    <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                        <button type="button" wire:click="$set('accountTypeFilter', '')" 
                                class="btn btn-xs {{ $accountTypeFilter === '' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            Semua ({{ $totalAccountsCount }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'BUDGET_130')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'BUDGET_130' ? 'btn-warning font-weight-bold text-dark' : 'btn-outline-secondary bg-white' }}"
                                title="130 Kategori Pos Anggaran Odoo">
                            <i class="fas fa-star text-warning mr-1"></i> 130 Pos Anggaran ({{ $totalBudgetPosCount }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'COGS')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'COGS' ? 'btn-danger font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            HPP / COGS ({{ $allAccounts->where('type', 'COGS')->count() }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'Expense')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'Expense' ? 'btn-secondary font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            Beban OPEX ({{ $allAccounts->where('type', 'Expense')->count() }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'Asset')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'Asset' ? 'btn-info font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            Asset (Piutang/Kas) ({{ $allAccounts->where('type', 'Asset')->count() }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'Liability')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'Liability' ? 'btn-dark font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            Liabilitas ({{ $allAccounts->where('type', 'Liability')->count() }})
                        </button>
                        <button type="button" wire:click="$set('accountTypeFilter', 'Revenue')" 
                                class="btn btn-xs {{ $accountTypeFilter === 'Revenue' ? 'btn-success font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}">
                            Revenue ({{ $allAccounts->where('type', 'Revenue')->count() }})
                        </button>
                    </div>

                    <!-- Search Box Akun -->
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" wire:model.live.debounce.300ms="searchPos" class="form-control" placeholder="Cari nama / kode pos akun...">
                        <div class="input-group-append">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0 table-responsive" style="max-height: 620px; overflow-x: auto; overflow-y: auto;">
                    <style>
                        .matrix-table {
                            font-size: 11.5px;
                            border-collapse: separate;
                            border-spacing: 0;
                        }
                        .matrix-table th, .matrix-table td {
                            border: 1px solid #e2e8f0;
                            vertical-align: middle !important;
                            text-align: center;
                            padding: 6px 4px;
                        }
                        .matrix-table th.unit-col {
                            min-width: 240px;
                            max-width: 260px;
                            text-align: left !important;
                            position: sticky;
                            left: 0;
                            background: #f8fafc;
                            z-index: 3;
                            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                        }
                        .matrix-table td.unit-col {
                            text-align: left !important;
                            position: sticky;
                            left: 0;
                            background: #ffffff;
                            z-index: 2;
                            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
                        }
                        .matrix-table th.pos-header {
                            min-width: 105px;
                            max-width: 120px;
                            padding: 8px 6px;
                            background: #f1f5f9;
                            position: sticky;
                            top: 0;
                            z-index: 2;
                        }
                        .matrix-check-btn {
                            width: 32px;
                            height: 32px;
                            border-radius: 6px;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                            transition: all 0.15s ease-in-out;
                            border: 1px solid #e2e8f0;
                            background: #ffffff;
                            color: transparent;
                        }
                        .matrix-check-btn:hover {
                            background: #f8fafc;
                            border-color: #0d9488;
                            color: #cbd5e1;
                        }
                        .matrix-check-btn.is-checked {
                            background: #0d9488;
                            border-color: #0f766e;
                            color: #ffffff !important;
                            box-shadow: 0 1px 3px rgba(13, 148, 136, 0.3);
                        }
                        .matrix-check-btn.is-checked:hover {
                            background: #0f766e;
                        }
                    </style>

                    <table class="table table-bordered table-hover m-0 matrix-table">
                        <thead>
                            <tr>
                                <th class="unit-col font-weight-bold text-uppercase bg-light" style="top: 0; z-index: 4;">
                                    UNIT KERJA (DEPARTEMEN)
                                </th>
                                @forelse($accounts as $acc)
                                    <th class="pos-header" title="{{ $acc['code'] }} - {{ $acc['name'] }}&#10;Kategori: {{ $acc['type'] }}&#10;PIC: {{ $acc['dept_name'] ?? '-' }}">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <code class="font-weight-bold mr-1" style="font-size: 11px;">{{ $acc['code'] }}</code>
                                            @if($acc['is_budget_pos'])
                                                <i class="fas fa-star text-warning" style="font-size: 9px;" title="Pos Anggaran 130"></i>
                                            @endif
                                        </div>
                                        <div class="font-weight-bold text-dark text-truncate my-1" style="font-size: 10px; max-width: 100px;">
                                            {{ $acc['name'] }}
                                        </div>
                                        <div class="badge badge-light border text-muted text-truncate" style="font-size: 9px; max-width: 95px;">
                                            {{ $acc['type'] }}
                                        </div>
                                    </th>
                                @empty
                                    <th class="text-center text-muted py-3">Tidak ada akun yang sesuai filter.</th>
                                @endforelse
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $dept)
                                @php
                                    $dCode = $dept->code;
                                    $kpiList = $deptObjectives->get($dCode) ?? collect([]);
                                @endphp
                                <tr>
                                    <td class="unit-col">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="badge badge-teal font-weight-bold mr-1" style="font-size: 11px;">
                                                    {{ $dept->code }}
                                                </span>
                                                <strong class="text-dark">{{ $dept->name }}</strong>
                                            </div>
                                            @if($kpiList->count() > 0)
                                                <span class="badge badge-light border text-muted ml-1" title="{{ $kpiList->count() }} Sasaran Mutu terhubung" style="font-size: 10px;">
                                                    <i class="fas fa-bullseye text-teal mr-1"></i>{{ $kpiList->count() }}
                                                </span>
                                            @endif
                                        </div>
                                        <small class="text-muted d-block mt-1" style="font-size: 10px;">
                                            {{ isset($dept->description) && $dept->description ? $dept->description : ('Divisi Operasional ' . $dept->name) }}
                                        </small>
                                    </td>
                                    @foreach($accounts as $acc)
                                        @php
                                            $accCode = $acc['code'];
                                            $isChecked = isset($matrix[$dCode][$accCode]) && (int)$matrix[$dCode][$accCode] === 1;
                                        @endphp
                                        <td class="p-1">
                                            <button type="button" 
                                                    wire:click="toggleCell('{{ $dCode }}', '{{ $accCode }}')"
                                                    class="matrix-check-btn {{ $isChecked ? 'is-checked' : '' }}"
                                                    title="{{ $dept->name }} &rarr; [{{ $accCode }}] {{ $acc['name'] }}: {{ $isChecked ? 'Terhubung (Klik untuk hapus ceklis)' : 'Belum Terkait (Klik untuk ceklis)' }}">
                                                <i class="fas fa-check" style="font-size: 13px;"></i>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-check-circle text-success mr-1"></i> <strong>Sistem Ceklis Aktif:</strong> Klik tombol kotak untuk memberi tanda centang / ceklis (<i class="fas fa-check text-teal"></i>) keterkaitan unit kerja dengan pos akun Odoo. Perubahan tersimpan secara instan.
                    </div>
                    <div>
                        Menampilkan <strong>{{ $accounts->count() }}</strong> dari <strong>{{ $totalAccountsCount }}</strong> Pos Akun &middot; <strong>{{ $totalDepts }} Unit Kerja</strong>.
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
