<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1><i class="fas fa-bullseye mr-2 text-teal"></i>Target Revenue <small class="text-muted">(Tingkat 1)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — pencapaian kumulatif di sini menjadi
                        <strong>F1</strong> pada skor puncak: 45% × Revenue + 55% × Rasio Keuangan.
                    </small>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <label for="tahunRevenue" class="mr-2 font-weight-bold">Tahun:</label>
                    <select wire:model.live="year" id="tahunRevenue" class="form-control form-control-sm d-inline-block" style="width:110px">
                        @foreach($years as $y)
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

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-stamp mr-1"></i> Target revenue setahun {{ $year }}</h3>
                </div>
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4 form-group mb-md-0">
                            <label class="small">Disahkan direksi (Rp)</label>
                            @can('manage revenue')
                                <x-input-rupiah wire:model.live.debounce.500ms="approvedTarget"
                                       class="form-control {{ $errors->has('approvedTarget') ? 'is-invalid' : '' }}" placeholder="mis. Rp 900.000.000.000" />
                                @error('approvedTarget') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            @else
                                <div>{{ rupiah($approvedTarget) }}</div>
                            @endcan
                        </div>
                        <div class="col-md-4 form-group mb-md-0">
                            <label class="small">Revisi tengah tahun (Rp) <span class="text-muted">— opsional</span></label>
                            @can('manage revenue')
                                <x-input-rupiah wire:model.live.debounce.500ms="revisedTarget"
                                       class="form-control {{ $errors->has('revisedTarget') ? 'is-invalid' : '' }}" placeholder="tidak ada revisi" />
                                @error('revisedTarget') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            @else
                                <div>{{ rupiah($revisedTarget) }}</div>
                            @endcan
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Faktor revisi (revisi ÷ disahkan)</div>
                            <div class="h5 mb-0 font-weight-bold">{{ $revisionFactor !== null ? number_format($revisionFactor, 4, ',', '.') : '1' }}</div>
                        </div>
                    </div>

                    @can('manage revenue')
                        <hr class="my-3">
                        <div class="d-flex flex-wrap align-items-center">
                            <span class="small font-weight-bold mr-2 mb-1">Isi target bulanan dari angka setahun:</span>
                            <button wire:click="phaseEvenly" class="btn btn-outline-info btn-sm mr-1 mb-1">
                                <i class="fas fa-equals mr-1"></i> Bagi rata 12 bulan
                            </button>
                            <button wire:click="phaseBySeason" class="btn btn-outline-info btn-sm mb-1">
                                <i class="fas fa-chart-area mr-1"></i> Ikuti pola musiman {{ (int) $year - 1 }}
                            </button>
                        </div>
                        @error('annualTarget') <div class="text-danger small mt-1"><i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}</div> @enderror
                    @endcan

                    <small class="text-muted d-block mt-2">
                        Langkah: (1) isi target setahun yang disahkan (revisi bila target diubah di tengah tahun) → (2) klik
                        <strong>Bagi rata</strong> atau <strong>Pola musiman</strong> — memakai angka revisi bila ada → (3) periksa tabel bulanan →
                        (4) <strong>Simpan</strong>. Angka setahunnya dapat disusun di
                        <a href="{{ route('revenue-planning', ['year' => $year]) }}">Perencanaan Target</a>.
                        Faktor revisi menyesuaikan target KPI di Cascade KPI: target × (1 + elastisitas × (faktor − 1)).
                    </small>
                </div>
            </div>

            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0"><i class="fas fa-calendar-alt mr-1"></i> Fasing bulanan {{ $year }}</h3>
                    <div class="card-tools">
                        @can('manage revenue')
                            <button wire:click="save" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan</button>
                        @endcan
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Bulan</th>
                                <th class="text-right" style="min-width:170px">Target (Rp)</th>
                                <th class="text-right" style="min-width:170px">Realisasi (Rp)</th>
                                <th class="text-right">Capaian bulan</th>
                                <th class="text-right">Kumulatif target</th>
                                <th class="text-right">Kumulatif realisasi</th>
                                <th class="text-right">Capaian kumulatif (F1)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ringkasan as $bulan => $r)
                                <tr>
                                    <td class="font-weight-bold align-middle">{{ $r['nama'] }}</td>
                                    <td>
                                        @can('manage revenue')
                                            <x-input-rupiah wire:model.live.debounce.500ms="rows.{{ $bulan }}.target"
                                                   class="form-control form-control-sm text-right {{ $errors->has('rows.'.$bulan.'.target') ? 'is-invalid' : '' }}" />
                                        @else
                                            <div class="text-right">{{ rupiah($rows[$bulan]['target']) }}</div>
                                        @endcan
                                    </td>
                                    <td>
                                        @can('manage revenue')
                                            <x-input-rupiah wire:model.live.debounce.500ms="rows.{{ $bulan }}.actual"
                                                   class="form-control form-control-sm text-right {{ $errors->has('rows.'.$bulan.'.actual') ? 'is-invalid' : '' }}"
                                                   placeholder="belum ada" />
                                        @else
                                            <div class="text-right">{{ rupiah($rows[$bulan]['actual']) }}</div>
                                        @endcan
                                    </td>
                                    <td class="text-right align-middle">{{ $r['bulanan'] !== null ? number_format($r['bulanan'], 1, ',', '.').'%' : '—' }}</td>
                                    <td class="text-right align-middle small text-muted">{{ rupiah($r['kum_target']) }}</td>
                                    <td class="text-right align-middle small text-muted">{{ rupiah($r['kum_actual']) }}</td>
                                    <td class="text-right align-middle font-weight-bold">
                                        @if($r['kumulatif'] !== null)
                                            @php($status = score_status($r['kumulatif']))
                                            <span style="color: {{ score_color($status) }}">{{ number_format($r['kumulatif'], 1, ',', '.') }}%</span>
                                        @else
                                            <span class="text-muted font-weight-normal">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td>Total {{ $year }}</td>
                                <td class="text-right">{{ rupiah($totalTarget) }}</td>
                                <td class="text-right">{{ rupiah($totalActual) }}</td>
                                <td colspan="4" class="text-right small text-muted font-weight-normal">
                                    Capaian dibatasi 100% — melebihi target tidak menambah nilai (konvensi BSC).
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>
