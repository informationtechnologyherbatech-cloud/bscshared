<div>
    @unless ($embedded)
    <section class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-8">
                    <h1><i class="fas fa-plug mr-2 text-teal"></i>Integrasi Odoo</h1>
                    <small class="text-muted">
                        Aplikasi ini <strong>menarik</strong> angka dari Odoo entitas — bukan sebaliknya. Di Odoo tidak ada yang
                        perlu dipasang selain satu pengguna yang berhak membaca jurnal.
                    </small>
                </div>
            </div>
        </div>
    </section>
    @endunless

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
                {{-- ─────────────── Sambungan ─────────────── --}}
                <div class="col-lg-5">
                    <div class="card card-teal card-outline">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-link mr-1"></i> Sambungan Odoo</h3>
                            <button type="button" wire:click="bukaPanduan" class="btn btn-xs btn-ghost"
                                    title="Apa yang perlu disiapkan di Odoo?">
                                <i class="fas fa-circle-question"></i>
                                <span class="d-none d-sm-inline ml-1">Panduan Odoo</span>
                            </button>
                        </div>
                        <div class="card-body">
                            <form wire:submit.prevent="saveConnection">
                                <div class="form-group">
                                    <label class="font-weight-bold">Alamat Odoo</label>
                                    <input type="url" wire:model="baseUrl" class="form-control" placeholder="https://erp.entitas.co.id">
                                    @error('baseUrl') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">Nama database Odoo</label>
                                    <input type="text" wire:model="databaseName" class="form-control" placeholder="erp_produksi">
                                    @error('databaseName') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                @if ($daftarPerusahaan || $sambungan?->company_id)
                                    <div class="form-group">
                                        <label class="font-weight-bold">Perusahaan di Odoo</label>
                                        <select wire:model="companyId" id="odooCompany" class="form-control">
                                            <option value="">Semua (database berisi satu perusahaan)</option>
                                            @foreach ($daftarPerusahaan as $perusahaan)
                                                <option value="{{ $perusahaan['id'] }}">{{ $perusahaan['name'] }}</option>
                                            @endforeach
                                            @if ($sambungan?->company_id && ! collect($daftarPerusahaan)->contains('id', $sambungan->company_id))
                                                <option value="{{ $sambungan->company_id }}">
                                                    {{ $sambungan->company_name ?? 'Perusahaan #'.$sambungan->company_id }}
                                                </option>
                                            @endif
                                        </select>
                                        <small class="text-muted">
                                            Satu database Odoo boleh memuat beberapa perusahaan. Kalau tidak dipilih,
                                            saldo semuanya akan terjumlah menjadi satu.
                                        </small>
                                    </div>
                                @endif

                                <div class="form-group">
                                    <label class="font-weight-bold">Pengguna Odoo</label>
                                    <input type="text" wire:model="username" class="form-control" placeholder="integrasi.bsc@entitas.co.id">
                                    <small class="text-muted">Cukup diberi hak <strong>membaca</strong> jurnal & bagan akun.</small>
                                    @error('username') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group">
                                    <label class="font-weight-bold">
                                        Kunci API {{ $punyaKunci ? '(biarkan kosong bila tidak diganti)' : '' }}
                                    </label>
                                    <input type="password" wire:model="apiKey" class="form-control" autocomplete="new-password"
                                           placeholder="{{ $sambungan?->maskedKey() ?? 'kunci API pengguna Odoo' }}">
                                    <small class="text-muted">
                                        @if ($sambungan?->maskedKey())
                                            Tersimpan: <code>{{ $sambungan->maskedKey() }}</code> — terenkripsi, tidak pernah ditampilkan utuh lagi.
                                        @else
                                            Tersimpan terenkripsi; tidak pernah ditampilkan utuh lagi.
                                        @endif
                                    </small>
                                    @error('apiKey') <small class="text-danger d-block">{{ $message }}</small> @enderror
                                </div>

                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="odooAktif" wire:model="isActive">
                                    <label class="custom-control-label" for="odooAktif">Tarikan terjadwal aktif</label>
                                </div>
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="odooRevenue" wire:model="fillsRevenue">
                                    <label class="custom-control-label" for="odooRevenue">
                                        Isi juga realisasi revenue bulanan dari akun penjualan
                                    </label>
                                </div>

                                @if ($canManage)
                                    <button type="submit" class="btn btn-teal btn-sm">
                                        <i class="fas fa-save mr-1"></i> Simpan sambungan
                                    </button>
                                    <button type="button" wire:click="testConnection" class="btn btn-ghost btn-sm">
                                        <i class="fas fa-satellite-dish mr-1"></i> Uji sambungan
                                    </button>
                                @endif
                            </form>
                        </div>
                        @if ($sambungan?->last_run_at)
                            <div class="card-footer py-2 small">
                                <span class="badge {{ $sambungan->last_status === 'ok' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $sambungan->last_status === 'ok' ? 'berhasil' : 'galat' }}
                                </span>
                                <span class="text-muted ml-1">{{ $sambungan->last_run_at->diffForHumans() }}</span>
                                <div class="text-muted mt-1">{{ $sambungan->last_message }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- ─────────────── Tarik sekarang ─────────────── --}}
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-download mr-1"></i> Tarik sekarang</h3>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                Di luar ini, tarikan berjalan sendiri tiap hari pukul 04.30 untuk periode berjalan.
                                Menarik ulang periode yang sama akan <strong>menimpa</strong> angkanya, bukan menambah.
                            </p>
                            <div class="form-group">
                                <label class="font-weight-bold">Periode</label>
                                <input type="month" wire:model="period" id="odooPeriod" class="form-control" style="max-width:200px">
                                @error('period') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            @if ($canManage)
                                <button type="button" wire:click="pullNow" class="btn btn-info btn-sm" wire:loading.attr="disabled">
                                    <i class="fas fa-rotate mr-1"></i> Tarik dari Odoo
                                </button>
                                <span wire:loading wire:target="pullNow" class="text-muted small ml-2">menarik…</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ─────────────── Pemetaan akun ─────────────── --}}
                <div class="col-lg-7">
                    <div class="card card-outline card-success">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-diagram-project mr-1"></i> Pemetaan Akun</h3>
                            @if ($canManage)
                                <div>
                                    <button type="button" wire:click="fetchAccounts" class="btn btn-sm btn-ghost">
                                        <i class="fas fa-cloud-arrow-down mr-1"></i> Ambil daftar akun
                                    </button>
                                    <button type="button" wire:click="editMapping" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus mr-1"></i> Pemetaan baru
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="card-body pb-0">
                            <p class="small text-muted">
                                Bagan akun tiap entitas berbeda, sedangkan 19 rasio selalu disusun dari 16 pos yang sama.
                                Di sinilah keduanya dipertemukan. Beberapa kode akun boleh menunjuk pos yang sama — nilainya dijumlahkan.
                            </p>
                            @php($posInti = ['PA01', 'PA02', 'PA03'])
                            @php($kurang = array_values(array_diff($posInti, $posDipetakan)))
                            @if ($kurang)
                                <div class="alert alert-warning py-2 small">
                                    <i class="fas fa-triangle-exclamation mr-1"></i>
                                    Pos inti belum dipetakan: <strong>{{ implode(', ', $kurang) }}</strong>.
                                    Tanpa Penjualan dan HPP, sebagian besar rasio tidak dapat dihitung.
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-sm table-hover m-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Kode akun</th>
                                        <th>Pos akun BSC</th>
                                        <th class="text-center">Tanda</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($pemetaan as $p)
                                        <tr>
                                            <td>
                                                <code>{{ $p->source_code }}</code>
                                                @if ($p->source_name)
                                                    <small class="d-block text-muted">{{ $p->source_name }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-light border">{{ $p->post_code }}</span>
                                                {{ $katalogPos[$p->post_code]['name'] ?? '' }}
                                                <small class="d-block text-muted">
                                                    {{ ($katalogPos[$p->post_code]['kind'] ?? '') === 'neraca' ? 'saldo akhir periode' : 'nilai YTD' }}
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                @if ($p->invert)
                                                    <span class="badge badge-warning" title="Saldo kredit di buku besar dibalik jadi positif">dibalik</span>
                                                @else
                                                    <span class="text-muted">apa adanya</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                @if ($canManage)
                                                    <button type="button" wire:click="editMapping({{ $p->id }})" class="btn btn-xs btn-ghost">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <button type="button" wire:click="deleteMapping({{ $p->id }})" class="btn btn-xs btn-ghost text-danger"
                                                            wire:confirm="Hapus pemetaan {{ $p->source_code }}?">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                Belum ada pemetaan akun. Tekan <strong>Ambil daftar akun</strong> untuk menarik bagan akun dari Odoo,
                                                lalu petakan yang dipakai.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Bagan akun yang baru diambil dari Odoo --}}
                    @if ($akunOdoo)
                        <div class="card card-outline card-secondary">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold">
                                    <i class="fas fa-list mr-1"></i> Bagan akun Odoo ({{ count($akunOdoo) }})
                                </h3>
                            </div>
                            <div class="card-body p-0 table-responsive" style="max-height:340px">
                                <table class="table table-sm table-hover m-0">
                                    <tbody>
                                        @foreach ($akunOdoo as $a)
                                            <tr>
                                                <td style="width:130px"><code>{{ $a['code'] }}</code></td>
                                                <td>{{ $a['name'] }}</td>
                                                <td class="text-right" style="width:110px">
                                                    @if ($canManage)
                                                        <button type="button" class="btn btn-xs btn-ghost"
                                                                wire:click="editMapping(null, '{{ $a['code'] }}', @js($a['name']))">
                                                            <i class="fas fa-plus mr-1"></i> Petakan
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
            </div>
        </div>
    </section>

    {{-- ─────────────── Panduan sisi Odoo ─────────────── --}}
    @if ($showPanduan)
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas fa-circle-question"></i></span>
                        <div>
                            <h5 class="modal-title">Yang perlu disiapkan di Odoo</h5>
                            <small>Tidak ada modul yang dipasang, tidak ada webhook, dan tidak perlu hak tulis</small>
                        </div>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">
                            Aplikasi ini <strong>menarik</strong> angka dari Odoo, bukan menunggu dikirimi. Karena itu yang
                            dibutuhkan di Odoo hanya satu pengguna yang boleh <strong>membaca</strong> jurnal.
                        </p>

                        <ol class="pl-3 mb-3">
                            <li class="mb-2">
                                <strong>Buat pengguna khusus untuk integrasi.</strong><br>
                                <span class="text-muted">Settings → Users &amp; Companies → Users → New</span>, bertipe
                                <em>Internal User</em>, misalnya <code>integrasi.bsc@entitas.co.id</code>.
                                Jangan memakai akun pribadi seseorang: bila orangnya keluar dan akunnya dinonaktifkan,
                                tarikan ikut mati.
                            </li>
                            <li class="mb-2">
                                <strong>Beri hak baca akuntansi saja.</strong><br>
                                Pada tab <em>Access Rights</em>, beri akses Akuntansi tingkat baca — di Odoo 16+ namanya
                                <em>Read-only</em>; pada versi lain <em>Billing</em> adalah yang terendah dan masih bisa
                                membaca jurnal. Aplikasi ini hanya memanggil <code>search_read</code> dan
                                <code>read_group</code>, tidak pernah menulis.
                            </li>
                            <li class="mb-2">
                                <strong>Buat kunci API untuk pengguna itu.</strong><br>
                                Masuk ke Odoo <strong>sebagai pengguna tersebut</strong>, lalu
                                <span class="text-muted">(ikon pengguna) → My Profile → Account Security → New API Key</span>.
                                Odoo meminta kata sandi pengguna itu, lalu menampilkan kuncinya <strong>sekali saja</strong> —
                                salin saat itu juga dan tempel di kolom <em>Kunci API</em> di sebelah kiri.
                                <br><small class="text-muted">Odoo di bawah versi 14 belum punya kunci API; pakai kata sandi pengguna integrasi tersebut.</small>
                            </li>
                            <li>
                                <strong>Catat tiga keterangan ini.</strong>
                                <table class="table table-sm mt-2 mb-0">
                                    <tbody>
                                        <tr>
                                            <td style="width:150px"><strong>Alamat Odoo</strong></td>
                                            <td>URL tempat Odoo dibuka, mis. <code>https://erp.entitas.co.id</code>.
                                                Untuk Odoo Online: <code>https://namaperusahaan.odoo.com</code>.</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nama database</strong></td>
                                            <td>Terlihat pada pemilih database di halaman login Odoo. Untuk Odoo Online
                                                biasanya sama dengan subdomainnya.</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Perusahaan</strong></td>
                                            <td>Hanya bila database itu memuat lebih dari satu perusahaan — pilih yang
                                                menjadi entitas ini.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </li>
                        </ol>

                        <h6 class="font-weight-bold"><i class="fas fa-list-check mr-1 text-teal"></i> Pastikan pada datanya</h6>
                        <ul class="pl-3">
                            <li><strong>Jurnal sudah diposting.</strong> Hanya entri berstatus <em>Posted</em> yang dihitung;
                                draf diabaikan. Jalankan tarikan setelah tutup buku bulanan.</li>
                            <li><strong>Akun punya kode.</strong> Pemetaan bekerja atas kolom <em>code</em> di
                                <span class="text-muted">Accounting → Configuration → Chart of Accounts</span>. Akun tanpa kode dilewati.</li>
                            <li><strong>Saldo awal tahun sudah dibukukan</strong> bila ingin rasio memakai rata-rata saldo
                                untuk pos neraca. Kalau belum, rasio memakai saldo akhir saja — tetap jalan, hanya kurang halus.</li>
                            <li><strong>Jaringan.</strong> Server aplikasi ini harus dapat menghubungi alamat Odoo lewat HTTPS.</li>
                        </ul>

                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            <strong>Satu database, beberapa perusahaan.</strong> Bila perusahaannya tidak dipilih, saldo
                            <em>semua</em> perusahaan akan terjumlah menjadi satu — angkanya salah tanpa pesan galat apa pun.
                            Karena itu tarikan ditolak sampai perusahaannya dipilih, termasuk pada tarikan terjadwal.
                        </div>
                    </div>
                    <div class="modal-ft">
                        <button type="button" wire:click="tutupPanduan" class="btn btn-teal btn-sm">
                            <i class="fas fa-check mr-1"></i> Mengerti
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif

    {{-- ─────────────── Formulir pemetaan ─────────────── --}}
    @if ($showMapping)
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas fa-diagram-project"></i></span>
                        <div>
                            <h5 class="modal-title">{{ $editingId ? 'Ubah pemetaan' : 'Pemetaan akun baru' }}</h5>
                            <small>Kode akun di Odoo → pos akun BSC</small>
                        </div>
                    </div>
                    <form wire:submit.prevent="saveMapping">
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="font-weight-bold">Kode akun di Odoo</label>
                                <input type="text" wire:model="sourceCode" id="mapSourceCode" class="form-control" placeholder="4-10001">
                                @error('sourceCode') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Nama akun <span class="text-muted">(opsional)</span></label>
                                <input type="text" wire:model="sourceName" class="form-control" placeholder="Penjualan Barang Dagang">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold">Pos akun BSC</label>
                                <select wire:model.live="postCode" id="mapPostCode" class="form-control">
                                    @foreach ($katalogPos as $kode => $pos)
                                        <option value="{{ $kode }}">{{ $kode }} - {{ $pos['name'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ $katalogPos[$postCode]['hint'] ?? '' }}</small>
                                @error('postCode') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="balikTanda" wire:model="invert">
                                <label class="custom-control-label" for="balikTanda">
                                    Balik tanda (akun bersaldo kredit)
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Di Odoo saldo = debit − kredit, jadi penjualan, utang, dan ekuitas bernilai negatif.
                                Pos akun BSC memakai angka positif apa adanya.
                            </small>
                        </div>
                        <div class="modal-ft">
                            <button type="button" wire:click="cancelMapping" class="btn btn-ghost btn-sm">Batal</button>
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-save mr-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    @endif
</div>
