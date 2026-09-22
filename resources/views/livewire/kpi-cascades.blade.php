<div>
    @php
        $statusBadge = ['Lolos' => 'success', 'Revisi' => 'danger', 'Belum diuji' => 'secondary'];
        $levelBadge = ['Head' => 'primary', 'Supervisor' => 'info', 'Staff' => 'light border'];
        $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 4, ',', '.'), '0'), ',');
    @endphp

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1><i class="fas fa-sitemap mr-2 text-teal"></i>Cascade KPI <small class="text-muted">(Tingkat 3)</small></h1>
                    <small class="text-muted">
                        {{ $entity?->legal_name ?? 'Entitas aktif' }} — KPI berjenjang Head (lag) → Supervisor (lead) → Staff (output),
                        tiap jabatan Σ bobot 100%, ditelusuri sampai rasio &amp; pos akun yang digerakkan.
                    </small>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <label for="tahunKpi" class="mr-1 font-weight-bold">Tahun:</label>
                    <select wire:model.live="year" id="tahunKpi" class="form-control form-control-sm d-inline-block mb-1" style="width:100px">
                        @foreach ($years as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="unitFilter" class="form-control form-control-sm d-inline-block mb-1" style="width:170px" aria-label="Saring unit">
                        <option value="">Semua unit</option>
                        @foreach ($summary as $kode => $s)
                            <option value="{{ $kode }}">{{ $kode }} — {{ \Illuminate\Support\Str::limit($unitNames[$kode] ?? '', 22) }}</option>
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
                {{-- Ringkasan per unit --}}
                <div class="col-xl-8">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-clipboard-check mr-1"></i> Ringkasan per unit kerja</h3>
                        </div>
                        <div class="card-body p-0 table-responsive" style="max-height:340px">
                            <table class="table table-sm table-hover m-0 text-center" style="font-size:.85rem">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-left">Unit</th>
                                        <th>Σ bobot Head</th>
                                        <th>Cek Head</th>
                                        <th>KPI Spv</th>
                                        <th>Spv ≠100%</th>
                                        <th>KPI Staff</th>
                                        <th>Staff ≠100%</th>
                                        <th>Brand</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($summary as $kode => $s)
                                        <tr wire:key="sum-{{ $kode }}" role="button" wire:click="$set('unitFilter', '{{ $kode }}')"
                                            class="{{ $unitFilter === $kode ? 'table-active' : '' }}">
                                            <td class="text-left"><code>{{ $kode }}</code> <small class="text-muted">{{ \Illuminate\Support\Str::limit($unitNames[$kode] ?? '', 26) }}</small></td>
                                            <td>{{ $fmt($s['head_weight']) }}%</td>
                                            <td>
                                                @if ($s['head_check'] === 'OK')
                                                    <span class="text-success font-weight-bold">OK</span>
                                                @elseif ($s['head_check'] === '—')
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <span class="text-danger font-weight-bold">{{ $s['head_check'] }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $s['supervisors'] }}</td>
                                            <td class="{{ $s['supervisor_bad'] ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $s['supervisors'] ? $s['supervisor_bad'] : '—' }}</td>
                                            <td>{{ $s['staff'] }}</td>
                                            <td class="{{ $s['staff_bad'] ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $s['staff'] ? $s['staff_bad'] : '—' }}</td>
                                            <td>{{ $s['brands'] ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Monitoring --}}
                <div class="col-xl-4">
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold"><i class="fas fa-satellite-dish mr-1"></i> Masukkan ke monitoring</h3>
                        </div>
                        <div class="card-body">
                            <p class="small mb-2">
                                <strong>{{ $approved }}</strong> dari {{ $totalKpi }} KPI {{ $year }} berstatus <span class="badge badge-success">Lolos</span>.
                                Hanya KPI Lolos yang masuk Objective Departemen untuk diisi realisasi bulanannya.
                            </p>
                            @canany(['manage objectives', 'manage ratios'])
                                @if ($periods->isEmpty())
                                    <div class="small text-muted">Belum ada periode {{ $year }}. Buat periodenya dulu di Piramida BSC.</div>
                                @else
                                    <div class="input-group input-group-sm">
                                        <select wire:model="syncPeriod" class="form-control" aria-label="Periode monitoring">
                                            <option value="">Pilih periode…</option>
                                            @foreach ($periods as $p => $status)
                                                <option value="{{ $p }}" @disabled($status === 'CLOSED')>{{ $p }}{{ $status === 'CLOSED' ? ' (ditutup)' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append">
                                            <button wire:click="syncToPeriod" class="btn btn-success" @disabled($approved === 0)>
                                                <i class="fas fa-arrow-right mr-1"></i> Masukkan
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">Realisasi yang sudah diisi tidak berubah; hanya definisi &amp; target yang diperbarui.</small>
                                @endif
                            @endcanany
                            <hr class="my-2">
                            <a href="{{ route('account-post-map') }}" class="small"><i class="fas fa-project-diagram mr-1"></i> Lihat Peta Pos Akun</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabel cascade --}}
            <div class="card card-teal card-outline">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold mb-2 mb-md-0">
                        <i class="fas fa-stream mr-1"></i> Tabel cascade {{ $year }}{{ $unitFilter ? ' — '.$unitFilter : '' }}
                        @if ($unitFilter)
                            <button wire:click="$set('unitFilter', '')" class="btn btn-link btn-sm p-0 ml-1">tampilkan semua</button>
                        @endif
                    </h3>
                    @if ($canWrite)
                        <button wire:click="openCreate('{{ $unitFilter }}')" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> KPI Head</button>
                    @endif
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm table-hover m-0" style="font-size:.85rem">
                        <thead class="bg-light">
                            <tr>
                                <th style="min-width:150px">Kode · Level</th>
                                <th style="min-width:230px">Sasaran kerja · Jabatan</th>
                                <th class="text-right">Target</th>
                                <th class="text-center">Bobot</th>
                                <th style="min-width:190px">Dampak</th>
                                <th class="text-center">Validasi</th>
                                <th style="min-width:160px">Cek</th>
                                <th class="text-right" style="min-width:110px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($nodes as $n)
                                @php($r = $n['row'])
                                @php($c = $checks[$r->id])
                                <tr wire:key="kpi-{{ $r->id }}">
                                    <td style="padding-left: {{ 0.5 + $n['depth'] * 1.25 }}rem">
                                        @if ($n['depth'] > 0)<span class="text-muted">└</span>@endif
                                        <code>{{ $r->code }}</code>
                                        <span class="badge badge-{{ $levelBadge[$r->level] ?? 'secondary' }}">{{ $r->level }}</span>
                                        <small class="d-block text-muted">{{ $r->measure_type }}{{ $r->individual_type ? ' · '.$r->individual_type : '' }} · {{ $r->kpi_type }}</small>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold">{{ $r->objective }}</div>
                                        <small class="text-muted">{{ $r->position }}{{ $r->brand ? ' · '.$r->brand : '' }}</small>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        {{ $fmt($r->target) }} <small class="text-muted">{{ $r->unit_label }}</small>
                                        <small class="d-block text-muted">{{ $r->polarity }}</small>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        {{ $fmt($r->weight) }}%
                                        <small class="d-block {{ $c['weight_ok'] ? 'text-success' : 'text-danger font-weight-bold' }}" title="Σ bobot jabatan ini">
                                            Σ {{ $fmt($c['weight_sum']) }}%
                                        </small>
                                    </td>
                                    <td>
                                        @if ($r->ratio_code)
                                            <code>{{ $r->ratio_code }}</code> {{ $c['impact_name'] }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                        @if ($r->post_code)
                                            <small class="d-block">
                                                <code>{{ $r->post_code }}</code> {{ $c['post_name'] }}{{ $r->direction ? ' · '.$r->direction : '' }}
                                                <span class="badge {{ $c['role'] === 'Pemilik' ? 'badge-primary' : ($c['role'] === 'Kontributor' ? 'badge-info' : 'badge-danger') }}">{{ $c['role'] }}</span>
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $statusBadge[$r->validation_status] ?? 'secondary' }}">{{ $r->validation_status }}</span>
                                    </td>
                                    <td>
                                        @if ($c['issues'] === [])
                                            <span class="text-success"><i class="fas fa-check-circle"></i> OK</span>
                                        @else
                                            <ul class="pl-3 mb-0 small text-danger">
                                                @foreach ($c['issues'] as $isu)
                                                    <li>{{ $isu }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <div class="btn-group btn-group-sm">
                                            @if ($canWrite && $r->level !== 'Staff')
                                                <button wire:click="openCreate('{{ $r->unit_code }}', '{{ $r->level === 'Head' ? 'Supervisor' : 'Staff' }}', '{{ $r->code }}')"
                                                        class="btn btn-outline-success" title="Tambah KPI {{ $r->level === 'Head' ? 'Supervisor' : 'Staff' }} di bawahnya">
                                                    <i class="fas fa-level-down-alt"></i>
                                                </button>
                                            @endif
                                            @if ($canWrite || $canValidate)
                                                <button wire:click="openEdit({{ $r->id }})" class="btn btn-outline-primary" title="{{ $canWrite ? 'Ubah' : 'Validasi' }}">
                                                    <i class="fas {{ $canWrite ? 'fa-edit' : 'fa-clipboard-check' }}"></i>
                                                </button>
                                            @endif
                                            @if ($canWrite)
                                                <button wire:click="confirmDelete({{ $r->id }})" class="btn btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        Belum ada KPI {{ $year }}{{ $unitFilter ? ' untuk '.$unitFilter : '' }}.
                                        @if ($canWrite) Mulai dari tombol <strong>KPI Head</strong>. @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    Langkah: (1) lihat pos akun yang dimiliki/dikontribusi unit di Peta Pos Akun; (2) tetapkan KPI Head (lag) + bobotnya;
                    (3) turunkan KPI Supervisor (lead) dan Staff (output: rutin/milestone) lewat tombol <i class="fas fa-level-down-alt"></i>;
                    (4) Keuangan menguji tiap KPI Driver lalu menetapkan status <strong>Lolos</strong>/<strong>Revisi</strong>.
                </div>
            </div>
        </div>
    </section>

    {{-- Formulir KPI --}}
    @if ($showModal)
        @php($kunciIsi = ! $canWrite)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5); overflow-y:auto">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header" style="background:#17a2b8;color:#fff;">
                        <h5 class="modal-title">{{ $editingId ? 'Ubah KPI '.$form['code'] : 'Tambah KPI '.$form['level'] }}</h5>
                        <button type="button" class="close text-white" wire:click="closeModal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <fieldset @disabled($kunciIsi)>
                            <h6 class="text-muted text-uppercase small font-weight-bold">Identitas</h6>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label class="small">Unit kerja</label>
                                    <select wire:model.live="form.unit_code" class="form-control form-control-sm @error('form.unit_code') is-invalid @enderror">
                                        <option value="">Pilih…</option>
                                        @foreach ($unitNames as $kode => $nama)
                                            <option value="{{ $kode }}">{{ $kode }} — {{ \Illuminate\Support\Str::limit($nama, 28) }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.unit_code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Level</label>
                                    <select wire:model.live="form.level" class="form-control form-control-sm">
                                        @foreach (\App\Models\KpiCascade::LEVELS as $l)
                                            <option value="{{ $l }}">{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Kode KPI</label>
                                    <input type="text" wire:model.blur="form.code" class="form-control form-control-sm text-uppercase @error('form.code') is-invalid @enderror">
                                    @error('form.code') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">KPI induk</label>
                                    <select wire:model="form.parent_code" class="form-control form-control-sm" @disabled($form['level'] === 'Head')>
                                        <option value="">{{ $form['level'] === 'Head' ? '— (Head)' : 'Pilih…' }}</option>
                                        @foreach ($parentOptions as $p)
                                            <option value="{{ $p->code }}">{{ $p->code }} — {{ \Illuminate\Support\Str::limit($p->objective, 30) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small">Brand <span class="text-muted">(BU berbasis brand)</span></label>
                                    <input type="text" wire:model="form.brand" class="form-control form-control-sm" placeholder="kosongkan bila bukan BU brand">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label class="small">Jabatan / PIC</label>
                                    <input type="text" wire:model="form.position" class="form-control form-control-sm @error('form.position') is-invalid @enderror"
                                           placeholder="tulis konsisten — dipakai menjumlah bobot per jabatan">
                                    @error('form.position') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-8">
                                    <label class="small">Sasaran kerja</label>
                                    <input type="text" wire:model="form.objective" class="form-control form-control-sm @error('form.objective') is-invalid @enderror">
                                    @error('form.objective') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <h6 class="text-muted text-uppercase small font-weight-bold mt-2">Ukuran &amp; bobot</h6>
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label class="small">Jenis ukuran</label>
                                    <select wire:model="form.measure_type" class="form-control form-control-sm">
                                        @foreach (['Lag', 'Lead', 'Output'] as $m)
                                            <option value="{{ $m }}">{{ $m }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Target</label>
                                    <input type="number" step="any" wire:model="form.target" class="form-control form-control-sm text-right @error('form.target') is-invalid @enderror">
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Satuan</label>
                                    <input type="text" wire:model="form.unit_label" class="form-control form-control-sm" placeholder="%, Rp, x, hari">
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Arah baik</label>
                                    <select wire:model="form.polarity" class="form-control form-control-sm">
                                        <option value="Naik">Naik</option>
                                        <option value="Turun">Turun</option>
                                        <option value="Rentang">Rentang</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Bobot (%)</label>
                                    <input type="number" step="any" min="0" max="100" wire:model="form.weight" class="form-control form-control-sm text-right @error('form.weight') is-invalid @enderror">
                                    @error('form.weight') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Periode pelaporan</label>
                                    <input type="text" wire:model="form.reporting_period" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label class="small">Metode pengukuran / rumus</label>
                                    <textarea wire:model="form.method" rows="2" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="small">Key initiative (lead measure)</label>
                                    <textarea wire:model="form.key_initiative" rows="2" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="small">Program kerja</label>
                                    <textarea wire:model="form.work_program" rows="2" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="small">Record / catatan</label>
                                    <textarea wire:model="form.record" rows="2" class="form-control form-control-sm"></textarea>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">KPI individu</label>
                                    <select wire:model="form.individual_type" class="form-control form-control-sm" @disabled($form['level'] !== 'Staff')>
                                        <option value="">—</option>
                                        <option value="Rutin">Rutin</option>
                                        <option value="Milestone">Milestone</option>
                                    </select>
                                </div>
                            </div>

                            <h6 class="text-muted text-uppercase small font-weight-bold mt-2">Dampak ke rasio &amp; pos akun</h6>
                            <div class="form-row">
                                <div class="form-group col-md-2">
                                    <label class="small">Jenis KPI</label>
                                    <select wire:model.live="form.kpi_type" class="form-control form-control-sm">
                                        <option value="Driver">Driver</option>
                                        <option value="Guardrail">Guardrail (dikunci)</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small">Rasio digerakkan</label>
                                    <select wire:model.live="form.ratio_code" class="form-control form-control-sm @error('form.ratio_code') is-invalid @enderror">
                                        <option value="">—</option>
                                        <option value="REV">REV — Target Revenue{{ $canClaimRevenue ? '' : ' (tidak tersambung)' }}</option>
                                        @foreach ($ratios as $kode => $r)
                                            <option value="{{ $kode }}">{{ $kode }} — {{ $r['name'] }}{{ in_array($kode, $claimable, true) ? '' : ' (tidak tersambung)' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="small">Pos akun digerakkan</label>
                                    <select wire:model="form.post_code" class="form-control form-control-sm" @disabled($form['ratio_code'] === '')>
                                        <option value="">—</option>
                                        @foreach (\App\Support\Bsc\RatioLibrary::postsOf((string) $form['ratio_code']) as $pos)
                                            <option value="{{ $pos }}">{{ $pos }} — {{ $posts[$pos]['name'] }} · {{ \App\Models\AccountPostRole::label($postRoles[$pos] ?? null) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Arah ke pos akun</label>
                                    <select wire:model="form.direction" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        <option value="Menaikkan">Menaikkan</option>
                                        <option value="Menurunkan">Menurunkan</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="small">Elastisitas e</label>
                                    <input type="number" step="any" wire:model="form.elasticity" class="form-control form-control-sm text-right" placeholder="0–1">
                                </div>
                            </div>
                            <small class="text-muted d-block mb-2">
                                "Tidak tersambung" = unit ini bukan Pemilik/Kontributor pada pos akun pembentuk rasio itu (Peta Pos Akun), sehingga klaimnya akan ditandai.
                                Guardrail (mis. kepatuhan) tidak wajib diarahkan ke rasio.
                            </small>
                        </fieldset>

                        <h6 class="text-muted text-uppercase small font-weight-bold mt-2">Validasi Keuangan</h6>
                        <fieldset @disabled(! $canValidate)>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label class="small">Status validasi</label>
                                    <select wire:model="form.validation_status" class="form-control form-control-sm">
                                        @foreach (\App\Models\KpiCascade::STATUSES as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-9">
                                    <label class="small">Catatan keuangan</label>
                                    <input type="text" wire:model="form.finance_notes" class="form-control form-control-sm">
                                </div>
                            </div>
                        </fieldset>
                        @unless ($canValidate)
                            <small class="text-muted">Status validasi ditetapkan Keuangan. Mengubah isi KPI yang sudah Lolos mengembalikannya ke "Belum diuji".</small>
                        @endunless
                    </div>
                    <div class="modal-footer">
                        <button wire:click="closeModal" class="btn btn-secondary">Batal</button>
                        <button wire:click="save" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmDeleteId)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title"><i class="fas fa-trash mr-1"></i> Hapus KPI</h5>
                        <button type="button" class="close text-white" wire:click="cancelDelete"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        KPI ini akan dihapus dari cascade. Riwayat realisasi bulanannya di Objective Departemen tetap disimpan.
                    </div>
                    <div class="modal-footer">
                        <button wire:click="cancelDelete" class="btn btn-secondary">Batal</button>
                        <button wire:click="delete" class="btn btn-danger">Ya, hapus</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
