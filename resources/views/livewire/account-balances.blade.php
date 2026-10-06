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
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — {{ count($posts) }} pos akun ini diolah menjadi rasio keuangan,
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

            @can('manage ratios')
                {{-- Katalog pos akun: menyesuaikan daftar pos dengan keadaan entitas --}}
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                        <h3 class="card-title font-weight-bold mb-0">
                            <i class="fas fa-sliders-h mr-1"></i> Katalog pos akun
                            <small class="text-muted ml-1">{{ count($catalog) }} pos · {{ count($posts) }} aktif</small>
                        </h3>
                        <button type="button" wire:click="toggleKelola" class="btn btn-sm btn-ghost">
                            <i class="fas fa-chevron-{{ $kelola ? 'up' : 'down' }} mr-1"></i>
                            {{ $kelola ? 'Tutup' : 'Sesuaikan pos akun' }}
                        </button>
                    </div>

                    @if ($kelola)
                        <div class="card-body">
                            <p class="text-muted small">
                                Pos akun inilah yang diisi di bawah dan dipakai rumus rasio. Pos <strong>bawaan</strong> berasal
                                dari metodologi dan dipakai rumus bawaan — namanya boleh disesuaikan, jenis dan kodenya tidak.
                                Pos tambahan bebas dibuat, lalu dipakai di rumus lewat menu
                                <a href="{{ route('ratio-catalog') }}">Katalog Rasio</a>.
                            </p>

                            <button type="button" wire:click="newPost" class="btn btn-sm btn-teal mb-3">
                                <i class="fas fa-plus mr-1"></i> Tambah pos akun
                            </button>

                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:70px">Kode</th>
                                            <th>Pos akun</th>
                                            <th style="width:110px">Jenis</th>
                                            <th style="width:110px">
                                                Sumber
                                                <i class="fas fa-circle-question text-muted"
                                                   title="Dari sistem mana angkanya datang. GL = buku besar akuntansi (General Ledger), masuk lewat Odoo atau unggahan CSV. HRIS = sistem kepegawaian."></i>
                                            </th>
                                            <th style="width:90px">Status</th>
                                            <th class="text-right" style="width:200px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($catalog as $kode => $pos)
                                            <tr class="{{ $pos['active'] ? '' : 'text-muted bg-light' }}">
                                                <td class="align-middle"><code>{{ $kode }}</code></td>
                                                <td class="align-middle">
                                                    <div class="font-weight-bold">{{ $pos['name'] }}</div>
                                                    <small class="text-muted">{{ $pos['hint'] }}</small>
                                                </td>
                                                <td class="align-middle"><span class="badge badge-light border">{{ post_kind_label($pos['kind']) }}</span></td>
                                                <td class="align-middle small">
                                                    <span title="{{ post_source_label($pos['source']) }}">{{ $pos['source'] }}</span>
                                                </td>
                                                <td class="align-middle">
                                                    @if ($pos['active'])
                                                        <span class="badge badge-success">Aktif</span>
                                                    @else
                                                        <span class="badge badge-secondary">Nonaktif</span>
                                                    @endif
                                                    @if ($pos['builtin'] ?? false)
                                                        <span class="badge badge-light border" title="Pos dari metodologi bawaan">Bawaan</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-right">
                                                    <button type="button" wire:click="editPost('{{ $kode }}')" class="btn btn-xs btn-ghost">
                                                        <i class="fas fa-pen mr-1"></i> Ubah
                                                    </button>
                                                    <button type="button" wire:click="togglePost('{{ $kode }}')" class="btn btn-xs btn-ghost">
                                                        {{ $pos['active'] ? 'Nonaktifkan' : 'Aktifkan' }}
                                                    </button>
                                                    @if (! ($pos['builtin'] ?? false))
                                                        <button type="button" wire:click="deletePost('{{ $kode }}')"
                                                                data-konfirmasi="Pos {{ $kode }} dihapus beserta seluruh angkanya di semua periode."
                                                                data-konfirmasi-judul="Hapus pos akun" data-konfirmasi-ok="Hapus"
                                                                data-konfirmasi-nada="bahaya" class="btn btn-xs btn-ghost text-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endcan

            {{-- Dasar baca yang sedang aktif. Pada mode "bulan terpilih saja" seluruh
                 pratinjau di halaman ini — nilai dipakai, rasio, dan F2 — memakai angka
                 bulan itu sendiri, supaya Finance dapat memeriksa satu bulan tanpa
                 tertutup bulan-bulan sebelumnya. Yang TERSIMPAN tetap dasar kumulatif. --}}
            @unless ($kumulatif)
                <div class="alert alert-info">
                    <h6 class="font-weight-bold mb-1">
                        <i class="fas fa-search-dollar mr-1"></i>
                        Memeriksa bulan {{ month_name($period) }} saja
                    </h6>
                    <p class="mb-1 small">
                        Nilai dipakai, rasio, dan F2 di halaman ini dihitung dari angka bulan
                        {{ month_name($period) }} sendiri — bukan Januari s.d. {{ month_name($period) }}.
                        Dipakai untuk memastikan bulan ini sudah betul.
                    </p>
                    <p class="mb-0 small text-muted">
                        Yang <strong>tersimpan</strong> saat menekan Simpan tetap atas dasar kumulatif,
                        karena itulah skor resmi entitas yang dibaca piramida dan konsolidasi holding.
                        Untuk kembali, aktifkan kembali centang kumulatif pada pilihan periode di bilah atas.
                    </p>
                </div>

                @if ($bulanan['needs_previous'] !== [])
                    <div class="alert alert-warning">
                        <h6 class="font-weight-bold mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            {{ count($bulanan['needs_previous']) }} pos aliran belum dapat dinilai per bulan
                        </h6>
                        <p class="mb-1 small">
                            Pos aliran diisi nilai YTD, jadi angka bulan {{ month_name($period) }} adalah
                            selisihnya terhadap <strong>{{ $bulanan['previous_period'] }}</strong> — dan periode
                            itu belum diisi. Angkanya dikosongkan, bukan dikira-kira: memakai YTD apa adanya
                            akan diam-diam kembali ke dasar kumulatif.
                        </p>
                        <p class="mb-0 small">
                            Pos: @foreach ($bulanan['needs_previous'] as $kode)<code>{{ $kode }}</code> {{ post_name($kode) }}@if (! $loop->last), @endif @endforeach
                        </p>
                    </div>
                @endif

                @if ($bulanan['negative'] !== [])
                    <div class="alert alert-danger">
                        <h6 class="font-weight-bold mb-1">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            {{ count($bulanan['negative']) }} pos aliran YTD-nya menyusut
                        </h6>
                        <p class="mb-1 small">
                            YTD {{ $period }} lebih kecil daripada YTD {{ $bulanan['previous_period'] }}, sehingga
                            angka bulan ini negatif. Wajar bila memang ada pembalikan jurnal; bila tidak, salah
                            satu dari kedua bulan itu keliru.
                        </p>
                        <p class="mb-0 small">
                            Pos: @foreach ($bulanan['negative'] as $kode)<code>{{ $kode }}</code> {{ post_name($kode) }}@if (! $loop->last), @endif @endforeach
                        </p>
                    </div>
                @endif
            @endunless

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
                                        <th class="text-right" style="min-width:150px">
                                            Bulan ini
                                            <i class="fas fa-question-circle text-muted"
                                               title="Angka bulan {{ month_name($period) }} saja — untuk pos aliran = YTD bulan ini dikurangi YTD bulan lalu. Inilah angka yang dapat dibandingkan dengan laporan bulanan Finance."></i>
                                        </th>
                                        <th class="text-right" style="min-width:190px">Nilai dipakai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($posts as $kode => $pos)
                                        @php($neraca = post_is_neraca($pos['kind']))
                                        {{-- id baris menjadi sasaran tombol "Ubah" dari penelusuran rasio. --}}
                                        <tr id="pos-{{ $kode }}">
                                            <td class="align-middle"><code>{{ $kode }}</code></td>
                                            <td class="align-middle">
                                                <div class="font-weight-bold">{{ $pos['name'] }}</div>
                                                <small class="text-muted">
                                                    <span class="badge badge-light border">{{ post_kind_label($pos['kind']) }}</span>
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
                                                    @php($hris = post_is_hris($pos['kind']))
                                                    {{-- Jumlah karyawan & jam kerja bukan rupiah: tanpa awalan Rp, tanpa desimal. --}}
                                                    <x-input-rupiah wire:model.live.debounce.500ms="values.{{ $kode }}.amount"
                                                           :prefix="$hris ? '' : 'Rp'" :decimals="$hris ? 0 : 2"
                                                           class="form-control form-control-sm text-right {{ $errors->has('values.'.$kode.'.amount') ? 'is-invalid' : '' }}"
                                                           placeholder="{{ $neraca ? 'saldo akhir' : (post_is_hris_rata($pos['kind']) ? 'rata-rata (orang)' : ($hris ? 'jam kerja YTD' : 'YTD')) }}" />
                                                @else
                                                    <div class="text-right">{{ $values[$kode]['amount'] !== '' ? number_format((float) $values[$kode]['amount'], 0, ',', '.') : '—' }}</div>
                                                @endif
                                            </td>
                                            {{-- Angka bulan ini sendiri. Pos aliran disimpan YTD, jadi angka
                                                 bulannya adalah selisih terhadap bulan lalu; pos neraca sudah
                                                 berupa saldo akhir bulan itu. --}}
                                            <td class="text-right align-middle">
                                                @php($bulanIni = $bulanan['inputs'][$kode]['amount'] ?? null)
                                                @if ($neraca)
                                                    <small class="text-muted">saldo akhir<br>(sudah per bulan)</small>
                                                @elseif (post_is_hris_rata($pos['kind']))
                                                    <small class="text-muted">rata-rata<br>(sudah per bulan)</small>
                                                @elseif (in_array($kode, $bulanan['needs_previous'], true))
                                                    <span class="badge badge-warning" title="Pos aliran disimpan sebagai nilai YTD, sehingga angka bulan ini adalah selisihnya terhadap {{ $bulanan['previous_period'] }}. Periode itu belum diisi.">
                                                        {{ $bulanan['previous_period'] }} belum diisi
                                                    </span>
                                                @elseif ($bulanIni === null)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <div class="font-weight-bold text-nowrap {{ $bulanIni < 0 ? 'text-danger' : '' }}">
                                                        @if (post_is_hris($pos['kind']))
                                                            {{ number_format($bulanIni, 0, ',', '.') }}
                                                        @else
                                                            {{ rupiah($bulanIni) }}
                                                        @endif
                                                    </div>
                                                    @if ($bulanIni < 0)
                                                        <small class="text-danger text-nowrap d-block" title="YTD {{ $period }} lebih kecil daripada YTD {{ $bulanan['previous_period'] }}. Wajar bila ada pembalikan jurnal; periksa bila tidak.">
                                                            <i class="fas fa-exclamation-triangle"></i> YTD menyusut
                                                        </small>
                                                    @endif
                                                @endif
                                            </td>
                                            {{-- Nilai dipakai + asalnya. Dibuat tidak boleh patah ke baris
                                                 berikutnya: "Rp" yang terpisah dari angkanya terbaca berantakan. --}}
                                            <td class="text-right align-middle">
                                                {{-- Pada mode "bulan terpilih saja" yang dipakai rumus adalah angka
                                                     bulan itu, jadi asal angkanya pun harus diceritakan dari situ. --}}
                                                @php($asalAmount = $kumulatif ? ($values[$kode]['amount'] ?? null) : $bulanIni)
                                                @php($asalOpening = $kumulatif ? ($values[$kode]['opening'] ?? null) : ($bulanan['inputs'][$kode]['opening'] ?? null))
                                                @php($asal = post_derivation($kode, $asalAmount, $asalOpening, $bulan, ! $kumulatif))
                                                @if (($hasil['used'][$kode] ?? null) === null)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <div class="font-weight-bold text-nowrap">
                                                        @if (post_is_hris($pos['kind']))
                                                            {{ number_format($hasil['used'][$kode], 0, ',', '.') }}
                                                        @else
                                                            {{ rupiah($hasil['used'][$kode]) }}
                                                        @endif
                                                    </div>
                                                    <small class="text-muted text-nowrap d-block" title="{{ $asal['arithmetic'] }}">
                                                        {{ $asal['label'] }}
                                                    </small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer small text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            @if ($kumulatif)
                                <strong>Aliran</strong> diisi nilai YTD Januari s.d. bulan {{ $bulan }}, lalu disetahunkan (× 12 ÷ {{ $bulan }}).
                                <strong>Neraca</strong> memakai rata-rata saldo awal tahun &amp; saldo akhir (bila saldo awal kosong, dipakai saldo akhir saja).
                                <strong>HRIS</strong>: jumlah karyawan dipakai apa adanya, jam kerja disetahunkan.
                            @else
                                Yang dinilai <strong>bulan {{ month_name($period) }} saja</strong>.
                                <strong>Aliran</strong> memakai angka bulan ini (YTD {{ $period }} − YTD {{ $bulanan['previous_period'] ?? '—' }}), lalu disetahunkan (× 12).
                                <strong>Neraca</strong> memakai rata-rata saldo akhir bulan lalu &amp; saldo akhir bulan ini.
                                <strong>HRIS</strong>: jumlah karyawan dipakai apa adanya.
                                Isiannya tetap diisi YTD seperti biasa — yang berubah hanya cara membacanya.
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Hasil --}}
                <div class="col-xl-5">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-calculator mr-1"></i> Skor Tingkat 2 (F2)</h3>
                            {{-- Dasar perhitungan ditulis di samping angkanya: 72 atas dasar
                                 kumulatif dan 72 atas dasar satu bulan bukan angka yang sama. --}}
                            <span class="badge {{ $kumulatif ? 'badge-light border' : 'badge-info' }} ml-2">
                                {{ $kumulatif ? 'kumulatif s.d. '.month_name($period) : month_name($period).' saja' }}
                            </span>
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

            {{-- rasio hasil hitungan --}}
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
                                    <td class="text-right">{{ ratio_format($r['actual'], $r['unit']) }}</td>
                                    <td class="text-right text-muted">{{ ratio_format($r['target'], $r['unit']) }}</td>
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

    {{-- Penyunting pos akun: modal, supaya tidak perlu menggulung layar --}}
    @if ($editing !== null)
        @php($bawaan = $editing !== '' && ($catalog[$editing]['builtin'] ?? false))
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas {{ $editing === '' ? 'fa-plus' : 'fa-pen-to-square' }}"></i></span>
                        <div>
                            <h5 class="modal-title">{{ $editing === '' ? 'Pos akun baru' : 'Ubah pos '.$editing }}</h5>
                            <small>Pos akun inilah yang diisi tiap periode dan dipakai rumus rasio</small>
                        </div>
                        <button type="button" class="modal-close" wire:click="cancelPost" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="posKode" class="small font-weight-bold">Kode</label>
                                <input type="text" id="posKode" wire:model="formCode" @disabled($editing !== '')
                                       class="form-control form-control-sm text-uppercase @error('formCode') is-invalid @enderror">
                                @error('formCode')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group col-md-9">
                                <label for="posNama" class="small font-weight-bold">Nama pos akun</label>
                                <input type="text" id="posNama" wire:model="formName"
                                       class="form-control form-control-sm @error('formName') is-invalid @enderror"
                                       placeholder="mis. Beban pemasaran digital">
                                @error('formName')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="posJenis" class="small font-weight-bold">Jenis — menentukan cara angkanya dipakai</label>
                            <select id="posJenis" wire:model="formKind" @disabled($bawaan)
                                    class="form-control form-control-sm @error('formKind') is-invalid @enderror">
                                @foreach ($kinds as $nilai => $keterangan)
                                    <option value="{{ $nilai }}">{{ $keterangan }}</option>
                                @endforeach
                            </select>
                            @error('formKind')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            @if ($bawaan)
                                <small class="text-muted">Pos bawaan: jenisnya dikunci karena dipakai rumus bawaan.</small>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="posSumber" class="small font-weight-bold">Sumber data — dari sistem mana angkanya datang</label>
                            <select id="posSumber" wire:model="formSource"
                                    class="form-control form-control-sm @error('formSource') is-invalid @enderror">
                                @foreach ($sources as $nilai => $keterangan)
                                    <option value="{{ $nilai }}">{{ $keterangan }}</option>
                                @endforeach
                                @unless (array_key_exists($formSource, $sources))
                                    <option value="{{ $formSource }}">{{ $formSource }}</option>
                                @endunless
                            </select>
                            @error('formSource')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group mb-0">
                            <label for="posKeterangan" class="small font-weight-bold">Keterangan <span class="text-muted font-weight-normal">(opsional)</span></label>
                            <input type="text" id="posKeterangan" wire:model="formHint"
                                   class="form-control form-control-sm @error('formHint') is-invalid @enderror"
                                   placeholder="Penjelasan singkat agar pengisi tidak salah tafsir.">
                            @error('formHint')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="modal-ft">
                        <button type="button" wire:click="cancelPost" class="btn btn-ghost">Batal</button>
                        <button type="button" wire:click="savePost" class="btn btn-teal"><i class="fas fa-save mr-1"></i> Simpan pos</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
