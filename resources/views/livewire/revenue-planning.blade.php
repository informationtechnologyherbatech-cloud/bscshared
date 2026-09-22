<div>
    @php
        $rp = fn ($n) => $n === null ? '—' : 'Rp '.number_format((float) $n, 0, ',', '.');
        $pct = fn ($n, $d = 2) => $n === null ? '—' : number_format($n * 100, $d, ',', '.').'%';
        $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
                  '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];
        $kunci = ! $canManage;
        $bu = $r['bottom_up'];
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-drafting-compass mr-2 text-teal"></i>Perencanaan Target Revenue <small class="text-muted">(Tingkat 1)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — menyusun target {{ $year }} dari lima sudut pandang, mengesahkan satu angka,
                        lalu memfasingnya ke 12 bulan. Tahun dasar: <strong>{{ $baseYear }}</strong>.
                    </small>
                </div>
                <div class="col-sm-4 text-sm-right mt-2 mt-sm-0">
                    <label for="tahunRencana" class="mr-2 font-weight-bold">Tahun target:</label>
                    <select wire:model.live="year" id="tahunRencana" class="form-control form-control-sm d-inline-block" style="width:100px">
                        @foreach ($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif

            <div class="row">
                {{-- A --}}
                <div class="col-lg-5">
                    <div class="card card-outline card-info">
                        <div class="card-header"><h3 class="card-title font-weight-bold">A. Estimasi akhir tahun {{ $baseYear }}</h3></div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-7">
                                    <label class="small">Revenue {{ $baseYear }} YTD (Rp)</label>
                                    <input type="number" min="0" step="any" wire:model.live.debounce.500ms="baseYtd" class="form-control form-control-sm text-right @error('baseYtd') is-invalid @enderror"
                                           placeholder="{{ $r['auto_ytd'] !== null ? number_format($r['auto_ytd'], 0, '', '') : 'isi manual' }}" @disabled($kunci)>
                                </div>
                                <div class="form-group col-5">
                                    <label class="small">Bulan berjalan (n)</label>
                                    <input type="number" min="1" max="12" wire:model.live.debounce.500ms="baseMonths" class="form-control form-control-sm text-right @error('baseMonths') is-invalid @enderror"
                                           placeholder="{{ $r['auto_months'] ?: '—' }}" @disabled($kunci)>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="small text-muted">Estimasi = YTD × 12 ÷ n</span>
                                <span class="h5 mb-0 font-weight-bold">{{ $rp($r['estimate']) }}</span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                @if ($r['auto_ytd'] !== null)
                                    Terisi otomatis dari realisasi {{ $baseYear }} di menu Target Revenue ({{ $r['auto_months'] }} bulan). Kosongkan kolom untuk memakai angka otomatis.
                                @else
                                    Belum ada realisasi {{ $baseYear }} di menu Target Revenue — isi YTD &amp; n secara manual.
                                @endif
                            </small>
                        </div>
                    </div>
                </div>

                {{-- B --}}
                <div class="col-lg-7">
                    <div class="card card-outline card-info">
                        <div class="card-header"><h3 class="card-title font-weight-bold">B. Proyeksi statistik {{ $year }} — CAGR &amp; regresi linear</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-sm m-0">
                                <thead class="bg-light"><tr><th>Tahun</th><th class="text-right">Realisasi / estimasi</th><th class="text-right">YoY</th></tr></thead>
                                <tbody>
                                    @foreach ($history as $t => $v)
                                        <tr>
                                            <td class="align-middle">{{ $t }}</td>
                                            <td class="p-1"><input type="number" min="0" step="any" wire:model.live.debounce.500ms="history.{{ $t }}" class="form-control form-control-sm text-right" placeholder="realisasi {{ $t }}" @disabled($kunci) aria-label="Realisasi {{ $t }}"></td>
                                            <td class="text-right align-middle">{{ $pct($r['yoy'][(int) $t] ?? null, 1) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="table-light">
                                        <td>{{ $baseYear }} <small class="text-muted">(estimasi A)</small></td>
                                        <td class="text-right">{{ $rp($r['estimate']) }}</td>
                                        <td class="text-right">{{ $pct($r['yoy'][$baseYear] ?? null, 1) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr><td>CAGR ({{ count($r['series']) > 1 ? array_key_last($r['series']) - array_key_first($r['series']) : 0 }} tahun)</td><td class="text-right font-weight-bold" colspan="2">{{ $pct($r['cagr']) }}</td></tr>
                                    <tr><td>Proyeksi {{ $year }} — CAGR</td><td class="text-right font-weight-bold" colspan="2">{{ $rp($r['cagr_projection']) }}</td></tr>
                                    <tr><td>Proyeksi {{ $year }} — regresi linear</td><td class="text-right font-weight-bold" colspan="2">{{ $rp($r['regression']) }}</td></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- C --}}
            <div class="card card-outline card-teal">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">C. Bottom-up brand × channel — basis {{ $baseYear }} &amp; growth {{ $year }}</h3>
                    @if ($canManage)
                        <div>
                            <button wire:click="addChannel" class="btn btn-sm btn-outline-secondary mb-1"><i class="fas fa-columns mr-1"></i> Channel</button>
                            <button wire:click="addBrand" class="btn btn-sm btn-outline-secondary mb-1"><i class="fas fa-plus mr-1"></i> Brand</button>
                        </div>
                    @endif
                </div>
                <div class="card-body p-0 table-responsive">
                    @if (empty($channels))
                        <div class="p-3 small text-muted">Belum ada channel. Tambahkan channel penjualan entitas ini (mis. distributor, modern trade, ekspor, marketplace).</div>
                    @else
                        <table class="table table-sm table-bordered m-0" style="font-size:.85rem">
                            <thead class="bg-light">
                                <tr>
                                    <th style="min-width:150px">Brand \ Channel</th>
                                    @foreach ($channels as $i => $c)
                                        <th class="p-1" style="min-width:140px">
                                            <div class="input-group input-group-sm">
                                                <input type="text" wire:model.live.debounce.500ms="channels.{{ $i }}" class="form-control" placeholder="nama channel" @disabled($kunci) aria-label="Channel {{ $i + 1 }}">
                                                @if ($canManage)
                                                    <div class="input-group-append">
                                                        <button wire:click="removeChannel({{ $i }})" wire:confirm="Hapus channel ini beserta angkanya?" class="btn btn-outline-danger" title="Hapus channel"><i class="fas fa-times"></i></button>
                                                    </div>
                                                @endif
                                            </div>
                                        </th>
                                    @endforeach
                                    <th class="text-right">Total brand {{ $baseYear }}</th>
                                    <th style="width:100px">Growth {{ $year }} (%)</th>
                                    <th class="text-right">Target brand {{ $year }}</th>
                                    @if ($canManage) <th></th> @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($brands as $bi => $b)
                                    <tr wire:key="brand-{{ $bi }}">
                                        <td class="p-1"><input type="text" wire:model.live.debounce.500ms="brands.{{ $bi }}.name" class="form-control form-control-sm" placeholder="nama brand" @disabled($kunci) aria-label="Nama brand {{ $bi + 1 }}"></td>
                                        @foreach ($channels as $ci => $c)
                                            <td class="p-1"><input type="number" min="0" step="any" wire:model.live.debounce.500ms="brands.{{ $bi }}.cells.{{ $ci }}" class="form-control form-control-sm text-right" @disabled($kunci) aria-label="Basis {{ $b['name'] }} {{ $c }}"></td>
                                        @endforeach
                                        <td class="text-right align-middle">{{ $rp($bu['brands'][$bi]['base'] ?? 0) }}</td>
                                        <td class="p-1"><input type="number" step="any" wire:model.live.debounce.500ms="brands.{{ $bi }}.growth" class="form-control form-control-sm text-right" @disabled($kunci) aria-label="Growth {{ $b['name'] }}"></td>
                                        <td class="text-right align-middle font-weight-bold">{{ $rp($bu['brands'][$bi]['target'] ?? 0) }}</td>
                                        @if ($canManage)
                                            <td class="align-middle text-center"><button wire:click="removeBrand({{ $bi }})" class="btn btn-xs btn-outline-danger" title="Hapus brand"><i class="fas fa-trash"></i></button></td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ count($channels) + 5 }}" class="text-center text-muted py-3">Belum ada brand.</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-light font-weight-bold">
                                <tr>
                                    <td>Basis {{ $baseYear }}</td>
                                    @foreach ($bu['channel_base'] as $v)
                                        <td class="text-right">{{ $rp($v) }}</td>
                                    @endforeach
                                    <td class="text-right">{{ $rp($bu['base']) }}</td>
                                    <td class="text-right">{{ $pct($bu['growth'], 1) }}</td>
                                    <td></td>
                                    @if ($canManage) <td></td> @endif
                                </tr>
                                <tr class="text-teal">
                                    <td>Target {{ $year }} per channel</td>
                                    @foreach ($bu['channel_target'] as $v)
                                        <td class="text-right">{{ $rp($v) }}</td>
                                    @endforeach
                                    <td></td><td></td>
                                    <td class="text-right">{{ $rp($bu['target']) }}</td>
                                    @if ($canManage) <td></td> @endif
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </div>
                <div class="card-footer small {{ $r['base_consistent'] === false ? 'text-danger' : 'text-muted' }}">
                    @if ($r['base_consistent'] === false)
                        <i class="fas fa-exclamation-triangle mr-1"></i> PERIKSA: Σ basis per sel ({{ $rp($bu['base']) }}) berbeda lebih dari 2% dari estimasi bagian A ({{ $rp($r['estimate']) }}).
                    @elseif ($r['base_consistent'] === true)
                        <i class="fas fa-check-circle text-success mr-1"></i> Σ basis {{ $baseYear }} konsisten dengan bagian A.
                    @endif
                    Target per channel menjadi target revenue unit channel di Cascade KPI; target per brand menjadi target PGM tiap brand.
                </div>
            </div>

            <div class="row">
                {{-- D --}}
                <div class="col-xl-7">
                    <div class="card card-outline card-warning">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold">D. Ansoff — inisiatif baru di luar tren</h3>
                            @if ($canManage)
                                <button wire:click="addInitiative" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus mr-1"></i> Inisiatif</button>
                            @endif
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm m-0" style="font-size:.85rem">
                                <thead class="bg-light">
                                    <tr><th>Kuadran</th><th>Inisiatif</th><th class="text-right">Revenue tambahan</th><th style="width:90px">Prob. (%)</th><th class="text-right">Expected value</th>@if ($canManage)<th></th>@endif</tr>
                                </thead>
                                <tbody>
                                    @forelse ($ansoff as $ai => $a)
                                        <tr wire:key="ansoff-{{ $ai }}">
                                            <td class="p-1">
                                                <select wire:model.live="ansoff.{{ $ai }}.quadrant" class="form-control form-control-sm" @disabled($kunci) aria-label="Kuadran">
                                                    @foreach ($quadrants as $k => $label)
                                                        <option value="{{ $k }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="p-1"><input type="text" wire:model="ansoff.{{ $ai }}.initiative" class="form-control form-control-sm" @disabled($kunci) aria-label="Inisiatif"></td>
                                            <td class="p-1"><input type="number" min="0" step="any" wire:model.live.debounce.500ms="ansoff.{{ $ai }}.revenue" class="form-control form-control-sm text-right" @disabled($kunci) aria-label="Revenue tambahan"></td>
                                            <td class="p-1"><input type="number" min="0" max="100" step="any" wire:model.live.debounce.500ms="ansoff.{{ $ai }}.probability" class="form-control form-control-sm text-right" @disabled($kunci) aria-label="Probabilitas"></td>
                                            <td class="text-right align-middle">{{ $rp((is_numeric($a['revenue']) ? (float) $a['revenue'] : 0) * (is_numeric($a['probability']) ? (float) $a['probability'] : 0) / 100) }}</td>
                                            @if ($canManage)
                                                <td class="align-middle"><button wire:click="removeInitiative({{ $ai }})" class="btn btn-xs btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button></td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada inisiatif baru.</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-light font-weight-bold">
                                    <tr><td colspan="4">Total expected value</td><td class="text-right">{{ $rp($r['ansoff_ev']) }}</td>@if ($canManage)<td></td>@endif</tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">Jangan hitung ganda dengan growth di bagian C — isi hanya inisiatif yang benar-benar baru.</div>
                    </div>
                </div>

                {{-- E --}}
                <div class="col-xl-5">
                    <div class="card card-outline card-secondary">
                        <div class="card-header"><h3 class="card-title font-weight-bold">E. SWOT — koreksi forecast</h3></div>
                        <div class="card-body">
                            <div class="form-row">
                                @foreach (['s' => 'Strength', 'w' => 'Weakness', 'o' => 'Opportunity', 't' => 'Threat'] as $k => $label)
                                    <div class="form-group col-6">
                                        <label class="small font-weight-bold">{{ $label }}</label>
                                        <textarea wire:model="swot.{{ $k }}" rows="3" class="form-control form-control-sm" placeholder="satu poin per baris" @disabled($kunci)></textarea>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-group mb-0">
                                <label class="small">Penyesuaian forecast dari SWOT (+/− %)</label>
                                <input type="number" step="any" min="-100" max="100" wire:model.live.debounce.500ms="swotAdjustment" class="form-control form-control-sm text-right" @disabled($kunci)>
                                <small class="text-muted">Mis. −3 bila threat dominan; +2 bila opportunity kuat dan sudah ada bukti pipeline.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="mb-3 text-right">
                    <button wire:click="save" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan perencanaan</button>
                </div>
            @endif

            {{-- F --}}
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title font-weight-bold">F. Rekonsiliasi &amp; pengesahan target {{ $year }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr><th>Metode</th><th class="text-right">Proyeksi {{ $year }}</th><th class="text-right">Selisih vs disahkan</th><th class="text-right">Selisih (%)</th><th>Sifat</th>@if ($canManage)<th></th>@endif</tr>
                        </thead>
                        <tbody>
                            @foreach ($r['methods'] as $k => $m)
                                @php($selisih = $m['value'] !== null && $r['approved'] ? $m['value'] - $r['approved'] : null)
                                <tr>
                                    <td>{{ $m['label'] }}</td>
                                    <td class="text-right font-weight-bold">{{ $rp($m['value']) }}</td>
                                    <td class="text-right {{ ($selisih ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $selisih === null ? '—' : $rp($selisih) }}</td>
                                    <td class="text-right">{{ $selisih === null ? '—' : $pct($selisih / $r['approved']) }}</td>
                                    <td class="small text-muted">{{ $m['nature'] }}</td>
                                    @if ($canManage)
                                        <td class="text-right">
                                            @if ($m['value'] !== null)
                                                <button wire:click="approve('{{ $k }}')" wire:confirm="Sahkan {{ $rp($m['value']) }} sebagai target revenue {{ $year }}?" class="btn btn-xs btn-outline-primary">Sahkan</button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-primary font-weight-bold">
                                <td>TARGET {{ $year }} DISAHKAN DIREKSI</td>
                                <td class="text-right">{{ $rp($r['approved']) }}</td>
                                <td colspan="{{ $canManage ? 4 : 3 }}">
                                    @if ($canManage)
                                        <div class="input-group input-group-sm" style="max-width:360px">
                                            <input type="number" min="0" step="any" wire:model="manualApproval" class="form-control @error('manualApproval') is-invalid @enderror" placeholder="atau ketik angka lain (Rp)">
                                            <div class="input-group-append"><button wire:click="approve('manual')" class="btn btn-primary">Sahkan</button></div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Selisih antarmetode = ruang diskusi risiko. Angka yang disahkan tersimpan sebagai target disahkan di menu Target Revenue
                    (revisi tengah tahun diisi di sana).
                </div>
            </div>

            {{-- G --}}
            <div class="card card-outline card-success">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">G. Fasing bulanan {{ $year }} — indeks musiman dari realisasi {{ $baseYear }}</h3>
                    @if ($canManage)
                        <button wire:click="applyPhasing" wire:confirm="Isi target bulanan {{ $year }} di menu Target Revenue? Target bulanan yang ada akan ditimpa; realisasi tidak berubah."
                                class="btn btn-sm btn-success" @disabled(! $r['approved'])>
                            <i class="fas fa-calendar-check mr-1"></i> Terapkan ke Target Revenue
                        </button>
                    @endif
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm m-0 text-center" style="font-size:.85rem">
                        <thead class="bg-light">
                            <tr><th class="text-left">Bulan</th>@foreach ($bulan as $nama)<th>{{ $nama }}</th>@endforeach<th>Total</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-left">Realisasi {{ $baseYear }}</td>
                                @foreach ($bulan as $k => $nama)
                                    <td>{{ $r['monthly'][$k] === null ? '—' : number_format($r['monthly'][$k] / 1e9, 1, ',', '.') }}</td>
                                @endforeach
                                <td>{{ number_format(array_sum(array_filter($r['monthly'])) / 1e9, 1, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="text-left">Indeks musiman</td>
                                @foreach ($bulan as $k => $nama)
                                    <td>{{ $r['index'] ? $pct($r['index'][$k], 1) : '—' }}</td>
                                @endforeach
                                <td>{{ $r['index'] ? $pct(array_sum($r['index']), 0) : '—' }}</td>
                            </tr>
                            <tr class="font-weight-bold text-teal">
                                <td class="text-left">Target {{ $year }}</td>
                                @foreach ($bulan as $k => $nama)
                                    <td>{{ $r['phasing'] ? number_format($r['phasing'][$k] / 1e9, 1, ',', '.') : '—' }}</td>
                                @endforeach
                                <td>{{ $r['phasing'] ? number_format(array_sum($r['phasing']) / 1e9, 1, ',', '.') : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Angka dalam miliar rupiah. Indeks = realisasi bulan ÷ estimasi akhir tahun {{ $baseYear }}; bulan tanpa realisasi berbagi rata sisa indeks.
                    @if (! $r['index']) Belum ada realisasi {{ $baseYear }}, sehingga fasing dibagi rata 12 bulan. @endif
                    @if (! $r['approved']) Sahkan target di bagian F lebih dulu. @endif
                </div>
            </div>
        </div>
    </section>
</div>
