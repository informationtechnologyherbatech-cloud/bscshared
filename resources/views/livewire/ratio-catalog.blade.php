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
                            <button wire:click="newRatio" class="btn btn-teal btn-sm mb-1"><i class="fas fa-plus mr-1"></i> Tambah rasio</button>
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
                                @if ($bisaUbah)<th class="text-right" style="width:80px">Rumus</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @php($grupSebelumnya = null)
                            @foreach ($library as $kode => $r)
                                @if ($r['group'] !== $grupSebelumnya)
                                    <tr class="bg-light"><td colspan="{{ $bisaUbah ? 7 : 6 }}" class="small font-weight-bold text-uppercase text-muted">{{ $r['group'] }}</td></tr>
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
                                    @if ($bisaUbah)
                                        <td class="align-middle text-right">
                                            <button type="button" wire:click="editRatio('{{ $kode }}')" class="btn btn-xs btn-ghost"
                                                    title="Ubah nama, kelompok, satuan, polaritas, dan rumusnya">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            @unless ($r['builtin'] ?? true)
                                                <span class="badge badge-light border" title="Rasio buatan entitas ini">sendiri</span>
                                            @endunless
                                        </td>
                                    @endif
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

    {{-- Penyunting rasio: modal, supaya tidak perlu menggulung layar ke atas --}}
    @if ($bisaUbah && $editing !== null)
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas {{ $editing === '' ? 'fa-plus' : 'fa-pen-to-square' }}"></i></span>
                        <div>
                            <h5 class="modal-title">{{ $editing === '' ? 'Rasio baru' : 'Ubah rasio '.$editing }}</h5>
                            <small>Rumus yang ditulis di sini adalah rumus yang dipakai menghitung</small>
                        </div>
                        <button type="button" class="modal-close" wire:click="cancelRatio" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    </div>

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label for="rasioKode" class="small font-weight-bold">Kode</label>
                                <input type="text" id="rasioKode" wire:model="formCode" @disabled($editing !== '')
                                       class="form-control form-control-sm text-uppercase @error('formCode') is-invalid @enderror">
                                @error('formCode')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="rasioNama" class="small font-weight-bold">Nama rasio</label>
                                <input type="text" id="rasioNama" wire:model="formName"
                                       class="form-control form-control-sm @error('formName') is-invalid @enderror"
                                       placeholder="mis. Beban pemasaran terhadap penjualan">
                                @error('formName')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label for="rasioSatuan" class="small font-weight-bold">Satuan</label>
                                <select id="rasioSatuan" wire:model="formUnit" class="form-control form-control-sm">
                                    <option value="%">% — persen</option>
                                    <option value="x">x — kali</option>
                                    <option value="hari">hari</option>
                                    <option value="Rp">Rp — rupiah</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="rasioBobot" class="small font-weight-bold">Bobot</label>
                                <input type="number" step="any" min="0" max="100" id="rasioBobot" wire:model="formWeight"
                                       class="form-control form-control-sm text-right @error('formWeight') is-invalid @enderror">
                                @error('formWeight')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="rasioKelompok" class="small font-weight-bold">Kelompok</label>
                                <select id="rasioKelompok" wire:model.live="formGroup"
                                        class="form-control form-control-sm @error('formGroup') is-invalid @enderror">
                                    @foreach ($groupNames as $nama)
                                        <option value="{{ $nama }}">{{ $nama }}</option>
                                    @endforeach
                                    <option value="__baru__">+ Kelompok baru…</option>
                                </select>
                                @error('formGroup')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                            @if ($formGroup === '__baru__')
                                <div class="form-group col-md-4">
                                    <label for="rasioKelompokBaru" class="small font-weight-bold">Nama kelompok baru</label>
                                    <input type="text" id="rasioKelompokBaru" wire:model="formGroupNew"
                                           class="form-control form-control-sm @error('formGroupNew') is-invalid @enderror"
                                           placeholder="mis. Efisiensi">
                                    @error('formGroupNew')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                                </div>
                            @endif
                            <div class="form-group col-md-4">
                                <label for="rasioPolaritas" class="small font-weight-bold">Polaritas — arah yang dianggap baik</label>
                                <select id="rasioPolaritas" wire:model="formPolarity" class="form-control form-control-sm">
                                    <option value="Naik">Naik — makin besar makin baik</option>
                                    <option value="Turun">Turun — makin kecil makin baik</option>
                                    <option value="Rentang">Rentang — baik bila mendekati target</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-2">

                        <div class="form-group">
                            <label for="rasioRumus" class="small font-weight-bold">
                                Rumus
                                <i class="fas fa-circle-question text-muted ml-1"
                                   title="Rumus inilah yang benar-benar dihitung, bukan sekadar keterangan. Pakai kode pos akun, angka, dan tanda + − × ÷ ( )."></i>
                            </label>
                            <input type="text" id="rasioRumus" wire:model.live.debounce.600ms="formExpression"
                                   class="form-control font-monospace @error('formExpression') is-invalid @enderror"
                                   placeholder="(PA01 - PA02) / PA01 * 100" autocomplete="off">
                            @error('formExpression')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror

                            {{-- Rumus yang sama, dibaca dengan nama pos akun --}}
                            @if ($readable !== '')
                                <div class="small mt-2">
                                    <span class="text-muted">Terbaca:</span>
                                    <strong>{{ $readable }}</strong>
                                    <span class="text-muted">— kalimat inilah yang akan tampil di bawah nama rasio.</span>
                                </div>
                            @endif
                        </div>

                        {{-- Daftar kode: klik untuk menyisipkannya ke rumus --}}
                        <div class="form-group">
                            <label class="small font-weight-bold d-block mb-1">
                                Klik untuk menyisipkan ke rumus
                                <span class="text-muted font-weight-normal">— tidak perlu menghafal kodenya</span>
                            </label>
                            {{-- Operator di luar kotak gulung: paling sering dipakai, jadi selalu terlihat --}}
                            <div class="rumus-operator mb-2">
                                @foreach (['+' => 'tambah', '-' => 'kurang', '*' => 'kali', '/' => 'bagi', '(' => 'buka kurung', ')' => 'tutup kurung', '100' => 'untuk persen', '365' => 'hari setahun'] as $tanda => $arti)
                                    <button type="button" class="btn btn-xs btn-ghost rumus-kode-btn" wire:click="insertCode(@js($tanda))" title="{{ $arti }}">
                                        <code>{{ $tanda }}</code>
                                    </button>
                                @endforeach
                            </div>
                            <div class="rumus-kode">
                                @php($grupSekarang = null)
                                @foreach ($codeHelp as $kode => $k)
                                    @if ($k['group'] !== $grupSekarang)
                                        <div class="text-muted text-uppercase small mt-2 mb-1">{{ $k['group'] }}</div>
                                        @php($grupSekarang = $k['group'])
                                    @endif
                                    <button type="button" class="btn btn-xs btn-ghost rumus-kode-btn" wire:click="insertCode(@js($kode))"
                                            title="{{ $k['hint'] }}">
                                        <code>{{ $kode }}</code> {{ \Illuminate\Support\Str::limit($k['name'], 28) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Pratinjau: rumus langsung dicoba dengan angka yang ada --}}
                        <div class="callout callout-info py-2 mb-0">
                            @if ($preview['error'])
                                <span class="text-danger"><i class="fas fa-times-circle mr-1"></i> {{ $preview['error'] }}</span>
                            @elseif ($preview['period'])
                                <div class="small text-muted">Dicoba dengan angka pos akun periode {{ period_label($preview['period']) }}:</div>
                                <div class="font-monospace small">{{ $preview['arithmetic'] }}</div>
                                <div class="h5 mb-0 font-weight-bold">
                                    {{ $preview['value'] === null ? 'belum dapat dihitung — ada pos yang kosong atau penyebutnya nol' : ratio_format($preview['value'], $formUnit) }}
                                </div>
                            @else
                                <span class="text-muted small">Pratinjau muncul setelah rumusnya diisi dan ada pos akun yang sudah terisi.</span>
                            @endif
                        </div>
                    </div>

                    <div class="modal-ft">
                        @if ($editing !== '' && ! ($library[$editing]['builtin'] ?? true))
                            <button type="button" wire:click="deleteRatio('{{ $editing }}')"
                                    data-konfirmasi="Rasio {{ $editing }} dihapus beserta target dan hasil hitungannya."
                                    data-konfirmasi-judul="Hapus rasio" data-konfirmasi-ok="Hapus"
                                    data-konfirmasi-nada="bahaya" class="btn btn-ghost text-danger mr-auto">
                                <i class="fas fa-trash mr-1"></i> Hapus rasio
                            </button>
                        @endif
                        <button type="button" wire:click="cancelRatio" class="btn btn-ghost">Batal</button>
                        <button type="button" wire:click="saveRatio" class="btn btn-teal"><i class="fas fa-save mr-1"></i> Simpan rasio</button>
                    </div>
                </div>
            </div>
        </div>

    @endif
</div>
