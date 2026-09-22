<div>
    @php
        $badge = [
            'Tercapai' => 'success',
            'Waspada' => 'warning',
            'Di Bawah Target' => 'danger',
            'Belum Ada Target' => 'info',
            'Belum Lengkap' => 'secondary',
        ];
        $bisaUbah = auth()->user()?->can('manage ratios') && ! $isClosed;
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1><i class="fas fa-file-invoice-dollar mr-2 text-teal"></i>Pos Akun <small class="text-muted">(Tingkat 2)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — 16 pos akun ini diolah menjadi 19 rasio keuangan,
                        lalu skornya menjadi <strong>F2</strong> pada skor puncak.
                    </small>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <label for="periodePos" class="mr-2 font-weight-bold">Periode:</label>
                    <input type="month" wire:model.live="period" id="periodePos" class="form-control form-control-sm d-inline-block" style="width:170px">
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
            @if ($isClosed)
                <div class="alert alert-secondary"><i class="fas fa-lock mr-1"></i> Periode {{ $period }} sudah DITUTUP — pos akun hanya dapat dilihat.</div>
            @endif
            @if (! $hasPeriod)
                <div class="alert alert-light border small mb-3">
                    <i class="fas fa-info-circle mr-1 text-info"></i>
                    Periode {{ $period }} belum dibuat di Piramida BSC. Pos akun tetap dapat disimpan, tetapi hasilnya baru
                    tampil di piramida setelah periode tersebut dibuat.
                </div>
            @endif

            <div class="row">
                {{-- Isian pos akun --}}
                <div class="col-xl-7">
                    <div class="card card-teal card-outline">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-edit mr-1"></i> Isian {{ $period }}</h3>
                            <div class="card-tools">
                                @if ($bisaUbah)
                                    <button wire:click="save" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan &amp; hitung rasio</button>
                                @endif
                            </div>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm m-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width:60px">Kode</th>
                                        <th>Pos akun</th>
                                        <th class="text-right" style="min-width:150px">Saldo awal tahun</th>
                                        <th class="text-right" style="min-width:150px">Nilai / saldo akhir</th>
                                        <th class="text-right">Nilai dipakai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($posts as $kode => $pos)
                                        @php($neraca = $pos['kind'] === \App\Support\Bsc\AccountPosts::NERACA)
                                        <tr>
                                            <td class="align-middle"><code>{{ $kode }}</code></td>
                                            <td class="align-middle">
                                                <div class="font-weight-bold">{{ $pos['name'] }}</div>
                                                <small class="text-muted">
                                                    <span class="badge badge-light border">{{ \App\Support\Bsc\AccountPosts::kindLabel($pos['kind']) }}</span>
                                                    {{ $pos['hint'] }}
                                                </small>
                                            </td>
                                            <td class="align-middle">
                                                @if ($neraca)
                                                    @if ($bisaUbah)
                                                        <x-input-rupiah wire:model.live.debounce.500ms="values.{{ $kode }}.opening"
                                                               class="form-control form-control-sm text-right {{ $errors->has('values.'.$kode.'.opening') ? 'is-invalid' : '' }}" placeholder="opsional" />
                                                    @else
                                                        <div class="text-right">{{ $values[$kode]['opening'] !== '' ? number_format((float) $values[$kode]['opening'], 0, ',', '.') : '—' }}</div>
                                                    @endif
                                                @else
                                                    <div class="text-right text-muted small">—</div>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @if ($bisaUbah)
                                                    @php($hris = in_array($pos['kind'], [\App\Support\Bsc\AccountPosts::HRIS_RATA, \App\Support\Bsc\AccountPosts::HRIS_ALIRAN], true))
                                                    {{-- Jumlah karyawan & jam kerja bukan rupiah: tanpa awalan Rp, tanpa desimal. --}}
                                                    <x-input-rupiah wire:model.live.debounce.500ms="values.{{ $kode }}.amount"
                                                           :prefix="$hris ? '' : 'Rp'" :decimals="$hris ? 0 : 2"
                                                           class="form-control form-control-sm text-right {{ $errors->has('values.'.$kode.'.amount') ? 'is-invalid' : '' }}"
                                                           placeholder="{{ $neraca ? 'saldo akhir' : ($pos['kind'] === \App\Support\Bsc\AccountPosts::HRIS_RATA ? 'rata-rata (orang)' : ($hris ? 'jam kerja YTD' : 'YTD')) }}" />
                                                @else
                                                    <div class="text-right">{{ $values[$kode]['amount'] !== '' ? number_format((float) $values[$kode]['amount'], 0, ',', '.') : '—' }}</div>
                                                @endif
                                            </td>
                                            <td class="text-right align-middle small text-muted">
                                                @if (($hasil['used'][$kode] ?? null) === null)
                                                    —
                                                @elseif (in_array($pos['kind'], [\App\Support\Bsc\AccountPosts::HRIS_RATA, \App\Support\Bsc\AccountPosts::HRIS_ALIRAN], true))
                                                    {{ number_format($hasil['used'][$kode], 0, ',', '.') }}
                                                @else
                                                    {{ rupiah($hasil['used'][$kode]) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Aliran</strong> diisi nilai YTD Januari s.d. bulan {{ $bulan }}, lalu disetahunkan (× 12 ÷ {{ $bulan }}).
                            <strong>Neraca</strong> memakai rata-rata saldo awal tahun &amp; saldo akhir (bila saldo awal kosong, dipakai saldo akhir saja).
                            <strong>HRIS</strong>: jumlah karyawan dipakai apa adanya, jam kerja disetahunkan.
                        </div>
                    </div>
                </div>

                {{-- Hasil --}}
                <div class="col-xl-5">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-calculator mr-1"></i> Skor Tingkat 2 (F2)</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-baseline mb-2">
                                <span class="display-4 font-weight-bold mr-2" style="font-size:2.4rem">
                                    {{ $hasil['f2'] !== null ? number_format($hasil['f2'], 1, ',', '.') : '—' }}
                                </span>
                                <span class="text-muted">/ 100</span>
                            </div>
                            <small class="text-muted d-block mb-3">
                                {{ $hasil['scored'] }} dari {{ $hasil['active'] }} rasio aktif sudah terskor.
                                @if ($hasil['scored'] < $hasil['active'])
                                    Rasio tanpa data atau tanpa target tidak ikut dihitung; bobotnya dinormalisasi.
                                @endif
                            </small>
                            <table class="table table-sm m-0">
                                <thead><tr><th>Kelompok</th><th class="text-right">Bobot</th><th class="text-right">Skor tertimbang</th></tr></thead>
                                <tbody>
                                    @foreach ($hasil['groups'] as $nama => $g)
                                        <tr>
                                            <td>{{ $nama }}</td>
                                            <td class="text-right">{{ number_format($g['weight'], 0) }}</td>
                                            <td class="text-right font-weight-bold">{{ $g['scored_weight'] > 0 ? number_format($g['weighted'], 1, ',', '.') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($bisaUbah)
                                <small class="text-muted d-block mt-2"><i class="fas fa-eye mr-1"></i> Pratinjau dari isian di kiri — tersimpan setelah menekan Simpan.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- 19 rasio --}}
            <div class="card card-outline card-secondary">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-list-ol mr-1"></i> Rasio keuangan hasil hitungan</h3>
                    @can('manage ratios')
                        <a href="{{ route('ratio-catalog', ['year' => substr($period, 0, 4)]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-sliders-h mr-1"></i> Atur rasio, bobot &amp; target
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Kode</th>
                                <th>Rasio</th>
                                <th>Polaritas</th>
                                <th class="text-right">Aktual</th>
                                <th class="text-right">Target</th>
                                <th class="text-right">Capaian</th>
                                <th class="text-right">Rubrik</th>
                                <th class="text-right">Bobot</th>
                                <th class="text-right">Tertimbang</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hasil['rows'] as $r)
                                <tr>
                                    <td><code>{{ $r['code'] }}</code></td>
                                    <td>
                                        <div class="font-weight-bold">{{ $r['name'] }}</div>
                                        <small class="text-muted">{{ $r['group'] }} · {{ $r['formula'] }}</small>
                                    </td>
                                    <td class="small">{{ $r['polarity'] }}</td>
                                    <td class="text-right">{{ \App\Support\Bsc\RatioLibrary::format($r['actual'], $r['unit']) }}</td>
                                    <td class="text-right text-muted">{{ \App\Support\Bsc\RatioLibrary::format($r['target'], $r['unit']) }}</td>
                                    <td class="text-right">{{ $r['achievement'] !== null ? number_format($r['achievement'], 1, ',', '.').'%' : '—' }}</td>
                                    <td class="text-right">{{ $r['rubric'] !== null ? number_format($r['rubric'], 0) : '—' }}</td>
                                    <td class="text-right">{{ rtrim(rtrim(number_format($r['weight'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-right font-weight-bold">{{ $r['weighted'] !== null ? number_format($r['weighted'], 2, ',', '.') : '—' }}</td>
                                    <td><span class="badge badge-{{ $badge[$r['status']] ?? 'secondary' }}">{{ $r['status'] }}</span></td>
                                </tr>
                            @endforeach
                            @if (empty($hasil['rows']))
                                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada rasio aktif untuk entitas ini.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Rubrik: capaian ≥ 90% → 100 · ≥ 80% → 80 · ≥ 75% → 70 · ≥ 65% → 60 · selebihnya 50.
                    Skor tertimbang = rubrik × bobot ÷ 100. Capaian dibatasi 100%.
                </div>
            </div>
        </div>
    </section>
</div>
