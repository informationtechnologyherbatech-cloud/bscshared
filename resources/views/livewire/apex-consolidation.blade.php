<div class="px-2 py-3 px-md-3">
    <!-- HEADER & PERIODE SELECTION -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-crown text-warning mr-2"></i> Level 1: Apex & Konsolidasi Hop 4
            </h2>
            <p class="text-muted mb-0">Pusat input data revenue puncak dan analisis transmisi ke rasio makro.</p>
        </div>
        <div class="d-flex align-items-center mt-3 mt-md-0">
            <span class="mr-2 font-weight-bold text-muted small">Periode Evaluasi:</span>
            <select wire:model.live="selectedPeriod" class="form-control form-control-sm font-weight-bold border-teal" style="width: 140px;">
                @foreach($periods as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
            @if($isClosed)
                <span class="badge badge-secondary ml-2 px-2 py-1"><i class="fas fa-lock mr-1"></i> CLOSED</span>
            @else
                <span class="badge badge-success ml-2 px-2 py-1"><i class="fas fa-lock-open mr-1"></i> OPEN</span>
            @endif
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- 1. EARLY WARNING ALERT / RED FLAGS (Jika ada rasio bermasalah) -->
    @if(count($redFlags) > 0)
        <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-bell text-danger mr-2 h5 mb-0"></i>
                <strong class="text-dark">Peringatan Dini Eksekutif (Early Warning Red Flags):</strong>
                <span class="badge badge-danger ml-2">{{ count($redFlags) }} Rasio Butuh Perhatian</span>
            </div>
            <div class="row">
                @foreach($redFlags as $rf)
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="p-2 bg-white rounded border d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">{{ $rf->category }}</small>
                                <strong class="text-dark small">{{ $rf->ratio_name }}</strong>
                            </div>
                            <div class="text-right">
                                <span class="badge {{ $rf->status === 'Off-Target' || $rf->status === 'Di Bawah Target' ? 'badge-danger' : 'badge-warning text-dark' }} px-2 py-1">
                                    {{ $rf->actual }}{{ $rf->unit }} ({{ number_format($rf->achievement_pct, 1) }}%)
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 4 RINGKASAN METRIK KONSOLIDASI (Hanya tampil pada sub menu Input Revenue) -->
    @if($activeTab === 'input')
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase font-weight-bold">Target Revenue (RKAP)</small>
                        <h2 class="font-weight-bold my-1 text-white">IDR {{ number_format($target_revenue, 2) }} M</h2>
                        <small class="text-white"><i class="fas fa-bullseye mr-1"></i> Pagu Baseline Target Tahunan</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase font-weight-bold">Realisasi Revenue Hop 4</small>
                        <h2 class="font-weight-bold my-1 text-white">IDR {{ number_format($actual_revenue, 2) }} M</h2>
                        <small class="text-white-50">Capaian: <strong>{{ number_format($revenueAchievementPct, 1) }}%</strong> dari Target</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase font-weight-bold">Deviasi / Gap Omset</small>
                        @php
                            $gapRev = $actual_revenue - $target_revenue;
                        @endphp
                        <h2 class="font-weight-bold my-1 text-white">{{ $gapRev >= 0 ? '+' : '' }}IDR {{ number_format($gapRev, 2) }} M</h2>
                        <small class="text-white-50">{{ $gapRev >= 0 ? 'Surplus Penjualan' : 'Defisit terhadap Target' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, {{ $revenueAchievementPct >= 100 ? '#059669 0%, #047857 100%' : ($revenueAchievementPct >= 80 ? '#d97706 0%, #b45309 100%' : '#dc2626 0%, #b91c1c 100%') }});">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase font-weight-bold">Status Capaian Omset</small>
                        <h2 class="font-weight-bold my-1 text-white">
                            {{ $revenueAchievementPct >= 100 ? 'TERCAPAI' : ($revenueAchievementPct >= 80 ? 'WASPADA' : 'DI BAWAH') }}
                        </h2>
                        <small class="text-white-50">Evaluasi Realisasi vs Target</small>
                    </div>
                </div>
            </div>
        </div>
    @endif


    <!-- MAIN CARD WITH TABS -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="font-weight-bold text-dark mb-2 mb-md-0">
                @if($activeTab === 'input')
                    <i class="fas fa-edit text-teal mr-2"></i> Form Target & Realisasi Revenue
                @elseif($activeTab === 'dupont')
                    <i class="fas fa-sitemap text-info mr-2"></i> DuPont Value Tree Decomposition
                @elseif($activeTab === 'sensitivity')
                    <i class="fas fa-sliders-h text-warning mr-2"></i> Simulator Sensitivitas Transmisi
                @elseif($activeTab === 'weights')
                    <i class="fas fa-layer-group text-primary mr-2"></i> Matriks Kontribusi 5 Pilar Rasio
                @endif
            </h5>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <button wire:click="setTab('input')" class="nav-link btn-sm font-weight-bold {{ $activeTab === 'input' ? 'active bg-teal text-white' : 'text-dark' }} mr-1">
                        <i class="fas fa-edit mr-1"></i> Input Revenue
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('sensitivity')" class="nav-link btn-sm font-weight-bold {{ $activeTab === 'sensitivity' ? 'active bg-warning text-dark' : 'text-dark' }} mr-1">
                        <i class="fas fa-sliders-h mr-1"></i> Analisis Sensitivitas
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('weights')" class="nav-link btn-sm font-weight-bold {{ $activeTab === 'weights' ? 'active bg-primary text-white' : 'text-dark' }} mr-1">
                        <i class="fas fa-layer-group mr-1"></i> Bobot 5 Pilar
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('dupont')" class="nav-link btn-sm font-weight-bold {{ $activeTab === 'dupont' ? 'active bg-info text-white' : 'text-dark' }} mr-1">
                        <i class="fas fa-sitemap mr-1"></i> DuPont Tree
                    </button>
                </li>
                <li class="nav-item">
                    <a href="{{ route('dampak') }}" class="nav-link btn-sm font-weight-bold text-purple border border-purple">
                        <i class="fas fa-vial mr-1"></i> Skenario / What-If Sandbox <i class="fas fa-external-link-alt ml-1 small"></i>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">
            
            <!-- TAB 1: FORM INPUT & PANDUAN PENGISIAN -->
            @if($activeTab === 'input')
                <div class="row">
                    <!-- FORM INPUT -->
                    <div class="col-md-7 mb-4 mb-md-0">
                        <div class="p-3 bg-light rounded border h-100">
                            <h5 class="font-weight-bold text-teal mb-3">
                                <i class="fas fa-pen-square mr-1"></i> Input Target & Realisasi Revenue
                            </h5>

                            @if($isClosed)
                                <div class="alert alert-warning small">
                                    <i class="fas fa-lock mr-1"></i> Periode <strong>{{ $selectedPeriod }}</strong> sedang berstatus <strong>CLOSED</strong>. Form dikunci untuk menjaga integritas data audit.
                                </div>
                            @endif

                            <form wire:submit.prevent="saveApexInput">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold text-dark small">Target Baseline Revenue (IDR Miliar):</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-white">Rp</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control" wire:model="target_revenue" {{ $isClosed ? 'disabled' : '' }}>
                                            <div class="input-group-append">
                                                <span class="input-group-text bg-white">M</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Target omset yang ditetapkan dalam RKAP tahunan.</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="font-weight-bold text-dark small">Realisasi Actual Revenue (IDR Miliar):</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-white">Rp</span>
                                            </div>
                                            <input type="number" step="0.01" class="form-control" wire:model="actual_revenue" {{ $isClosed ? 'disabled' : '' }}>
                                            <div class="input-group-append">
                                                <span class="input-group-text bg-white">M</span>
                                            </div>
                                        </div>
                                        <small class="text-muted">Pencapaian riil pendapatan pada periode ini.</small>
                                    </div>
                                </div>

                                @can('manage ratios')
                                    @if(!$isClosed)
                                        <button type="submit" class="btn btn-teal btn-block font-weight-bold mt-3">
                                            <i class="fas fa-save mr-1"></i> Simpan Data Revenue
                                        </button>
                                    @endif
                                @else
                                    <div class="alert alert-info small mb-0 mt-3">
                                        <i class="fas fa-info-circle mr-1"></i> Anda memiliki akses baca (Read-Only). Perubahan angka hanya diizinkan untuk Admin FAT atau Super Admin.
                                    </div>
                                @endcan
                            </form>
                        </div>
                    </div>

                    <!-- PANDUAN CARA MENGISI -->
                    <div class="col-md-5">
                        <div class="p-3 bg-white rounded border border-info h-100 shadow-sm">
                            <h5 class="font-weight-bold text-info mb-3">
                                <i class="fas fa-question-circle mr-1"></i> Petunjuk & Cara Mengisi
                            </h5>
                            <ol class="small text-muted pl-3 mb-3" style="line-height: 1.8;">
                                <li>
                                    <strong>Pilih Periode Aktif:</strong> Pastikan memilih bulan evaluasi yang sesuai di bagian atas (contoh: <code>2026-08</code>).
                                </li>
                                <li>
                                    <strong>Target Baseline Revenue:</strong> Masukkan nilai target omset penjualan perusahaan dari RKAP tahunan (dalam satuan <em>Miliar Rupiah</em>).
                                </li>
                                <li>
                                    <strong>Realisasi Actual Revenue:</strong> Masukkan pencapaian omset pendapatan riil penutupan buku pada periode berjalan.
                                </li>
                                <li>
                                    <strong>Klik Simpan:</strong> Sistem akan secara instan menyimpan data omset revenue periode ini dan mengupdate capaian target perusahaan.
                                </li>
                            </ol>

                            <div class="card bg-light border-0 p-2.5 mb-0">
                                <strong class="small text-dark"><i class="fas fa-chart-line text-teal mr-1"></i> Kinerja Omset Periode Ini:</strong>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="small text-muted">Persentase Capaian:</span>
                                    <span class="badge {{ $revenueAchievementPct >= 100 ? 'badge-success' : ($revenueAchievementPct >= 80 ? 'badge-warning text-dark' : 'badge-danger') }} font-weight-bold px-2 py-1" style="font-size: 12px;">
                                        {{ number_format($revenueAchievementPct, 2) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- TAB 2: DUPONT VALUE TREE -->
            @if($activeTab === 'dupont')
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="font-weight-bold text-teal mb-0"><i class="fas fa-project-diagram mr-1"></i> Dekomposisi Du-Pont: Struktur Penggerak Apex Keuangan (ROE)</h6>
                        <small class="text-muted font-italic">ROE = Net Profit Margin × Asset Turnover × Financial Leverage</small>
                    </div>
                    <div class="row align-items-center mt-3 text-center">
                        <!-- ROE APEX -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="card border border-success shadow-sm bg-white p-3 h-100">
                                <span class="badge badge-success mb-2 align-self-center px-3 py-1">APEX METRIC</span>
                                <h5 class="font-weight-bold text-dark mb-1">Return on Equity (ROE)</h5>
                                <h2 class="font-weight-bold text-success my-1">{{ number_format($dupontData['roe']['actual'], 2) }}%</h2>
                                <small class="text-muted">Target: {{ number_format($dupontData['roe']['target'], 2) }}% | Capaian: <strong>{{ number_format($dupontData['roe']['achievement'], 1) }}%</strong></small>
                            </div>
                        </div>
                        <div class="col-md-1 d-none d-md-block text-muted h3 font-weight-bold mb-0">=</div>

                        <!-- NPM -->
                        <div class="col-md-2 col-6 mb-3 mb-md-0">
                            <div class="card border shadow-sm bg-white p-2 h-100 {{ $dupontData['npm']['status'] === 'Waspada' ? 'border-warning' : 'border-success' }}">
                                <small class="text-muted font-weight-bold text-uppercase">1. Profitabilitas</small>
                                <h6 class="font-weight-bold text-dark mt-1 mb-0">Net Profit Margin</h6>
                                <h3 class="font-weight-bold my-1 {{ $dupontData['npm']['status'] === 'Waspada' ? 'text-warning' : 'text-success' }}">{{ number_format($dupontData['npm']['actual'], 1) }}%</h3>
                                <small class="text-muted">Target: {{ number_format($dupontData['npm']['target'], 1) }}%</small>
                                <span class="badge {{ $dupontData['npm']['status'] === 'Waspada' ? 'badge-warning text-dark' : 'badge-success' }} mt-1">{{ $dupontData['npm']['status'] }}</span>
                            </div>
                        </div>
                        <div class="col-md-1 d-none d-md-block text-muted h3 font-weight-bold mb-0">×</div>

                        <!-- ASSET TURNOVER -->
                        <div class="col-md-2 col-6 mb-3 mb-md-0">
                            <div class="card border border-success shadow-sm bg-white p-2 h-100">
                                <small class="text-muted font-weight-bold text-uppercase">2. Efisiensi Aset</small>
                                <h6 class="font-weight-bold text-dark mt-1 mb-0">Asset Turnover</h6>
                                <h3 class="font-weight-bold text-success my-1">{{ number_format($dupontData['asset_turnover']['actual'], 2) }}x</h3>
                                <small class="text-muted">Target: {{ number_format($dupontData['asset_turnover']['target'], 2) }}x</small>
                                <span class="badge badge-success mt-1">Efisiensi Tinggi</span>
                            </div>
                        </div>
                        <div class="col-md-1 d-none d-md-block text-muted h3 font-weight-bold mb-0">×</div>

                        <!-- LEVERAGE -->
                        <div class="col-md-2 col-12">
                            <div class="card border border-info shadow-sm bg-white p-2 h-100">
                                <small class="text-muted font-weight-bold text-uppercase">3. Solvabilitas</small>
                                <h6 class="font-weight-bold text-dark mt-1 mb-0">Financial Leverage</h6>
                                <h3 class="font-weight-bold text-info my-1">{{ number_format($dupontData['leverage']['actual'], 2) }}x</h3>
                                <small class="text-muted">Target Max: {{ number_format($dupontData['leverage']['target'], 2) }}x</small>
                                <span class="badge badge-info mt-1">Struktur Aman</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- TAB 3: SIMULATOR SENSITIVITAS REVENUE -->
            @if($activeTab === 'sensitivity')
                <div class="p-3 bg-light rounded border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold text-teal mb-0"><i class="fas fa-sliders-h mr-1"></i> Simulator Sensitivitas Transmisi: Deviasi Revenue Terhadap Apex Score</h6>
                        <span class="badge badge-pill badge-primary px-3 py-1">Simulasi Interaktif</span>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="font-weight-bold text-dark">Geser Deviasi Realisasi Revenue: <span class="badge badge-info h6 mb-0">{{ $simulatedRevenueDev >= 0 ? '+' : '' }}{{ $simulatedRevenueDev }}%</span></label>
                            <input type="range" class="form-range w-100" min="-20" max="20" step="1" wire:model.live="simulatedRevenueDev">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Pesimis (-20%)</span>
                                <span>Baseline (0%)</span>
                                <span>Optimis (+20%)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded border shadow-sm">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Revenue Hasil Simulasi:</span>
                                    <strong class="text-dark">IDR {{ number_format($simulatedRevenue, 2) }} M ({{ number_format($simulatedRevenuePct, 1) }}%)</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Proyeksi Apex Score Baru:</span>
                                    <h4 class="font-weight-bold {{ $simulatedApexScore >= $apexScore ? 'text-success' : 'text-danger' }} mb-0">{{ number_format($simulatedApexScore, 2) }}%</h4>
                                </div>
                                <div class="d-flex justify-content-between align-items-center border-top pt-2 small">
                                    <span class="text-muted">Dampak Perubahan ke Apex:</span>
                                    <span class="font-weight-bold {{ $apexDelta >= 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="fas {{ $apexDelta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1"></i> {{ $apexDelta >= 0 ? '+' : '' }}{{ number_format($apexDelta, 2) }} Poin
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- TAB 4: MATRIKS KONTRIBUSI 5 PILAR -->
            @if($activeTab === 'weights')
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="font-weight-bold text-teal mb-0"><i class="fas fa-layer-group mr-1"></i> Bobot Kontribusi 5 Pilar Rasio ke Hop 4</h6>
                        <span class="badge badge-info px-2 py-1">Terkoneksi Rasio Keuangan</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered bg-white mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Pilar Kategori</th>
                                    <th class="text-center" style="width: 15%;">Bobot Strategis</th>
                                    <th class="text-center" style="width: 20%;">Rata-Rata Capaian</th>
                                    <th class="text-center" style="width: 20%;">Kontribusi Poin</th>
                                    <th class="text-center" style="width: 15%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pillarContributions as $p)
                                    <tr>
                                        <td><i class="fas {{ $p['icon'] }} mr-1" style="color: {{ $p['color'] }}"></i> <strong>{{ $p['name'] }}</strong></td>
                                        <td class="text-center font-weight-bold">{{ $p['weight'] }}%</td>
                                        <td class="text-center font-weight-bold">{{ number_format($p['score'], 1) }}%</td>
                                        <td class="text-center text-teal font-weight-bold">+{{ number_format($p['contrib'], 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $p['status'] === 'Tercapai' ? 'badge-success' : ($p['status'] === 'Waspada' ? 'badge-warning' : 'badge-danger') }}">{{ $p['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. FORM EDIT PEMBOBOTAN 5 PILAR (DINAMIS) -->
                @can('manage ratios')
                    <div class="card border border-info shadow-sm">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                            <strong class="text-dark small"><i class="fas fa-sliders-h text-info mr-1"></i> Pengaturan Bobot 5 Pilar Finansial</strong>
                            <span class="badge {{ round(array_sum(array_map('floatval', $pillarWeights)), 1) == 100.0 ? 'badge-success' : 'badge-danger' }} px-2 py-1">
                                Total: {{ array_sum(array_map('floatval', $pillarWeights)) }}% {{ round(array_sum(array_map('floatval', $pillarWeights)), 1) == 100.0 ? '(Valid 100%)' : '(Wajib 100%)' }}
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <form wire:submit.prevent="savePillarWeights">
                                <div class="form-row">
                                    <div class="form-group col-md col-6">
                                        <label class="small font-weight-bold">Profitabilitas (%):</label>
                                        <input type="number" step="1" wire:model.live="pillarWeights.Profitabilitas" class="form-control form-control-sm text-center">
                                    </div>
                                    <div class="form-group col-md col-6">
                                        <label class="small font-weight-bold">Likuiditas (%):</label>
                                        <input type="number" step="1" wire:model.live="pillarWeights.Likuiditas" class="form-control form-control-sm text-center">
                                    </div>
                                    <div class="form-group col-md col-6">
                                        <label class="small font-weight-bold">Aktivitas (%):</label>
                                        <input type="number" step="1" wire:model.live="pillarWeights.Aktivitas" class="form-control form-control-sm text-center">
                                    </div>
                                    <div class="form-group col-md col-6">
                                        <label class="small font-weight-bold">Produktivitas (%):</label>
                                        <input type="number" step="1" wire:model.live="pillarWeights.Produktivitas" class="form-control form-control-sm text-center">
                                    </div>
                                    <div class="form-group col-md col-6">
                                        <label class="small font-weight-bold">Solvabilitas (%):</label>
                                        <input type="number" step="1" wire:model.live="pillarWeights.Solvabilitas" class="form-control form-control-sm text-center">
                                    </div>
                                </div>
                                <div class="text-right">
                                    <button type="submit" class="btn btn-sm btn-teal font-weight-bold">
                                        <i class="fas fa-save mr-1"></i> Simpan Pembobotan Pilar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif

        </div>
    </div>
</div>