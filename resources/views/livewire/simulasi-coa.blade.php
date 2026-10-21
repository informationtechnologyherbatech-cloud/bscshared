<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-calculator text-teal mr-2"></i> Simulasi Saldo Akun (CoA) & Stress Testing
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="form-inline float-right">
                        <label for="periodSel" class="mr-2 font-weight-bold">Periode Buku:</label>
                        <select wire:model.live="selectedPeriod" id="periodSel" class="form-control form-control-sm border-teal mr-2">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        <button wire:click="resetCoa" class="btn btn-sm btn-outline-secondary" title="Kembalikan semua saldo akun ke nilai buku riil">
                            <i class="fas fa-undo mr-1"></i> Reset Saldo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- PRESET STRESS-TEST SCENARIOS -->
            <div class="card bg-gradient-light border-teal shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-lg-6 mb-2 mb-lg-0">
                            <h5 class="font-weight-bold mb-1 text-teal">
                                <i class="fas fa-microscope mr-1"></i> Stress-Test Buku Besar (Chart of Accounts)
                            </h5>
                            <p class="text-muted small mb-0">
                                Lakukan simulasi penyesuaian debet-kredit pada saldo akun buku besar untuk menguji konsistensi kalkulasi rasio finansial Menu 2 secara transparan.
                            </p>
                        </div>
                        <div class="col-lg-6 text-lg-right">
                            <span class="small font-weight-bold mr-2 text-secondary">Stress Testing Cepat:</span>
                            <div class="btn-group btn-group-sm shadow-xs" role="group">
                                <button type="button" wire:click="applyStressTest('lonjakan_bahan')" class="btn {{ $stressTestScenario === 'lonjakan_bahan' ? 'btn-danger font-weight-bold' : 'btn-outline-danger' }}">
                                    <i class="fas fa-fire mr-1"></i> Lonjakan Simplisia (+4.3M)
                                </button>
                                <button type="button" wire:click="applyStressTest('pengetatan_piutang')" class="btn {{ $stressTestScenario === 'pengetatan_piutang' ? 'btn-success font-weight-bold' : 'btn-outline-success' }}">
                                    <i class="fas fa-hand-holding-usd mr-1"></i> Tarik Piutang (+8M Kas)
                                </button>
                                <button type="button" wire:click="applyStressTest('ekspansi_mesin')" class="btn {{ $stressTestScenario === 'ekspansi_mesin' ? 'btn-primary font-weight-bold' : 'btn-outline-primary' }}">
                                    <i class="fas fa-industry mr-1"></i> Beli Mesin Pabrik
                                </button>
                                <button type="button" wire:click="resetCoa" class="btn {{ $stressTestScenario === 'none' ? 'btn-dark font-weight-bold' : 'btn-outline-dark' }}">
                                    <i class="fas fa-dot-circle mr-1"></i> Normal
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4 CARD RINGKASAN STRUKTUR NERACA & BALANCE CHECK DISEMBUNYIKAN ATAS PERMINTAAN USER --}}
            {{--
            <div class="row mb-2">
                ... 4 cards ...
            </div>
            --}}

            <div class="row">
                <!-- TABEL SIMULASI BAGAN AKUN (CHART OF ACCOUNTS) -->
                <div class="col-lg-7 mb-4">
                    <div class="card card-outline card-teal shadow-sm h-100">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                            <div>
                                <h3 class="card-title font-weight-bold mb-1 mb-md-0">
                                    <i class="fas fa-list mr-1 text-teal"></i> Bagan Akun & Pos Anggaran Odoo
                                </h3>
                                <span class="badge badge-success ml-2 font-weight-normal">
                                    <i class="fas fa-link mr-1"></i> Terhubung Aplikasi Finance
                                </span>
                            </div>
                            <div class="card-tools d-flex align-items-center flex-wrap mt-2 mt-md-0">
                                <!-- 3 PILIHAN SWITCH MODE RENTANG WAKTU -->
                                <div class="btn-group btn-group-sm mr-2 shadow-xs" role="group">
                                    <button type="button" 
                                            wire:click="setViewMode('monthly')" 
                                            class="btn {{ $viewMode === 'monthly' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary' }}"
                                            title="Menampilkan beban pengeluaran hanya pada bulan terpilih (MTD)">
                                        <i class="fas fa-calendar-day mr-1"></i> Bulanan (MTD)
                                    </button>
                                    <button type="button" 
                                            wire:click="setViewMode('actual')" 
                                            class="btn {{ $viewMode === 'actual' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary' }}"
                                            title="Menampilkan akumulasi realisasi berjalan sejak Januari s/d bulan terpilih (YTD Odoo)">
                                        <i class="fas fa-layer-group mr-1"></i> Akumulasi (YTD)
                                    </button>
                                    <button type="button" 
                                            wire:click="setViewMode('budget')" 
                                            class="btn {{ $viewMode === 'budget' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary' }}"
                                            title="Menampilkan target pagu anggaran setahun penuh">
                                        <i class="fas fa-calendar-alt mr-1"></i> Pagu Setahun
                                    </button>
                                </div>

                                <div class="input-group input-group-sm mr-2" style="width: 140px;">
                                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari akun/dept...">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    </div>
                                </div>
                                <select wire:model.live="filterAccountType" class="form-control form-control-sm border-teal" style="width: 125px;">
                                    <option value="all">Semua Jenis</option>
                                    <option value="asset">Aset (1xxxx)</option>
                                    <option value="liability">Liabilitas (2xxxx)</option>
                                    <option value="equity">Ekuitas (3xxxx)</option>
                                    <option value="income">Pendapatan (4xxxx)</option>
                                    <option value="expense">Beban/HPP (5x/6x)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Info bar mode yang aktif -->
                        <div class="bg-light px-3 py-2 border-bottom d-flex align-items-center justify-content-between small">
                            <div>
                                <i class="fas fa-info-circle text-teal mr-1"></i> 
                                Basis Saldo Buku: 
                                <strong class="text-teal">
                                    @if($viewMode === 'monthly')
                                        Realisasi Bulan Berjalan (MTD {{ $selectedPeriod }})
                                    @elseif($viewMode === 'actual')
                                        Akumulasi Berjalan Odoo (YTD s/d {{ $selectedPeriod }})
                                    @else
                                        Pagu Anggaran 1 Tahun Penuh (Budget)
                                    @endif
                                </strong>
                                @if($viewMode === 'monthly')
                                    <span class="text-muted ml-1">(Beban aktual murni yang dicatat pada bulan {{ $selectedPeriod }})</span>
                                @elseif($viewMode === 'actual')
                                    <span class="text-muted ml-1">(Realisasi akumulatif mutasi akun buku besar Odoo)</span>
                                @else
                                    <span class="text-muted ml-1">(Target pagu tahunan berbasis rasio anggaran dari revenue)</span>
                                @endif
                            </div>
                            <span class="badge badge-light border">
                                Total Akun: {{ count($filteredAccounts) }}
                            </span>
                        </div>

                        <div class="card-body p-0 table-responsive" style="max-height: 580px; overflow-y: auto;">
                            <table class="table table-sm table-hover table-bordered m-0 align-middle" style="font-size: 13px;">
                                <thead class="bg-light position-sticky" style="top: 0; z-index: 2;">
                                    <tr>
                                        <th style="width: 14%;">Kode</th>
                                        <th style="width: 38%;">Nama Akun / Pos Anggaran</th>
                                        <th class="text-right" style="width: 20%;">
                                            Saldo Buku Riil (Rp)
                                            <div class="text-muted" style="font-size: 10px; font-weight: normal;">
                                                @if($viewMode === 'monthly')
                                                    [Bulan Berjalan MTD]
                                                @elseif($viewMode === 'actual')
                                                    [Akumulasi YTD]
                                                @else
                                                    [Pagu Setahun]
                                                @endif
                                            </div>
                                        </th>
                                        <th class="text-center" style="width: 17%;">
                                            Penyesuaian (&Delta; Rp)
                                            <div class="text-muted" style="font-size: 10px; font-weight: normal;">[Ketik Angka Riil]</div>
                                        </th>
                                        <th class="text-right" style="width: 17%;">Saldo Hasil (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($filteredAccounts as $acc)
                                        <tr>
                                            <td><code>{{ $acc['code'] }}</code></td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="font-weight-bold text-dark">{{ $acc['name'] }}</span>
                                                    <button type="button" 
                                                            wire:click="showAccountDetails('{{ $acc['code'] }}', '{{ addslashes($acc['name']) }}')" 
                                                            class="btn btn-xs btn-outline-teal ml-1" 
                                                            title="Lihat Rincian Mutasi / Transaksi Odoo">
                                                        <i class="fas fa-search-dollar mr-1"></i> Detail
                                                    </button>
                                                </div>
                                                <div class="d-flex align-items-center flex-wrap mt-1">
                                                    <span class="badge badge-light border mr-1">{{ $acc['group'] }}</span>
                                                    @if(isset($acc['dept']))
                                                        <span class="badge badge-teal mr-1" style="font-size: 10px;">{{ $acc['dept'] }}</span>
                                                    @endif
                                                    @if(isset($acc['ratio_percent']) && $acc['ratio_percent'] > 0)
                                                        <span class="badge badge-info mr-1" style="font-size: 10px;">Pagu: {{ $acc['ratio_percent'] }}%</span>
                                                    @endif
                                                    @if(isset($acc['actual_amount']) && isset($acc['budget_amount']) && $acc['budget_amount'] > 0)
                                                        @php
                                                            $serapan = round(($acc['actual_amount'] / $acc['budget_amount']) * 100, 1);
                                                        @endphp
                                                        <span class="badge {{ $serapan > 100 ? 'badge-danger' : 'badge-light border' }}" style="font-size: 10px;" title="Realisasi Rp {{ number_format($acc['actual_amount'] * 1000000000, 0, ',', '.') }} dari Pagu Rp {{ number_format($acc['budget_amount'] * 1000000000, 0, ',', '.') }}">
                                                             Serapan: {{ $serapan }}%
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-right font-weight-normal text-dark">
                                                <div class="font-weight-bold text-nowrap">
                                                    Rp {{ number_format($acc['baseline_raw'] ?? ($acc['baseline'] * 1000000000), 0, ',', '.') }}
                                                </div>
                                                <small class="text-muted d-block" style="font-size: 10px;">
                                                    ({{ number_format($acc['baseline'], 2, ',', '.') }} M)
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="input-group input-group-sm mx-auto" style="min-width: 140px;">
                                                    <input type="text" 
                                                           value="{{ isset($coaDeltas[$acc['code']]) && $coaDeltas[$acc['code']] != 0 ? number_format($coaDeltas[$acc['code']], 0, ',', '.') : '' }}"
                                                           oninput="formatIndoNumber(this, '{{ $acc['code'] }}')"
                                                           placeholder="0"
                                                           class="form-control form-control-sm text-right font-weight-bold {{ isset($coaDeltas[$acc['code']]) && $coaDeltas[$acc['code']] != 0 ? 'border-teal bg-light text-teal' : '' }}">
                                                </div>
                                                @if(isset($coaDeltas[$acc['code']]) && $coaDeltas[$acc['code']] != 0)
                                                    <small class="d-block font-weight-bold mt-1 text-teal" style="font-size: 10px;">
                                                        {{ ($coaDeltas[$acc['code']] > 0 ? '+' : '') . 'Rp ' . number_format($coaDeltas[$acc['code']], 0, ',', '.') }}
                                                        ({{ number_format($acc['delta_miliar'], 2, ',', '.') }} M)
                                                    </small>
                                                @endif
                                            </td>
                                            <td class="text-right font-weight-bold {{ $acc['delta'] != 0 ? 'text-teal' : 'text-dark' }}">
                                                <div class="text-nowrap">
                                                    Rp {{ number_format($acc['simulated_raw'] ?? ($acc['simulated'] * 1000000000), 0, ',', '.') }}
                                                </div>
                                                <small class="text-muted d-block font-weight-normal" style="font-size: 10px;">
                                                    ({{ number_format($acc['simulated'], 2, ',', '.') }} M)
                                                </small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-light p-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i> Nilai saldo akun di atas menampilkan <strong>angka nominal riil penuh (Rupiah)</strong> yang tercatat di Odoo ERP. Masukkan angka simulasi pada kolom <em>Penyesuaian (&Delta; M)</em> dalam satuan Miliar.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- HASIL TRANSPARAN TRANSMISI KE 15 RASIO KEUANGAN MENU 2 -->
                <div class="col-lg-5 mb-4">
                    <div class="card card-outline card-teal shadow-sm h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-balance-scale mr-1 text-teal"></i> Verifikasi Rasio Keuangan Menu 2
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-striped m-0 align-middle" style="font-size: 13px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 44%;">Rasio Finansial</th>
                                        <th class="text-center" style="width: 22%;">Benchmark</th>
                                        <th class="text-center" style="width: 18%;">Hasil CoA</th>
                                        <th class="text-center" style="width: 16%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $groupedRatios = collect($ratios)->groupBy('category');
                                    @endphp
                                    @foreach($groupedRatios as $catName => $catItems)
                                        <tr style="background-color: #ffff00;">
                                            <td colspan="4" class="py-1 px-3 font-weight-bold text-dark text-uppercase border-top border-bottom border-dark" style="font-size: 13px; letter-spacing: 0.5px;">
                                                <strong>{{ $catName }}</strong>
                                            </td>
                                        </tr>
                                        @foreach($catItems as $r)
                                            <tr>
                                                <td class="pl-3">
                                                    <div class="font-weight-bold text-dark">{{ $r['name'] }}</div>
                                                </td>
                                                <td class="text-center font-weight-bold text-muted" style="font-size: 12px;">
                                                    {{ $r['benchmark_display'] }}
                                                </td>
                                                <td class="text-center font-weight-bold text-teal">
                                                    {{ number_format($r['val'], 2) }} {{ $r['unit'] }}
                                                </td>
                                                <td class="text-center">
                                                    @if($r['status'] === 'Tercapai')
                                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check"></i></span>
                                                    @elseif($r['status'] === 'Waspada')
                                                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation"></i></span>
                                                    @else
                                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times"></i></span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-light p-2">
                            <small class="text-muted">
                                <i class="fas fa-check-double mr-1 text-teal"></i> Formula perhitungan rasio di atas 100% konsisten dengan formula matematis di modul <strong>Rasio Keuangan (Menu 2)</strong>.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- MODAL RINCIAN TRANSAKSI MUTASI ODOO ERP -->
    @if($showDetailModal)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5); z-index: 1050;" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                <div class="modal-content shadow-lg border-teal">
                    <div class="modal-header bg-gradient-teal text-white py-2">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-file-invoice mr-2"></i> Rincian Mutasi Odoo: {{ $selectedAccountCode }} - {{ $selectedAccountName }}
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeDetailModal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="d-flex align-items-center justify-content-between p-2 mb-3 bg-light rounded border">
                            <div>
                                <span class="text-muted small">Periode Buku:</span>
                                <strong class="text-dark ml-1">{{ $selectedPeriod }}</strong>
                                <span class="badge badge-info ml-2">
                                    {{ $viewMode === 'monthly' ? 'Bulan Berjalan (MTD)' : 'Akumulasi (YTD)' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted small">Total Mutasi:</span>
                                <strong class="text-teal ml-1" style="font-size: 16px;">
                                    Rp {{ number_format($accountTransactionsTotal, 2, ',', '.') }}
                                </strong>
                                <span class="badge badge-secondary ml-2">
                                    {{ $accountTransactionsCount }} Transaksi
                                </span>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-sm table-hover table-striped table-bordered align-middle m-0" style="font-size: 12px;">
                                <thead class="bg-light position-sticky" style="top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 5%;" class="text-center">#</th>
                                        <th style="width: 12%;" class="text-center">Tanggal</th>
                                        <th style="width: 22%;">No. Referensi / PO</th>
                                        <th style="width: 41%;">Keterangan Transaksi / Nama Barang</th>
                                        <th style="width: 20%;" class="text-right">Nominal (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accountTransactions as $idx => $trx)
                                        <tr>
                                            <td class="text-center text-muted">{{ $idx + 1 }}</td>
                                            <td class="text-center text-nowrap font-weight-bold">{{ $trx->date }}</td>
                                            <td>
                                                <code class="text-dark font-weight-bold">{{ $trx->reference ?: '-' }}</code>
                                                @if($trx->odoo_move_line_id)
                                                    <span class="d-block text-muted" style="font-size: 10px;">Move Line: #{{ $trx->odoo_move_line_id }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $trx->description }}</td>
                                            <td class="text-right font-weight-bold text-dark text-nowrap">
                                                Rp {{ number_format($trx->amount, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary"></i>
                                                Tidak ada catatan transaksi Odoo untuk akun ini pada periode yang dipilih.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer py-2 bg-light">
                        <small class="text-muted mr-auto">
                            <i class="fas fa-database mr-1 text-teal"></i> Data bersumber langsung secara dinamis dari database Odoo ERP (tabel <code>expenses</code>).
                        </small>
                        <button type="button" class="btn btn-sm btn-secondary font-weight-bold" wire:click="closeDetailModal">
                            <i class="fas fa-times mr-1"></i> Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        let formatTimer = {};
        function formatIndoNumber(el, code) {
            let val = el.value;
            // Izinkan tanda minus di depan jika user ingin mengurangi beban
            let isNegative = val.startsWith('-');
            let rawNum = val.replace(/\D/g, '');
            
            if (rawNum === '') {
                el.value = isNegative ? '-' : '';
            } else {
                let formatted = new Intl.NumberFormat('id-ID').format(rawNum);
                el.value = (isNegative ? '-' : '') + formatted;
            }

            // Debounce pengiriman ke Livewire agar performa ketikan sangat mulus
            clearTimeout(formatTimer[code]);
            formatTimer[code] = setTimeout(() => {
                let finalVal = rawNum === '' ? 0 : parseInt(rawNum, 10);
                if (isNegative) finalVal = -finalVal;
                @this.set('coaDeltas.' + code, finalVal);
            }, 400);
        }
    </script>
</div>
