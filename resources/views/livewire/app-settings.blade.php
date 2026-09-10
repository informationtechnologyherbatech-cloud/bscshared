<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1><i class="fas fa-cogs mr-2"></i>Setting Sistem</h1></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Administrasi / Setting</li>
                    </ol>
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

            <div class="card card-primary card-outline card-tabs">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a wire:click="switchTab('identity')" class="nav-link {{ $activeTab==='identity' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-id-card mr-1"></i> Identitas Aplikasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a wire:click="switchTab('api')" class="nav-link {{ $activeTab==='api' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-key mr-1"></i> Kunci API Gateway
                            </a>
                        </li>
                        <li class="nav-item">
                            <a wire:click="switchTab('system')" class="nav-link {{ $activeTab==='system' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-server mr-1"></i> Informasi Sistem
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    @if($activeTab === 'identity')
                        {{-- Preview --}}
                        <div class="callout callout-info">
                            <div class="d-flex align-items-center">
                                @if($logoPath)
                                    <img src="{{ asset('storage/'.$logoPath) }}" alt="Logo" style="height:50px" class="mr-3 elevation-2">
                                @else
                                    <div class="bg-teal text-white rounded p-2 mr-3" style="width:50px;height:50px;line-height:38px;text-align:center;"><i class="fas fa-chart-line"></i></div>
                                @endif
                                <div>
                                    <h5 class="mb-0" style="color: {{ $app_primary_color }}">{{ $app_name }} <small class="text-muted">{{ app_version() }}</small></h5>
                                    <p class="mb-0 text-muted">{{ $company_name }}{{ $app_tagline ? ' · '.$app_tagline : '' }}</p>
                                    @if($faviconPath)
                                        <small>Favicon: <img src="{{ asset('storage/'.$faviconPath) }}" style="height:16px"> {{ $faviconPath }}</small>
                                    @endif
                                </div>
                                <div class="ml-auto d-flex align-items-center">
                                    <span class="mr-2 small">Warna Primary:</span>
                                    <span style="display:inline-block;width:24px;height:24px;background:{{ $app_primary_color }};border-radius:4px;border:1px solid #ddd"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Identitas entitas pengguna aplikasi --}}
                        <div class="card card-outline card-teal">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-building mr-1"></i> Identitas Entitas</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Aplikasi ini dapat dipakai entitas mana pun. Isi identitas di bawah untuk menentukan
                                    perusahaan pemilik data — nilai ini dipakai pada judul halaman, sidebar, halaman login
                                    dan footer.
                                </p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nama Entitas *</label>
                                            <input type="text" wire:model="entity_name" class="form-control @error('entity_name') is-invalid @enderror" placeholder="mis. Herbatech Innopharma">
                                            <small class="text-muted">Nama pendek entitas, dipakai pada sidebar &amp; judul.</small>
                                            @error('entity_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Nama Perusahaan *</label>
                                            <input type="text" wire:model="company_name" class="form-control @error('company_name') is-invalid @enderror" placeholder="mis. PT Herbatech Innopharma Industry">
                                            <small class="text-muted">Nama resmi/legal, dipakai pada footer &amp; hak cipta.</small>
                                            @error('company_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Alamat Perusahaan</label>
                                            <textarea wire:model="company_address" rows="3" class="form-control @error('company_address') is-invalid @enderror" placeholder="Jalan, kota, provinsi, kode pos"></textarea>
                                            @error('company_address') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nomor Kontak</label>
                                            <input type="text" wire:model="company_phone" class="form-control @error('company_phone') is-invalid @enderror" placeholder="mis. (021) 1234567 / 0812-3456-7890">
                                            @error('company_phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Email Kontak</label>
                                            <input type="email" wire:model="company_email" class="form-control @error('company_email') is-invalid @enderror" placeholder="mis. info@perusahaan.co.id">
                                            @error('company_email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Situs Web</label>
                                            <input type="url" wire:model="company_website" class="form-control @error('company_website') is-invalid @enderror" placeholder="https://perusahaan.co.id">
                                            @error('company_website') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Identitas aplikasi & branding --}}
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-id-card mr-1"></i> Identitas Aplikasi &amp; Branding</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Nama Aplikasi *</label>
                                            <input type="text" wire:model="app_name" class="form-control @error('app_name') is-invalid @enderror">
                                            @error('app_name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Tagline</label>
                                            <input type="text" wire:model="app_tagline" class="form-control" placeholder="mis. Balanced Scorecard Enterprise">
                                        </div>
                                        <div class="row">
                                            <div class="col-6 form-group">
                                                <label>Tahun *</label>
                                                <input type="text" wire:model="app_year" class="form-control @error('app_year') is-invalid @enderror">
                                                @error('app_year') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="col-6 form-group">
                                                <label>Warna Primary *</label>
                                                <input type="color" wire:model.live="app_primary_color" class="form-control" style="height:38px;padding:2px">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Logo (PNG/JPG/SVG max 2MB)</label>
                                            <div class="custom-file">
                                                <input type="file" wire:model="logoUpload" class="custom-file-input @error('logoUpload') is-invalid @enderror" accept="image/*">
                                                <label class="custom-file-label">{{ $logoUpload ? $logoUpload->getClientOriginalName() : 'Pilih file logo' }}</label>
                                            </div>
                                            @error('logoUpload') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label>Favicon (PNG/ICO max 1MB)</label>
                                            <div class="custom-file">
                                                <input type="file" wire:model="faviconUpload" class="custom-file-input @error('faviconUpload') is-invalid @enderror">
                                                <label class="custom-file-label">{{ $faviconUpload ? $faviconUpload->getClientOriginalName() : 'Pilih file favicon' }}</label>
                                            </div>
                                            @error('faviconUpload') <span class="text-danger small">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group mb-0">
                                            <label>Versi Aplikasi</label>
                                            <input type="text" class="form-control" value="{{ app_version() }}" readonly>
                                            <small class="text-muted">Diambil dari <code>app_version()</code> pada <code>app/Helpers/helper.php</code> (ubah lewat <code>APP_VERSION</code> di berkas .env).</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <button wire:click="resetIdentity" class="btn btn-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset Default</button>
                            <button wire:click="saveIdentity" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                        </div>

                    @elseif($activeTab === 'api')

                        <div class="alert alert-warning">
                            <i class="fas fa-shield-alt mr-1"></i> Hanya <strong>Super Admin</strong> yang dapat mengelola kunci API Gateway. Kunci bersifat rahasia (G-07).
                        </div>

                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-plus-circle mr-1"></i> Generate Kunci Baru</h6>
                            </div>
                            <div class="card-body">
                                <div class="input-group">
                                    <input type="text" wire:model="newKeyName" class="form-control" placeholder="Nama kunci, mis: Gateway Default">
                                    <div class="input-group-append">
                                        <button wire:click="generateApiKey" class="btn btn-info"><i class="fas fa-key mr-1"></i> Generate Baru</button>
                                    </div>
                                </div>
                                <small class="text-muted">Generate akan menonaktifkan kunci lama otomatis (hanya 1 aktif).</small>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Nama</th><th>Kunci</th><th>Status</th><th>Dibuat</th><th class="text-center">Aksi</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($apiKeys as $k)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td><strong>{{ $k->name }}</strong></td>
                                            <td>
                                                @if($showKeyId === $k->id)
                                                    <code>{{ $k->key }}</code>
                                                    <button wire:click="revealKey({{ $k->id }})" class="btn btn-xs btn-light ml-1"><i class="fas fa-eye-slash"></i></button>
                                                @else
                                                    <code>bsc_live_****{{ substr($k->key, -4) }}</code>
                                                    <button wire:click="revealKey({{ $k->id }})" class="btn btn-xs btn-light ml-1"><i class="fas fa-eye"></i> Lihat</button>
                                                @endif
                                            </td>
                                            <td>
                                                @if($k->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-secondary">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td><small>{{ $k->created_at->format('d M Y H:i') }}</small></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button wire:click="toggleKey({{ $k->id }})" class="btn btn-outline-warning" title="Toggle"><i class="fas fa-power-off"></i></button>
                                                    <button wire:click="deleteKey({{ $k->id }})" class="btn btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada kunci API.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="callout callout-secondary mt-3">
                            <small><strong>Endpoint Inbound:</strong> <code>http://127.0.0.1:8000/api/v1/bsc/sync/finance-coa</code> — Header wajib: <code>X-API-Key</code> + <code>Idempotency-Key</code></small>
                        </div>

                    @elseif($activeTab === 'system')
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <tbody>
                                    @foreach($systemInfo as $label => $val)
                                        <tr>
                                            <th style="width:30%">{{ $label }}</th>
                                            <td>
                                                @if($label==='App Env')
                                                    <span class="badge {{ $val==='production' ? 'badge-danger' : 'badge-warning' }}">{{ $val }}</span>
                                                @elseif($label==='App Debug')
                                                    <span class="badge {{ $val==='true' ? 'badge-danger' : 'badge-success' }}">{{ $val }}</span>
                                                @else
                                                    <code>{{ $val }}</code>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-info-circle mr-1"></i> Informasi ini read-only untuk audit & troubleshooting (FR-13.4).
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
