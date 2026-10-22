<div>
    <!-- Content Header -->
    <section class="content-header py-2">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 20px;">
                        <i class="fas fa-flask text-warning mr-2"></i> Uji Indikator Departemen (Level 3)
                    </h1>
                    <small class="text-muted">
                        Simulasi What-If: Uji perubahan nilai capaian sasaran mutu (KPI) dan pantau langsung rambatannya ke 130 Pos Anggaran Odoo & Akun Finansial.
                    </small>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="d-inline-flex align-items-center flex-wrap" style="gap: 8px;">
                        <!-- Filter Periode -->
                        <div class="input-group input-group-sm" style="width: 170px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0"><i class="fas fa-calendar-alt text-warning"></i></span>
                            </div>
                            <select wire:model.live="selectedPeriod" class="form-control font-weight-bold border-left-0">
                                @foreach($periods as $p)
                                    <option value="{{ $p }}">Periode {{ $p }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Mode Simulasi Toggle (Target vs Realisasi) -->
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" wire:click="$set('simulationMode', 'actual')" class="btn {{ $simulationMode === 'actual' ? 'btn-warning font-weight-bold text-dark' : 'btn-outline-secondary bg-white' }}" title="Uji Simulasi Realisasi (Actual)">
                                <i class="fas fa-chart-line mr-1"></i> Uji Realisasi
                            </button>
                            <button type="button" wire:click="$set('simulationMode', 'target')" class="btn {{ $simulationMode === 'target' ? 'btn-info font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" title="Uji Kenaikan Target KPI">
                                <i class="fas fa-bullseye mr-1"></i> Uji Target KPI
                            </button>
                        </div>

                        <!-- Tombol Reset Simulasi -->
                        <button type="button" wire:click="resetSimulation" class="btn btn-sm btn-outline-secondary" title="Kembalikan semua nilai ke data baseline asli">
                            <i class="fas fa-undo mr-1"></i> Reset Baseline
                        </button>

                        <!-- Tombol Terapkan ke Sasaran Departemen (/objectives) -->
                        @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                            <button type="button" wire:click="$set('showApplyModal', true)" class="btn btn-sm btn-success font-weight-bold shadow-sm" title="Terapkan hasil simulasi ke data sasaran departemen resmi">
                                <i class="fas fa-save mr-1"></i> Terapkan ke Sasaran Departemen
                            </button>
                        @endif

                        <a href="{{ route('department-objectives') }}" class="btn btn-sm btn-outline-warning text-dark font-weight-bold">
                            <i class="fas fa-external-link-alt mr-1"></i> Buka /objectives
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                    <i class="fas fa-check-circle mr-2"></i> {{ session('message') }}
                    <button type="button" class="close text-white" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <!-- BARIS SKENARIO CEPAT & STATISTIK HASIL UJI -->
            <div class="row mb-3">
                <!-- Card Skenario Cepat -->
                <div class="col-lg-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <strong class="text-dark small"><i class="fas fa-bolt text-warning mr-1"></i> Terapkan Skenario Uji Cepat:</strong>
                            <span class="badge badge-light border text-muted">Stress-Test Otomatis</span>
                        </div>
                        <div class="card-body p-2 d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <button type="button" wire:click="applyStressTest('all_target')" class="btn btn-sm btn-outline-success font-weight-bold flex-fill py-2">
                                <i class="fas fa-bullseye mr-1"></i> Skenario 100% Target
                                <div class="text-muted small" style="font-size: 10px; font-weight: normal;">Semua KPI capai target</div>
                            </button>
                            <button type="button" wire:click="applyStressTest('optimis_10')" class="btn btn-sm btn-outline-info font-weight-bold flex-fill py-2">
                                <i class="fas fa-arrow-trend-up mr-1"></i> Skenario Optimis (+10%)
                                <div class="text-muted small" style="font-size: 10px; font-weight: normal;">Peningkatan performa 10%</div>
                            </button>
                            <button type="button" wire:click="applyStressTest('stress_15')" class="btn btn-sm btn-outline-danger font-weight-bold flex-fill py-2">
                                <i class="fas fa-fire-extinguisher mr-1"></i> Stress Test Krisis (-15%)
                                <div class="text-muted small" style="font-size: 10px; font-weight: normal;">Penurunan performa 15%</div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card Ringkasan Dampak Simulasi -->
                <div class="col-lg-6 mb-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <strong class="text-dark small"><i class="fas fa-heartbeat text-danger mr-1"></i> Proyeksi Kesehatan Indikator (Hasil Uji):</strong>
                            <span class="badge badge-warning text-dark font-weight-bold">Live What-If</span>
                        </div>
                        <div class="card-body p-2">
                            <div class="row text-center">
                                <div class="col-4 border-right">
                                    <div class="text-muted small">Tercapai (Aman)</div>
                                    <div class="h4 font-weight-bold text-success mb-0">{{ $totalSimTercapai }}</div>
                                    <small class="text-muted">dari {{ $totalKpi }} KPI</small>
                                </div>
                                <div class="col-4 border-right">
                                    <div class="text-muted small">Waspada</div>
                                    <div class="h4 font-weight-bold text-warning mb-0">{{ $totalSimWaspada }}</div>
                                    <small class="text-muted">kinerja 80-99%</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted small">Di Bawah Target</div>
                                    <div class="h4 font-weight-bold text-danger mb-0">{{ $totalSimDiBawah }}</div>
                                    <small class="text-muted">butuh Action Plan</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- WIDGET GRAFIK & SIMULATOR ELASTISITAS OPERASIONAL (OEE vs COGS vs NPM) -->
            <div class="card border-0 shadow-sm mb-3" style="background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
                    <div>
                        <strong class="text-dark">
                            <i class="fas fa-chart-line text-primary mr-1"></i> Visualisasi Grafik Elastisitas Operasional: Pengaruh OEE Mesin & Scrap Rate ke COGS dan NPM
                        </strong>
                        <div class="text-muted small">Simulasi transmisi mikro-operasional lantai pabrik cetak botol ke laporan laba rugi.</div>
                    </div>
                    <span class="badge badge-primary px-2 py-1 font-weight-bold">
                        <i class="fas fa-microchip mr-1"></i> Model Elastisitas Manufaktur
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <!-- TUAS SLIDER ELASTISITAS -->
                        <div class="col-lg-5 border-right pr-lg-4 mb-3 mb-lg-0">
                            <!-- Slider OEE Mesin -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small font-weight-bold text-dark mb-0">
                                        <i class="fas fa-cogs text-teal mr-1"></i> Pergeseran OEE Mesin Cetak (Blow/Inj):
                                    </label>
                                    <span class="badge badge-teal font-weight-bold">{{ $oeeShift >= 0 ? '+' : '' }}{{ $oeeShift }}%</span>
                                </div>
                                <input type="range" class="form-range w-100" min="-10" max="15" step="1" wire:model.live="oeeShift">
                                <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                                    <span>Drop (-10%)</span>
                                    <span>Baseline (0%)</span>
                                    <span>Lonjakan (+15%)</span>
                                </div>
                            </div>

                            <!-- Slider Scrap Rate Bahan Resin -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small font-weight-bold text-dark mb-0">
                                        <i class="fas fa-recycle text-warning mr-1"></i> Pergeseran Scrap Rate Biji Plastik:
                                    </label>
                                    <span class="badge badge-warning text-dark font-weight-bold">{{ $scrapShift >= 0 ? '+' : '' }}{{ $scrapShift }}%</span>
                                </div>
                                <input type="range" class="form-range w-100" min="-5" max="5" step="0.5" wire:model.live="scrapShift">
                                <div class="d-flex justify-content-between text-muted" style="font-size: 10px;">
                                    <span>Hemat / Efisien (-5%)</span>
                                    <span>Baseline (0%)</span>
                                    <span>Boros / Cacat (+5%)</span>
                                </div>
                            </div>

                            <div class="p-2 bg-light rounded border text-muted small mt-2" style="font-size: 11px;">
                                <i class="fas fa-lightbulb text-warning mr-1"></i> <strong>Rasio Elastisitas:</strong> Tiap <strong>+5% OEE</strong> menekan beban overhead tetap pabrik sebesar <strong>~2.25%</strong>, dan tiap <strong>-1% Scrap</strong> menghemat <strong>0.75% HPP</strong> resin polimer murni.
                            </div>
                        </div>

                        <!-- GRAFIK BATANG & HASIL PROYEKSI -->
                        <div class="col-lg-7 pl-lg-4">
                            <div class="row">
                                <!-- Box HPP / COGS -->
                                <div class="col-md-6 mb-2">
                                    <div class="p-2 bg-white rounded border shadow-xs h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small font-weight-bold">HPP / COGS Pabrik:</span>
                                            <span class="badge {{ $elasticityData['cogsDeltaNominal'] <= 0 ? 'badge-success' : 'badge-danger' }} font-weight-bold">
                                                {{ $elasticityData['cogsDeltaNominal'] <= 0 ? 'Efisiensi' : 'Pembengkakan' }}
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-baseline mb-1">
                                            <h4 class="font-weight-bold text-dark mb-0">IDR {{ number_format($elasticityData['simCogs'], 2) }} M</h4>
                                            <small class="text-muted ml-2">(Baseline: {{ number_format($elasticityData['baseCogs'], 2) }} M)</small>
                                        </div>
                                        <div class="small {{ $elasticityData['cogsDeltaNominal'] <= 0 ? 'text-success' : 'text-danger' }} font-weight-bold mb-2">
                                            <i class="fas {{ $elasticityData['cogsDeltaNominal'] <= 0 ? 'fa-arrow-down' : 'fa-arrow-up' }} mr-1"></i>
                                            {{ $elasticityData['cogsDeltaNominal'] <= 0 ? '-' : '+' }}IDR {{ number_format(abs($elasticityData['cogsDeltaNominal']), 2) }} M ({{ $elasticityData['cogsReductionPct'] }}%)
                                        </div>
                                        <!-- Visual Progress Bar Efisiensi COGS -->
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar {{ $elasticityData['cogsDeltaNominal'] <= 0 ? 'bg-success' : 'bg-danger' }}" role="progressbar" style="width: {{ min(100, max(10, 100 - ($elasticityData['cogsReductionPct'] * 3))) }}%;"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Box Net Profit Margin (NPM) -->
                                <div class="col-md-6 mb-2">
                                    <div class="p-2 bg-white rounded border shadow-xs h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small font-weight-bold">Net Profit Margin (NPM):</span>
                                            <span class="badge {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'badge-success' : 'badge-danger' }} font-weight-bold">
                                                {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'Ekspansi Laba' : 'Laba Tertekan' }}
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-baseline mb-1">
                                            <h4 class="font-weight-bold {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'text-success' : 'text-danger' }} mb-0">{{ number_format($elasticityData['simNpmPct'], 2) }}%</h4>
                                            <small class="text-muted ml-2">(Baseline: {{ number_format($elasticityData['baseNpmPct'], 2) }}%)</small>
                                        </div>
                                        <div class="small {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold mb-2">
                                            <i class="fas {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} mr-1"></i>
                                            {{ $elasticityData['npmDeltaNominal'] >= 0 ? '+' : '-' }}IDR {{ number_format(abs($elasticityData['npmDeltaNominal']), 2) }} M tambahan laba bersih
                                        </div>
                                        <!-- Visual Progress Bar Kenaikan NPM -->
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar {{ $elasticityData['npmDeltaNominal'] >= 0 ? 'bg-teal' : 'bg-warning' }}" role="progressbar" style="width: {{ min(100, max(15, ($elasticityData['simNpmPct'] / 85) * 100)) }}%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mini Perbandingan GPM -->
                            <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light border mt-1 small">
                                <span class="text-dark font-weight-bold">Gross Profit Margin (GPM) Baru:</span>
                                <div>
                                    <span class="font-weight-bold text-teal">{{ number_format($elasticityData['simGpmPct'], 2) }}%</span>
                                    <span class="text-muted"> (Sebelumnya: {{ number_format($elasticityData['baseGpmPct'], 2) }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                    <div>
                        <strong class="text-dark">
                            <i class="fas fa-sitemap text-teal mr-2"></i> Proyeksi Dampak ke 130 Pos Anggaran Odoo & Akun Finansial Pabrik Botol Plastik
                        </strong>
                        <span class="badge badge-teal font-weight-normal px-2 py-0.5 ml-1">130 Pos &bull; Tingkat 3 &rarr; Tingkat 2 CoA</span>
                    </div>
                    <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                        <!-- Search Box Pos Card -->
                        <div class="input-group input-group-sm" style="width: 170px;">
                            <input type="text" wire:model.live.debounce.300ms="searchPosCard" class="form-control" placeholder="Cari pos/kode/dept...">
                            @if($searchPosCard)
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" wire:click="$set('searchPosCard', '')">&times;</button>
                                </div>
                            @endif
                        </div>

                        <!-- Button Group Kategori Pos -->
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" wire:click="$set('posTypeFilter', 'ALL')" class="btn {{ $posTypeFilter === 'ALL' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">Semua</button>
                            <button type="button" wire:click="$set('posTypeFilter', 'LINKED')" class="btn {{ $posTypeFilter === 'LINKED' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">
                                <i class="fas fa-link mr-1"></i>Terhubung KPI
                            </button>
                            <button type="button" wire:click="$set('posTypeFilter', 'COGS')" class="btn {{ $posTypeFilter === 'COGS' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">COGS / HPP</button>
                            <button type="button" wire:click="$set('posTypeFilter', 'Expense')" class="btn {{ $posTypeFilter === 'Expense' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">Expense</button>
                            <button type="button" wire:click="$set('posTypeFilter', 'Asset')" class="btn {{ $posTypeFilter === 'Asset' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">Asset</button>
                            <button type="button" wire:click="$set('posTypeFilter', 'Liability')" class="btn {{ $posTypeFilter === 'Liability' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">Liability</button>
                            <button type="button" wire:click="$set('posTypeFilter', 'Revenue')" class="btn {{ $posTypeFilter === 'Revenue' ? 'btn-teal font-weight-bold text-white' : 'btn-outline-secondary bg-white' }}" style="font-size: 11px;">Revenue</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2" style="max-height: 380px; overflow-y: auto;">
                    <div class="row">
                        @forelse($pos130Cards as $cp)
                            @php
                                $hStatus = $cp['overall_health'];
                                $borderCls = 'border-secondary';
                                $badgeCls = 'badge-secondary';
                                if ($hStatus === 'Kritis (Gap Tinggi)') {
                                    $borderCls = 'border-danger';
                                    $badgeCls = 'badge-danger';
                                } elseif ($hStatus === 'Waspada') {
                                    $borderCls = 'border-warning';
                                    $badgeCls = 'badge-warning text-dark';
                                } elseif ($hStatus === 'Sehat & Prima') {
                                    $borderCls = 'border-success';
                                    $badgeCls = 'badge-success';
                                }
                            @endphp
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-2">
                                <div class="p-2 rounded border bg-white h-100 {{ $borderCls }} shadow-xs" style="border-width: 1.5px !important; font-size: 11.5px;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center" style="gap: 4px;">
                                            <span class="badge badge-teal font-weight-bold" style="font-size: 11px;">{{ $cp['code'] }}</span>
                                            <span class="badge {{ $cp['type'] === 'COGS' ? 'badge-warning text-dark' : ($cp['type'] === 'Expense' ? 'badge-light border' : 'badge-info') }} py-0 px-1" style="font-size: 9px;">
                                                {{ $cp['type'] }}
                                            </span>
                                        </div>
                                        <span class="badge {{ $badgeCls }} font-weight-bold" style="font-size: 9.5px;">
                                            {{ $hStatus }}
                                        </span>
                                    </div>
                                    <div class="font-weight-bold text-dark text-truncate" title="{{ $cp['name'] }}" style="line-height: 1.25;">
                                        {{ $cp['name'] }}
                                    </div>
                                    <div class="text-muted small d-flex justify-content-between align-items-center mt-1" style="font-size: 10.5px;">
                                        <span>Dept: <strong>{{ $cp['dept_name'] }}</strong></span>
                                        <span class="badge badge-light border">{{ $cp['dept_code'] }}</span>
                                    </div>
                                    <div class="mt-2 pt-1 border-top d-flex justify-content-between text-muted" style="font-size: 10.5px;">
                                        <span>Terhubung: <strong>{{ count($cp['kpis']) }}</strong> KPI</span>
                                        @if(($cp['dibawah_count'] ?? 0) > 0)
                                            <span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $cp['dibawah_count'] }} GAP</span>
                                        @elseif(($cp['waspada_count'] ?? 0) > 0)
                                            <span class="text-warning font-weight-bold">{{ $cp['waspada_count'] }} Waspada</span>
                                        @elseif(count($cp['kpis']) > 0)
                                            <span class="text-success font-weight-bold"><i class="fas fa-check mr-1"></i>Aman</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-4 text-muted small">
                                <i class="fas fa-filter mr-1 text-secondary"></i> Tidak ada pos anggaran yang cocok dengan filter atau kata kunci "{{ $searchPosCard }}".
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer bg-light py-1.5 px-3 text-muted small d-flex justify-content-between align-items-center">
                    <div>
                        Menampilkan <strong>{{ count($pos130Cards) }}</strong> dari <strong>{{ $totalPos130 }}</strong> Pos Anggaran Odoo & Akun Finansial.
                    </div>
                    <div>
                        <span class="text-muted font-italic">*Klik tombol filter atau ketik di search box untuk mencari pos spesifik.</span>
                    </div>
                </div>
            </div>

            <!-- TABEL SIMULATOR WHAT-IF PER INDIKATOR (KPI) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                    <div>
                        <strong class="text-dark"><i class="fas fa-sliders-h text-warning mr-2"></i> Meja Uji Nilai Realisasi KPI (What-If Sliders/Input):</strong>
                        <div class="text-muted small">Gunakan tombol &plusmn;5% / &plusmn;10% atau ketik angka langsung untuk melihat perubahan capaian & catatan dampak secara otomatis.</div>
                    </div>
                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <!-- Search Box KPI -->
                        <div class="input-group input-group-sm" style="width: 180px;">
                            <input type="text" wire:model.live.debounce.300ms="searchKpi" class="form-control" placeholder="Cari nama/kode KPI...">
                            @if($searchKpi)
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" wire:click="$set('searchKpi', '')">&times;</button>
                                </div>
                            @endif
                        </div>

                        <!-- Filter Dept Odoo -->
                        <select wire:model.live="selectedDept" class="form-control form-control-sm" style="width: 180px;">
                            <option value="">-- Semua Departemen --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->code }}">{{ $d->name }} ({{ $d->code }})</option>
                            @endforeach
                        </select>

                        <!-- Sort By -->
                        <select wire:model.live="sortBy" class="form-control form-control-sm" style="width: 160px;">
                            <option value="default">Urut Default</option>
                            <option value="gap_desc">GAP Terbesar &uarr;</option>
                            <option value="gap_asc">Capaian Tertinggi</option>
                            <option value="kpi_code">Kode KPI</option>
                        </select>

                        <!-- Toggle Gap Only -->
                        <div class="custom-control custom-switch" style="font-size: 12px;">
                            <input type="checkbox" class="custom-control-input" id="filterGap" wire:model.live="filterGapOnly">
                            <label class="custom-control-label font-weight-bold text-danger" for="filterGap" style="cursor: pointer;">
                                Hanya GAP
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-bordered table-hover table-sm m-0 align-middle" style="font-size: 12.5px;">
                            <thead class="bg-light position-sticky" style="top: 0; z-index: 2;">
                                <tr>
                                    <th style="width: 8%;">Kode KPI</th>
                                    <th style="width: 20%;">Sasaran Mutu (Nama KPI)</th>
                                    <th style="width: 11%;">Dept PIC</th>
                                    <th class="text-center" style="width: 7%;">Target</th>
                                    <th class="text-center" style="width: 7%;">Aktual Asli</th>
                                    <th class="text-center {{ $simulationMode === 'target' ? 'bg-info text-white' : 'bg-warning text-dark' }}" style="width: 19%;">
                                        @if($simulationMode === 'target')
                                            <i class="fas fa-bullseye mr-1"></i> Target Baru Uji (Simulasi)
                                        @else
                                            <i class="fas fa-edit mr-1"></i> Realisasi Uji (Simulasi)
                                        @endif
                                    </th>
                                    <th class="text-center" style="width: 11%;">Hasil Uji Capaian</th>
                                    <th style="width: 18%;">Dampak Finansial & Operasional</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kpiSimulations as $kpi)
                                    @php
                                        $isGap = ($kpi['sim_status'] !== 'Tercapai');
                                    @endphp
                                    <tr wire:key="kpi-row-{{ $kpi['id'] }}" class="{{ $isGap ? 'table-warning' : '' }}">
                                        <td class="font-weight-bold">
                                            <code>{{ $kpi['kpi_code'] }}</code>
                                        </td>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $kpi['kpi_name'] }}</div>
                                            <small class="text-muted">Arah: <strong>{{ $kpi['polarity'] }}</strong> ({{ $kpi['unit'] }})</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-light border text-dark font-weight-bold px-2 py-1">
                                                {{ $kpi['dept_name'] }} ({{ $kpi['dept_code'] }})
                                            </span>
                                        </td>
                                        <td class="text-center font-weight-bold {{ $simulationMode === 'target' ? 'text-info font-weight-bold' : 'text-muted' }}">
                                            @if($simulationMode === 'target' && $kpi['diff_target'] != 0)
                                                <del class="text-muted small d-block">{{ number_format($kpi['target'], 2, ',', '.') }}</del>
                                                <span class="text-info font-weight-bold">{{ number_format($kpi['sim_target'], 2, ',', '.') }}</span>
                                            @else
                                                {{ number_format($kpi['target'], 2, ',', '.') }}
                                            @endif
                                            <div style="font-size: 10px;">{{ $kpi['unit'] }}</div>
                                        </td>
                                        <td class="text-center text-muted">
                                            @if($simulationMode === 'actual' && $kpi['diff_val'] != 0)
                                                <del class="text-muted small d-block">{{ number_format($kpi['actual'], 2, ',', '.') }}</del>
                                                <span class="text-warning font-weight-bold">{{ number_format($kpi['sim_actual'], 2, ',', '.') }}</span>
                                            @else
                                                {{ number_format($kpi['actual'], 2, ',', '.') }}
                                            @endif
                                            <div style="font-size: 10px;">{{ $kpi['unit'] }} (Baseline)</div>
                                        </td>
                                        <td class="text-center bg-light">
                                            <!-- Step Adjustment Buttons (+5%, -5%, +10%, -10%) -->
                                            <div class="btn-group btn-group-xs mb-1 w-100" role="group">
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', -10)" class="btn btn-outline-secondary py-0" title="Turun 10%" style="font-size: 10px;">-10%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', -5)" class="btn btn-outline-secondary py-0" title="Turun 5%" style="font-size: 10px;">-5%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', 5)" class="btn btn-outline-secondary py-0" title="Naik 5%" style="font-size: 10px;">+5%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', 10)" class="btn btn-outline-secondary py-0" title="Naik 10%" style="font-size: 10px;">+10%</button>
                                            </div>

                                            @if($simulationMode === 'target')
                                                <!-- INPUT SIMULASI TARGET -->
                                                <div class="input-group input-group-sm">
                                                    <input type="number" 
                                                           step="any" 
                                                           wire:key="input-target-{{ $kpi['id'] }}"
                                                           value="{{ $kpi['sim_target'] }}"
                                                           wire:change="updateSimulatedTarget('{{ $kpi['id'] }}', $event.target.value)"
                                                           wire:keydown.enter="updateSimulatedTarget('{{ $kpi['id'] }}', $event.target.value)"
                                                           class="form-control form-control-sm text-center font-weight-bold text-dark border-info"
                                                           style="background: #f0fdf4;">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text bg-white small px-2">{{ $kpi['unit'] }}</span>
                                                        <button type="button" 
                                                                onclick="let inp = this.closest('.input-group').querySelector('input'); @this.updateSimulatedTarget('{{ $kpi['id'] }}', inp.value);"
                                                                class="btn btn-info btn-xs font-weight-bold" 
                                                                title="Terapkan Target Uji">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @else
                                                <!-- INPUT SIMULASI REALISASI (ACTUAL) -->
                                                <div class="input-group input-group-sm">
                                                    <input type="number" 
                                                           step="any" 
                                                           wire:key="input-kpi-{{ $kpi['id'] }}"
                                                           value="{{ $kpi['sim_actual'] }}"
                                                           wire:change="updateSimulatedActual('{{ $kpi['id'] }}', $event.target.value)"
                                                           wire:keydown.enter="updateSimulatedActual('{{ $kpi['id'] }}', $event.target.value)"
                                                           class="form-control form-control-sm text-center font-weight-bold text-dark border-warning"
                                                           style="background: #fffdf5;">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text bg-white small px-2">{{ $kpi['unit'] }}</span>
                                                        <button type="button" 
                                                                onclick="let inp = this.closest('.input-group').querySelector('input'); @this.updateSimulatedActual('{{ $kpi['id'] }}', inp.value);"
                                                                class="btn btn-warning btn-xs font-weight-bold" 
                                                                title="Terapkan Realisasi Uji">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($kpi['sim_status'] === 'Tercapai')
                                                <span class="badge badge-success px-2 py-1 font-weight-bold d-block mb-1">
                                                    <i class="fas fa-check-circle mr-1"></i> 100.0% Tercapai
                                                </span>
                                            @elseif($kpi['sim_status'] === 'Waspada')
                                                <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold d-block mb-1">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> {{ number_format($kpi['sim_ach'], 1) }}% Waspada
                                                </span>
                                            @else
                                                <span class="badge badge-danger px-2 py-1 font-weight-bold d-block mb-1">
                                                    <i class="fas fa-times-circle mr-1"></i> {{ number_format($kpi['sim_ach'], 1) }}% Gap
                                                </span>
                                            @endif

                                            <!-- Indikator Perubahan Nilai Uji vs Baseline Asli -->
                                            @if($kpi['diff_val'] != 0)
                                                <div class="mt-1" style="line-height: 1.2;">
                                                    @php
                                                        $isBetter = ($kpi['polarity'] === 'Turun') ? ($kpi['diff_val'] < 0) : ($kpi['diff_val'] > 0);
                                                    @endphp
                                                    <span class="badge {{ $isBetter ? 'badge-success' : 'badge-danger' }} px-1" style="font-size: 10px;">
                                                        &Delta; {{ ($kpi['diff_val'] > 0 ? '+' : '') . number_format($kpi['diff_val'], 2, ',', '.') }} {{ $kpi['unit'] }}
                                                    </span>
                                                    <div class="{{ $isBetter ? 'text-success' : 'text-danger' }} font-weight-bold" style="font-size: 10.5px;">
                                                        {{ $isBetter ? 'Lebih Baik' : 'Memburuk' }}
                                                    </div>
                                                </div>
                                            @else
                                                <small class="text-muted d-block mt-1">= Sama dg Asli</small>
                                            @endif
                                        </td>
                                        <td>
                                            <!-- Akun Odoo COA Terpilih (Piutang, Kas, HPP, dll) -->
                                            @if(count($kpi['odoo_codes']) > 0)
                                                <div class="mb-1 d-flex flex-wrap" style="gap: 4px;">
                                                    @foreach($kpi['odoo_codes'] as $oCode)
                                                        @php
                                                            $acc = $allAccountsMap[$oCode] ?? null;
                                                        @endphp
                                                        <span class="badge badge-warning text-dark font-weight-normal px-2 py-0.5 shadow-xs" style="font-size: 10px;" title="{{ $acc ? $acc->name : $oCode }}">
                                                            <i class="fas fa-coins mr-1"></i><strong>{{ $oCode }}</strong>
                                                            @if($acc)
                                                                - {{ \Illuminate\Support\Str::limit($acc->name, 18) }}
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <!-- Pos Anggaran Odoo (130 Pos / PA01-PA12) -->
                                            @if(count($kpi['budget_codes']) > 0)
                                                <div class="d-flex flex-wrap mb-1" style="gap: 4px;">
                                                    @foreach($kpi['budget_codes'] as $pc)
                                                        @php
                                                            $acc = $allAccountsMap[$pc] ?? null;
                                                            $isCore = isset($posMap[$pc]);
                                                        @endphp
                                                        <span class="badge {{ $isGap ? 'badge-danger' : ($isCore ? 'badge-teal' : 'badge-info') }} px-1.5 py-0.5" style="font-size: 10px;" title="{{ $acc ? $acc->name : ($posMap[$pc]['name'] ?? $pc) }}">
                                                            <i class="fas fa-link mr-1"></i>{{ $pc }}
                                                            @if($acc)
                                                                - {{ \Illuminate\Support\Str::limit($acc->name, 16) }}
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <!-- Catatan Tambahan Dampak (Manual vs Otomatis) -->
                                            @if(!empty($kpi['impact_note']))
                                                <div class="p-1 px-2 rounded mt-1 shadow-xs" style="font-size: 10.5px; line-height: 1.25; background-color: #f0f7ff; border: 1px solid #b8daff; border-left: 3px solid #007bff !important;">
                                                    <span class="badge badge-primary text-white font-weight-bold py-0 px-1 mr-1" style="font-size: 9px;">
                                                        <i class="fas fa-pen mr-1"></i>Manual
                                                    </span>
                                                    <span class="text-dark font-italic">{{ $kpi['impact_note'] }}</span>
                                                </div>
                                            @elseif(!empty($kpi['auto_note']))
                                                <div class="p-1 px-2 rounded mt-1 shadow-xs" style="font-size: 10.5px; line-height: 1.25; background-color: #fffdf5; border: 1px solid #ffeeba; border-left: 3px solid #ffc107 !important;">
                                                    <span class="badge badge-warning text-dark font-weight-bold py-0 px-1 mr-1" style="font-size: 9px;">
                                                        <i class="fas fa-robot mr-1"></i>Otomatis
                                                    </span>
                                                    <span class="text-dark font-italic">{{ $kpi['auto_note'] }}</span>
                                                </div>
                                            @elseif(empty($kpi['budget_codes']) && empty($kpi['odoo_codes']))
                                                <span class="text-muted small font-italic">- Belum ditautkan ke akun -</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-bullseye fa-3x mb-2 d-block text-secondary"></i>
                                            Tidak ada data indikator sasaran mutu yang ditemukan untuk disimulasikan.<br>
                                            <a href="{{ route('department-objectives') }}" class="btn btn-sm btn-warning text-dark font-weight-bold mt-2">
                                                <i class="fas fa-plus mr-1"></i> Buat Sasaran Mutu Terlebih Dahulu
                                            </a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <i class="fas fa-info-circle text-warning mr-1"></i> Meja Uji Indikator berjalan di memory sandbox (tidak mengubah data riil database kecuali Anda menekan tombol "Terapkan ke Sasaran Mutu").
                    </div>
                    <div>
                        Menampilkan <strong>{{ count($kpiSimulations) }}</strong> dari <strong>{{ $totalKpi }}</strong> Sasaran Mutu &bull; Periode: <strong>{{ $selectedPeriod }}</strong>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- MODAL KONFIRMASI TERAPKAN KE SASARAN DEPARTEMEN (/objectives) -->
    @if($showApplyModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white py-2">
                        <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                            <i class="fas fa-check-double mr-1"></i> Konfirmasi Terapkan Hasil Uji Simulasi ke /objectives
                        </h5>
                        <button type="button" class="close text-white" wire:click="$set('showApplyModal', false)">&times;</button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="alert alert-info py-2 mb-3" style="font-size: 12.5px;">
                            <i class="fas fa-info-circle mr-1"></i> <strong>Pilih Parameter yang Ingin Diterapkan:</strong><br>
                            Anda dapat memilih untuk memperbarui <strong>Target Baru</strong> hasil simulasi, <strong>Realisasi (Actual)</strong>, atau keduanya ke menu <strong>Sasaran Departemen & KPI (/objectives)</strong> untuk periode <strong>{{ $selectedPeriod }}</strong>.
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark d-block mb-1">Cakupan Penyimpanan ke Database Resmi:</label>
                            <div class="custom-control custom-radio mb-1">
                                <input type="radio" id="modeBoth" wire:model.live="applyMode" value="both" class="custom-control-input">
                                <label class="custom-control-label small font-weight-bold" for="modeBoth">
                                    Simpan Target & Realisasi Baru Sekaligus (Rekomendasi)
                                </label>
                            </div>
                            <div class="custom-control custom-radio mb-1">
                                <input type="radio" id="modeTarget" wire:model.live="applyMode" value="target_only" class="custom-control-input">
                                <label class="custom-control-label small text-info font-weight-bold" for="modeTarget">
                                    Hanya Simpan Nilai Target Baru (Realisasi Tetap)
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="modeActual" wire:model.live="applyMode" value="actual_only" class="custom-control-input">
                                <label class="custom-control-label small text-warning font-weight-bold" for="modeActual">
                                    Hanya Simpan Nilai Realisasi Baru (Target Tetap)
                                </label>
                            </div>
                        </div>

                        <p class="small text-muted mb-0">
                            Apakah Anda yakin ingin menerapkan parameter simulasi ini menjadi data resmi di menu <strong>/objectives</strong>?
                        </p>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('showApplyModal', false)">Batal</button>
                        <button type="button" class="btn btn-sm btn-success font-weight-bold" wire:click="applySimulationToLive">
                            <i class="fas fa-save mr-1"></i> Ya, Terapkan ke Sasaran Departemen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
