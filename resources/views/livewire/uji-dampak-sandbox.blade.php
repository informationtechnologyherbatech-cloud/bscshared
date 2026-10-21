<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-flask text-teal mr-2"></i> Uji Dampak / What-If Sandbox
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="form-inline float-right">
                        <label for="periodSel" class="mr-2 font-weight-bold">Periode Baseline:</label>
                        <select wire:model.live="selectedPeriod" id="periodSel" class="form-control form-control-sm border-teal mr-2">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        <button wire:click="resetToBaseline" class="btn btn-sm btn-outline-secondary" title="Kembalikan semua tuas ke baseline data riil">
                            <i class="fas fa-undo mr-1"></i> Reset Tuas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <!-- BANNER INFO SANDBOX & PRESET SKENARIO -->
            <div class="card bg-gradient-light border-teal shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-lg-6 mb-2 mb-lg-0">
                            <h5 class="font-weight-bold mb-1 text-teal">
                                <i class="fas fa-shield-alt mr-1"></i> Ruang Simulasi Hipotetis Finansial (Safe Sandbox)
                            </h5>
                            <p class="text-muted small mb-0">
                                Simulasi ini berjalan di memori sesi & tidak mengubah data riil Odoo ERP. Geser tuas variabel di bawah untuk melihat transmisi dampaknya ke Skor Apex, Laba Rugi, dan 15 Rasio Keuangan.
                            </p>
                        </div>
                        <div class="col-lg-6 text-lg-right">
                            <span class="small font-weight-bold mr-2 text-secondary">Preset Cepat:</span>
                            <div class="btn-group btn-group-sm shadow-xs" role="group">
                                <button type="button" wire:click="applyPreset('optimis')" class="btn {{ $activePreset === 'optimis' ? 'btn-success font-weight-bold' : 'btn-outline-success' }}">
                                    <i class="fas fa-rocket mr-1"></i> Optimis (+15% Sales)
                                </button>
                                <button type="button" wire:click="applyPreset('moderat')" class="btn {{ $activePreset === 'moderat' ? 'btn-primary font-weight-bold' : 'btn-outline-primary' }}">
                                    <i class="fas fa-chart-line mr-1"></i> Moderat (+5% Sales)
                                </button>
                                <button type="button" wire:click="applyPreset('krisis')" class="btn {{ $activePreset === 'krisis' ? 'btn-danger font-weight-bold' : 'btn-outline-danger' }}">
                                    <i class="fas fa-biohazard mr-1"></i> Krisis / Tekanan
                                </button>
                                <button type="button" wire:click="applyPreset('baseline')" class="btn {{ $activePreset === 'baseline' ? 'btn-dark font-weight-bold' : 'btn-outline-dark' }}">
                                    <i class="fas fa-dot-circle mr-1"></i> Data Riil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- APEX HIGHLIGHTS COMPARISON (KARTU DAMPAK TINGKAT 1) -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="info-box bg-white shadow-sm border">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-bullseye"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Proyeksi Skor Apex Tingkat 1</span>
                            <span class="info-box-number text-dark" style="font-size: 22px;">
                                {{ number_format($simApexScore, 2) }}
                                <small class="ml-2 font-weight-bold {{ $apexDelta >= 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ $apexDelta >= 0 ? '+' : '' }}{{ number_format($apexDelta, 2) }} pts)
                                </small>
                            </span>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar {{ $simApexScore >= 95 ? 'bg-success' : ($simApexScore >= 80 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ min(100, $simApexScore) }}%"></div>
                            </div>
                            <span class="progress-description text-muted small mt-1">
                                Baseline Riil: <strong>{{ number_format($baseApexScore, 2) }}</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-box bg-white shadow-sm border">
                        <span class="info-box-icon bg-teal elevation-1"><i class="fas fa-money-bill-wave"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Proyeksi Omzet Revenue</span>
                            <span class="info-box-number text-dark" style="font-size: 22px;">
                                Rp {{ number_format($simRevenue, 2, ',', '.') }} M
                                <small class="ml-2 font-weight-bold {{ ($simRevenue - $baseRevenue) >= 0 ? 'text-success' : 'text-danger' }}">
                                    ({{ ($simRevenue - $baseRevenue) >= 0 ? '+' : '' }}{{ number_format((($simRevenue - $baseRevenue) / $baseRevenue) * 100, 1) }}%)
                                </small>
                            </span>
                            <span class="progress-description text-muted small mt-1">
                                Baseline: Rp {{ number_format($baseRevenue, 2, ',', '.') }} M &middot; Target: Rp {{ number_format($baseTargetRevenue, 2, ',', '.') }} M
                            </span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="info-box bg-white shadow-sm border">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Dampak Arus Kas Operasi (OCF)</span>
                            <span class="info-box-number text-dark" style="font-size: 22px;">
                                <span class="{{ $simCashDelta >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $simCashDelta >= 0 ? '+' : '' }}Rp {{ number_format($simCashDelta, 2, ',', '.') }} M
                                </span>
                            </span>
                            <span class="progress-description text-muted small mt-1">
                                Akumulasi Laba + Efek Siklus Piutang & Utang
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TUAS SIMULATOR SLIDERS & MINI LABA RUGI -->
            <div class="row">
                <!-- PANEL TUAS VARIABEL -->
                <div class="col-lg-6 mb-4">
                    <div class="card card-outline card-teal shadow-sm h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-sliders-h mr-1 text-teal"></i> 5 Tuas Variabel Strategis Herbal
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- Slider 1: Sales Growth -->
                            <div class="form-group mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold mb-0">
                                        <i class="fas fa-chart-line text-info mr-1"></i> Volume Penjualan / Sales Growth:
                                    </label>
                                    <span class="badge {{ $salesVolumeGrowth >= 0 ? 'badge-success' : 'badge-danger' }} px-2 py-1" style="font-size: 14px;">
                                        {{ $salesVolumeGrowth >= 0 ? '+' : '' }}{{ number_format($salesVolumeGrowth, 1) }} %
                                    </span>
                                </div>
                                <input type="range" wire:model.live="salesVolumeGrowth" min="-30" max="30" step="0.5" class="form-control-range">
                                <small class="text-muted d-flex justify-content-between">
                                    <span>-30% (Kontraksi)</span>
                                    <span>0% (Netral)</span>
                                    <span>+30% (Ekspansi Kuat)</span>
                                </small>
                            </div>

                            <!-- Slider 2: COGS / HPP Bahan Baku -->
                            <div class="form-group mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold mb-0">
                                        <i class="fas fa-seedling text-warning mr-1"></i> Harga Bahan Baku Herbal / HPP:
                                    </label>
                                    <span class="badge {{ $cogsChange <= 0 ? 'badge-success' : 'badge-danger' }} px-2 py-1" style="font-size: 14px;">
                                        {{ $cogsChange >= 0 ? '+' : '' }}{{ number_format($cogsChange, 1) }} %
                                    </span>
                                </div>
                                <input type="range" wire:model.live="cogsChange" min="-20" max="20" step="0.5" class="form-control-range">
                                <small class="text-muted d-flex justify-content-between">
                                    <span>-20% (Efisiensi Simplisia)</span>
                                    <span>0% (Stabil)</span>
                                    <span>+20% (Kenaikan Harga Bahan)</span>
                                </small>
                            </div>

                            <!-- Slider 3: OPEX Change -->
                            <div class="form-group mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold mb-0">
                                        <i class="fas fa-cogs text-secondary mr-1"></i> Beban Operasional / OPEX:
                                    </label>
                                    <span class="badge {{ $opexChange <= 0 ? 'badge-success' : 'badge-danger' }} px-2 py-1" style="font-size: 14px;">
                                        {{ $opexChange >= 0 ? '+' : '' }}{{ number_format($opexChange, 1) }} %
                                    </span>
                                </div>
                                <input type="range" wire:model.live="opexChange" min="-20" max="20" step="0.5" class="form-control-range">
                                <small class="text-muted d-flex justify-content-between">
                                    <span>-20% (Penghematan Pabrik)</span>
                                    <span>0% (Normal)</span>
                                    <span>+20% (Biaya Membengkak)</span>
                                </small>
                            </div>

                            <!-- Slider 4: AR Collection (DSO) -->
                            <div class="form-group mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold mb-0">
                                        <i class="fas fa-receipt text-primary mr-1"></i> Perubahan Siklus Piutang (DSO):
                                    </label>
                                    <span class="badge {{ $arCollectionDaysShift <= 0 ? 'badge-success' : 'badge-warning' }} px-2 py-1" style="font-size: 14px;">
                                        {{ $arCollectionDaysShift >= 0 ? '+' : '' }}{{ $arCollectionDaysShift }} Hari
                                    </span>
                                </div>
                                <input type="range" wire:model.live="arCollectionDaysShift" min="-30" max="30" step="1" class="form-control-range">
                                <small class="text-muted d-flex justify-content-between">
                                    <span>-30 Hari (Penagihan Diperketat)</span>
                                    <span>0 Hari</span>
                                    <span>+30 Hari (Kredit Distributor Longgar)</span>
                                </small>
                            </div>

                            <!-- Slider 5: AP Payment (DPO) -->
                            <div class="form-group mb-0">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="font-weight-bold mb-0">
                                        <i class="fas fa-file-invoice-dollar text-purple mr-1"></i> Perubahan Termin Bayar Vendor (DPO):
                                    </label>
                                    <span class="badge {{ $apPaymentDaysShift >= 0 ? 'badge-success' : 'badge-secondary' }} px-2 py-1" style="font-size: 14px;">
                                        {{ $apPaymentDaysShift >= 0 ? '+' : '' }}{{ $apPaymentDaysShift }} Hari
                                    </span>
                                </div>
                                <input type="range" wire:model.live="apPaymentDaysShift" min="-30" max="30" step="1" class="form-control-range">
                                <small class="text-muted d-flex justify-content-between">
                                    <span>-30 Hari (Bayar Lebih Awal)</span>
                                    <span>0 Hari</span>
                                    <span>+30 Hari (Negosiasi Termin Panjang)</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MINI INCOME STATEMENT (LABA RUGI HIPOTETIS) -->
                <div class="col-lg-6 mb-4">
                    <div class="card card-outline card-teal shadow-sm h-100">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-table mr-1 text-teal"></i> Proyeksi Laba Rugi Hipotetis (Miliar IDR)
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-striped m-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 35%;">Akun / Pos Keuangan</th>
                                        <th class="text-right" style="width: 22%;">Baseline</th>
                                        <th class="text-right" style="width: 23%;">Simulasi</th>
                                        <th class="text-right" style="width: 20%;">Selisih (&Delta;)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="font-weight-bold text-teal">Pendapatan Penjualan (Revenue)</td>
                                        <td class="text-right font-weight-bold">{{ number_format($baseRevenue, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold text-teal">{{ number_format($simRevenue, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold {{ ($simRevenue - $baseRevenue) >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ ($simRevenue - $baseRevenue) >= 0 ? '+' : '' }}{{ number_format($simRevenue - $baseRevenue, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Beban Pokok Penjualan (HPP / COGS)</td>
                                        <td class="text-right">{{ number_format($baseCOGS, 2, ',', '.') }}</td>
                                        <td class="text-right text-dark">{{ number_format($simCOGS, 2, ',', '.') }}</td>
                                        <td class="text-right {{ ($simCOGS - $baseCOGS) <= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ ($simCOGS - $baseCOGS) >= 0 ? '+' : '' }}{{ number_format($simCOGS - $baseCOGS, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr class="table-active">
                                        <td class="font-weight-bold">Laba Kotor (Gross Profit)</td>
                                        <td class="text-right font-weight-bold">{{ number_format($baseGrossProfit, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($simGrossProfit, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold {{ ($simGrossProfit - $baseGrossProfit) >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ ($simGrossProfit - $baseGrossProfit) >= 0 ? '+' : '' }}{{ number_format($simGrossProfit - $baseGrossProfit, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Beban Operasional (OPEX)</td>
                                        <td class="text-right">{{ number_format($baseOPEX, 2, ',', '.') }}</td>
                                        <td class="text-right text-dark">{{ number_format($simOPEX, 2, ',', '.') }}</td>
                                        <td class="text-right {{ ($simOPEX - $baseOPEX) <= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ ($simOPEX - $baseOPEX) >= 0 ? '+' : '' }}{{ number_format($simOPEX - $baseOPEX, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="font-weight-bold">Laba Usaha (EBIT)</td>
                                        <td class="text-right font-weight-bold">{{ number_format($baseGrossProfit - $baseOPEX, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($simEBIT, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold {{ ($simEBIT - ($baseGrossProfit - $baseOPEX)) >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ ($simEBIT - ($baseGrossProfit - $baseOPEX)) >= 0 ? '+' : '' }}{{ number_format($simEBIT - ($baseGrossProfit - $baseOPEX), 2, ',', '.') }}
                                        </td>
                                    </tr>
                                    <tr class="bg-teal text-white">
                                        <td class="font-weight-bold">Laba Bersih Setelah Pajak (EAT)</td>
                                        <td class="text-right font-weight-bold">{{ number_format($baseNetProfit, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($simNetProfit, 2, ',', '.') }}</td>
                                        <td class="text-right font-weight-bold">
                                            {{ ($simNetProfit - $baseNetProfit) >= 0 ? '+' : '' }}{{ number_format($simNetProfit - $baseNetProfit, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-light p-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i> Transmisi simulasi ini secara otomatis memperbarui margin keuntungan (GPM, EBIT Margin, NPM) serta rasio pengembalian modal (ROE, ROA).
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MATRIKS TRANSMISI DAMPAK KE 15 RASIO KEUANGAN LEVEL 2 -->
            <div class="card card-outline card-teal shadow-sm mb-4">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-coins mr-1 text-teal"></i> Matriks Transmisi Dampak ke 15 Rasio Keuangan (Level 2)
                    </h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 12%;">Pilar</th>
                                <th style="width: 25%;">Nama Rasio Keuangan</th>
                                <th class="text-center" style="width: 10%;">Target</th>
                                <th class="text-center" style="width: 11%;">Baseline (Riil)</th>
                                <th class="text-center" style="width: 12%;">Hasil Simulasi</th>
                                <th class="text-center" style="width: 10%;">Dampak (&Delta;)</th>
                                <th class="text-center" style="width: 10%;">Capaian</th>
                                <th class="text-center" style="width: 10%;">Status Baru</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ratiosComparison as $item)
                                <tr>
                                    <td>
                                        <span class="badge badge-secondary">{{ $item['category'] }}</span>
                                    </td>
                                    <td class="font-weight-bold text-dark">
                                        {{ $item['name'] }}
                                    </td>
                                    <td class="text-center">{{ number_format($item['target'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="text-center">{{ number_format($item['baseline'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="text-center font-weight-bold text-teal">
                                        {{ number_format($item['simulated'], 2) }} {{ $item['unit'] }}
                                    </td>
                                    <td class="text-center font-weight-bold">
                                        @if($item['diff'] == 0)
                                            <span class="text-muted">&plusmn; 0.00</span>
                                        @elseif($item['diff_direction'] === 'better')
                                            <span class="text-success"><i class="fas fa-arrow-up mr-1"></i> {{ $item['diff'] > 0 ? '+' : '' }}{{ number_format($item['diff'], 2) }}</span>
                                        @else
                                            <span class="text-danger"><i class="fas fa-arrow-down mr-1"></i> {{ $item['diff'] > 0 ? '+' : '' }}{{ number_format($item['diff'], 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center font-weight-bold">
                                        {{ number_format($item['sim_achievement'], 1) }}%
                                    </td>
                                    <td class="text-center">
                                        @if($item['sim_status'] === 'Tercapai')
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                        @elseif($item['sim_status'] === 'Waspada')
                                            <span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-circle"></i> Waspada</span>
                                        @else
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Data rasio keuangan periode ini belum tersedia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>
