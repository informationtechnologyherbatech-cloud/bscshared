<div>
    @php($bisaUbah = auth()->user()?->can('manage ratios'))

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1><i class="fas fa-scale-balanced mr-2 text-teal"></i>Katalog Rasio <small class="text-muted">(Tingkat 2)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — pilih rasio yang dipakai, bobotnya, dan target tahunannya.
                        Susunan boleh berbeda antar entitas; skornya tetap F2 berskala 0–100.
                    </small>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <label for="tahunRasio" class="mr-2 font-weight-bold">Target tahun:</label>
                    <select wire:model.live="year" id="tahunRasio" class="form-control form-control-sm d-inline-block" style="width:110px">
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
                <div class="col-lg-6">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale mr-1"></i> Bobot per kelompok</h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm m-0">
                                <thead class="bg-light">
                                    <tr><th>Kelompok</th><th class="text-right">Rasio aktif</th><th class="text-right">Bobot</th><th class="text-right">Acuan</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($groups as $nama => $g)
                                        @php($sesuai = abs($g['weight'] - $g['standard']) < 0.01)
                                        <tr>
                                            <td>{{ $nama }}</td>
                                            <td class="text-right">{{ $g['count'] }}</td>
                                            <td class="text-right font-weight-bold {{ $sesuai ? 'text-success' : 'text-warning' }}">
                                                {{ rtrim(rtrim(number_format($g['weight'], 2, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td class="text-right text-muted">{{ number_format($g['standard'], 0) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-light font-weight-bold">
                                    <tr>
                                        <td colspan="2">Total rasio aktif</td>
                                        <td class="text-right {{ abs($totalWeight - 100) < 0.01 ? 'text-success' : 'text-danger' }}">
                                            {{ rtrim(rtrim(number_format($totalWeight, 2, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="text-right text-muted">100</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">
                            Acuan kelompok mengikuti kesepakatan BSC (30/25/20/15/10). Bobot kelompok yang menyimpang boleh,
                            asalkan disengaja — kuning hanya pengingat. Bila total bukan 100, F2 tetap dihitung dengan normalisasi.
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-check-double mr-1"></i> Cek konsistensi target {{ $year }}</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach ($checks as $c)
                                    <li class="list-group-item py-2 d-flex align-items-start">
                                        @if ($c['ok'] === true)
                                            <i class="fas fa-check-circle text-success mt-1 mr-2"></i>
                                        @elseif ($c['ok'] === false)
                                            <i class="fas fa-times-circle text-danger mt-1 mr-2"></i>
                                        @else
                                            <i class="far fa-circle text-muted mt-1 mr-2"></i>
                                        @endif
                                        <div>
                                            <div class="{{ $c['ok'] === false ? 'font-weight-bold text-danger' : '' }}">{{ $c['label'] }}</div>
                                            <small class="text-muted">{{ $c['ok'] === null ? 'Belum dapat dicek — salah satu target kosong.' : $c['note'] }}</small>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-list-ol mr-1"></i> Rasio, bobot &amp; target {{ $year }}</h3>
                    <div class="card-tools">
                        @if ($bisaUbah)
                            <button wire:click="resetWeights" class="btn btn-outline-secondary btn-sm mb-1"><i class="fas fa-undo mr-1"></i> Bobot usulan</button>
                            <button wire:click="save" class="btn btn-primary btn-sm mb-1"><i class="fas fa-save mr-1"></i> Simpan</button>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-center" style="width:60px">Aktif</th>
                                <th>Kode</th>
                                <th>Rasio</th>
                                <th>Polaritas</th>
                                <th class="text-right" style="min-width:100px">Bobot</th>
                                <th class="text-right" style="min-width:150px">Target {{ $year }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($grupSebelumnya = null)
                            @foreach ($library as $kode => $r)
                                @if ($r['group'] !== $grupSebelumnya)
                                    <tr class="bg-light"><td colspan="6" class="small font-weight-bold text-uppercase text-muted">{{ $r['group'] }}</td></tr>
                                    @php($grupSebelumnya = $r['group'])
                                @endif
                                <tr class="{{ $rows[$kode]['active'] ? '' : 'text-muted' }}">
                                    <td class="text-center align-middle">
                                        <input type="checkbox" wire:model.live="rows.{{ $kode }}.active" @disabled(! $bisaUbah) aria-label="Aktifkan {{ $r['name'] }}">
                                    </td>
                                    <td class="align-middle"><code>{{ $kode }}</code></td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold">{{ $r['name'] }}</div>
                                        <small class="text-muted">{{ $r['formula'] }}</small>
                                    </td>
                                    <td class="align-middle small">{{ $r['polarity'] }}</td>
                                    <td class="align-middle">
                                        @if ($bisaUbah)
                                            <input type="number" min="0" max="100" step="any" wire:model.live.debounce.500ms="rows.{{ $kode }}.weight"
                                                   class="form-control form-control-sm text-right @error('rows.'.$kode.'.weight') is-invalid @enderror">
                                        @else
                                            <div class="text-right">{{ $rows[$kode]['weight'] }}</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if ($bisaUbah)
                                            <div class="input-group input-group-sm">
                                                @if ($r['unit'] === 'Rp')
                                                    <x-input-rupiah wire:model.live.debounce.500ms="rows.{{ $kode }}.target"
                                                           class="form-control text-right {{ $errors->has('rows.'.$kode.'.target') ? 'is-invalid' : '' }}" placeholder="belum ada" />
                                                @else
                                                <input type="number" step="any" wire:model.live.debounce.500ms="rows.{{ $kode }}.target"
                                                       class="form-control text-right @error('rows.'.$kode.'.target') is-invalid @enderror" placeholder="belum ada">
                                                <div class="input-group-append"><span class="input-group-text">{{ $r['unit'] }}</span></div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-right">{{ $rows[$kode]['target'] !== '' ? ratio_format((float) $rows[$kode]['target'], $r['unit']) : '—' }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Target persen ditulis dalam persen (mis. 37 untuk 37%). Target berlaku untuk semua bulan di tahun {{ $year }}.
                    Setelah disimpan, periode {{ $year }} yang sudah punya pos akun dihitung ulang, kecuali periode yang sudah ditutup.
                </div>
            </div>
        </div>
    </section>
</div>
