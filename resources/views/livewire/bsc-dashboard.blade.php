<div>
    <!-- Custom Pyramid & Interactive Styles (Perfect Triangle Geometry) -->
    <style>
        .pyramid-wrapper {
            position: relative;
            max-width: 800px;
            height: 440px;
            margin: 0 auto 20px auto;
            padding: 10px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .pyramid-tier {
            position: relative;
            cursor: pointer;
            width: 100%;
            height: 105px;
            margin-bottom: 3px;
            transition: all 0.25s ease-in-out;
            text-align: center;
            color: #ffffff;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            user-select: none;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.15));
        }

        .pyramid-tier:hover {
            filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.35)) brightness(1.1);
            transform: scale(1.015);
            z-index: 10;
        }

        .pyramid-tier.active-tier {
            filter: drop-shadow(0 10px 25px rgba(23, 162, 184, 0.6)) brightness(1.15);
            transform: scale(1.025);
            z-index: 12;
        }

        /* PERFECT TRIANGLE POLYGON CLIP PATHS */
        .tier-1 {
            clip-path: polygon(50% 0%, 62.5% 100%, 37.5% 100%);
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        }

        .tier-2 {
            clip-path: polygon(37.5% 0%, 62.5% 0%, 75% 100%, 25% 100%);
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }

        .tier-3 {
            clip-path: polygon(25% 0%, 75% 0%, 87.5% 100%, 12.5% 100%);
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        }

        .tier-4 {
            clip-path: polygon(12.5% 0%, 87.5% 0%, 100% 100%, 0% 100%);
            background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
        }

        .tier-content {
            padding: 5px 20px;
            z-index: 2;
            text-shadow: 0 1px 3px rgba(0,0,0,0.4);
        }

        /*
         * Puncak segitiga sangat sempit: pada 800px, lebar yang terlihat hanya
         * 200px di dasar tingkat 1 dan menyempit ke titik di atas. Teks karena
         * itu didorong turun ke bagian yang lebar, diperkecil, dan dibatasi
         * lebarnya — kalau tidak, ia terpotong oleh clip-path.
         */
        .tier-1 .tier-content {
            margin-top: 28px;
            max-width: 120px;
            padding: 0 2px;
        }

        .tier-1 .tier-title {
            font-size: 9px;
            letter-spacing: 0;
            line-height: 1.2;
        }

        .tier-1 .tier-score {
            font-size: 20px;
        }

        /* Keterangan panjangnya sudah ada pada kartu Apex Score di atas piramida. */
        .tier-1 .tier-subtitle {
            display: none;
        }

        /*
         * Di bawah 768px bentuk segitiga dilepas dan tiap tingkat menjadi balok
         * bertumpuk (lihat blok responsif pada public/css/custom-app.css), sehingga
         * pembatasan di atas tidak lagi diperlukan dan judulnya kembali tampil utuh.
         */

        .tier-title {
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
            font-weight: 700;
        }

        .tier-score {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.1;
        }

        .tier-subtitle {
            font-size: 11px;
            opacity: 0.95;
            font-weight: 400;
        }

        .click-hint-badge {
            position: absolute;
            right: 22%;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(4px);
            padding: 3px 8px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .drill-down-card {
            border: 2px solid #17a2b8;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        /*
         * PIRAMIDA DI LAYAR SEMPIT
         * Segitiga clip-path ikut menyempit mengikuti lebar layar, sedangkan teksnya tidak —
         * di ponsel label tingkat 2 sampai 4 terpotong. Di bawah 768px bentuk segitiga dilepas
         * dan tiap tingkat menjadi balok bertumpuk: warna, urutan, skor, dan sifat
         * klik-untuk-telusur tetap sama, tetapi seluruh teksnya terbaca utuh.
         */
        @media (max-width: 767.98px) {
            .pyramid-wrapper {
                height: auto;
                max-width: 100%;
                padding: 0;
            }

            .pyramid-tier {
                clip-path: none;
                height: auto;
                min-height: 62px;
                padding: 12px 14px;
                margin-bottom: 8px;
                border-radius: 10px;
            }

            /* Efek perbesar menimbulkan geseran mendatar pada layar sempit. */
            .pyramid-tier:hover,
            .pyramid-tier.active-tier {
                transform: none;
            }

            .tier-content,
            .tier-1 .tier-content {
                padding: 0;
                margin-top: 0;
                max-width: none;
                width: 100%;
            }

            .tier-title,
            .tier-1 .tier-title {
                font-size: 12px;
                letter-spacing: 0.3px;
            }

            .tier-score,
            .tier-1 .tier-score {
                font-size: 22px;
            }

            .tier-subtitle {
                font-size: 10px;
                line-height: 1.35;
            }

            .click-hint-badge {
                position: static;
                transform: none;
                display: inline-flex;
                margin-top: 8px;
            }
        }

        @media (max-width: 400px) {
            .tier-score,
            .tier-1 .tier-score {
                font-size: 20px;
            }
        }
    </style>

    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-cubes text-teal mr-2"></i> Dashboard Piramida BSC
                        <small class="text-muted d-block" style="font-size: 13px;">Interaktif & Telusur Kinerja 4 Tingkat Perusahaan</small>
                    </h1>
                </div>
                <div class="col-sm-6 text-right">
                    <div class="form-inline float-right">
                        <span class="mr-2 badge {{ $isClosed ? 'badge-secondary' : 'badge-success' }} p-2">
                            <i class="fas {{ $isClosed ? 'fa-lock' : 'fa-lock-open' }} mr-1"></i> {{ $isClosed ? 'CLOSED (Terkunci)' : 'OPEN (Aktif)' }}
                        </span>
                        @can('can_override')
                        <button wire:click="togglePeriodStatus" class="btn btn-xs {{ $isClosed ? 'btn-outline-success' : 'btn-outline-secondary' }} mr-3" title="Kunci / Buka Periode">
                            {{ $isClosed ? 'Buka Periode' : 'Kunci Periode' }}
                        </button>
                        @endcan
                        <label for="periodSelect" class="mr-2 font-weight-bold">Periode:</label>
                        <select wire:model.live="selectedPeriod" id="periodSelect" class="form-control form-control-sm border-teal mr-2">
                            @foreach($periods as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                        @can('can_override')
                        <button wire:click="$set('showCreatePeriodModal', true)" class="btn btn-teal btn-sm">
                            <i class="fas fa-plus-circle mr-1"></i> Periode Baru
                        </button>
                        @endcan
                    </div>
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

            @if(session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if($isStale)
                <div class="alert alert-warning alert-dismissible fade show border-warning" role="alert">
                    <i class="fas fa-clock mr-2 text-dark"></i>
                    <strong>Peringatan Kesegaran Data:</strong> Data belum disinkronkan lebih dari 26 jam (Terakhir: {{ $lastSyncTime ? $lastSyncTime->diffForHumans() : 'N/A' }}).
                </div>
            @endif

            @if($isClosed)
                <div class="alert alert-secondary fade show border-dark" role="alert">
                    <i class="fas fa-lock mr-2"></i>
                    <strong>Periode Terkunci (CLOSED):</strong> Periode {{ $selectedPeriod }} telah ditutup secara operasional. Seluruh perubahan nilai KPI/Rasio dibekukan.
                </div>
            @endif

            <!-- APEX SCORE SUMMARY BAR -->
            <div class="card bg-gradient-navy text-white shadow-sm mb-4">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <h5 class="font-weight-bold text-uppercase mb-1 text-warning">
                                <i class="fas fa-crown mr-2"></i> Apex Score Hop 4 (Konsolidasi)
                            </h5>
                            <small class="text-white-50">
                                @if(count($apexBreakdown) > 0)
                                    Rata-rata terbobot:
                                    @foreach($apexBreakdown as $bagian)
                                        <strong>{{ $bagian['weight'] }}% {{ $bagian['label'] }}</strong>{{ ! $loop->last ? ' + ' : '' }}
                                    @endforeach
                                    @if(count($apexBreakdown) < 3)
                                        <span class="d-block">Tingkat tanpa data pada periode ini dikeluarkan, bobotnya dibagi ke tingkat yang tersedia.</span>
                                    @endif
                                @else
                                    Belum ada data pada periode ini, sehingga skor belum dapat dihitung.
                                @endif
                            </small>
                        </div>
                        <div class="col-md-5 text-md-right text-center mt-2 mt-md-0">
                            <span class="h2 font-weight-bold text-warning mb-0 mr-3">{{ number_format($apexScore, 1) }}%</span>
                            <span class="badge badge-pill {{ $apexScore >= 100 ? 'badge-success' : ($apexScore >= 80 ? 'badge-warning' : 'badge-danger') }} px-3 py-2 font-weight-bold">
                                Status: {{ $apexScore >= 100 ? 'TERCAPAI' : ($apexScore >= 80 ? 'WASPADA' : 'DI BAWAH TARGET') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISUAL SEGITIGA PIRAMIDA SEMPURNA BSC (INTERAKTIF KLIK TELUSUR) -->
            <div class="card card-outline card-teal elevation-2 mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-play fa-rotate-270 text-teal mr-2"></i> Piramida BSC Segitiga Sempurna (Klik Setiap Tingkat untuk Telusur Detail)
                    </h3>
                    <span class="badge badge-info p-2" style="font-size: 11px;">
                        <i class="fas fa-mouse-pointer mr-1"></i> Klik Tingkat Piramida Untuk Telusur
                    </span>
                </div>
                <div class="card-body bg-light position-relative p-4">

                    <div class="pyramid-wrapper">

                        <!-- TINGKAT 1: APEX KEUANGAN (PUNCAK SEGITIGA SEMPURNA 50% 0%) -->
                        <div class="pyramid-tier tier-1 {{ $activeLevel === 1 ? 'active-tier' : '' }}" wire:click="selectLevel(1)">
                            <div class="tier-content">
                                <div class="tier-title">Tingkat 1: Apex</div>
                                <div class="tier-score">{{ number_format($apexScore, 1) }}%</div>
                            </div>
                            @if($activeLevel === 1)
                                <div class="click-hint-badge"><i class="fas fa-check-circle text-warning"></i> Aktif Telusur</div>
                            @endif
                        </div>

                        <!-- TINGKAT 2: RASIO KEUANGAN (MID-TOP TRAPEZOID 37.5% - 62.5% TO 25% - 75%) -->
                        <div class="pyramid-tier tier-2 {{ $activeLevel === 2 ? 'active-tier' : '' }}" wire:click="selectLevel(2)">
                            <div class="tier-content">
                                <div class="tier-title"><i class="fas fa-chart-line text-white mr-1"></i> Tingkat 2: Rasio Keuangan</div>
                                <div class="tier-score">{{ number_format($avgRatioScore, 1) }}%</div>
                                <div class="tier-subtitle">{{ $ratioCount }} Rasio Keuangan <br> (Likuiditas, Solvabilitas, Aktivitas, Profitabilitas, Produktivitas)</div>
                            </div>
                            @if($activeLevel === 2)
                                <div class="click-hint-badge"><i class="fas fa-check-circle text-warning"></i> Aktif Telusur</div>
                            @endif
                        </div>

                        <!-- TINGKAT 3: OBJECTIVE DEPARTEMEN (MID-BOTTOM TRAPEZOID 25% - 75% TO 12.5% - 87.5%) -->
                        <div class="pyramid-tier tier-3 {{ $activeLevel === 3 ? 'active-tier' : '' }}" wire:click="selectLevel(3)">
                            <div class="tier-content">
                                <div class="tier-title"><i class="fas fa-bullseye text-white mr-1"></i> Tingkat 3: Objective Dept</div>
                                <div class="tier-score">{{ number_format($avgObjScore, 1) }}%</div>
                                <div class="tier-subtitle">{{ $counts['total_kpi'] }} Sasaran Mutu Operasional Departemen</div>
                            </div>
                            @if($activeLevel === 3)
                                <div class="click-hint-badge"><i class="fas fa-check-circle text-warning"></i> Aktif Telusur</div>
                            @endif
                        </div>

                        <!-- TINGKAT 4: PROGRAM KERJA / ACTION PLANS (BASE TRAPEZOID 12.5% - 87.5% TO 0% - 100%) -->
                        <div class="pyramid-tier tier-4 {{ $activeLevel === 4 ? 'active-tier' : '' }}" wire:click="selectLevel(4)">
                            <div class="tier-content">
                                <div class="tier-title"><i class="fas fa-tasks text-white mr-1"></i> Tingkat 4: Program Kerja (Action Plans)</div>
                                <div class="tier-score">{{ number_format($avgActionProgress, 1) }}%</div>
                                <div class="tier-subtitle">Inisiatif Mitigasi Perbaikan & Program Eksekusi</div>
                            </div>
                            @if($activeLevel === 4)
                                <div class="click-hint-badge" style="right: 15%;"><i class="fas fa-check-circle text-warning"></i> Aktif Telusur</div>
                            @endif
                        </div>

                    </div>

                    <!-- Visual Arrow Pointer for Active Drill-Down -->
                    <div class="text-center mt-2">
                        <div class="badge badge-pill badge-info px-4 py-2 font-weight-bold shadow-sm" style="font-size: 13px;">
                            <i class="fas fa-arrow-down mr-1"></i> Menampilkan Detail Drill-Down untuk Tingkat {{ $activeLevel }}
                        </div>
                    </div>

                </div>
            </div>

            <!-- PANEL TELUSUR DETAIL (DYNAMIC DRILL-DOWN PANEL) -->
            <div class="card drill-down-card bg-white mb-4">
                <div class="card-header bg-teal text-white d-flex justify-content-between align-items-center">
                    <h4 class="card-title font-weight-bold mb-0">
                        @if($activeLevel === 1)
                            <i class="fas fa-crown mr-2"></i> Telusur Detail Tingkat 1: Apex Keuangan & Revenue Puncak
                        @elseif($activeLevel === 2)
                            <i class="fas fa-chart-line mr-2"></i> Telusur Detail Tingkat 2: Rasio Keuangan Perusahaan
                        @elseif($activeLevel === 3)
                            <i class="fas fa-bullseye mr-2"></i> Telusur Detail Tingkat 3: Objective & Sasaran Mutu Departemen
                        @elseif($activeLevel === 4)
                            <i class="fas fa-tasks mr-2"></i> Telusur Detail Tingkat 4: Program Kerja & Action Plans
                        @endif
                    </h4>

                    <div>
                        @if($activeLevel === 2)
                            <a href="{{ route('financial-ratios', ['status' => $statusFilter, 'period' => $selectedPeriod]) }}" class="btn btn-sm btn-light text-teal font-weight-bold">
                                Halaman Rasio Utuh <i class="fas fa-external-link-alt ml-1"></i>
                            </a>
                        @elseif($activeLevel === 3)
                            <a href="{{ route('department-objectives', ['status' => $statusFilter, 'period' => $selectedPeriod]) }}" class="btn btn-sm btn-light text-teal font-weight-bold">
                                Halaman Objective Utuh <i class="fas fa-external-link-alt ml-1"></i>
                            </a>
                        @elseif($activeLevel === 4)
                            <a href="{{ route('action-plans', ['status' => $statusFilter]) }}" class="btn btn-sm btn-light text-teal font-weight-bold">
                                Halaman Action Plans Utuh <i class="fas fa-external-link-alt ml-1"></i>
                            </a>
                        @else
                            <a href="{{ route('bsc-wiring', ['period' => $selectedPeriod]) }}" class="btn btn-sm btn-light text-teal font-weight-bold">
                                Lihat Wiring Causes <i class="fas fa-project-diagram ml-1"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body">

                    <!-- BARIS FILTER & SEARCH TELUSUR -->
                    @if($activeLevel > 1)
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-5 mb-2 mb-md-0">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-search text-teal"></i></span>
                                    </div>
                                    <input type="text" wire:model.live.debounce.250ms="searchQuery" class="form-control" placeholder="Cari nama indikator, kode KPI, atau departemen...">
                                </div>
                            </div>
                            <div class="col-md-7 text-md-right">
                                <span class="mr-2 font-weight-bold text-muted small">Filter Status:</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" wire:click="filterStatus('all')" class="btn {{ $statusFilter === 'all' ? 'btn-teal' : 'btn-outline-secondary' }}">Semua</button>
                                    <button type="button" wire:click="filterStatus('bermasalah')" class="btn {{ $statusFilter === 'bermasalah' ? 'btn-danger font-weight-bold' : 'btn-outline-danger' }}"><i class="fas fa-exclamation-triangle mr-1"></i> Hanya Bermasalah</button>
                                    <button type="button" wire:click="filterStatus('Tercapai')" class="btn {{ $statusFilter === 'Tercapai' ? 'btn-success' : 'btn-outline-success' }}">Tercapai</button>
                                    <button type="button" wire:click="filterStatus('Waspada')" class="btn {{ $statusFilter === 'Waspada' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">Waspada</button>
                                    <button type="button" wire:click="filterStatus('Di Bawah Target')" class="btn {{ $statusFilter === 'Di Bawah Target' ? 'btn-danger' : 'btn-outline-danger' }}">Di Bawah Target</button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- DRILL-DOWN CONTENT ACCORDING TO ACTIVE LEVEL -->
                    @if($activeLevel === 1)
                        <!-- TINGKAT 1: APEX DRILL DOWN -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-light rounded border border-teal h-100">
                                    <h5 class="font-weight-bold text-teal"><i class="fas fa-calculator mr-2"></i> Rincian Bobot Apex Score</h5>
                                    <p class="text-muted small">Apex Score dihitung dari kombinasi realisasi Revenue puncak dan rata-rata rasio keuangan perusahaan:</p>
                                    <ul class="list-group list-group-flush small mb-3">
                                        <li class="list-group-item bg-transparent d-flex justify-content-between">
                                            <span>Skor Realisasi Revenue (45%)</span>
                                            <strong class="text-dark">96.00%</strong>
                                        </li>
                                        <li class="list-group-item bg-transparent d-flex justify-content-between">
                                            <span>Skor Rata-Rata 7 Rasio Keuangan (55%)</span>
                                            <strong class="text-dark">{{ number_format($avgRatioScore, 2) }}%</strong>
                                        </li>
                                        <li class="list-group-item bg-transparent d-flex justify-content-between font-weight-bold border-top">
                                            <span class="text-teal">Total Konsolidasi Apex Score</span>
                                            <span class="text-teal h5 font-weight-bold mb-0">{{ number_format($apexScore, 2) }}%</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-light rounded border border-info h-100">
                                    <h5 class="font-weight-bold text-info"><i class="fas fa-chart-line mr-2"></i> Capaian Target Revenue</h5>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <small class="text-muted d-block">Baseline RKAP 2026</small>
                                            <h4 class="font-weight-bold text-dark">IDR 120.00 M</h4>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">Realisasi Puncak</small>
                                            <h4 class="font-weight-bold text-success">IDR 115.20 M</h4>
                                        </div>
                                    </div>
                                    <div class="progress mt-3 style-progress" style="height: 10px;">
                                        <div class="progress-bar bg-success" style="width: 96%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between small text-muted mt-1">
                                        <span>Capaian Target: 96.00%</span>
                                        <span class="text-danger">Delta: -4.00%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    @elseif($activeLevel === 2)
                        <!-- TINGKAT 2: RASIO KEUANGAN TABLE -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kategori Rasio</th>
                                        <th>Nama Indikator Rasio</th>
                                        <th class="text-center">Target</th>
                                        <th class="text-center">Actual</th>
                                        <th class="text-center">Capaian (%)</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi Telusur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($filteredRatios as $r)
                                        <tr>
                                            <td><span class="badge badge-info">{{ $r->category }}</span></td>
                                            <td class="font-weight-bold">{{ $r->ratio_name }}</td>
                                            <td class="text-center">{{ number_format($r->target, 2) }}</td>
                                            <td class="text-center font-weight-bold text-dark">{{ number_format($r->actual, 2) }}</td>
                                            <td class="text-center font-weight-bold text-teal">{{ number_format($r->achievement_pct, 1) }}%</td>
                                            <td class="text-center">
                                                @if($r->status === 'Tercapai')
                                                    <span class="badge badge-tercapai px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                                @elseif($r->status === 'Waspada')
                                                    <span class="badge badge-waspada px-2 py-1"><i class="fas fa-exclamation-triangle"></i> Waspada</span>
                                                @else
                                                    <span class="badge badge-dibawah px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <button wire:click="inspectItem('ratio', {{ $r->id }})" class="btn btn-xs btn-outline-teal">
                                                    <i class="fas fa-search-plus mr-1"></i> Inspect Telusur
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3">Tidak ada data rasio keuangan yang sesuai filter.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    @elseif($activeLevel === 3)
                        <!-- TINGKAT 3: OBJECTIVE DEPARTEMEN TABLE -->
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Dept</th>
                                        <th>Kode KPI</th>
                                        <th>Nama Sasaran Mutu (Objective)</th>
                                        <th class="text-center">Target</th>
                                        <th class="text-center">Actual</th>
                                        <th class="text-center">Capaian (%)</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Mitigasi Action</th>
                                        <th class="text-center">Aksi Telusur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($filteredObjectives as $obj)
                                        <tr>
                                            <td><span class="badge badge-dark">{{ $obj->dept_code }}</span></td>
                                            <td><code>{{ $obj->kpi_code }}</code></td>
                                            <td class="font-weight-bold">{{ $obj->kpi_name }}</td>
                                            <td class="text-center">{{ number_format($obj->target, 1) }}</td>
                                            <td class="text-center font-weight-bold text-dark">{{ number_format($obj->actual, 1) }}</td>
                                            <td class="text-center font-weight-bold text-teal">{{ number_format($obj->achievement_pct, 1) }}%</td>
                                            <td class="text-center">
                                                @if($obj->status === 'Tercapai')
                                                    <span class="badge badge-tercapai px-2 py-1"><i class="fas fa-check-circle"></i> Tercapai</span>
                                                @elseif($obj->status === 'Waspada')
                                                    <span class="badge badge-waspada px-2 py-1"><i class="fas fa-exclamation-triangle"></i> Waspada</span>
                                                @else
                                                    <span class="badge badge-dibawah px-2 py-1"><i class="fas fa-times-circle"></i> Off-Target</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-pill badge-light border text-purple">
                                                    <i class="fas fa-tasks mr-1"></i> {{ $obj->actionPlans->count() }} Plan
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button wire:click="inspectItem('objective', {{ $obj->id }})" class="btn btn-xs btn-outline-teal">
                                                    <i class="fas fa-search-plus mr-1"></i> Inspect Telusur
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-3">Tidak ada data sasaran mutu departemen yang sesuai filter.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    @elseif($activeLevel === 4)
                        <!-- TINGKAT 4: PROGRAM KERJA / ACTION PLANS CARDS -->
                        <div class="row">
                            @forelse($filteredActionPlans as $ap)
                                <div class="col-md-6 mb-3">
                                    <div class="card card-outline card-danger h-100 shadow-sm">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge badge-dark">{{ $ap->owner_dept }}</span>
                                                <span class="badge {{ $ap->status === 'Selesai' ? 'badge-success' : ($ap->status === 'Dalam Proses' ? 'badge-warning' : 'badge-danger') }}">
                                                    {{ $ap->status }}
                                                </span>
                                            </div>
                                            <h6 class="font-weight-bold text-dark mb-2">{{ $ap->title }}</h6>
                                            <small class="text-muted d-block mb-2">
                                                <i class="fas fa-link text-purple mr-1"></i> KPI Terkait:
                                                <strong>{{ $ap->objective ? $ap->objective->kpi_code . ' - ' . $ap->objective->kpi_name : 'Umum' }}</strong>
                                            </small>

                                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                                <span>Progress Pelaksanaan:</span>
                                                <strong class="text-danger">{{ $ap->progress_pct }}%</strong>
                                            </div>
                                            <div class="progress progress-sm">
                                                <div class="progress-bar bg-danger" style="width: {{ $ap->progress_pct }}%"></div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light p-2 text-right">
                                            <button wire:click="inspectItem('action', {{ $ap->id }})" class="btn btn-xs btn-outline-teal">
                                                <i class="fas fa-search-plus mr-1"></i> Inspect Detail
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center text-muted py-4">
                                    Tidak ada data program kerja (action plan) yang sesuai filter.
                                </div>
                            @endforelse
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </section>

    <!-- MODAL INSPECT TELUSUR ITEM -->
    @if($showModal && $selectedItemDetail)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-teal shadow-lg">
                    <div class="modal-header bg-teal text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-search-plus mr-2"></i> Inspeksi Telusur Lineage: {{ $selectedItemDetail['type'] }}
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeModal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="p-3 bg-light rounded mb-3 border">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <span class="badge badge-secondary mb-1">{{ $selectedItemDetail['code'] }}</span>
                                    <h4 class="font-weight-bold text-dark mb-0">{{ $selectedItemDetail['name'] }}</h4>
                                </div>
                                <div class="col-md-4 text-md-right mt-2 mt-md-0">
                                    <div class="h3 font-weight-bold text-teal mb-0">{{ $selectedItemDetail['achievement'] }}</div>
                                    <small class="text-muted">Capaian Status: <strong>{{ $selectedItemDetail['status'] }}</strong></small>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded bg-white">
                                    <small class="text-muted d-block font-weight-bold text-uppercase"><i class="fas fa-arrow-up text-info mr-1"></i> Upstream Driver (Sebab)</small>
                                    <div class="font-weight-bold text-dark" style="font-size: 13px;">{{ $selectedItemDetail['upstream'] }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="p-2 border rounded bg-white">
                                    <small class="text-muted d-block font-weight-bold text-uppercase"><i class="fas fa-arrow-down text-danger mr-1"></i> Downstream Impact (Akibat/Mitigasi)</small>
                                    <div class="font-weight-bold text-dark" style="font-size: 13px;">{{ $selectedItemDetail['downstream'] }}</div>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small mb-0">
                            <strong>Deskripsi Alignment:</strong> {{ $selectedItemDetail['description'] }}
                        </p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeModal">Tutup Telusur</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL BUAT PERIODE BARU (PRD G-05) -->
    @if($showCreatePeriodModal)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-teal shadow-lg">
                    <div class="modal-header bg-teal text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-calendar-plus mr-2"></i> Tambah Periode Pelaporan Baru
                        </h5>
                        <button type="button" class="close text-white" wire:click="$set('showCreatePeriodModal', false)">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2" style="font-size: 12px;">
                            <i class="fas fa-info-circle mr-1"></i> <strong>Aturan PRD G-05:</strong> Periode baru akan dibuat dengan nilai realisasi diawali <code>0.00</code> (bukan menyalin nilai target) sampai data aktual disinkronkan.
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Kode Periode Baru (YYYY-MM):</label>
                            <input type="text" wire:model="newPeriodInput" class="form-control @error('newPeriodInput') is-invalid @enderror" placeholder="Misal: 2026-09">
                            @error('newPeriodInput')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('showCreatePeriodModal', false)">Batal</button>
                        <button type="button" class="btn btn-teal btn-sm font-weight-bold" wire:click="createNewPeriod">
                            <i class="fas fa-save mr-1"></i> Buat Periode
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>

