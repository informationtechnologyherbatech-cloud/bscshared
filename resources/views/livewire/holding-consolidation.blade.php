<div>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $skor = function (?float $n) {
            if ($n === null) {
                return '<span class="text-muted">—</span>';
            }
            $status = \App\Support\ScoreStatus::for($n);

            return '<span class="font-weight-bold" style="color:'.e(\App\Support\ScoreStatus::color($status)).'">'.e(number_format($n, 1, ',', '.')).'</span>';
        };
        $g = $data['group'];
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-layer-group mr-2 text-teal"></i>Konsolidasi Holding</h1>
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
                            <p>Skor puncak grup {{ $period }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-mountain"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{!! $g['f1'] === null ? '—' : e(number_format($g['f1'], 1, ',', '.')) !!}</h3>
                            <p>F1 grup — revenue setelah eliminasi</p>
                        </div>
                        <div class="icon"><i class="fas fa-bullseye"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3>{!! $g['f2'] === null ? '—' : e(number_format($g['f2'], 1, ',', '.')) !!}</h3>
                            <p>F2 grup — {{ $g['f2_weighting'] === 'revenue' ? 'dibobot target revenue entitas' : 'rata-rata entitas' }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>

            {{-- Per entitas --}}
            <div class="card card-teal card-outline">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-building mr-1"></i> Skor per entitas — {{ $period }}</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Entitas</th>
                                <th class="text-right">Target revenue YTD</th>
                                <th class="text-right">Realisasi YTD</th>
                                <th class="text-center">F1</th>
                                <th class="text-center">F2</th>
                                <th class="text-center">Skor puncak</th>
                                <th class="text-center">Sasaran mutu</th>
                                <th class="text-center">KPI Lolos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['entities'] as $b)
                                <tr>
                                    <td>
                                        <strong>{{ $b['entity']->name }}</strong>
                                        <small class="d-block text-muted">{{ $b['entity']->legal_name }} · {{ $b['entity']->industryLabel() }}</small>
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
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td>Σ entitas (kotor)</td>
                                <td class="text-right">{{ $rp($g['revenue_target_gross']) }}</td>
                                <td class="text-right">{{ $rp($g['revenue_actual_gross']) }}</td>
                                <td colspan="5"></td>
                            </tr>
                            <tr class="text-danger">
                                <td>− Eliminasi antarentitas</td>
                                <td class="text-right">{{ $rp($g['elimination_planned']) }}</td>
                                <td class="text-right">{{ $rp($g['elimination_actual']) }}</td>
                                <td colspan="5" class="small text-muted">rencana dikurangkan dari target, realisasi dari realisasi</td>
                            </tr>
                            <tr class="font-weight-bold">
                                <td>Grup (bersih)</td>
                                <td class="text-right">{{ $rp($g['revenue_target_net']) }}</td>
                                <td class="text-right">{{ $rp($g['revenue_actual_net']) }}</td>
                                <td class="text-center">{!! $skor($g['f1']) !!}</td>
                                <td class="text-center">{!! $skor($g['f2']) !!}</td>
                                <td class="text-center h6 mb-0">{!! $skor($g['apex']) !!}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Skor tiap entitas dihitung dengan mesin yang sama seperti Piramida BSC entitas itu. F1 grup = realisasi bersih ÷ target
                    bersih (kumulatif, maks 100). F2 grup = F2 entitas dibobot target revenue YTD-nya — rasio laporan konsolidasi penuh
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
                                <input type="number" min="0" step="any" wire:model="form.planned_amount" class="form-control form-control-sm text-right @error('form.planned_amount') is-invalid @enderror">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="small">Realisasi (Rp)</label>
                                <input type="number" min="0" step="any" wire:model="form.actual_amount" class="form-control form-control-sm text-right @error('form.actual_amount') is-invalid @enderror">
                                @error('form.actual_amount') <span class="invalid-feedback">{{ $message }}</span> @enderror
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
