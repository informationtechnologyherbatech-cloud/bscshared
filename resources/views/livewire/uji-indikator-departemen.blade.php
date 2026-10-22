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
                        Simulasi What-If: Uji perubahan nilai capaian sasaran mutu (KPI) dan pantau langsung rambatannya ke 12 Pos Finansial Inti Pabrik Botol Plastik.
                    </small>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="d-inline-flex align-items-center" style="gap: 8px;">
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

                        <!-- Tombol Reset Simulasi -->
                        <button type="button" wire:click="resetSimulation" class="btn btn-sm btn-outline-secondary" title="Kembalikan semua nilai ke data aktual">
                            <i class="fas fa-undo mr-1"></i> Reset Baseline
                        </button>

                        <a href="{{ route('department-objectives') }}" class="btn btn-sm btn-outline-warning text-dark font-weight-bold">
                            <i class="fas fa-bullseye mr-1"></i> Sasaran Mutu
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
            </div>

            <!-- PROYEKSI STATUS KESEHATAN 12 POS FINANSIAL INTI -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <strong class="text-dark">
                        <i class="fas fa-sitemap text-teal mr-2"></i> Proyeksi Dampak ke 12 Pos Finansial Inti Pabrik Botol Plastik
                    </strong>
                    <span class="badge badge-teal font-weight-normal px-2 py-1">Tingkat 3 &rarr; Tingkat 2 CoA</span>
                </div>
                <div class="card-body p-2">
                    <div class="row">
                        @foreach($corePos as $cp)
                            @php
                                $pData = $posSimulations[$cp['code']] ?? null;
                                $hStatus = $pData ? $pData['overall_health'] : 'Netral';
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
                                <div class="p-2 rounded border bg-white h-100 {{ $borderCls }}" style="border-width: 1.5px !important; font-size: 12px;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge badge-teal">{{ $cp['code'] }}</span>
                                        <span class="badge {{ $badgeCls }} font-weight-bold" style="font-size: 10px;">
                                            {{ $hStatus }}
                                        </span>
                                    </div>
                                    <div class="font-weight-bold text-dark text-truncate" title="{{ $cp['name'] }}">
                                        {{ $cp['name'] }}
                                    </div>
                                    <div class="text-muted small" style="font-size: 11px;">
                                        Rasio: <strong>{{ $cp['ratio'] }}</strong>
                                    </div>
                                    <div class="mt-2 pt-1 border-top d-flex justify-content-between text-muted" style="font-size: 11px;">
                                        <span>Terhubung: <strong>{{ count($pData['kpis'] ?? []) }}</strong> KPI</span>
                                        @if(($pData['dibawah_count'] ?? 0) > 0)
                                            <span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $pData['dibawah_count'] }} GAP</span>
                                        @elseif(($pData['waspada_count'] ?? 0) > 0)
                                            <span class="text-warning font-weight-bold">{{ $pData['waspada_count'] }} Waspada</span>
                                        @else
                                            <span class="text-success font-weight-bold"><i class="fas fa-check mr-1"></i>Aman</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- TABEL SIMULATOR WHAT-IF PER INDIKATOR (KPI) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                    <div>
                        <strong class="text-dark"><i class="fas fa-sliders-h text-warning mr-2"></i> Meja Uji Nilai Realisasi KPI (What-If Sliders/Input):</strong>
                        <div class="text-muted small">Ubah angka pada kolom Realisasi Uji untuk melihat perubahan capaian & proyeksi dampak secara langsung.</div>
                    </div>
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <!-- Filter Dept Odoo -->
                        <select wire:model.live="selectedDept" class="form-control form-control-sm" style="width: 200px;">
                            <option value="">-- Semua Departemen Odoo --</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->code }}">{{ $d->name }} ({{ $d->code }})</option>
                            @endforeach
                        </select>

                        <!-- Toggle Gap Only -->
                        <div class="custom-control custom-switch" style="font-size: 12px;">
                            <input type="checkbox" class="custom-control-input" id="filterGap" wire:model.live="filterGapOnly">
                            <label class="custom-control-label font-weight-bold text-danger" for="filterGap" style="cursor: pointer;">
                                Tampilkan Hanya Yang GAP / Bermasalah
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 580px; overflow-y: auto;">
                        <table class="table table-bordered table-hover table-sm m-0 align-middle" style="font-size: 12.5px;">
                            <thead class="bg-light position-sticky" style="top: 0; z-index: 2;">
                                <tr>
                                    <th style="width: 8%;">Kode KPI</th>
                                    <th style="width: 24%;">Sasaran Mutu (Nama KPI)</th>
                                    <th style="width: 12%;">Dept PIC</th>
                                    <th class="text-center" style="width: 7%;">Target</th>
                                    <th class="text-center" style="width: 8%;">Aktual Asli</th>
                                    <th class="text-center bg-warning text-dark" style="width: 14%;">
                                        <i class="fas fa-edit mr-1"></i> Realisasi Uji (Simulasi)
                                    </th>
                                    <th class="text-center" style="width: 11%;">Hasil Uji Capaian</th>
                                    <th style="width: 16%;">Dampak ke Pos Finansial</th>
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
                                            @php
                                                $dObj = collect($departments)->firstWhere('code', $kpi['dept_code']);
                                            @endphp
                                            <span class="badge badge-light border text-dark font-weight-bold">
                                                {{ $dObj ? $dObj->name : $kpi['dept_code'] }}
                                            </span>
                                        </td>
                                        <td class="text-center font-weight-bold text-muted">
                                            {{ number_format($kpi['target'], 2, ',', '.') }} {{ $kpi['unit'] }}
                                        </td>
                                        <td class="text-center text-muted">
                                            {{ number_format($kpi['actual'], 2, ',', '.') }} {{ $kpi['unit'] }}
                                            <div style="font-size: 10px;">(Baseline)</div>
                                        </td>
                                        <td class="text-center bg-light">
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
                                                            title="Terapkan Nilai Uji">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </div>
                                            </div>
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
                                                        {{ $isBetter ? 'Lebih Efisien/Baik' : 'Menurun/Memburuk' }}
                                                    </div>
                                                </div>
                                            @else
                                                <small class="text-muted d-block mt-1">= Sama dg Asli</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(count($kpi['pos_codes']) > 0)
                                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                                    @foreach($kpi['pos_codes'] as $pc)
                                                        @if(isset($posMap[$pc]))
                                                            <span class="badge {{ $isGap ? 'badge-danger' : 'badge-teal' }}" style="font-size: 10px;" title="{{ $posMap[$pc]['name'] }}">
                                                                <i class="fas fa-link mr-1"></i>{{ $pc }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                                <div class="text-dark small mt-1 font-italic" style="font-size: 11px; line-height: 1.2;">
                                                    @if($isGap)
                                                        <span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Risiko pembengkakan / target pos terancam</span>
                                                    @else
                                                        <span class="text-success"><i class="fas fa-check mr-1"></i>Mendukung pencapaian target pos finansial</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted small font-italic">- Tidak terhubung langsung ke pos -</span>
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

                <div class="card-footer bg-light py-2 text-muted small d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle text-warning mr-1"></i> Meja Uji Indikator berjalan di memory sandbox (tidak mengubah data riil database hingga disimpan resmi di Level 3).
                    </div>
                    <div>
                        Periode: <strong>{{ $selectedPeriod }}</strong>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
