<div class="px-2 py-3 px-md-3 py-md-3">
    <!-- FLASH MESSAGE -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 mb-3 rounded-lg" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-lg mr-2 text-success"></i>
                <div class="font-weight-medium">{{ session('message') }}</div>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- EXECUTIVE HEADER -->
    <div class="card shadow-sm border-0 mb-4 bg-white rounded-xl overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                <div class="d-flex align-items-center mb-3 mb-lg-0">
                    <div class="tree-icon-box mr-3 shadow-sm">
                        <i class="fas fa-sitemap fa-lg"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h4 class="font-weight-bold text-dark mb-0 font-display">Pohon Nilai DuPont</h4>
                            <span class="badge badge-pill badge-primary-subtle ml-2">ROE & Driver Operasional</span>
                        </div>
                        <p class="text-muted mb-0 small mt-1">
                            Dekomposisi Transmisi Kinerja Apex (ROE) ke Efisiensi Modal Kerja dan Sasaran Mutu Departemen
                        </p>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- PERIODE SELECTOR -->
                    <div class="input-group input-group-sm mr-2 shadow-2xs" style="width: 175px;">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light border-right-0 text-muted">
                                <i class="fas fa-calendar-alt text-teal"></i>
                            </span>
                        </div>
                        <select wire:model.live="selectedPeriod" class="form-control font-weight-bold border-left-0 bg-light text-dark">
                            @foreach($periods as $p)
                                <option value="{{ $p->period }}">{{ $p->period }} ({{ $p->status }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- VIEW MODE TOGGLE -->
                    <div class="btn-group btn-group-sm mr-2 shadow-2xs" role="group">
                        <button type="button" wire:click="$set('viewMode', 'visual')" 
                                class="btn {{ $viewMode === 'visual' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary bg-white' }}">
                            <i class="fas fa-project-diagram mr-1"></i> Pohon Visual
                        </button>
                        <button type="button" wire:click="$set('viewMode', 'schematic')" 
                                class="btn {{ $viewMode === 'schematic' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary bg-white' }}">
                            <i class="fas fa-code mr-1"></i> Skema Kode
                        </button>
                        <button type="button" wire:click="$set('viewMode', 'table')" 
                                class="btn {{ $viewMode === 'table' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary bg-white' }}">
                            <i class="fas fa-list-ul mr-1"></i> Kamus Driver
                        </button>
                    </div>

                    <button wire:click="resetBaseline" class="btn btn-sm btn-outline-danger shadow-2xs" title="Kembalikan semua nilai ke baseline">
                        <i class="fas fa-redo-alt mr-1"></i> Reset Baseline
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP EXECUTIVE KPI CARDS -->
    <div class="row mb-4">
        <!-- 1. APEX ROE -->
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="executive-card card-roe shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge-kpi badge-kpi-emerald">
                                <i class="fas fa-crown mr-1"></i> APEX METRIC
                            </span>
                            <span class="text-white-50 small font-weight-medium">Level 0</span>
                        </div>
                        <div class="text-white-80 text-uppercase font-weight-bold small tracking-wider mt-2">Return on Equity (ROE)</div>
                        <div class="metric-value text-white my-1">
                            {{ number_format($metrics['roe'], 2) }}<span class="metric-unit">%</span>
                        </div>
                    </div>
                    <div class="metric-footer border-top-glass pt-2 mt-2">
                        <div class="d-flex justify-content-between text-white-75 small">
                            <span>Formula:</span>
                            <span class="font-weight-medium">NPM × ATO × (1 + DER)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. ROA -->
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="executive-card card-roa shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge-kpi badge-kpi-sky">
                                <i class="fas fa-chart-line mr-1"></i> PRODUKTIVITAS ASET
                            </span>
                            <span class="text-white-50 small font-weight-medium">Efisiensi</span>
                        </div>
                        <div class="text-white-80 text-uppercase font-weight-bold small tracking-wider mt-2">Return on Assets (ROA)</div>
                        <div class="metric-value text-white my-1">
                            {{ number_format($metrics['roa'], 2) }}<span class="metric-unit">%</span>
                        </div>
                    </div>
                    <div class="metric-footer border-top-glass pt-2 mt-2">
                        <div class="d-flex justify-content-between text-white-75 small">
                            <span>Formula:</span>
                            <span class="font-weight-medium">EBIT Margin × ATO</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CASH CONVERSION CYCLE (CCC) -->
        <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
            <div class="executive-card card-ccc shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge-kpi badge-kpi-amber">
                                <i class="fas fa-sync-alt mr-1"></i> MODAL KERJA
                            </span>
                            <span class="text-white-50 small font-weight-medium">Siklus Kas</span>
                        </div>
                        <div class="text-white-80 text-uppercase font-weight-bold small tracking-wider mt-2">Cash Conversion Cycle</div>
                        <div class="metric-value text-white my-1">
                            {{ number_format($metrics['ccc'], 1) }}<span class="metric-unit"> Hari</span>
                        </div>
                    </div>
                    <div class="metric-footer border-top-glass pt-2 mt-2">
                        <div class="d-flex justify-content-between text-white-75 small">
                            <span>DSO + DIO - DPO:</span>
                            <span class="font-weight-medium">{{ number_format($metrics['dso'], 0) }} + {{ number_format($metrics['dio'], 0) }} - {{ number_format($metrics['dpo'], 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. LIKUIDITAS (CR & QR) -->
        <div class="col-xl-3 col-md-6">
            <div class="executive-card card-liquidity shadow-sm h-100">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge-kpi badge-kpi-indigo">
                                <i class="fas fa-shield-alt mr-1"></i> DAYA TAHAN LIKUIDITAS
                            </span>
                            <span class="text-white-50 small font-weight-medium">Solvabilitas</span>
                        </div>
                        <div class="text-white-80 text-uppercase font-weight-bold small tracking-wider mt-2">Current & Quick Ratio</div>
                        <div class="d-flex align-items-baseline gap-3 my-1">
                            <div class="mr-3">
                                <small class="text-white-50 d-block font-weight-bold" style="font-size: 10px;">CURRENT</small>
                                <span class="metric-value-sm text-white">{{ number_format($metrics['cr'], 2) }}x</span>
                            </div>
                            <div class="pl-3 border-left border-white-20">
                                <small class="text-white-50 d-block font-weight-bold" style="font-size: 10px;">QUICK</small>
                                <span class="metric-value-sm text-warning">{{ number_format($metrics['qr'], 2) }}x</span>
                            </div>
                        </div>
                    </div>
                    <div class="metric-footer border-top-glass pt-2 mt-2">
                        <div class="d-flex justify-content-between text-white-75 small">
                            <span>Aset Lancar / Liabilitas:</span>
                            <span class="font-weight-medium">Rp {{ number_format($metrics['totalAsetLancar'], 1) }}M / Rp {{ number_format($metrics['kewajibanLancar'], 1) }}M</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT VIEW -->
    @if($viewMode === 'visual')
        <!-- VISUAL DUPONT TREE -->
        <div class="card shadow-sm border-0 mb-4 rounded-xl overflow-hidden bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span class="tree-badge-circle mr-2"><i class="fas fa-network-wired text-primary"></i></span>
                    <h5 class="font-weight-bold text-dark mb-0">Peta Transmisi Pohon Nilai DuPont</h5>
                </div>
                <div class="text-muted small">
                    <i class="fas fa-info-circle text-teal mr-1"></i> Klik kartu kode (seperti <span class="badge badge-light border text-dark font-weight-bold">S1</span>, <span class="badge badge-light border text-dark font-weight-bold">B1</span>) untuk membuka penjelasan sasaran mutu
                </div>
            </div>

            <div class="card-body p-3 p-md-4 tree-canvas">
                <!-- ROOT NODE (ROE) -->
                <div class="tree-root-container text-center mb-4">
                    <div class="tree-node-apex shadow-md">
                        <div class="apex-header">
                            <span class="badge badge-pill badge-success-subtle px-3 py-1 text-uppercase font-weight-bold">
                                <i class="fas fa-crown text-warning mr-1"></i> Apex Kinerja Finansial
                            </span>
                        </div>
                        <h4 class="font-weight-bold text-dark mb-1 mt-2 font-display">Return on Equity (ROE)</h4>
                        <div class="apex-number text-success font-weight-bold">{{ number_format($metrics['roe'], 2) }}%</div>
                        <div class="apex-formula-pill">
                            <span class="text-primary font-weight-bold">{{ number_format($metrics['npm'], 2) }}% (NPM)</span>
                            <span class="text-muted mx-1">×</span>
                            <span class="text-info font-weight-bold">{{ number_format($metrics['ato'], 2) }}x (ATO)</span>
                            <span class="text-muted mx-1">×</span>
                            <span class="text-purple font-weight-bold" style="color: #7c3aed;">{{ number_format($metrics['leverageMultiplier'], 2) }}x (Leverage)</span>
                        </div>
                    </div>
                    <!-- CONNECTING STEM -->
                    <div class="tree-stem-v"></div>
                </div>

                <!-- TREE LEVEL 1: 3 PILAR UTAMA -->
                <div class="row tree-branches-row">
                    <!-- CABANG 1: PROFITABILITAS (NPM) -->
                    <div class="col-lg-4 mb-3 mb-lg-0">
                        <div class="tree-branch-card branch-profitability shadow-sm h-100">
                            <div class="branch-header bg-gradient-primary-subtle border-bottom p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-primary small text-uppercase tracking-wider">
                                        <i class="fas fa-chart-pie mr-1"></i> 1. Profitabilitas
                                    </span>
                                    <span class="badge badge-primary font-weight-bold">NPM</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-baseline mt-2">
                                    <h4 class="font-weight-bold text-dark mb-0 font-display">{{ number_format($metrics['npm'], 2) }}%</h4>
                                    <small class="text-muted">Net Profit Margin</small>
                                </div>
                            </div>

                            <div class="branch-body p-3">
                                <!-- EBIT MARGIN SUB-NODE -->
                                <div class="tree-subcard mb-2 p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark small">EBIT Margin</span>
                                        <span class="badge badge-light border text-teal font-weight-bold">{{ number_format($metrics['ebitMargin'], 2) }}%</span>
                                    </div>
                                    <small class="text-muted d-block font-italic mb-2" style="font-size: 11px;">
                                        Formula: GPM - (Beban Usaha ÷ Penjualan)
                                    </small>

                                    <!-- GPM -->
                                    <div class="driver-group-box mb-2 p-2 rounded bg-white border">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-weight-bold text-dark small">Gross Profit Margin (GPM)</span>
                                            <span class="badge badge-success font-weight-bold">{{ number_format($gpm, 1) }}%</span>
                                        </div>
                                        <small class="text-muted d-block mb-1.5" style="font-size: 10.5px;">Indikator Penggerak GPM:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach(['S1','S4','B1','P1','P3','P4','P5','M1','Q1'] as $kd)
                                                <button type="button" wire:click="showDetailKode('{{ $kd }}')" 
                                                        class="kpi-pill kpi-pill-success" 
                                                        title="{{ $kpiDictionary[$kd]['nama'] ?? '' }}">
                                                    {{ $kd }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- BEBAN USAHA -->
                                    <div class="driver-group-box p-2 rounded bg-white border">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-weight-bold text-dark small">Beban Usaha / Penjualan</span>
                                            <span class="badge badge-danger font-weight-bold">{{ number_format($bebanUsahaRatio, 1) }}%</span>
                                        </div>
                                        <small class="text-muted d-block mb-1.5" style="font-size: 10.5px;">Indikator Penggerak Beban Usaha:</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach(['G1','G2','L2','Q2','C4'] as $kd)
                                                <button type="button" wire:click="showDetailKode('{{ $kd }}')" 
                                                        class="kpi-pill kpi-pill-danger" 
                                                        title="{{ $kpiDictionary[$kd]['nama'] ?? '' }}">
                                                    {{ $kd }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- BUNGA & PAJAK DEDUCTIONS -->
                                <div class="d-flex justify-content-between align-items-center small py-1 px-2 text-muted">
                                    <span>Beban Bunga / Penjualan:</span>
                                    <span class="font-weight-bold text-danger">-{{ number_format($bungaRatio, 1) }}%</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small py-1 px-2 text-muted">
                                    <span>Beban Pajak / Penjualan:</span>
                                    <span class="font-weight-bold text-danger">-{{ number_format($pajakRatio, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CABANG 2: EFISIENSI ASET (ATO) -->
                    <div class="col-lg-4 mb-3 mb-lg-0">
                        <div class="tree-branch-card branch-turnover shadow-sm h-100">
                            <div class="branch-header bg-gradient-info-subtle border-bottom p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-info small text-uppercase tracking-wider">
                                        <i class="fas fa-sync-alt mr-1"></i> 2. Efisiensi Aset
                                    </span>
                                    <span class="badge badge-info font-weight-bold">ATO</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-baseline mt-2">
                                    <h4 class="font-weight-bold text-dark mb-0 font-display">{{ number_format($metrics['ato'], 2) }}x</h4>
                                    <small class="text-muted">Asset Turnover (Penjualan ÷ Aset)</small>
                                </div>
                            </div>

                            <div class="branch-body p-3">
                                <!-- PIUTANG (AR) -->
                                <div class="tree-subcard mb-2 p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="font-weight-bold text-dark small">Piutang Usaha (AR)</span>
                                            <small class="text-muted d-block" style="font-size: 10.5px;">DSO: {{ number_format($metrics['dso'], 0) }} Hari</small>
                                        </div>
                                        <span class="badge badge-info font-weight-bold">Rp {{ number_format($metrics['piutang'], 2) }} M</span>
                                    </div>
                                    <small class="text-muted d-block mb-1.5" style="font-size: 10px;">DSO × Penjualan ÷ 360 | Penggerak:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(['S5','C1','C2','C3','L1'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" 
                                                    class="kpi-pill kpi-pill-info" 
                                                    title="{{ $kpiDictionary[$kd]['nama'] ?? '' }}">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- PERSEDIAAN (INVENTORY) -->
                                <div class="tree-subcard mb-2 p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="font-weight-bold text-dark small">Persediaan (Stok Pabrik)</span>
                                            <small class="text-muted d-block" style="font-size: 10.5px;">DIO: {{ number_format($metrics['dio'], 0) }} Hari</small>
                                        </div>
                                        <span class="badge badge-warning text-dark font-weight-bold">Rp {{ number_format($metrics['persediaan'], 2) }} M</span>
                                    </div>
                                    <small class="text-muted d-block mb-1.5" style="font-size: 10px;">DIO × HPP ÷ 360 | Penggerak:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(['I1','P6','I2','I3','I4','I5','S3','B4'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" 
                                                    class="kpi-pill kpi-pill-warning" 
                                                    title="{{ $kpiDictionary[$kd]['nama'] ?? '' }}">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- KAS IDLE & ASET TETAP -->
                                <div class="tree-subcard p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark small">Kas Idle & Aset Tetap</span>
                                        <span class="badge badge-secondary font-weight-bold">Rp {{ number_format($metrics['kas'] + $metrics['asetTetap'], 2) }} M</span>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <span class="small text-muted mr-1 font-weight-medium">Kas:</span>
                                        @foreach(['F1','F2'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" class="kpi-pill kpi-pill-secondary">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                        <span class="small text-muted ml-2 mr-1 font-weight-medium">Aset:</span>
                                        @foreach(['P2','P4','M1','F8'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" class="kpi-pill kpi-pill-secondary">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CABANG 3: SOLVABILITAS (1 + DER) -->
                    <div class="col-lg-4">
                        <div class="tree-branch-card branch-leverage shadow-sm h-100">
                            <div class="branch-header bg-gradient-purple-subtle border-bottom p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-purple small text-uppercase tracking-wider">
                                        <i class="fas fa-balance-scale mr-1"></i> 3. Solvabilitas
                                    </span>
                                    <span class="badge badge-purple font-weight-bold">1 + DER</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-baseline mt-2">
                                    <h4 class="font-weight-bold text-dark mb-0 font-display">{{ number_format($metrics['leverageMultiplier'], 2) }}x</h4>
                                    <small class="text-muted">Equity Multiplier (Aset ÷ Ekuitas)</small>
                                </div>
                            </div>

                            <div class="branch-body p-3">
                                <!-- UTANG USAHA (AP) -->
                                <div class="tree-subcard mb-2 p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="font-weight-bold text-dark small">Utang Usaha (AP Vendor)</span>
                                            <small class="text-muted d-block" style="font-size: 10.5px;">DPO: {{ number_format($metrics['dpo'], 0) }} Hari</small>
                                        </div>
                                        <span class="badge badge-purple font-weight-bold">Rp {{ number_format($metrics['utangUsaha'], 2) }} M</span>
                                    </div>
                                    <small class="text-muted d-block mb-1.5" style="font-size: 10px;">DPO × HPP ÷ 360 | Penggerak:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(['B2','B3'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" 
                                                    class="kpi-pill kpi-pill-purple" 
                                                    title="{{ $kpiDictionary[$kd]['nama'] ?? '' }}">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- UTANG BERBUNGA -->
                                <div class="tree-subcard mb-2 p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark small">Utang Berbunga (Bank/Leasing)</span>
                                        <span class="badge badge-secondary font-weight-bold">Rp {{ number_format($metrics['utangBerbunga'], 2) }} M</span>
                                    </div>
                                    <small class="text-muted d-block mb-1.5" style="font-size: 10px;">Penggerak Pinjaman:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(['F3','F4','F5','F6','F8'] as $kd)
                                            <button type="button" wire:click="showDetailKode('{{ $kd }}')" class="kpi-pill kpi-pill-secondary">
                                                {{ $kd }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- EKUITAS -->
                                <div class="tree-subcard p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="font-weight-bold text-dark small">Ekuitas Bersih</span>
                                        <span class="badge badge-success font-weight-bold">Rp {{ number_format($metrics['ekuitas'], 2) }} M</span>
                                    </div>
                                    <small class="text-muted d-block mb-1.5" style="font-size: 10px;">Penggerak Modal & Laba:</small>
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <button type="button" wire:click="showDetailKode('F7')" class="kpi-pill kpi-pill-success">
                                            F7
                                        </button>
                                        <span class="badge badge-light border text-muted small py-1">+ Laba Bersih (NPM)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @elseif($viewMode === 'schematic')
        <!-- SCHEMATIC TREE VIEW (SESUAI GAMBAR STRUKTUR ASLI DENGAN DESAIN EXECUTIVE TERMINAL) -->
        <div class="card shadow-sm border-0 mb-4 rounded-xl overflow-hidden bg-white">
            <div class="card-header bg-dark-navy text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-code text-teal mr-2"></i>
                    <h5 class="font-weight-bold mb-0 text-white font-display">Skema Hierarki Dekomposisi Pohon DuPont (Buku Pedoman)</h5>
                </div>
                <span class="badge badge-teal px-3 py-1 font-weight-bold">Live Execution Engine</span>
            </div>
            <div class="card-body p-4 bg-code-terminal text-light">
<pre class="terminal-code m-0">
<span class="t-highlight-gold">ROE = NPM × ATO × (1 + DER)</span>                                      <span class="t-meta">[ Capaian Apex: {{ number_format($metrics['roe'], 2) }}% ]</span>
├── <span class="t-pilar-blue">NPM = EBIT Margin - Bunga/Penjualan - Pajak/Penjualan</span>        <span class="t-meta">[ {{ number_format($metrics['npm'], 2) }}% ]</span>
│   └── <span class="t-sub">EBIT Margin = GPM - Beban Usaha/Penjualan</span>                <span class="t-meta">[ {{ number_format($metrics['ebitMargin'], 2) }}% ]</span>
│       ├── GPM ............. ← <span class="t-driver-kpi">S1 S4 B1 P1 P3 P4 P5 M1 Q1</span>       <span class="t-meta">[ {{ number_format($gpm, 1) }}% ]</span>
│       └── Beban usaha .... ← <span class="t-driver-kpi-danger">G1 G2 L2 Q2 C4</span>                   <span class="t-meta">[ {{ number_format($bebanUsahaRatio, 1) }}% ]</span>
├── <span class="t-pilar-teal">ATO = Penjualan ÷ Total Aset</span>                                 <span class="t-meta">[ {{ number_format($metrics['ato'], 2) }}x ]</span>
│   ├── Piutang = DSO × Penjualan ÷ 360 ← <span class="t-driver-kpi-info">S5 C1 C2 C3 L1</span>        <span class="t-meta">[ Rp {{ number_format($metrics['piutang'], 2) }} M | DSO: {{ number_format($metrics['dso'], 0) }}h ]</span>
│   ├── Persediaan = DIO × HPP ÷ 360    ← <span class="t-driver-kpi-warn">I1 P6 I2 I3 I4 I5 S3 B4</span>  <span class="t-meta">[ Rp {{ number_format($metrics['persediaan'], 2) }} M | DIO: {{ number_format($metrics['dio'], 0) }}h ]</span>
│   ├── Kas idle                        ← <span class="t-driver-kpi-muted">F1 F2</span>                  <span class="t-meta">[ Rp {{ number_format($metrics['kas'], 2) }} M ]</span>
│   └── Aset tetap .................... ← <span class="t-driver-kpi-muted">P2 P4 M1 F8</span>            <span class="t-meta">[ Rp {{ number_format($metrics['asetTetap'], 2) }} M ]</span>
└── <span class="t-pilar-purple">(1 + DER) = Total Aset ÷ Ekuitas</span>                             <span class="t-meta">[ {{ number_format($metrics['leverageMultiplier'], 2) }}x ]</span>
    ├── Utang usaha = DPO × HPP ÷ 360   ← <span class="t-driver-kpi-purple">B2 B3</span>                  <span class="t-meta">[ Rp {{ number_format($metrics['utangUsaha'], 2) }} M | DPO: {{ number_format($metrics['dpo'], 0) }}h ]</span>
    ├── Utang berbunga                  ← <span class="t-driver-kpi-muted">F3 F4 F5 F6 F8</span>         <span class="t-meta">[ Rp {{ number_format($metrics['utangBerbunga'], 2) }} M ]</span>
    └── Ekuitas                         ← <span class="t-driver-kpi-success">F7 + NPM</span>               <span class="t-meta">[ Rp {{ number_format($metrics['ekuitas'], 2) }} M ]</span>

<hr class="terminal-divider">
<span class="t-header-identitas">Identitas penghubung:</span>
ROA = EBIT Margin × ATO                           = {{ number_format($metrics['ebitMargin'], 2) }}% × {{ number_format($metrics['ato'], 2) }}x = <span class="t-val-blue font-weight-bold">{{ number_format($metrics['roa'], 2) }}%</span>
DTA = DER ÷ (1 + DER)                             = {{ number_format($metrics['der'], 2) }} ÷ {{ number_format($metrics['leverageMultiplier'], 2) }} = <span class="t-val-blue font-weight-bold">{{ number_format($metrics['dta'], 2) }}%</span>
CCC = DSO + DIO - DPO                             = {{ number_format($metrics['dso'], 0) }} + {{ number_format($metrics['dio'], 0) }} - {{ number_format($metrics['dpo'], 0) }} = <span class="t-val-gold font-weight-bold">{{ number_format($metrics['ccc'], 1) }} Hari</span>

DSO = 360 ÷ AR Turnover ({{ number_format($metrics['arTurnover'], 1) }}x)           DIO = 360 ÷ Inventory Turnover ({{ number_format($metrics['invTurnover'], 1) }}x)
DSO ≈ Term kredit rata-rata (S5) + Rata-rata hari terlambat (C1) = {{ number_format($termKreditS5, 0) }} + {{ number_format($hariTerlambatC1, 0) }} = <span class="t-val-teal font-weight-bold">{{ number_format($metrics['dso'], 0) }} Hari</span>
DIO = DIO bahan baku (I1) + DIO WIP (P6) + DIO barang jadi (I2) = {{ number_format($dioBahanBakuI1, 0) }} + {{ number_format($dioWipP6, 0) }} + {{ number_format($dioBarangJadiI2, 0) }} = <span class="t-val-teal font-weight-bold">{{ number_format($metrics['dio'], 0) }} Hari</span>
DPO ≈ Term supplier rata-rata (B2) - hari bayar lebih cepat     = {{ number_format($termSupplierB2, 0) }} - {{ number_format($hariBayarCepat, 0) }} = <span class="t-val-teal font-weight-bold">{{ number_format($metrics['dpo'], 0) }} Hari</span>

CR  = (Kas + Piutang + Persediaan) ÷ Kewajiban lancar   = Rp {{ number_format($metrics['totalAsetLancar'], 2) }} M ÷ Rp {{ number_format($metrics['kewajibanLancar'], 2) }} M = <span class="t-val-green font-weight-bold">{{ number_format($metrics['cr'], 2) }}x</span>
QR  = (Kas + Piutang) ÷ Kewajiban lancar              = Rp {{ number_format($metrics['kas'] + $metrics['piutang'], 2) }} M ÷ Rp {{ number_format($metrics['kewajibanLancar'], 2) }} M = <span class="t-val-green font-weight-bold">{{ number_format($metrics['qr'], 2) }}x</span>
</pre>
            </div>
        </div>

    @elseif($viewMode === 'table')
        <!-- KAMUS DRIVER -->
        <div class="card shadow-sm border-0 mb-4 rounded-xl overflow-hidden bg-white">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="font-weight-bold text-dark mb-0 font-display">
                    <i class="fas fa-book-reader text-primary mr-2"></i> Kamus Pemetaan Driver Indikator Operasional
                </h5>
                <span class="badge badge-light border text-muted">Total: {{ count($kpiDictionary) }} Sasaran Driver</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 85px;" class="text-center">Kode</th>
                            <th style="width: 28%;">Nama Sasaran Mutu / Driver</th>
                            <th style="width: 18%;">Departemen PIC</th>
                            <th style="width: 12%;" class="text-center">Polaritas</th>
                            <th>Mekanisme Transmisi Finansial ke DuPont</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kpiDictionary as $kode => $info)
                            <tr>
                                <td class="text-center">
                                    <button type="button" wire:click="showDetailKode('{{ $kode }}')" class="kpi-pill kpi-pill-primary">
                                        {{ $kode }}
                                    </button>
                                </td>
                                <td class="font-weight-bold text-dark">{{ $info['nama'] }}</td>
                                <td>
                                    <span class="badge badge-light border px-2 py-1 font-weight-medium text-dark">
                                        {{ $info['dept'] }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if($info['polaritas'] === 'Naik')
                                        <span class="badge badge-success-subtle font-weight-bold px-2 py-1">
                                            <i class="fas fa-arrow-up mr-1"></i> Naik
                                        </span>
                                    @else
                                        <span class="badge badge-warning-subtle font-weight-bold px-2 py-1">
                                            <i class="fas fa-arrow-down mr-1"></i> Turun
                                        </span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $info['dampak'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- LIVE SIMULATOR CONTROLS & IDENTITAS PENGHUBUNG -->
    <div class="row">
        <!-- SIMULATOR CONTROLS -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 rounded-xl bg-white overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="tree-badge-circle mr-2"><i class="fas fa-sliders-h text-teal"></i></span>
                        <h5 class="font-weight-bold text-dark mb-0 font-display">Simulator Sensitivitas Driver Operasional</h5>
                    </div>
                    <span class="badge badge-light border text-muted">Perhitungan Otomatis Real-Time</span>
                </div>
                <div class="card-body p-4">
                    <!-- SECTION A: PENJUALAN & MARGIN -->
                    <div class="simulator-section mb-4">
                        <div class="section-title text-primary font-weight-bold small text-uppercase tracking-wider mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-chart-line mr-1.5"></i> A. Penjualan & Margin Keuntungan (EBIT & NPM)</span>
                            <span class="badge badge-primary-subtle font-weight-medium">EBIT: {{ number_format($metrics['ebitMargin'], 2) }}%</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="slider-box p-3 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Penjualan / Omset:</span>
                                        <span class="badge badge-primary font-weight-bold">Rp {{ number_format($penjualan, 1) }} M</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="20" max="300" step="1" wire:model.live="penjualan">
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                                        <span>20 M</span><span>Baseline: 100 M</span><span>300 M</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="slider-box p-3 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Gross Profit Margin (GPM):</span>
                                        <span class="badge badge-success font-weight-bold">{{ number_format($gpm, 1) }}%</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="10" max="60" step="0.5" wire:model.live="gpm">
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                                        <span>10%</span><span>Baseline: 30%</span><span>60%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Beban Usaha / Penj.:</span>
                                        <span class="badge badge-danger font-weight-bold">{{ number_format($bebanUsahaRatio, 1) }}%</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="3" max="30" step="0.5" wire:model.live="bebanUsahaRatio">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Bunga / Penjualan:</span>
                                        <span class="badge badge-secondary font-weight-bold">{{ number_format($bungaRatio, 1) }}%</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="0" max="8" step="0.1" wire:model.live="bungaRatio">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Pajak / Penjualan:</span>
                                        <span class="badge badge-secondary font-weight-bold">{{ number_format($pajakRatio, 1) }}%</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="0" max="8" step="0.1" wire:model.live="pajakRatio">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION B: SIKLUS KAS (DSO, DIO, DPO) -->
                    <div class="simulator-section mb-4">
                        <div class="section-title text-teal font-weight-bold small text-uppercase tracking-wider mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clock mr-1.5"></i> B. Siklus Modal Kerja & Perputaran (CCC = {{ number_format($metrics['ccc'], 1) }} Hari)</span>
                            <span class="badge badge-teal-subtle font-weight-medium">DSO ({{ number_format($metrics['dso'], 0) }}) + DIO ({{ number_format($metrics['dio'], 0) }}) - DPO ({{ number_format($metrics['dpo'], 0) }})</span>
                        </div>
                        <div class="row">
                            <!-- DSO -->
                            <div class="col-md-6 mb-3">
                                <div class="slider-box p-3 rounded bg-light border h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-info small"><i class="fas fa-file-invoice mr-1"></i> Piutang: DSO ({{ number_format($metrics['dso'], 0) }} Hari)</span>
                                        <span class="badge badge-light border text-muted">Piutang: Rp {{ number_format($metrics['piutang'], 2) }}M</span>
                                    </div>
                                    <label class="small text-muted d-flex justify-content-between mb-1">
                                        <span>Term Kredit Konsumen (S5):</span>
                                        <strong class="text-dark">{{ number_format($termKreditS5, 0) }} Hari</strong>
                                    </label>
                                    <input type="range" class="form-range w-100 mb-2" min="10" max="90" step="1" wire:model.live="termKreditS5">

                                    <label class="small text-muted d-flex justify-content-between mb-1">
                                        <span>Rata-rata Terlambat Bayar (C1):</span>
                                        <strong class="text-danger">+{{ number_format($hariTerlambatC1, 0) }} Hari</strong>
                                    </label>
                                    <input type="range" class="form-range w-100" min="0" max="60" step="1" wire:model.live="hariTerlambatC1">
                                </div>
                            </div>

                            <!-- DIO -->
                            <div class="col-md-6 mb-3">
                                <div class="slider-box p-3 rounded bg-light border h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-warning small text-dark"><i class="fas fa-boxes mr-1"></i> Persediaan: DIO ({{ number_format($metrics['dio'], 0) }} Hari)</span>
                                        <span class="badge badge-light border text-muted">Stok: Rp {{ number_format($metrics['persediaan'], 2) }}M</span>
                                    </div>
                                    <label class="small text-muted d-flex justify-content-between mb-1">
                                        <span>DIO Biji Plastik (I1):</span>
                                        <strong class="text-dark">{{ number_format($dioBahanBakuI1, 0) }} Hari</strong>
                                    </label>
                                    <input type="range" class="form-range w-100 mb-2" min="5" max="60" step="1" wire:model.live="dioBahanBakuI1">

                                    <label class="small text-muted d-flex justify-content-between mb-1">
                                        <span>DIO WIP Preform (P6):</span>
                                        <strong class="text-dark">{{ number_format($dioWipP6, 0) }} Hari</strong>
                                    </label>
                                    <input type="range" class="form-range w-100 mb-2" min="1" max="30" step="1" wire:model.live="dioWipP6">

                                    <label class="small text-muted d-flex justify-content-between mb-1">
                                        <span>DIO Botol Jadi (I2):</span>
                                        <strong class="text-dark">{{ number_format($dioBarangJadiI2, 0) }} Hari</strong>
                                    </label>
                                    <input type="range" class="form-range w-100" min="5" max="60" step="1" wire:model.live="dioBarangJadiI2">
                                </div>
                            </div>

                            <!-- DPO -->
                            <div class="col-md-12 mb-2">
                                <div class="slider-box p-3 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-purple small"><i class="fas fa-hand-holding-usd mr-1"></i> Utang Usaha: DPO ({{ number_format($metrics['dpo'], 0) }} Hari)</span>
                                        <span class="badge badge-light border text-muted">Utang AP: Rp {{ number_format($metrics['utangUsaha'], 2) }}M</span>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <label class="small text-muted d-flex justify-content-between mb-1">
                                                <span>Term Pembayaran Supplier (B2):</span>
                                                <strong class="text-dark">{{ number_format($termSupplierB2, 0) }} Hari</strong>
                                            </label>
                                            <input type="range" class="form-range w-100" min="15" max="90" step="1" wire:model.live="termSupplierB2">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="small text-muted d-flex justify-content-between mb-1">
                                                <span>Hari Bayar Lebih Cepat:</span>
                                                <strong class="text-danger">-{{ number_format($hariBayarCepat, 0) }} Hari</strong>
                                            </label>
                                            <input type="range" class="form-range w-100" min="0" max="30" step="1" wire:model.live="hariBayarCepat">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION C: NERACA & STRUKTUR MODAL -->
                    <div class="simulator-section">
                        <div class="section-title text-dark font-weight-bold small text-uppercase tracking-wider mb-3 pb-2 border-bottom">
                            <i class="fas fa-landmark mr-1.5"></i> C. Struktur Aset Tetap, Kas & Kewajiban Finansial
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Kas Idle:</span>
                                        <span class="badge badge-secondary font-weight-bold">Rp {{ number_format($kasIdle, 1) }} M</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="1" max="25" step="0.5" wire:model.live="kasIdle">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Aset Tetap (Pabrik):</span>
                                        <span class="badge badge-secondary font-weight-bold">Rp {{ number_format($asetTetap, 1) }} M</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="10" max="150" step="1" wire:model.live="asetTetap">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="slider-box p-2.5 rounded bg-light border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-dark font-weight-bold small">Utang Bank Berbunga:</span>
                                        <span class="badge badge-secondary font-weight-bold">Rp {{ number_format($utangBerbunga, 1) }} M</span>
                                    </div>
                                    <input type="range" class="form-range w-100" min="5" max="80" step="1" wire:model.live="utangBerbunga">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMULA IDENTITY SUMMARY -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 rounded-xl bg-white overflow-hidden h-100">
                <div class="card-header bg-dark-navy text-white py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calculator text-warning mr-2"></i>
                        <h5 class="font-weight-bold mb-0 text-white font-display">Identitas Penghubung</h5>
                    </div>
                    <span class="badge badge-pill badge-light text-dark font-weight-bold" style="font-size: 10px;">Executive Matrix</span>
                </div>
                <div class="card-body p-3">
                    <div class="identity-list">
                        <!-- ROA -->
                        <div class="identity-item mb-2 p-2.5 rounded bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small">ROA (Return on Assets)</span>
                                <span class="badge badge-info font-weight-bold">{{ number_format($metrics['roa'], 2) }}%</span>
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                EBIT Margin ({{ number_format($metrics['ebitMargin'], 2) }}%) × ATO ({{ number_format($metrics['ato'], 2) }}x)
                            </div>
                        </div>

                        <!-- DTA -->
                        <div class="identity-item mb-2 p-2.5 rounded bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small">DTA (Debt to Total Assets)</span>
                                <span class="badge badge-secondary font-weight-bold">{{ number_format($metrics['dta'], 2) }}%</span>
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                DER ({{ number_format($metrics['der'], 2) }}) ÷ (1 + DER: {{ number_format($metrics['leverageMultiplier'], 2) }})
                            </div>
                        </div>

                        <!-- CCC -->
                        <div class="identity-item mb-2 p-2.5 rounded bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small">Cash Conversion Cycle (CCC)</span>
                                <span class="badge badge-warning text-dark font-weight-bold">{{ number_format($metrics['ccc'], 1) }} Hari</span>
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                DSO ({{ number_format($metrics['dso'], 0) }}) + DIO ({{ number_format($metrics['dio'], 0) }}) - DPO ({{ number_format($metrics['dpo'], 0) }})
                            </div>
                        </div>

                        <!-- PERPUTARAN DSO & DIO -->
                        <div class="identity-item mb-2 p-2.5 rounded bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small">Perputaran Piutang & Stok</span>
                                <div class="d-flex gap-1">
                                    <span class="badge badge-light border text-dark font-weight-bold">AR: {{ number_format($metrics['arTurnover'], 1) }}x</span>
                                    <span class="badge badge-light border text-dark font-weight-bold ml-1">ITO: {{ number_format($metrics['invTurnover'], 1) }}x</span>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                Rasio Kecepatan Perputaran per 360 Hari Buku
                            </div>
                        </div>

                        <!-- LIKUIDITAS CR & QR -->
                        <div class="identity-item p-2.5 rounded bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold text-dark small">Rasio Lancar & Cepat</span>
                                <div>
                                    <span class="badge badge-success font-weight-bold">CR: {{ number_format($metrics['cr'], 2) }}x</span>
                                    <span class="badge badge-warning font-weight-bold text-dark ml-1">QR: {{ number_format($metrics['qr'], 2) }}x</span>
                                </div>
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                Aset Lancar Rp {{ number_format($metrics['totalAsetLancar'], 2) }}M ÷ Kewajiban Lancar Rp {{ number_format($metrics['kewajibanLancar'], 2) }}M
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL KODE DRIVER -->
    @if($selectedKode && isset($kpiDictionary[$selectedKode]))
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 1055;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-2xl rounded-2xl overflow-hidden">
                    <div class="modal-header bg-dark-navy text-white py-3 px-4 border-0">
                        <div class="d-flex align-items-center">
                            <span class="kpi-pill kpi-pill-primary px-2.5 py-1 mr-2 text-white font-weight-bold" style="font-size: 13px;">{{ $selectedKode }}</span>
                            <h5 class="modal-title font-weight-bold text-white mb-0 font-display">Detail Driver Indikator</h5>
                        </div>
                        <button type="button" wire:click="closeDetailKode" class="close text-white opacity-75 hover-opacity-100" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        <div class="mb-3">
                            <label class="text-muted small text-uppercase font-weight-bold mb-1" style="letter-spacing: 0.5px;">Sasaran Mutu Operasional</label>
                            <h5 class="font-weight-bold text-dark mb-0 font-display" style="line-height: 1.35;">{{ $kpiDictionary[$selectedKode]['nama'] }}</h5>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="text-muted small text-uppercase font-weight-bold mb-1">Departemen PIC</label>
                                <div class="p-2 rounded bg-light border font-weight-bold text-dark small">
                                    <i class="fas fa-building text-primary mr-1"></i> {{ $kpiDictionary[$selectedKode]['dept'] }}
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small text-uppercase font-weight-bold mb-1">Polaritas Target</label>
                                <div class="p-2 rounded bg-light border font-weight-bold small">
                                    @if($kpiDictionary[$selectedKode]['polaritas'] === 'Naik')
                                        <span class="text-success"><i class="fas fa-arrow-up mr-1"></i> Naik (Makin Tinggi Baik)</span>
                                    @else
                                        <span class="text-danger"><i class="fas fa-arrow-down mr-1"></i> Turun (Efisiensi)</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-xl border mb-2">
                            <label class="text-muted small text-uppercase font-weight-bold mb-1 d-flex align-items-center">
                                <i class="fas fa-project-diagram text-teal mr-1.5"></i> Mekanisme Transmisi ke Pohon DuPont:
                            </label>
                            <p class="text-dark small mb-0 font-weight-normal" style="line-height: 1.5;">
                                {{ $kpiDictionary[$selectedKode]['dampak'] }}
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-4 border-top">
                        <button type="button" wire:click="closeDetailKode" class="btn btn-secondary btn-sm px-3 rounded-pill font-weight-medium">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<!-- BESPOKE PROFESSIONAL DUPONT STYLING -->
<style>
    /* EXECUTIVE TYPOGRAPHY & LAYOUT TOKENS */
    .font-display {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        letter-spacing: -0.02em;
    }
    .rounded-xl { border-radius: 14px !important; }
    .rounded-2xl { border-radius: 18px !important; }
    .shadow-2xs { box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
    .shadow-2xl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }

    /* TREE ICON BOX */
    .tree-icon-box {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .tree-badge-circle {
        width: 32px;
        height: 32px;
        background-color: #f1f5f9;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* EXECUTIVE CARDS */
    .executive-card {
        border-radius: 14px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .executive-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1) !important;
    }
    .card-roe {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        border: 1px solid rgba(5, 150, 105, 0.2);
    }
    .card-roa {
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        border: 1px solid rgba(2, 132, 199, 0.2);
    }
    .card-ccc {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        border: 1px solid rgba(217, 119, 6, 0.2);
    }
    .card-liquidity {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .border-top-glass {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
    }
    .border-white-20 {
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    .badge-kpi {
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 2.5px 7px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }
    .badge-kpi-emerald { background-color: rgba(255, 255, 255, 0.2); color: #ecfdf5; }
    .badge-kpi-sky { background-color: rgba(255, 255, 255, 0.2); color: #f0f9ff; }
    .badge-kpi-amber { background-color: rgba(255, 255, 255, 0.2); color: #fffbeb; }
    .badge-kpi-indigo { background-color: rgba(255, 255, 255, 0.15); color: #e0e7ff; }

    .metric-value {
        font-size: clamp(1.6rem, 2vw, 2.1rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.03em;
        white-space: nowrap;
    }
    .metric-value-sm {
        font-size: clamp(1.25rem, 1.5vw, 1.55rem);
        font-weight: 800;
        line-height: 1.1;
        white-space: nowrap;
    }
    .metric-unit {
        font-size: 0.95rem;
        font-weight: 600;
        opacity: 0.9;
    }

    /* TEAL BUTTON & SUBTLE BADGES */
    .btn-teal {
        background-color: #0d9488;
        border-color: #0d9488;
        color: white;
    }
    .btn-teal:hover {
        background-color: #0f766e;
        border-color: #0f766e;
        color: white;
    }
    .badge-primary-subtle { background-color: #e0f2fe; color: #0369a1; }
    .badge-teal-subtle { background-color: #ccfbf1; color: #0f766e; }
    .badge-success-subtle { background-color: #dcfce7; color: #15803d; }
    .badge-warning-subtle { background-color: #fef3c7; color: #b45309; }

    /* VISUAL DUPONT TREE SYSTEM */
    .tree-canvas {
        background-color: #f8fafc;
        border-radius: 12px;
    }
    .tree-node-apex {
        background: #ffffff;
        border: 2px solid #10b981;
        border-radius: 16px;
        padding: 18px 28px;
        display: inline-block;
        min-width: 340px;
        max-width: 90%;
        position: relative;
    }
    .apex-number {
        font-size: 2.8rem;
        line-height: 1;
        letter-spacing: -0.04em;
    }
    .apex-formula-pill {
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 5px 14px;
        font-size: 11.5px;
        display: inline-block;
        margin-top: 8px;
    }
    .tree-stem-v {
        width: 3px;
        height: 28px;
        background: linear-gradient(to bottom, #10b981, #cbd5e1);
        margin: 0 auto;
    }

    .tree-branch-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .tree-branch-card:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    .bg-gradient-primary-subtle { background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 100%); }
    .bg-gradient-info-subtle { background: linear-gradient(180deg, #f0fdfa 0%, #ffffff 100%); }
    .bg-gradient-purple-subtle { background: linear-gradient(180deg, #faf5ff 0%, #ffffff 100%); }

    .text-purple { color: #7c3aed !important; }
    .badge-purple { background-color: #7c3aed; color: #ffffff; }

    /* KPI PILLS */
    .kpi-pill {
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        cursor: pointer;
        border: 1px solid;
        transition: all 0.15s ease;
        line-height: 1.3;
    }
    .kpi-pill:hover {
        transform: scale(1.08);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .kpi-pill-primary { background: #e0f2fe; border-color: #bae6fd; color: #0284c7; }
    .kpi-pill-success { background: #dcfce7; border-color: #bbf7d0; color: #16a34a; }
    .kpi-pill-danger  { background: #fee2e2; border-color: #fecaca; color: #dc2626; }
    .kpi-pill-info    { background: #e0f2fe; border-color: #bae6fd; color: #0284c7; }
    .kpi-pill-warning { background: #fef3c7; border-color: #fde68a; color: #d97706; }
    .kpi-pill-purple  { background: #f3e8ff; border-color: #e9d5ff; color: #7c3aed; }
    .kpi-pill-secondary { background: #f1f5f9; border-color: #e2e8f0; color: #475569; }

    /* TERMINAL SCHEMATIC VIEW */
    .bg-dark-navy { background-color: #0f172a !important; }
    .bg-code-terminal {
        background-color: #0b1120;
        border-radius: 0 0 14px 14px;
        overflow-x: auto;
    }
    .terminal-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 13.5px;
        line-height: 1.65;
    }
    .t-highlight-gold { color: #fbbf24; font-weight: 700; }
    .t-pilar-blue { color: #38bdf8; font-weight: 700; }
    .t-pilar-teal { color: #2dd4bf; font-weight: 700; }
    .t-pilar-purple { color: #c084fc; font-weight: 700; }
    .t-sub { color: #94a3b8; font-weight: 600; }
    .t-meta { color: #64748b; }
    .t-driver-kpi { color: #fde047; font-weight: 700; }
    .t-driver-kpi-danger { color: #f87171; font-weight: 700; }
    .t-driver-kpi-info { color: #38bdf8; font-weight: 700; }
    .t-driver-kpi-warn { color: #fbbf24; font-weight: 700; }
    .t-driver-kpi-purple { color: #d8b4fe; font-weight: 700; }
    .t-driver-kpi-muted { color: #94a3b8; font-weight: 700; }
    .t-driver-kpi-success { color: #4ade80; font-weight: 700; }
    .t-header-identitas { color: #f59e0b; font-weight: 700; font-size: 14px; }
    .t-val-blue { color: #38bdf8; }
    .t-val-gold { color: #fbbf24; }
    .t-val-teal { color: #2dd4bf; }
    .t-val-green { color: #4ade80; }
    .terminal-divider { border-color: rgba(255, 255, 255, 0.1); margin: 16px 0; }
</style>
