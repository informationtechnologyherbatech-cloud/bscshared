<div>
    @php
        $hasilBadge = fn (?string $h) => match (true) {
            $h === null => 'secondary',
            str_starts_with($h, 'LOLOS') => 'success',
            $h === 'REVISI MINOR' => 'warning',
            default => 'danger',
        };
        $statusBadge = ['Lolos' => 'success', 'Revisi' => 'danger', 'Belum diuji' => 'secondary'];
        $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 2, ',', '.').'%';
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-vial mr-2 text-teal"></i>Uji Indikator <small class="text-muted">(Tingkat 4)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — Keuangan menguji bahwa tiap KPI benar-benar menggerakkan rasio
                        keuangan sebelum masuk monitoring. Uji A wajib untuk semua KPI; Uji B untuk KPI Driver.
                    </small>
                </div>
                <div class="col-sm-4 text-sm-right mt-2 mt-sm-0">
                    <label for="tahunUji" class="mr-2 font-weight-bold">Tahun KPI:</label>
                    <select wire:model.live="year" id="tahunUji" class="form-control form-control-sm d-inline-block" style="width:100px">
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

            @if (! $kpi)
                {{-- Daftar KPI --}}
                <div class="card card-teal card-outline">
                    <div class="card-header">
                        <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> KPI cascade {{ $year }}</h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-hover m-0" style="font-size:.85rem">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Sasaran kerja</th>
                                    <th>Jenis</th>
                                    <th>Dampak</th>
                                    <th class="text-center">Uji A</th>
                                    <th class="text-center">Uji B</th>
                                    <th class="text-center">Status validasi</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($nodes as $n)
                                    @php($r = $n['row'])
                                    <tr wire:key="uji-{{ $r->id }}">
                                        <td style="padding-left: {{ 0.5 + $n['depth'] * 1.25 }}rem"><code>{{ $r->code }}</code></td>
                                        <td>{{ $r->objective }} <small class="text-muted d-block">{{ $r->position }}{{ $r->brand ? ' · '.$r->brand : '' }}</small></td>
                                        <td class="small">{{ $r->kpi_type }}</td>
                                        <td class="small">{{ $r->ratio_code ? $r->ratio_code.' · '.$r->post_code : '—' }}</td>
                                        <td class="text-center">
                                            @if ($r->test?->uji_a_result)
                                                <span class="badge badge-{{ $hasilBadge($r->test->uji_a_result) }}">{{ $r->test->uji_a_result }}</span>
                                            @else
                                                <span class="text-muted small">belum</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($r->test?->uji_b_result)
                                                <span class="badge badge-{{ $hasilBadge($r->test->uji_b_result) }}">{{ $r->test->uji_b_result }}</span>
                                            @else
                                                <span class="text-muted small">{{ $r->isGuardrail() ? 'tidak perlu' : 'belum' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="badge badge-{{ $statusBadge[$r->validation_status] ?? 'secondary' }}">{{ $r->validation_status }}</span></td>
                                        <td class="text-right">
                                            <button wire:click="select({{ $r->id }})" class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-vial mr-1"></i>{{ $r->test ? 'Buka' : 'Uji' }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada KPI {{ $year }}. Susun dulu di menu Cascade KPI.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                @php($uA = $result['ujiA'])
                @php($uB = $result['ujiB'])
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <div>
                        <button wire:click="closeTest" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left mr-1"></i> Daftar KPI</button>
                        <code class="h5">{{ $kpi->code }}</code>
                        <span class="h5">{{ $kpi->objective }}</span>
                        <small class="text-muted d-block d-md-inline ml-md-2">{{ $kpi->unit_code }} · {{ $kpi->level }} · {{ $kpi->kpi_type }}
                            @if ($kpi->ratio_code) · klaim <strong>{{ $kpi->ratio_code }}</strong> {{ $impactName($kpi->ratio_code) }} lewat {{ $kpi->post_code }} @endif
                        </small>
                    </div>
                    <span class="badge badge-{{ $statusBadge[$kpi->validation_status] ?? 'secondary' }} p-2">Status: {{ $kpi->validation_status }}</span>
                </div>

                <div class="row">
                    {{-- Uji A --}}
                    <div class="col-xl-5">
                        <div class="card card-outline card-info">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title font-weight-bold">Uji A — logika sebab-akibat</h3>
                                <span class="badge badge-{{ $hasilBadge($uA) }} p-2">{{ $uA ?? 'Belum lengkap' }}</span>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm m-0">
                                    @foreach ($questions as $q => $teks)
                                        @php($auto = in_array($q, $autoQuestions, true))
                                        @php($wajib = ! $kpi->isGuardrail() || in_array($q, [5, 7, 8], true))
                                        <tr class="{{ $wajib ? '' : 'text-muted' }}">
                                            <td class="align-middle" style="width:40px"><strong>Q{{ $q }}</strong></td>
                                            <td class="align-middle small">
                                                {{ $teks }}
                                                @if ($auto) <span class="badge badge-light border">otomatis</span> @endif
                                                @if (! $wajib) <span class="badge badge-light border">tidak wajib (guardrail)</span> @endif
                                            </td>
                                            <td class="align-middle text-right" style="width:130px">
                                                @if ($auto)
                                                    <span class="{{ $result['answers'][$q] ? 'text-success' : 'text-danger' }} font-weight-bold">{{ $result['answers'][$q] ? 'Ya' : 'Tidak' }}</span>
                                                @else
                                                    <select wire:model.live="answers.{{ $q }}" class="form-control form-control-sm" @disabled(! $canTest) aria-label="Jawaban Q{{ $q }}">
                                                        <option value="">—</option>
                                                        <option value="ya">Ya</option>
                                                        <option value="tidak">Tidak</option>
                                                    </select>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                            <div class="card-footer small text-muted">
                                Driver harus 8 Ya (7 Ya = revisi minor); Guardrail cukup Q5, Q7, Q8.
                                Jawaban otomatis dihitung dari Peta Pos Akun &amp; Cascade KPI — perbaiki di sana bila Tidak.
                                @if ($result['row']['issues'])
                                    <ul class="mb-0 pl-3 text-danger">
                                        @foreach ($result['row']['issues'] as $isu)
                                            <li>{{ $isu }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Uji B masukan --}}
                    <div class="col-xl-7">
                        <div class="card card-outline card-warning">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="card-title font-weight-bold">Uji B — simulasi dampak ke rasio</h3>
                                @if ($uB)
                                    <span class="badge badge-{{ $hasilBadge($uB['result']) }} p-2">{{ $uB['result'] }}</span>
                                @endif
                            </div>
                            <div class="card-body">
                                @if ($result['ujiBReason'])
                                    <div class="text-muted small mb-2"><i class="fas fa-info-circle mr-1"></i>{{ $result['ujiBReason'] }}</div>
                                @endif
                                @if ($kpi->ratio_code)
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label class="small">Baseline (periode pos akun)</label>
                                            <select wire:model.live="period" class="form-control form-control-sm" @disabled(! $canTest)>
                                                @forelse ($periods as $p)
                                                    <option value="{{ $p }}">{{ $p }}</option>
                                                @empty
                                                    <option value="">belum ada</option>
                                                @endforelse
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label class="small">Perbaikan KPI disimulasikan (%)</label>
                                            <input type="number" step="any" wire:model.live.debounce.500ms="improvement" class="form-control form-control-sm text-right" @disabled(! $canTest)>
                                        </div>
                                        <div class="form-group col-md-4 small text-muted d-flex align-items-end">
                                            Target rasio: tahun {{ $kpi->year }} (Katalog Rasio).
                                        </div>
                                    </div>
                                    <div class="table-responsive" style="max-height:330px">
                                        <table class="table table-sm table-bordered m-0" style="font-size:.8rem">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Pos akun</th>
                                                    <th style="width:95px">Koefisien</th>
                                                    <th class="text-right">Δ pos</th>
                                                    <th class="text-right">Baseline</th>
                                                    <th class="text-right">Skenario</th>
                                                    <th>Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($posts as $kode => $pos)
                                                    @php($u = $uB['used'][$kode] ?? null)
                                                    <tr class="{{ ($coefficients[$kode] ?? '') !== '' && (float) $coefficients[$kode] != 0 ? 'table-warning' : '' }}">
                                                        <td><code>{{ $kode }}</code> {{ \Illuminate\Support\Str::limit($pos['name'], 26) }}</td>
                                                        <td class="p-1">
                                                            <input type="number" step="any" wire:model.live.debounce.500ms="coefficients.{{ $kode }}"
                                                                   class="form-control form-control-sm text-right px-1" placeholder="0" @disabled(! $canTest)
                                                                   aria-label="Koefisien {{ $kode }}">
                                                        </td>
                                                        <td class="text-right">{{ $u ? $pct($u['delta_pct']) : '—' }}</td>
                                                        <td class="text-right text-muted">{{ $u && $u['baseline'] !== null ? number_format($u['baseline'], 0, ',', '.') : '—' }}</td>
                                                        <td class="text-right">{{ $u && $u['scenario'] !== null ? number_format($u['scenario'], 0, ',', '.') : '—' }}</td>
                                                        <td class="p-1">
                                                            <input type="text" wire:model="coefNotes.{{ $kode }}" class="form-control form-control-sm" @disabled(! $canTest)
                                                                   aria-label="Keterangan {{ $kode }}">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Koefisien transmisi = berapa % pos akun bergerak tiap 1% KPI bergerak (− = menurunkan), ditetapkan bersama unit.
                                        Contoh: ad cost ratio membaik → biaya iklan (PA03) turun, koefisien −0,3.
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                @if ($uB)
                    <div class="row">
                        <div class="col-xl-8">
                            <div class="card card-outline card-secondary">
                                <div class="card-header">
                                    <h3 class="card-title font-weight-bold">Dampak ke 19 rasio</h3>
                                </div>
                                <div class="card-body p-0 table-responsive">
                                    <table class="table table-sm table-hover m-0" style="font-size:.8rem">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Rasio</th>
                                                <th class="text-right">Baseline</th>
                                                <th class="text-right">Skenario</th>
                                                <th class="text-right">Δ %</th>
                                                <th class="text-right">Target</th>
                                                <th class="text-center">Skor</th>
                                                <th class="text-right">Tertimbang</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($uB['rows'] as $r)
                                                <tr class="{{ $r['claimed'] ? 'table-info font-weight-bold' : '' }}">
                                                    <td><code>{{ $r['code'] }}</code> {{ $r['name'] }} @if ($r['claimed']) <span class="badge badge-info">diklaim</span> @endif</td>
                                                    <td class="text-right">{{ \App\Support\Bsc\RatioLibrary::format($r['baseline'], $r['unit']) }}</td>
                                                    <td class="text-right">{{ \App\Support\Bsc\RatioLibrary::format($r['scenario'], $r['unit']) }}</td>
                                                    <td class="text-right {{ ($r['delta_pct'] ?? 0) > 0 ? 'text-success' : (($r['delta_pct'] ?? 0) < 0 ? 'text-danger' : 'text-muted') }}">
                                                        {{ $r['delta_pct'] === null ? '—' : $pct($r['delta_pct']) }}
                                                    </td>
                                                    <td class="text-right text-muted">{{ \App\Support\Bsc\RatioLibrary::format($r['target'], $r['unit']) }}</td>
                                                    <td class="text-center">{{ $r['rubric_baseline'] ?? '—' }} → {{ $r['rubric_scenario'] ?? '—' }}</td>
                                                    <td class="text-right">{{ $r['weighted_baseline'] ?? '—' }} → {{ $r['weighted_scenario'] ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <div class="card card-outline card-{{ $uB['result'] === 'LOLOS' ? 'success' : 'danger' }}">
                                <div class="card-header"><h3 class="card-title font-weight-bold">Kesimpulan Uji B</h3></div>
                                <div class="card-body p-0">
                                    <table class="table table-sm m-0 small">
                                        <tr><td>Δ {{ $uB['claimed'] === 'REV' ? 'Penjualan' : 'rasio yang diklaim' }}</td>
                                            <td class="text-right font-weight-bold">{{ $uB['claimed_delta'] === null ? '—' : number_format($uB['claimed_delta'], $uB['claimed'] === 'REV' ? 0 : 4, ',', '.') }}</td></tr>
                                        <tr><td>Rasio yang diklaim bergerak?</td><td class="text-right">{{ $uB['moved'] ? 'YA' : 'TIDAK — periksa pos akun / koefisien' }}</td></tr>
                                        <tr><td>Arah pergerakan menguntungkan?</td>
                                            <td class="text-right">{{ $uB['favourable'] === null ? 'Rentang — nilai manual' : ($uB['favourable'] ? 'YA' : 'TIDAK') }}</td></tr>
                                        <tr><td>Skor Tingkat 2 (F2)</td>
                                            <td class="text-right">{{ $uB['f2_baseline'] ?? '—' }} → {{ $uB['f2_scenario'] ?? '—' }} ({{ $uB['delta_score'] === null ? '—' : sprintf('%+.2f', $uB['delta_score']) }})</td></tr>
                                        <tr><td>Rasio lain yang ikut bergerak</td><td class="text-right">{{ $uB['others_moved'] }}</td></tr>
                                    </table>
                                </div>
                                <div class="card-footer small">
                                    {{ $uB['result'] === 'LOLOS'
                                        ? 'LOLOS — KPI terbukti menggerakkan rasio yang diklaim ke arah baik.'
                                        : 'REVISI — rasio yang diklaim tidak bergerak / arah salah; periksa pos akun, arah, atau koefisien.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card card-outline card-primary">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="small">Catatan keuangan</label>
                            <textarea wire:model="notes" rows="2" class="form-control form-control-sm" @disabled(! $canTest)></textarea>
                        </div>
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="small mb-2">
                                Status yang dianjurkan:
                                @if ($result['recommended'])
                                    <span class="badge badge-{{ $statusBadge[$result['recommended']] }} p-2">{{ $result['recommended'] }}</span>
                                @else
                                    <span class="text-muted">lengkapi {{ $uA === null ? 'Uji A' : 'Uji B' }} dulu</span>
                                @endif
                                @if ($kpi->test?->tested_at)
                                    <span class="text-muted ml-2">· terakhir diuji {{ $kpi->test->tested_at->format('d/m/Y H:i') }}{{ $kpi->test->tester ? ' oleh '.$kpi->test->tester->name : '' }}</span>
                                @endif
                            </div>
                            @if ($canTest)
                                <div class="mb-2">
                                    <button wire:click="save" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan hasil uji</button>
                                    <button wire:click="applyStatus('Lolos')" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i> Tetapkan Lolos</button>
                                    <button wire:click="applyStatus('Revisi')" class="btn btn-outline-danger btn-sm"><i class="fas fa-undo mr-1"></i> Tetapkan Revisi</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
