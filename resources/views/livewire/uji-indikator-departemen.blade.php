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

                        <!-- Tombol Reset Simulasi -->
                        <button type="button" wire:click="resetSimulation" class="btn btn-sm btn-outline-secondary" title="Kembalikan semua nilai ke data aktual asli">
                            <i class="fas fa-undo mr-1"></i> Reset Baseline
                        </button>

                        <!-- Tombol Terapkan ke Live -->
                        @if(auth()->user()?->can('manage objectives') || auth()->user()?->can('can_write_kpi'))
                            <button type="button" wire:click="$set('showApplyModal', true)" class="btn btn-sm btn-success font-weight-bold" title="Terapkan hasil simulasi ke data sasaran mutu resmi">
                                <i class="fas fa-check-double mr-1"></i> Terapkan ke Sasaran Mutu
                            </button>
                        @endif

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
                        <i class="fas fa-sitemap text-teal mr-2"></i> Proyeksi Dampak ke Pos Finansial Inti Pabrik Botol Plastik
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
                                    <th class="text-center bg-warning text-dark" style="width: 18%;">
                                        <i class="fas fa-edit mr-1"></i> Realisasi Uji (Simulasi)
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
                                        <td class="text-center font-weight-bold text-muted">
                                            {{ number_format($kpi['target'], 2, ',', '.') }} {{ $kpi['unit'] }}
                                        </td>
                                        <td class="text-center text-muted">
                                            {{ number_format($kpi['actual'], 2, ',', '.') }} {{ $kpi['unit'] }}
                                            <div style="font-size: 10px;">(Baseline)</div>
                                        </td>
                                        <td class="text-center bg-light">
                                            <!-- Step Adjustment Buttons (+5%, -5%, +10%, -10%) -->
                                            <div class="btn-group btn-group-xs mb-1 w-100" role="group">
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', -10)" class="btn btn-outline-secondary py-0" title="Turun 10%" style="font-size: 10px;">-10%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', -5)" class="btn btn-outline-secondary py-0" title="Turun 5%" style="font-size: 10px;">-5%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', 5)" class="btn btn-outline-secondary py-0" title="Naik 5%" style="font-size: 10px;">+5%</button>
                                                <button type="button" wire:click="adjustSimulatedPercent('{{ $kpi['id'] }}', 10)" class="btn btn-outline-secondary py-0" title="Naik 10%" style="font-size: 10px;">+10%</button>
                                            </div>

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

    <!-- MODAL KONFIRMASI TERAPKAN KE LIVE -->
    @if($showApplyModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white py-2">
                        <h5 class="modal-title font-weight-bold" style="font-size: 15px;">
                            <i class="fas fa-check-double mr-1"></i> Konfirmasi Terapkan Hasil Uji Simulasi
                        </h5>
                        <button type="button" class="close text-white" wire:click="$set('showApplyModal', false)">&times;</button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="alert alert-warning py-2 mb-3" style="font-size: 12.5px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Perhatian:</strong>
                            Aksi ini akan memperbarui nilai <strong>Realisasi (Actual)</strong> pada seluruh Sasaran Mutu (KPI) periode <strong>{{ $selectedPeriod }}</strong> di database resmi sesuai dengan angka hasil uji simulasi di layar ini.
                        </div>
                        <p class="small text-muted mb-0">
                            Apakah Anda yakin ingin menerapkan seluruh nilai uji simulasi ini menjadi data capaian sasaran mutu resmi?
                        </p>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('showApplyModal', false)">Batal</button>
                        <button type="button" class="btn btn-sm btn-success font-weight-bold" wire:click="applySimulationToLive">
                            <i class="fas fa-save mr-1"></i> Ya, Terapkan Sekarang
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
