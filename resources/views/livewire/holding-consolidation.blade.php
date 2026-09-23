<div>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $skor = function (?float $n) {
            if ($n === null) {
                return '<span class="text-muted">—</span>';
            }
            $status = score_status($n);

            return '<span class="font-weight-bold" style="color:'.e(score_color($status)).'">'.e(number_format($n, 1, ',', '.')).'</span>';
        };
        $g = $data['group'];
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-city mr-2 text-teal"></i>Konsolidasi Holding</h1>
                    <small class="text-muted">
                        Erhanesia Mulia Corpora — skor keempat entitas dengan skala yang sama (0–100), dan revenue grup setelah
                        eliminasi penjualan antarentitas.
                    </small>
                </div>
                <div class="col-sm-4 text-sm-right mt-2 mt-sm-0">
                    <label for="periodeKonsol" class="mr-2 font-weight-bold">Periode:</label>
                    <input type="month" wire:model.live="period" id="periodeKonsol" class="form-control form-control-sm d-inline-block" style="width:170px">
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

            {{-- Ringkasan grup --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="small-box bg-teal">
                        <div class="inner">
                            <h3>{!! $g['apex'] === null ? '—' : e(number_format($g['apex'], 1, ',', '.')) !!}</h3>
                            <p>Skor puncak grup {{ $period }} @if ($g['unreachable'])<small class="d-block" title="Entitas yang belum terbaca tidak ikut dihitung">belum lengkap</small>@endif</p>
                        </div>
                        <div class="icon"><i class="fas fa-mountain"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{!! $g['f1'] === null ? '—' : e(number_format($g['f1'], 1, ',', '.')) !!}</h3>
                            <p>F1 grup — revenue setelah eliminasi @if ($g['unreachable'])<small class="d-block" title="Entitas yang belum terbaca tidak ikut dihitung">belum lengkap</small>@endif</p>
                        </div>
                        <div class="icon"><i class="fas fa-bullseye"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{!! $g['f2'] === null ? '—' : e(number_format($g['f2'], 1, ',', '.')) !!}</h3>
                            <p>F2 grup — {{ $g['f2_weighting'] === 'revenue' ? 'dibobot target revenue entitas' : 'rata-rata entitas' }} @if ($g['unreachable'])<small class="d-block" title="Entitas yang belum terbaca tidak ikut dihitung">belum lengkap</small>@endif</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>

            {{-- Per entitas --}}
            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-building mr-1"></i> Skor per entitas — {{ $period }}</h3>
                    <div class="d-flex align-items-center">
                        @if ($data['group']['fetched_at'])
                            <small class="text-muted mr-2">Diambil {{ \Illuminate\Support\Carbon::parse($data['group']['fetched_at'])->diffForHumans() }}</small>
                        @endif
                        <button type="button" wire:click="refreshSummaries" class="btn btn-sm btn-ghost" title="Ambil ulang dari sumber tiap entitas">
                            <i class="fas fa-rotate mr-1"></i> Segarkan
                        </button>
                    </div>
                </div>
                @if ($data['group']['unreachable'])
                    <div class="alert alert-warning m-3 mb-0 py-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        Sumber data <strong>{{ implode(', ', $data['group']['unreachable']) }}</strong> tidak terjangkau, jadi angka grup belum lengkap.
                        Periksa sambungan atau kunci API entitas tersebut.
                        @if (($data['group']['eliminations_skipped'] ?? 0) > 0)
                            {{ $data['group']['eliminations_skipped'] }} baris eliminasi yang melibatkan entitas itu ikut dikesampingkan,
                            agar revenue grup tidak dikurangi oleh penjualan yang revenuenya sendiri belum terhitung.
                        @endif
                    </div>
                @endif
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Entitas</th>
                                <th>Sumber data</th>
                                <th class="text-right">Target revenue YTD</th>
                                <th class="text-right">Realisasi YTD</th>
                                <th class="text-center">F1</th>
                                <th class="text-center">F2</th>
                                <th class="text-center">Skor puncak</th>
                                <th class="text-center">Sasaran mutu</th>
                                <th class="text-center">KPI Lolos</th>
                                <th class="text-center">Telusur</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['entities'] as $b)
                                <tr>
                                    <td>
                                        <strong>{{ $b['entity']->name }}</strong>
                                        <small class="d-block text-muted">{{ $b['entity']->legal_name }} · {{ $b['entity']->industryLabel() }}</small>
                                    </td>
                                    <td>
                                        @if ($b['status'] === 'ok')
                                            <span class="badge badge-light border"><i class="fas fa-plug mr-1"></i>{{ $b['source_label'] }}</span>
                                        @else
                                            <span class="badge badge-danger" title="{{ $b['message'] }}"><i class="fas fa-link-slash mr-1"></i>tidak terjangkau</span>
                                            <small class="d-block text-muted">{{ $b['source_label'] }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $b['revenue_target'] > 0 ? $rp($b['revenue_target']) : '—' }}</td>
                                    <td class="text-right">{{ $b['revenue_target'] > 0 || $b['revenue_actual'] > 0 ? $rp($b['revenue_actual']) : '—' }}</td>
                                    <td class="text-center">{!! $skor($b['f1']) !!}</td>
                                    <td class="text-center">{!! $skor($b['f2']) !!}</td>
                                    <td class="text-center h6 mb-0">{!! $skor($b['apex']) !!}</td>
                                    <td class="text-center">
                                        {!! $skor($b['objective_score']) !!}
                                        <small class="d-block text-muted">{{ $b['objectives'] }} sasaran</small>
                                    </td>
                                    <td class="text-center">{{ $b['kpi_approved'] }} / {{ $b['kpi_total'] }}</td>
                                    <td class="text-center">
                                        @if ($b['ratios'] || $b['units'])
                                            <button type="button" class="btn btn-ghost btn-sm" data-toggle="collapse"
                                                    data-target="#telusur-{{ $b['entity']->code }}" title="Rasio & unit kerja entitas ini">
                                                <i class="fas fa-magnifying-glass-plus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($b['ratios'] || $b['units'])
                                    <tr class="collapse bg-light" id="telusur-{{ $b['entity']->code }}">
                                        <td colspan="10">
                                            <div class="row">
                                                <div class="col-lg-7">
                                                    <h6 class="font-weight-bold"><i class="fas fa-percent mr-1"></i> Rasio keuangan {{ $b['entity']->code }}</h6>
                                                    <table class="table table-sm mb-3">
                                                        <thead><tr><th>Rasio</th><th class="text-right">Target</th><th class="text-right">Realisasi</th><th class="text-center">Capaian</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($b['ratios'] as $r)
                                                                <tr>
                                                                    <td>{{ $r['code'] ?? '?' }} — {{ $r['name'] ?? '' }}</td>
                                                                    <td class="text-right text-muted">{{ ratio_format($r['target'] ?? null, $r['unit'] ?? '') }}</td>
                                                                    <td class="text-right">{{ ratio_format($r['actual'] ?? null, $r['unit'] ?? '') }}</td>
                                                                    <td class="text-center">{!! $skor($r['achievement'] ?? null) !!}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="col-lg-5">
                                                    <h6 class="font-weight-bold"><i class="fas fa-building-user mr-1"></i> Unit kerja</h6>
                                                    <table class="table table-sm mb-3">
                                                        <thead><tr><th>Unit</th><th class="text-center">Sasaran</th><th class="text-center">Capaian</th></tr></thead>
                                                        <tbody>
                                                            @forelse ($b['units'] as $u)
                                                                <tr>
                                                                    <td>{{ $u['code'] ?? '?' }} — {{ $u['name'] ?? '' }}</td>
                                                                    <td class="text-center">{{ $u['objectives'] ?? 0 }}</td>
                                                                    <td class="text-center">{!! $skor($u['score'] ?? null) !!}</td>
                                                                </tr>
                                                            @empty
                                                                <tr><td colspan="3" class="text-muted">Belum ada sasaran mutu pada periode ini.</td></tr>
                                                            @endforelse
                                                        </tbody>
                                                    </table>
                                                    <small class="text-muted">Hanya ringkasan; isi sasaran mutu & pos akun tetap di entitas.</small>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td>Σ entitas (kotor)</td>
                                <td></td>
                                <td class="text-right">{{ $rp($g['revenue_target_gross']) }}</td>
                                <td class="text-right">{{ $rp($g['revenue_actual_gross']) }}</td>
                                <td colspan="6"></td>
                            </tr>
                            <tr class="text-danger">
                                <td>− Eliminasi antarentitas</td>
                                <td></td>
                                <td class="text-right">{{ $rp($g['elimination_planned']) }}</td>
                                <td class="text-right">{{ $rp($g['elimination_actual']) }}</td>
                                <td colspan="6" class="small text-muted">rencana dikurangkan dari target, realisasi dari realisasi</td>
                            </tr>
                            <tr class="font-weight-bold">
                                <td>Grup (bersih)</td>
                                <td></td>
                                <td class="text-right">{{ $rp($g['revenue_target_net']) }}</td>
                                <td class="text-right">{{ $rp($g['revenue_actual_net']) }}</td>
                                <td class="text-center">{!! $skor($g['f1']) !!}</td>
                                <td class="text-center">{!! $skor($g['f2']) !!}</td>
                                <td class="text-center h6 mb-0">{!! $skor($g['apex']) !!}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Skor tiap entitas dihitung dengan mesin yang sama seperti Piramida BSC entitas itu. F1 grup = realisasi bersih ÷ target
                    bersih (kumulatif, maks 100). F2 grup = F2 entitas dibobot target revenue YTD-nya — entitas yang sumbernya tidak terjangkau tidak ikut menambah total kotor, jadi angka grup pada saat itu belum lengkap. Rasio laporan konsolidasi penuh
                    membutuhkan pos akun konsolidasi, yang belum dicatat. Skor puncak = 0,45 × F1 + 0,55 × F2.
                </div>
            </div>

            {{-- Eliminasi --}}
            <div class="card card-outline card-danger">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-exchange-alt mr-1"></i> Penjualan antarentitas {{ substr($period, 0, 4) }} (s.d. {{ $period }})</h3>
                    @if ($canManage && ! $showForm)
                        <button wire:click="openCreate" class="btn btn-sm btn-outline-danger"><i class="fas fa-plus mr-1"></i> Catat penjualan antarentitas</button>
                    @endif
                </div>

                @if ($showForm)
                    <div class="card-body border-bottom bg-light">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label class="small">Periode</label>
                                <input type="month" wire:model="form.period" class="form-control form-control-sm @error('form.period') is-invalid @enderror">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small">Penjual</label>
                                <select wire:model="form.seller_entity_id" class="form-control form-control-sm @error('form.seller_entity_id') is-invalid @enderror">
                                    <option value="">Pilih…</option>
                                    @foreach ($entities as $e)
                                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.seller_entity_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label class="small">Pembeli</label>
                                <select wire:model="form.buyer_entity_id" class="form-control form-control-sm @error('form.buyer_entity_id') is-invalid @enderror">
                                    <option value="">Pilih…</option>
                                    @foreach ($entities as $e)
                                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                                    @endforeach
                                </select>
                                @error('form.buyer_entity_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small">Rencana (Rp)</label>
                                <x-input-rupiah wire:model="form.planned_amount" class="form-control form-control-sm text-right {{ $errors->has('form.planned_amount') ? 'is-invalid' : '' }}" />
                                @error('form.planned_amount') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small">Realisasi (Rp)</label>
                                <x-input-rupiah wire:model="form.actual_amount" class="form-control form-control-sm text-right {{ $errors->has('form.actual_amount') ? 'is-invalid' : '' }}" />
                                @error('form.actual_amount') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-8 mb-md-0">
                                <label class="small">Keterangan</label>
                                <input type="text" wire:model="form.notes" class="form-control form-control-sm" placeholder="mis. pasokan produk Eyebost Herbatech → Erdigma">
                            </div>
                            <div class="col-md-4 text-right">
                                <button wire:click="cancel" class="btn btn-sm btn-secondary">Batal</button>
                                <button wire:click="save" class="btn btn-sm btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Periode</th>
                                <th>Penjual → Pembeli</th>
                                <th class="text-right">Rencana</th>
                                <th class="text-right">Realisasi</th>
                                <th>Keterangan</th>
                                @if ($canManage) <th class="text-right">Aksi</th> @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['eliminations'] as $e)
                                <tr wire:key="ic-{{ $e->id }}">
                                    <td>{{ $e->period }}</td>
                                    <td>{{ $e->seller?->name }} <i class="fas fa-arrow-right text-muted mx-1"></i> {{ $e->buyer?->name }}</td>
                                    <td class="text-right">{{ $e->planned_amount === null ? '—' : $rp($e->planned_amount) }}</td>
                                    <td class="text-right">{{ $e->actual_amount === null ? '—' : $rp($e->actual_amount) }}</td>
                                    <td class="small text-muted">{{ $e->notes }}</td>
                                    @if ($canManage)
                                        <td class="text-right text-nowrap">
                                            <button wire:click="openEdit({{ $e->id }})" class="btn btn-xs btn-outline-primary" title="Ubah"><i class="fas fa-edit"></i></button>
                                            <button wire:click="delete({{ $e->id }})" wire:confirm="Hapus baris eliminasi ini?" class="btn btn-xs btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-muted py-3">Belum ada penjualan antarentitas tercatat untuk tahun ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Catat setiap penjualan dari satu entitas grup ke entitas grup lain (mis. Herbatech memasok produk ke Erdigma). Penjualan
                    itu sudah masuk revenue penjual, sehingga di tingkat grup harus dikeluarkan agar tidak terhitung dua kali.
                </div>
            </div>
        </div>
    </section>
</div>
