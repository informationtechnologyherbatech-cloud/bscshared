<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-network-wired mr-2 text-teal"></i>Sumber Data Entitas</h1>
                    <small class="text-muted">
                        Holding hanya menyimpan <strong>di mana</strong> data tiap entitas berada — alamat API beserta kuncinya,
                        atau nama databasenya. Datanya sendiri tetap tinggal di entitas masing-masing.
                    </small>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-triangle-exclamation mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif

            @unless ($strict)
                <div class="alert alert-info py-2">
                    <i class="fas fa-circle-info mr-1"></i>
                    Entitas yang sumbernya belum diatur dibaca dari database aplikasi ini. Setelah semua entitas diarahkan ke
                    sumbernya, aktifkan <code>BSC_REQUIRE_ENTITY_SOURCES=true</code> agar salah ketik tidak diam-diam menampilkan angka lokal.
                </div>
            @endunless

            <div class="card card-teal card-outline">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Entitas</th>
                                <th>Sumber data</th>
                                <th>Kunci API</th>
                                <th>Sambungan terakhir</th>
                                <th class="text-center" style="width:190px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $b)
                                @php($e = $b['entity'])
                                @php($s = $b['source'])
                                @php($r = $b['record'])
                                <tr>
                                    <td>
                                        <strong>{{ $e->name }}</strong>
                                        <small class="d-block text-muted">{{ $e->legal_name }} · {{ $e->industryLabel() }}</small>
                                    </td>
                                    <td>
                                        @switch($s['driver'])
                                            @case('api')
                                                <span class="badge badge-light border"><i class="fas fa-globe mr-1"></i>API</span>
                                                <small class="d-block text-muted">{{ $s['api_url'] }}</small>
                                                @break
                                            @case('database')
                                                <span class="badge badge-light border"><i class="fas fa-database mr-1"></i>Database</span>
                                                <small class="d-block text-muted">{{ $s['database'] }}</small>
                                                @break
                                            @default
                                                <span class="badge badge-light border"><i class="fas fa-house mr-1"></i>Database aplikasi ini</span>
                                        @endswitch
                                        <small class="text-muted">{{ $s['origin'] === 'tidak diatur' ? 'belum diatur' : 'diatur lewat '.$s['origin'] }}</small>
                                    </td>
                                    <td>
                                        @if ($r?->maskedKey())
                                            <code>{{ $r->maskedKey() }}</code>
                                            <small class="d-block text-muted">tersimpan terenkripsi</small>
                                        @elseif ($s['driver'] === 'api' && $s['api_key'])
                                            <small class="text-muted">dari berkas .env</small>
                                        @else
                                            <small class="text-muted">—</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($r?->last_checked_at)
                                            @if ($r->last_status === 'ok')
                                                <span class="badge badge-success"><i class="fas fa-plug-circle-check mr-1"></i>Tersambung</span>
                                            @else
                                                <span class="badge badge-danger"><i class="fas fa-link-slash mr-1"></i>Gagal</span>
                                            @endif
                                            <small class="d-block text-muted">{{ $r->last_message }}</small>
                                            <small class="text-muted">{{ $r->last_checked_at->diffForHumans() }}</small>
                                        @else
                                            <small class="text-muted">belum pernah diuji</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button wire:click="test({{ $e->id }})" class="btn btn-sm btn-ghost" wire:loading.attr="data-loading" wire:target="test({{ $e->id }})" title="Uji sambungan ke entitas ini">
                                            <i class="fas fa-plug-circle-check mr-1"></i> Uji
                                        </button>
                                        @if ($canManage)
                                            <button wire:click="edit({{ $e->id }})" class="btn btn-sm btn-teal">
                                                <i class="fas fa-pen-to-square mr-1"></i> Atur
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Kunci API dibuat di aplikasi entitas (Setting Sistem → tab API), lalu ditempel di sini. Kunci disimpan
                    terenkripsi dan tidak pernah ditampilkan utuh lagi. Nilai pada berkas <code>.env</code> tetap dipakai untuk
                    entitas yang belum diatur di layar ini.
                </div>
            </div>
        </div>
    </section>

    {{-- Formulir sumber data satu entitas --}}
    @if ($editingId)
        @php($entitas = $baris->firstWhere('entity.id', $editingId)['entity'])
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas fa-network-wired"></i></span>
                        <div>
                            <h5 class="modal-title">Sumber data {{ $entitas->name }}</h5>
                            <small>Di mana data entitas ini dibaca holding</small>
                        </div>
                        <button type="button" class="modal-close" wire:click="cancel" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Cara membaca</label>
                            <select wire:model.live="driver" class="form-control {{ $errors->has('driver') ? 'is-invalid' : '' }}">
                                <option value="api">API — entitas di server lain</option>
                                <option value="database">Database terpisah — satu server, database sendiri</option>
                                <option value="lokal">Database aplikasi ini — semuanya di satu database</option>
                            </select>
                            @error('driver') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                        </div>

                        @if ($driver === 'api')
                            <div class="form-group">
                                <label class="font-weight-bold">Alamat aplikasi entitas</label>
                                <input type="url" wire:model="apiUrl" placeholder="https://bsc.aej.co.id"
                                       class="form-control {{ $errors->has('apiUrl') ? 'is-invalid' : '' }}">
                                <small class="text-muted">Cukup alamat pokoknya; holding menambahkan sendiri <code>/api/v1/…</code>. Wajib HTTPS di server produksi.</small>
                                @error('apiUrl') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Kunci API {{ $punyaKunci ? '(biarkan kosong bila tidak diganti)' : '' }}</label>
                                <input type="password" wire:model="apiKey" autocomplete="new-password" placeholder="bsc_live_…"
                                       class="form-control {{ $errors->has('apiKey') ? 'is-invalid' : '' }}">
                                <small class="text-muted">Dibuat di aplikasi entitas: Setting Sistem → tab API → Generate Kunci Baru.</small>
                                @error('apiKey') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            </div>
                        @elseif ($driver === 'database')
                            <div class="form-group">
                                <label class="font-weight-bold">Nama database entitas</label>
                                <input type="text" wire:model="databaseName" placeholder="db_bsc_aej"
                                       class="form-control {{ $errors->has('databaseName') ? 'is-invalid' : '' }}">
                                <small class="text-muted">Dibuka memakai kredensial <code>DB_*</code> aplikasi ini, hanya untuk dibaca.</small>
                                @error('databaseName') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                            </div>
                        @else
                            <p class="text-muted mb-0">
                                Data entitas ini dibaca dari database aplikasi holding. Pilihan ini untuk pemasangan tunggal
                                atau lingkungan pengembangan — pada pemakaian sebenarnya, tiap entitas sebaiknya punya databasenya sendiri.
                            </p>
                        @endif
                    </div>
                    <div class="modal-ft">
                        <button wire:click="cancel" class="btn btn-ghost">Batal</button>
                        <button wire:click="save" class="btn btn-teal" wire:loading.attr="data-loading" wire:target="save"><i class="fas fa-save mr-1"></i> Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
