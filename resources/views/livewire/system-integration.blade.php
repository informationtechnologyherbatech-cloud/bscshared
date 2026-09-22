<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-plug text-primary mr-2"></i> Integrasi Sistem & Gateway Inbound (Hop 4)
                    </h1>
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

            <!-- PIPELINE INTEGRASI 4-HOP BANNER -->
            <div class="card card-outline card-primary mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-project-diagram mr-1"></i> Rantai Integrasi 4-Hop Enterprise
                    </h3>
                </div>
                <div class="card-body bg-light">
                    <div class="row text-center align-items-center">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="p-3 bg-white border rounded shadow-sm">
                                <span class="badge badge-secondary mb-1">Hop 1</span>
                                <h6 class="font-weight-bold mb-1"><i class="fas fa-database text-info mr-1"></i> Odoo ERP</h6>
                                <small class="text-muted">Pencatatan Transaksi & CoA</small>
                                <div class="mt-2"><span class="badge badge-success"><i class="fas fa-link"></i> Connected</span></div>
                            </div>
                        </div>
                        <div class="col-md-1 text-center d-none d-md-block text-muted">
                            <i class="fas fa-chevron-right fa-2x"></i>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="p-3 bg-white border rounded shadow-sm">
                                <span class="badge badge-secondary mb-1">Hop 2</span>
                                <h6 class="font-weight-bold mb-1"><i class="fas fa-calculator text-warning mr-1"></i> Finance Monitoring</h6>
                                <small class="text-muted">Hitung & Setujui Rasio</small>
                                <div class="mt-2"><span class="badge badge-success"><i class="fas fa-link"></i> Connected</span></div>
                            </div>
                        </div>
                        <div class="col-md-1 text-center d-none d-md-block text-muted">
                            <i class="fas fa-chevron-right fa-2x"></i>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-white border border-primary rounded shadow-sm bg-gradient-teal text-white">
                                <span class="badge badge-light text-teal mb-1 font-weight-bold">Hop 4 (Tujuan)</span>
                                <h6 class="font-weight-bold mb-1"><i class="fas fa-chart-line mr-1"></i> Super Apps BSC</h6>
                                <small class="text-white-50">Konsolidasi & Lineage Score</small>
                                <div class="mt-2"><span class="badge badge-light text-teal"><i class="fas fa-check-circle"></i> Active Endpoint</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WADAH PENERIMAAN DATA FINANCE ERP (BARU) -->
            <div class="card card-outline card-success mb-4">
                <div class="card-header bg-success text-white">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-file-invoice-dollar mr-2"></i> Wadah Penerimaan Data Finance ERP (Odoo CoA Receiver & Financial Staging)
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-light text-success font-weight-bold">Status Endpoint: ONLINE</span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Ringkasan Akun Finance Diterima & Laba Bersih -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="info-box bg-light border">
                                <span class="info-box-icon bg-info"><i class="fas fa-shopping-cart"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text font-weight-bold text-uppercase">4101 · Penjualan</span>
                                    <span class="info-box-number text-info">Rp {{ number_format($salesPayload) }} JT</span>
                                    <small class="text-muted">Pendapatan Operasional</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light border">
                                <span class="info-box-icon bg-danger"><i class="fas fa-boxes"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text font-weight-bold text-uppercase">5101 · HPP</span>
                                    <span class="info-box-number text-danger">Rp {{ number_format($hppPayload) }} JT</span>
                                    <small class="text-muted">Beban Pokok Penjualan</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-light border">
                                <span class="info-box-icon bg-warning"><i class="fas fa-file-invoice"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text font-weight-bold text-uppercase">6101 · Beban Opex</span>
                                    <span class="info-box-number text-warning">Rp {{ number_format($opexPayload) }} JT</span>
                                    <small class="text-muted">Beban Operasional & Distribusi</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-success border">
                                <span class="info-box-icon bg-white text-success"><i class="fas fa-coins"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text font-weight-bold text-uppercase">Laba Bersih Operasional</span>
                                    <span class="info-box-number text-white">Rp {{ number_format($netProfitCalculated) }} JT</span>
                                    <small class="text-white-50">Laba = Penjualan - HPP - Opex</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Simulasi / Manual Receiving Data Finance -->
                    <form wire:submit.prevent="processFinancePayload">
                        <div class="card card-body bg-light border mb-3">
                            <h6 class="font-weight-bold text-success mb-3">
                                <i class="fas fa-edit mr-1"></i> Form Penerimaan Payload Data CoA Finance (Pembaruan Saldo Transaksi)
                            </h6>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">Periode Laporan</label>
                                    <input type="month" wire:model.live="financePeriod" class="form-control form-control-sm font-weight-bold @error('financePeriod') is-invalid @enderror">
                                    @error('financePeriod') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">4101 · Penjualan (JT)</label>
                                    <input type="number" step="100" wire:model="salesPayload" class="form-control form-control-sm font-weight-bold text-primary">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">5101 · HPP (JT)</label>
                                    <input type="number" step="100" wire:model="hppPayload" class="form-control form-control-sm font-weight-bold text-danger">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">6101 · Beban Operasional (JT)</label>
                                    <input type="number" step="100" wire:model="opexPayload" class="form-control form-control-sm font-weight-bold text-warning">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">1101 · Kas & Bank (JT)</label>
                                    <input type="number" step="100" wire:model="kasPayload" class="form-control form-control-sm font-weight-bold">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">1201 · Piutang Usaha (JT)</label>
                                    <input type="number" step="100" wire:model="piutangPayload" class="form-control form-control-sm font-weight-bold">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">1301 · Persediaan Barang (JT)</label>
                                    <input type="number" step="100" wire:model="persediaanPayload" class="form-control form-control-sm font-weight-bold">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">2101 · Hutang Usaha (JT)</label>
                                    <input type="number" step="100" wire:model="hutangPayload" class="form-control form-control-sm font-weight-bold">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label class="small font-weight-bold">3101 · Modal / Ekuitas (JT)</label>
                                    <input type="number" step="100" wire:model="modalPayload" class="form-control form-control-sm font-weight-bold">
                                </div>
                                <div class="col-md-9 small text-muted d-flex align-items-center">
                                    <span>
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Saldo masuk ke <a href="{{ route('account-balances', ['period' => $financePeriod]) }}">Pos Akun</a>
                                        (Penjualan→PA01, HPP→PA02, Beban→PA03, Persediaan→PA05, Piutang→PA06, Hutang→PA07, Kas→PA08, Ekuitas→PA13),
                                        lalu 19 rasio dihitung ulang dengan target dari Katalog Rasio. Aliran = nilai YTD; neraca = saldo akhir.
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                @can('manage integration')
                                <button type="submit" class="btn btn-success btn-sm font-weight-bold px-4">
                                    <i class="fas fa-sync-alt mr-1"></i> Terima & Sinkronkan Data Finance ERP
                                </button>
                                @endcan
                            </div>
                        </div>
                    </form>

                    <!-- Tabel Hasil Penerimaan & Dampak Rasio Keuangan -->
                    @if($financialRatios->count() > 0)
                        <h6 class="font-weight-bold text-dark mt-2 mb-2"><i class="fas fa-chart-pie text-success mr-1"></i> Data Rasio Keuangan Diterima & Terhitung</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Kelompok Rasio</th>
                                        <th>Nama Rasio Keuangan</th>
                                        <th>Target</th>
                                        <th>Realisasi</th>
                                        <th>Pencapaian (%)</th>
                                        <th>Status Kinerja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($financialRatios as $fr)
                                        <tr>
                                            <td><span class="badge badge-info">{{ $fr->category }}</span></td>
                                            <td class="font-weight-bold">{{ $fr->ratio_name }}</td>
                                            <td><code>{{ $fr->display($fr->target) }}</code></td>
                                            <td class="font-weight-bold text-primary">{{ $fr->display($fr->actual) }}</td>
                                            <td><span class="badge badge-pill badge-primary">{{ number_format((float) $fr->achievement_pct, 1, ',', '.') }}%</span></td>
                                            <td>
                                                <span class="badge badge-{{ $fr->status == 'Tercapai' ? 'success' : ($fr->status == 'Waspada' ? 'warning' : 'danger') }}">
                                                    {{ $fr->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="row">
                <!-- API Gateway Credentials & Inbound Settings -->
                <div class="col-md-6">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-key mr-1"></i> Pengaturan API Gateway Inbound
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="small font-weight-bold text-uppercase">Endpoint API Inbound (POST):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-weight-bold bg-light" value="{{ $inboundEndpoint }}" readonly>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-info" onclick="navigator.clipboard.writeText('{{ $inboundEndpoint }}')">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="small font-weight-bold text-uppercase">Secret API Key (Header `X-BSC-API-KEY`):</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-weight-bold bg-light" value="{{ $apiKey }}" readonly>
                                    <div class="input-group-append">
                                        @can('manage apikey')
                                        <button wire:click="generateApiKey" class="btn btn-warning">
                                            <i class="fas fa-sync-alt"></i> Regenerate
                                        </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-light border small text-muted mb-0">
                                <i class="fas fa-shield-alt text-info mr-1"></i>
                                Semua payload masuk diwajibkan menyertakan header <code>X-BSC-API-KEY</code> dan parameter <code>idempotency_key</code> untuk mencegah duplikasi data transaksi.
                            </div>
                        </div>
                    </div>

                    <!-- Upload Berkas Manual Project (CSV / Excel) -->
                    <div class="card card-outline card-success mt-4">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-file-excel mr-1"></i> Pengiriman Data Berkas (CSV / Excel Project)
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Jika sistem project belum terhubung langsung via API, unggah berkas CSV sesuai format baku panduan adaptasi:
                                <code>PERIODE</code>, <code>DEPT_CODE</code>, <code>KPI_CODE</code>, <code>TARGET</code>, <code>ACTUAL</code>, <code>IDEMPOTENCY_KEY</code>.
                            </p>
                            <form wire:submit.prevent="uploadCsv">
                                <div class="form-group">
                                    <label>Pilih Berkas CSV / Text</label>
                                    <input type="file" wire:model="csvFile" class="form-control-file border p-1 rounded">
                                    @error('csvFile') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                </div>
                                @can('manage integration')
                                <button type="submit" class="btn btn-success btn-sm btn-block">
                                    <i class="fas fa-upload mr-1"></i> Unggah & Proses Data Project
                                </button>
                                @endcan
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Simulator Payload API Inbound -->
                <div class="col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-vial mr-1"></i> Simulator Pengiriman API Payload HRIS/Dept
                            </h3>
                        </div>
                        <div class="card-body">
                            <form wire:submit.prevent="processManualPayload">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Departemen Pengirim</label>
                                        <select wire:model.live="deptPayload" class="form-control form-control-sm @error('deptPayload') is-invalid @enderror">
                                            @foreach($units as $u)
                                                <option value="{{ $u->code }}">{{ $u->code }} — {{ \Illuminate\Support\Str::limit($u->name, 30) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Kode KPI Acuan <small class="text-muted">(periode {{ $currentPeriod }})</small></label>
                                        <select wire:model.live="kpiCodePayload" class="form-control form-control-sm @error('kpiCodePayload') is-invalid @enderror">
                                            @forelse($kpiOptions as $k)
                                                <option value="{{ $k->kpi_code }}">{{ $k->kpi_code }} — {{ \Illuminate\Support\Str::limit($k->kpi_name, 30) }}</option>
                                            @empty
                                                <option value="">belum ada sasaran di unit ini</option>
                                            @endforelse
                                        </select>
                                        @error('kpiCodePayload') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Target Disepakati</label>
                                        <input type="number" step="0.01" wire:model="targetPayload" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Realisasi Aktual</label>
                                        <input type="number" step="0.01" wire:model="actualPayload" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tautan URL Bukti Evidensi (S3 / Cloud PDF)</label>
                                    <input type="url" wire:model="evidenceUrlPayload" class="form-control form-control-sm @error('evidenceUrlPayload') is-invalid @enderror" placeholder="https://…">
                                    <small class="text-muted">Target KPI yang tertaut Cascade KPI tidak diubah oleh payload.</small>
                                </div>
                                @can('manage integration')
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-paper-plane mr-1"></i> Uji Coba Kirim API Payload Inbound
                                </button>
                                @endcan
                            </form>
                        </div>
                    </div>

                    <!-- Ringkasan Audit Integrasi Terakhir -->
                    <div class="card card-outline card-secondary mt-4">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-list-check mr-1"></i> Log Integrasi Masuk Terakhir
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-striped m-0">
                                <thead>
                                    <tr>
                                        <th>Dept</th>
                                        <th>Idempotency Key</th>
                                        <th>Status</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentLogs as $log)
                                        <tr>
                                            <td><span class="badge badge-dark">{{ $log->dept_code }}</span></td>
                                            <td><code>{{ Str::limit($log->idempotency_key, 22) }}</code></td>
                                            <td><span class="badge badge-success">{{ $log->status }}</span></td>
                                            <td><small class="text-muted">{{ $log->created_at->diffForHumans() }}</small></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-2">Belum ada log integrasi.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
