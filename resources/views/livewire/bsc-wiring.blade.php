<div>
    <!-- Custom Wiring Styling (Dual Theme & Bezier Auto-Tuner Support) -->
    <style>
        .wiring-container-base {
            border-radius: 16px;
            padding: 30px 20px;
            position: relative;
            min-height: 840px;
            transition: all 0.3s ease;
        }

        /* Light Mode (Image 1 Style) */
        .wiring-theme-light .wiring-container-base {
            background: #ffffff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            color: #334155;
        }

        /* Dark Mode (Image 2 Style) */
        .wiring-theme-dark {
            background-color: #0b0f17;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 16px;
        }

        .wiring-theme-dark .wiring-container-base {
            background: #090d16;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45);
            border: 1px solid #1e293b;
        }

        .category-pill-item {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            color: #334155;
        }

        .wiring-theme-dark .category-pill-item {
            background: #1a2234;
            border-color: #27354a;
            color: #e2e8f0;
        }

        .dot-indicator-sm {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        /* 3-Column Grid Layout */
        .bezier-grid-col {
            display: grid;
            grid-template-columns: 220px 250px 1fr;
            gap: 60px;
            position: relative;
            z-index: 2;
            align-items: center;
        }

        .revenue-node-box {
            border: 2px solid #059669;
            background: #ecfdf5;
            padding: 18px 20px;
            border-radius: 16px;
            position: relative;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.08);
        }

        .wiring-theme-dark .revenue-node-box {
            background: #061e16;
            box-shadow: 0 4px 20px rgba(5, 150, 105, 0.15);
        }

        .wiring-node-card {
            border-radius: 14px;
            padding: 10px 14px;
            background: #ffffff;
            position: relative;
            transition: all 0.2s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }

        .wiring-theme-dark .wiring-node-card {
            background: #111726;
            border-color: #1e293b;
        }

        .node-dot-right {
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        .node-dot-left {
            position: absolute;
            left: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        /* SVG Flow Overlay */
        .svg-flow-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        /* Simulator Box */
        .cascade-simulator-container {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .wiring-theme-dark .cascade-simulator-container {
            background: #111827;
            border-color: #1e293b;
        }

        /* Table Card Styling */
        .dept-card-wrapper {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .wiring-theme-dark .dept-card-wrapper {
            background: #0d131d;
            border-color: #1e293b;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        }

        .dept-card-header {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #ffffff;
        }

        .dept-card-header h5 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }

        .dept-header-badge {
            background: #ffffff;
            border-radius: 20px;
            padding: 3px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .kpi-table-custom {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            font-size: 13px;
        }

        .kpi-table-custom tr {
            border-bottom: 1px solid #f1f5f9;
            position: relative;
        }

        .wiring-theme-dark .kpi-table-custom tr {
            border-bottom-color: #192233;
        }

        .kpi-table-custom tr:last-child {
            border-bottom: none;
        }

        .kpi-table-custom td {
            padding: 10px 16px;
            vertical-align: middle;
        }

        .kpi-code-coral {
            color: #f43f5e;
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 600;
        }

        .left-indicator-bar {
            position: relative;
            padding-left: 18px !important;
        }

        .left-indicator-bar::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--bar-color, #64748b);
        }

        .badge-e-green {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #047857;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .wiring-theme-dark .badge-e-green {
            background: #062016;
            color: #34d399;
        }

        .badge-e-yellow {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            color: #b45309;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .wiring-theme-dark .badge-e-yellow {
            background: #271e0c;
            color: #fbbf24;
        }

        .badge-e-locked {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #64748b;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .wiring-theme-dark .badge-e-locked {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        /*
         * DIAGRAM ALUR DI LAYAR SEMPIT
         * Grid tiga kolom memakai lebar tetap (220px + 250px + 1fr, jarak 60px) sehingga
         * membutuhkan sekitar 590px. Di bawah itu isinya terpotong — bukan sekadar sempit —
         * karena wadahnya memakai overflow-hidden. Di lebar ini kolomnya ditumpuk.
         *
         * Garis bezier digambar dari posisi antarnode pada tata letak mendatar, jadi ia
         * disembunyikan saat kolomnya menumpuk; urutan kartu sendiri sudah menyatakan alurnya.
         */
        @media (max-width: 991.98px) {
            .bezier-grid-col {
                grid-template-columns: 1fr;
                gap: 14px;
                align-items: stretch;
            }

            .svg-flow-overlay {
                display: none;
            }

            /* Tinggi tetap hanya berguna untuk merentang garis bezier; saat ditumpuk ia
               menyisakan area kosong yang panjang. */
            .wiring-container-base {
                min-height: 0;
            }

            #perspectivesCol,
            #departmentsCol {
                min-height: 0 !important;
                height: auto !important;
            }

            /* Titik sambung tanpa garis penghubung hanya menjadi bulatan menggantung. */
            .node-dot-left,
            .node-dot-right {
                display: none;
            }

            .revenue-node-box {
                min-width: 0;
                width: 100%;
            }

            .cascade-simulator-container {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 12px 14px;
            }

            .cascade-simulator-container input[type="range"] {
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        }
    </style>

    @php
        $skor = fn ($n) => $n === null ? '—' : number_format($n, 1, ',', '.');
        $rp = fn ($n) => $n === null ? '—' : 'Rp '.number_format((float) $n, 0, ',', '.');
        $angka = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, ',', '.'), '0'), ',');
        $warnaSkor = fn ($n) => $n === null ? '#94a3b8' : ($n >= 90 ? '#10b981' : ($n >= 80 ? '#f59e0b' : '#e11d48'));
    @endphp

    <div x-data="{ darkTheme: true }" :class="darkTheme ? 'wiring-theme-dark' : 'wiring-theme-light'">

        <!-- Content Header -->
        <div class="content-header p-2">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-lg-6">
                        <h1 class="m-0 font-weight-bold" :class="darkTheme ? 'text-white' : 'text-dark'">
                            <i class="fas fa-project-diagram text-teal mr-2"></i> Wiring / Peta Hubungan Transmisi Kaskade
                        </h1>
                        <small class="text-muted">{{ $entity?->legal_name ?? 'Entitas aktif' }} — target revenue → perspektif rasio → unit kerja, ditarik dari Cascade KPI.</small>
                    </div>
                    <div class="col-lg-6 text-lg-right mt-2 mt-lg-0">
                        <div class="form-inline justify-content-lg-end">
                            <button type="button" @click="darkTheme = !darkTheme" class="btn btn-sm mr-3 mb-1 font-weight-bold shadow-sm" :class="darkTheme ? 'btn-outline-warning' : 'btn-outline-dark'">
                                <i class="fas" :class="darkTheme ? 'fa-sun mr-1' : 'fa-moon mr-1'"></i>
                                <span x-text="darkTheme ? 'Light Theme' : 'Dark Theme'"></span>
                            </button>

                            <label for="periodSelW" class="mr-2 font-weight-bold" :class="darkTheme ? 'text-light' : 'text-dark'">Periode:</label>
                            <select wire:model.live="selectedPeriod" id="periodSelW" class="form-control form-control-sm border-teal mr-3 mb-1" :class="darkTheme ? 'bg-dark text-white' : ''">
                                @forelse($periods as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @empty
                                    <option value="{{ $selectedPeriod }}">{{ $selectedPeriod }}</option>
                                @endforelse
                            </select>

                            <div class="btn-group btn-group-sm shadow-sm mb-1" role="group">
                                <button type="button" wire:click="switchTab('flow')" class="btn {{ $activeTab === 'flow' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-layer-group mr-1"></i> Wiring Rasio (Makro)
                                </button>
                                <button type="button" wire:click="switchTab('cascade')" class="btn {{ $activeTab === 'cascade' ? 'btn-teal font-weight-bold' : 'btn-outline-secondary' }}">
                                    <i class="fas fa-list-check mr-1"></i> Wiring Sasaran Mutu ({{ $objectiveCount }} KPI)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <!-- FILTER: PERSPEKTIF & UNIT KERJA -->
                <div class="card mb-4 border-0 elevation-1" :class="darkTheme ? 'bg-dark text-white' : ''" style="border-radius: 14px;">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-lg-7 mb-2 mb-lg-0">
                                @foreach($perspectives as $p)
                                    <div class="category-pill-item" style="border-color: {{ $p['color'] }};">
                                        <span class="dot-indicator-sm" style="background-color: {{ $p['color'] }};"></span>
                                        <span>{{ $p['name'] }}</span>
                                        <span class="ml-2 font-weight-bold" style="font-size: 11px; color: {{ $warnaSkor($p['score']) }};">{{ $skor($p['score']) }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="col-lg-5 text-lg-right">
                                <div class="d-inline-flex align-items-center">
                                    <label for="unitSelect" class="mr-2 font-weight-bold small text-muted mb-0">UNIT KERJA:</label>
                                    <select wire:model.live="selectedUnit" id="unitSelect" class="form-control form-control-sm border-teal d-inline-block" :class="darkTheme ? 'bg-dark text-white' : ''" style="width: auto; max-width: 240px;">
                                        <option value="all">Semua unit</option>
                                        @foreach($departments as $d)
                                            <option value="{{ $d['code'] }}">{{ $d['code'] }} — {{ \Illuminate\Support\Str::limit($d['name'], 28) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="d-block mt-1">
                                    <div class="form-check d-inline-block">
                                        <input type="checkbox" wire:model.live="hanyaBergeser" class="form-check-input" id="checkBergeser">
                                        <label class="form-check-label small text-muted font-weight-bold" for="checkBergeser">hanya yang bergeser</label>
                                    </div>
                                    @if($activeTab === 'flow')
                                        <div class="form-check d-inline-block ml-3">
                                            <input type="checkbox" wire:model.live="showPotential" class="form-check-input" id="checkPotensial">
                                            <label class="form-check-label small text-muted font-weight-bold" for="checkPotensial">jalur potensial (Peta Pos Akun)</label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($activeTab === 'flow')
                    <!-- TAB 1: DIAGRAM ALUR -->
                    <div class="wiring-container-base position-relative overflow-hidden mb-4" id="wiringContainer" wire:key="wiring-flow">

                        <svg class="svg-flow-overlay" id="bezierSvgLayer" xmlns="http://www.w3.org/2000/svg" style="z-index: 1;" wire:ignore></svg>

                        <div class="bezier-grid-col">

                            <!-- KOLOM 1: TARGET REVENUE -->
                            <div>
                                <div class="revenue-node-box position-relative" id="targetRevenueNode">
                                    <div class="node-dot-right" id="revDotRight" style="background-color: #6366f1;"></div>
                                    <small class="font-weight-bold d-block mb-1" style="color: #10b981;">Target revenue {{ substr($selectedPeriod, 0, 4) }}{{ $revenue['approved'] ? ' (disahkan)' : '' }}</small>
                                    <h2 class="font-weight-bold mb-1" style="font-size: 22px; color: #10b981; font-family: monospace;">{{ $rp($revenue['annual']) }}</h2>
                                    <small class="text-muted d-block" style="font-family: monospace;">YTD {{ $rp($revenue['actual_ytd']) }} / {{ $rp($revenue['target_ytd']) }}</small>
                                    <small class="d-block font-weight-bold" style="font-family: monospace; color: {{ $warnaSkor($revenue['f1']) }};">F1 {{ $skor($revenue['f1']) }}</small>
                                    @if(! $revenue['annual'])
                                        <small class="text-muted d-block mt-1">Isi di menu Target Revenue / Perencanaan Target.</small>
                                    @endif
                                </div>
                            </div>

                            <!-- KOLOM 2: PERSPEKTIF -->
                            <div class="d-flex flex-column justify-content-between h-100 py-2" id="perspectivesCol" style="min-height: 740px;">
                                @foreach($perspectives as $p)
                                    <div class="wiring-node-card perspective-node-item" data-id="{{ $p['id'] }}" data-color="{{ $p['color'] }}" style="border-color: {{ $p['color'] }}; margin-bottom: 0;" wire:key="p-{{ $p['id'] }}">
                                        <div class="node-dot-left dot-p-left" style="background-color: {{ $p['color'] }};"></div>
                                        <div class="node-dot-right dot-p-right" style="background-color: {{ $p['color'] }};"></div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="font-weight-bold mb-0" style="color: {{ $p['color'] }}; font-size: 13.5px;">{{ $p['name'] }}</h6>
                                                <small class="text-muted d-block" style="font-size: 11px;">{{ $p['detail'] }}</small>
                                            </div>
                                            <div class="font-weight-bold h6 mb-0" style="font-size: 13.5px; color: {{ $warnaSkor($p['score']) }};">{{ $skor($p['score']) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- KOLOM 3: UNIT KERJA -->
                            <div class="d-flex flex-column h-100 py-2" id="departmentsCol" style="min-height: 740px; gap: 8px;">
                                @forelse($filteredDepartments as $dept)
                                    <div class="wiring-node-card dept-node-item d-flex justify-content-between align-items-center"
                                         data-links="{{ implode('|', $dept['links']) }}"
                                         data-potential="{{ $showPotential ? implode('|', $dept['potential']) : '' }}"
                                         style="margin-bottom: 0;" wire:key="d-{{ $dept['code'] }}">
                                        <div class="node-dot-left dot-d-left bg-secondary"></div>
                                        <div>
                                            <h6 class="font-weight-bold mb-0" :class="darkTheme ? 'text-white' : 'text-dark'" style="font-size: 13px;">
                                                <span class="text-muted" style="font-size: 11px;">{{ $dept['code'] }}</span> {{ \Illuminate\Support\Str::limit($dept['name'], 34) }}
                                            </h6>
                                            <small class="text-muted" style="font-size: 11px;">
                                                {{ $dept['sasaran'] }} sasaran · {{ $dept['bergeser'] }} bergeser · {{ $dept['kpi'] }} KPI cascade
                                            </small>
                                        </div>
                                        <div class="text-nowrap">
                                            @foreach($dept['dots'] as $dotColor)
                                                <span class="dot-indicator-sm" style="background-color: {{ $dotColor }}; margin-right: 3px;"></span>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted small p-3">Tidak ada unit kerja yang cocok dengan filter.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted mb-4">
                        <span class="mr-3"><svg width="28" height="6"><line x1="0" y1="3" x2="28" y2="3" stroke="#10b981" stroke-width="2.5"/></svg> KPI cascade unit mengklaim perspektif ini</span>
                        <span><svg width="28" height="6"><line x1="0" y1="3" x2="28" y2="3" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4 3"/></svg> jalur potensial — unit Pemilik/Kontributor pos akun pembentuknya (Peta), belum ada KPI</span>
                    </div>
                @else
                    <!-- TAB 2: SASARAN MUTU PER UNIT + UJI KASKADE REVENUE -->
                    <div class="wiring-container-base mb-4">
                        <div class="row align-items-center mb-4">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="revenue-node-box d-inline-block" style="min-width: 220px;">
                                    <small class="font-weight-bold d-block" style="color: #10b981;">Target revenue {{ substr($selectedPeriod, 0, 4) }}</small>
                                    <span class="font-weight-bold" style="font-family: monospace; color: #10b981;">{{ $rp($revenue['simulated']) }}</span>
                                    <small class="text-muted d-block" style="font-family: monospace;">
                                        k {{ number_format($factor, 3, ',', '.') }} · {{ sprintf('%+.1f', ($factor - 1) * 100) }}%
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="cascade-simulator-container">
                                    <span class="font-weight-bold text-teal"><i class="fas fa-sliders-h mr-2"></i> Uji Kaskade Revenue:</span>
                                    <input type="range" wire:model.live.debounce.150ms="cascadeValue" min="50" max="150" step="1" class="form-control-range mx-3" style="max-width: 280px;" aria-label="Revenue revisi (% dari target)">
                                    <span class="badge badge-pill badge-light border text-teal px-3 py-2 font-weight-bold" style="font-size: 15px;">{{ $cascadeValue }}%</span>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Simulasi revisi revenue: target tiap KPI menyesuaikan diri = target × (1 + e × (k − 1)).
                                    KPI dikunci (guardrail, e = 0) tidak berubah. Tidak menyimpan apa pun — revisi resmi diisi di menu Target Revenue.
                                </small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                @forelse($deptCards as $card)
                                    <div class="dept-card-wrapper" wire:key="card-{{ $card['code'] }}">
                                        <div class="dept-card-header" style="background-color: #0b192c;">
                                            <h5>{{ $card['code'] }} — {{ $card['title'] }}</h5>
                                            <span class="dept-header-badge" style="color: #0b192c;">{{ count($card['kpis']) }} sasaran</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="kpi-table-custom">
                                                <tbody>
                                                    @foreach($card['kpis'] as $kpi)
                                                        <tr class="dept-row-item">
                                                            <td class="left-indicator-bar" style="--bar-color: {{ $kpi['accent'] }}; width: 100px;">
                                                                <span class="kpi-code-coral">{{ $kpi['code'] }}</span>
                                                            </td>
                                                            <td>
                                                                <span :class="darkTheme ? 'text-light' : 'text-dark'">{{ $kpi['name'] }}</span>
                                                            </td>
                                                            <td class="text-center" style="width: 140px;">
                                                                <span class="{{ $kpi['elasticity_type'] === 'green' ? 'badge-e-green' : ($kpi['elasticity_type'] === 'yellow' ? 'badge-e-yellow' : 'badge-e-locked') }}">{{ $kpi['elasticity'] }}</span>
                                                            </td>
                                                            <td class="text-right" style="width: 190px;">
                                                                <span class="small text-muted d-block">target {{ $angka($kpi['target']) }} {{ $kpi['unit_label'] }}</span>
                                                                @if(abs($factor - 1) > 1e-9 && abs($kpi['adjusted'] - $kpi['target']) > 1e-9)
                                                                    <span class="small d-block text-info">→ disesuaikan {{ $angka($kpi['adjusted']) }}</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-right font-weight-bold" style="width: 150px; color: {{ $kpi['accent'] }};">
                                                                {{ $angka($kpi['actual']) }} {{ $kpi['unit_label'] }}
                                                                <small class="d-block text-muted font-weight-normal">{{ number_format($kpi['achievement'], 1, ',', '.') }}%</small>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted p-3">Belum ada sasaran mutu pada periode {{ $selectedPeriod }}{{ $selectedUnit !== 'all' ? ' untuk unit '.$selectedUnit : '' }}.
                                        Masukkan KPI Lolos lewat menu Cascade KPI.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </section>
    </div>

    <script>
        (function () {
            // Garis bezier dihitung dari posisi simpul di layar. Pasangan unit →
            // perspektif dibaca dari atribut data-links / data-potential, bukan
            // ditulis mati, dan digambar ulang setiap kali Livewire memperbarui DOM.
            function gambarWiring() {
                const container = document.getElementById('wiringContainer');
                const svg = document.getElementById('bezierSvgLayer');
                if (!container || !svg) return;

                const c = container.getBoundingClientRect();
                svg.setAttribute('width', c.width);
                svg.setAttribute('height', c.height);

                const titik = (el) => {
                    const r = el.getBoundingClientRect();
                    return { x: r.left + r.width / 2 - c.left, y: r.top + r.height / 2 - c.top };
                };
                const kurva = (a, b, warna, tebal, putus) => {
                    const dx = (b.x - a.x) * 0.45;
                    return `<path d="M ${a.x} ${a.y} C ${a.x + dx} ${a.y}, ${b.x - dx} ${b.y}, ${b.x} ${b.y}" stroke="${warna}" stroke-width="${tebal}" fill="none" opacity="${putus ? 0.45 : 0.85}"${putus ? ' stroke-dasharray="5 4"' : ''} />`;
                };

                const rev = document.getElementById('revDotRight');
                const perspektif = {};
                document.querySelectorAll('.perspective-node-item').forEach((p) => { perspektif[p.dataset.id] = p; });

                let paths = '';
                if (rev) {
                    Object.values(perspektif).forEach((p) => {
                        const kiri = p.querySelector('.dot-p-left');
                        if (kiri) paths += kurva(titik(rev), titik(kiri), p.dataset.color, 2.5, false);
                    });
                }

                document.querySelectorAll('.dept-node-item').forEach((d) => {
                    const masuk = d.querySelector('.dot-d-left');
                    if (!masuk) return;
                    const tujuan = titik(masuk);
                    const gambar = (daftar, putus) => (daftar || '').split('|').filter(Boolean).forEach((id) => {
                        const p = perspektif[id];
                        const kanan = p && p.querySelector('.dot-p-right');
                        if (kanan) paths += kurva(titik(kanan), tujuan, putus ? '#94a3b8' : p.dataset.color, putus ? 1.2 : 2, putus);
                    });
                    gambar(d.dataset.potential, true);
                    gambar(d.dataset.links, false);
                });

                svg.innerHTML = paths;
            }

            window.bscGambarWiring = gambarWiring;
            setTimeout(gambarWiring, 100);
            setTimeout(gambarWiring, 400);

            if (!window.__bscWiringTerdaftar) {
                window.__bscWiringTerdaftar = true;
                window.addEventListener('resize', () => window.bscGambarWiring && window.bscGambarWiring());
                const daftarkan = () => window.Livewire && window.Livewire.hook('morphed', () => requestAnimationFrame(() => window.bscGambarWiring && window.bscGambarWiring()));
                window.Livewire ? daftarkan() : document.addEventListener('livewire:init', daftarkan);
            }
        })();
    </script>
</div>
