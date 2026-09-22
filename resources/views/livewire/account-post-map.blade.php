<div>
    @php($bisaUbah = auth()->user()?->can('manage ratios'))

    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-table-cells mr-2 text-teal"></i>Peta Pos Akun <small class="text-muted">(Tingkat 3)</small></h1>
            <small class="text-muted">
                {{ $entity?->legal_name ?? 'Entitas aktif' }} — siapa <strong>Pemilik (O)</strong> dan <strong>Kontributor (K)</strong>
                tiap pos akun. Peta ini menentukan rasio mana yang boleh diklaim unit di Cascade KPI.
            </small>
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

            <div class="callout callout-info small">
                <strong>Pemilik (O)</strong> memegang <em>lag measure</em> atas pos akun itu — angkanya masuk KPI Head unit dan
                target rupiahnya dibebankan ke unit ini. <strong>Kontributor (K)</strong> hanya memasang <em>lead/output measure</em>
                yang mempengaruhi pos itu, dengan bobot lebih kecil, tanpa dibebani target rupiah.
                Satu pos akun tepat satu Pemilik, kecuali <strong>PA01 Penjualan</strong>: tiap unit channel memiliki porsinya sendiri.
            </div>

            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-th mr-1"></i> Unit kerja × pos akun</h3>
                    @if ($bisaUbah)
                        <button wire:click="save" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
                    @endif
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-bordered m-0 text-center" style="font-size:.85rem">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-left" style="min-width:170px">Unit kerja</th>
                                @foreach ($posts as $kode => $pos)
                                    <th title="{{ $pos['name'] }}" style="min-width:58px">{{ $kode }}<br><small class="text-muted font-weight-normal">{{ \Illuminate\Support\Str::limit($pos['name'], 12) }}</small></th>
                                @endforeach
                                <th>Σ O</th>
                                <th>Σ K</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cells as $unit => $baris)
                                <tr>
                                    <td class="text-left align-middle">
                                        <code>{{ $unit }}</code>
                                        <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($units[$unit] ?? 'unit nonaktif', 32) }}</small>
                                    </td>
                                    @foreach ($posts as $kode => $pos)
                                        @php($nilai = $baris[$kode] ?? '')
                                        <td class="align-middle p-1 {{ $nilai === 'O' ? 'table-primary' : ($nilai === 'K' ? 'table-info' : '') }}">
                                            @if ($bisaUbah)
                                                <select wire:model.live="cells.{{ $unit }}.{{ $kode }}" class="form-control form-control-sm px-1"
                                                        aria-label="Peran {{ $unit }} pada {{ $kode }}">
                                                    <option value="">—</option>
                                                    <option value="O">O</option>
                                                    <option value="K">K</option>
                                                </select>
                                            @else
                                                <strong>{{ $nilai }}</strong>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="align-middle font-weight-bold">{{ collect($baris)->filter(fn ($v) => $v === 'O')->count() }}</td>
                                    <td class="align-middle">{{ collect($baris)->filter(fn ($v) => $v === 'K')->count() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($posts) + 3 }}" class="text-muted py-4">Belum ada unit kerja aktif. Tambahkan lewat menu Unit Kerja.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <th class="text-left">Pemilik</th>
                                @foreach ($ownerChecks as $kode => $c)
                                    <td class="small {{ $c['ok'] ? 'text-success' : 'text-danger font-weight-bold' }}" title="{{ $c['note'] }}">
                                        {{ count($c['owners']) }}<br>
                                        <i class="fas {{ $c['ok'] ? 'fa-check' : 'fa-exclamation-triangle' }}"></i>
                                    </td>
                                @endforeach
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @php($bermasalah = collect($ownerChecks)->reject(fn ($c) => $c['ok']))
                <div class="card-footer small {{ $bermasalah->isEmpty() ? 'text-success' : 'text-danger' }}">
                    @if ($bermasalah->isEmpty())
                        <i class="fas fa-check-circle mr-1"></i> Semua pos akun sudah punya Pemilik yang tepat.
                    @else
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        @foreach ($bermasalah as $kode => $c)
                            <strong>{{ $kode }}</strong>: {{ $c['note'] }}{{ $c['owners'] ? ' ('.implode(', ', $c['owners']).')' : '' }}@if (! $loop->last) · @endif
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-link mr-1"></i> Rasio yang boleh diklaim tiap unit</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-bordered m-0 text-center" style="font-size:.85rem">
                        <thead class="bg-light">
                            <tr>
                                <th class="text-left" style="min-width:190px">Rasio</th>
                                @foreach (array_keys($cells) as $unit)
                                    <th><code>{{ $unit }}</code></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ratios as $kode => $r)
                                <tr>
                                    <td class="text-left">
                                        <code>{{ $kode }}</code> {{ $r['name'] }}
                                        <small class="d-block text-muted">{{ implode(' · ', array_map(fn ($p, $peran) => $p.' ('.$peran.')', array_keys($ratioPosts[$kode]), $ratioPosts[$kode])) }}</small>
                                    </td>
                                    @foreach (array_keys($cells) as $unit)
                                        <td class="align-middle text-teal">{{ in_array($kode, $claimable[$unit], true) ? '●' : '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td class="text-left">Σ rasio tersambung</td>
                                @foreach (array_keys($cells) as $unit)
                                    <td>{{ count($claimable[$unit]) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    ● = unit adalah Pemilik/Kontributor pada salah satu pos akun pembentuk rasio, sehingga rasio itu boleh dipilih
                    sebagai dampak KPI-nya. P = pembilang, Y = penyebut, P+ / P− = bagian pembilang yang menambah / mengurangi.
                    Target Revenue (REV) boleh diklaim unit yang terpetakan pada PA01 Penjualan.
                </div>
            </div>
        </div>
    </section>
</div>
