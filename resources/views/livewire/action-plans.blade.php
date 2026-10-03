<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-7">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-list-check text-teal mr-2"></i> Program Kerja / Action Plan (Tingkat 4)
                    </h1>
                    <small class="text-muted">
                        Program kerja menempel pada satu periode — periode yang sama inilah yang dibaca
                        Tingkat 4 di Piramida BSC.
                    </small>
                </div>
                <div class="col-sm-5 text-sm-right mt-2 mt-sm-0">
                    <label for="periodeAksi" class="mr-2 font-weight-bold">Periode:</label>
                    <select wire:model.live="selectedPeriod" id="periodeAksi" class="form-control form-control-sm d-inline-block" style="width:150px">
                        @foreach ($periods as $p)
                            <option value="{{ $p }}">{{ period_label($p) }}</option>
                        @endforeach
                        <option value="semua">Semua periode</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if ($periodClosed)
                <div class="alert alert-secondary">
                    <i class="fas fa-lock mr-1"></i>
                    Periode {{ period_label($targetPeriod) }} sudah <strong>DITUTUP</strong> — program kerja periode ini hanya dapat dilihat.
                </div>
            @endif

            <div class="row">
                <!-- Form Tambah Program Kerja -->
                <div class="col-md-4">
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-plus-circle mr-1"></i> Tambah Inisiatif Perbaikan
                            </h3>
                        </div>
                        @unless ($canWrite && ! $periodClosed)
                            <div class="card-body">
                                @if ($periodClosed)
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-lock mr-1"></i>
                                        Periode {{ period_label($targetPeriod) }} sudah ditutup, jadi program kerjanya
                                        hanya dapat dilihat. Pilih periode lain di atas, atau buka kembali periodenya
                                        lewat Piramida BSC bila memang perlu diubah.
                                    </p>
                                @else
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-eye mr-1"></i>
                                        Anda membuka halaman ini sebagai pembaca. Menambah program kerja dan mengubah
                                        progresnya butuh izin <strong>manage actionplans</strong> — ada pada peran
                                        Admin HRIS, Admin FAT, Kepala Departemen, Operator, dan Super Admin.
                                    </p>
                                @endif
                            </div>
                        @else
                        <div class="card-body">
                            <form wire:submit.prevent="createPlan">
                                <div class="form-group">
                                    <label>Judul Program Kerja</label>
                                    <input type="text" wire:model="title" class="form-control form-control-sm" placeholder="Contoh: Kalibrasi Ulang Mesin Line 2">
                                    @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group">
                                    <label>Departemen Penanggung Jawab</label>
                                    <select wire:model="ownerDept" class="form-control form-control-sm">
                                        <option value="">— Pilih unit kerja —</option>
                                        @foreach($units as $unit)
                                            <option value="{{ $unit->code }}">{{ $unit->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('ownerDept') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group">
                                    <label>Terkait KPI (Opsional)</label>
                                    <select wire:model="objectiveId" class="form-control form-control-sm">
                                        <option value="">-- Pilih Sasaran Mutu --</option>
                                        @foreach($offTargetObjectives as $offObj)
                                            <option value="{{ $offObj->id }}">[{{ $offObj->dept_code }}] {{ $offObj->kpi_code }} - {{ Str::limit($offObj->kpi_name, 30) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($offTargetObjectives->isEmpty())
                                        <small class="text-muted">Belum ada sasaran mutu di periode {{ period_label($targetPeriod) }}.</small>
                                    @endif
                                </div>

                                {{-- Periode tujuan disebut terang-terangan: daftar di halaman ini
                                     tersaring per periode, jadi pembuatnya harus tahu isiannya mendarat di mana. --}}
                                <p class="small text-muted">
                                    <i class="fas fa-calendar-day mr-1"></i>
                                    Disimpan pada periode <strong>{{ period_label($targetPeriod) }}</strong>.
                                </p>
                                <button type="submit" class="btn btn-danger btn-sm btn-block">
                                    <i class="fas fa-save mr-1"></i> Simpan Program Kerja
                                </button>
                            </form>
                        </div>
                        @endunless
                    </div>
                </div>

                <!-- Tabel Program Kerja -->
                <div class="col-md-8">
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-list-ol mr-1"></i> Progress Inisiatif & Tindak Lanjut KPI
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-bordered m-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 8%;">Dept</th>
                                        <th style="width: 10%;">Periode</th>
                                        <th style="width: 35%;">Judul Inisiatif / Program Kerja</th>
                                        <th style="width: 25%;">Progress Fisik (%)</th>
                                        <th class="text-center" style="width: 15%;">Status</th>
                                        <th class="text-center" style="width: 17%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($actionPlans as $plan)
                                        <tr>
                                            <td><span class="badge badge-dark">{{ $plan->owner_dept }}</span></td>
                                            <td class="small">{{ $plan->period ? period_label($plan->period) : '—' }}</td>
                                            <td>
                                                <div class="font-weight-bold text-dark">
                                                    {{ $plan->title }}
                                                    @if ($plan->isCarriedOver())
                                                        <span class="badge badge-warning"
                                                              title="Belum selesai di periode {{ period_label($plan->carried_from) }}, dibawa ke periode ini.">
                                                            <i class="fas fa-arrow-right-long mr-1"></i> Lanjutan
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($plan->isCarriedOver())
                                                    <small class="text-muted d-block">Lanjutan dari {{ period_label($plan->carried_from) }}</small>
                                                @endif
                                                @if($plan->objective)
                                                    <small class="text-muted"><i class="fas fa-link mr-1"></i> KPI: {{ $plan->objective->kpi_code }}</small>
                                                @endif
                                            </td>

                                            {{--
                                                Progres diubah lewat modal, bukan disisipkan ke dalam sel:
                                                slider + tombol di dalam sel membuat lebar kolom melar dan
                                                tata letak tabel rusak.
                                            --}}
                                                <td class="align-middle">
                                                    <div class="progress progress-xs mb-1">
                                                        <div class="progress-bar {{ $plan->progress_pct >= 100 ? 'bg-success' : ($plan->progress_pct >= 50 ? 'bg-warning' : 'bg-danger') }}" 
                                                             style="width: {{ $plan->progress_pct }}%"></div>
                                                    </div>
                                                    <small class="font-weight-bold">{{ $plan->progress_pct }}% Selesai</small>
                                                </td>
                                                <td class="text-center">
                                                    @if($plan->status === 'Completed')
                                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle"></i> Selesai</span>
                                                    @else
                                                        <span class="badge badge-primary px-2 py-1"><i class="fas fa-spinner fa-spin mr-1"></i> On Progress</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if ($canWrite && ! $periodClosed)
                                                        <button wire:click="editProgressModal({{ $plan->id }})" class="btn btn-xs btn-outline-danger">
                                                            <i class="fas fa-sliders-h mr-1"></i> Update Progres
                                                        </button>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                Belum ada program kerja pada periode ini.
                                                @if ($lainPeriode)
                                                    <div class="mt-1">Ada <strong>{{ $lainPeriode }}</strong> program kerja di periode lain — pilih <em>Semua periode</em> untuk melihatnya.</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($actionPlans->hasPages())
                            <div class="d-flex justify-content-between align-items-center flex-wrap px-3 pt-3">
                                <small class="text-muted mb-2">Menampilkan {{ $actionPlans->firstItem() }}–{{ $actionPlans->lastItem() }} dari {{ $actionPlans->total() }}</small>
                                {{ $actionPlans->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Penyunting progres: modal, supaya tabelnya tidak melar dan angkanya terbaca jelas --}}
    @if ($canWrite && ! $periodClosed && $editingPlan)
        @php($statusBaru = $editProgress >= 100 ? 'Selesai' : ($editProgress > 0 ? 'On Progress' : 'Belum mulai'))
        <div class="modal show d-block modal-lw" tabindex="-1" role="dialog" aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-hd">
                        <span class="modal-hd-icon"><i class="fas fa-sliders-h"></i></span>
                        <div>
                            <h5 class="modal-title">Perbarui progres</h5>
                            <small>{{ $editingPlan->owner_dept }} · {{ $editingPlan->period ? period_label($editingPlan->period) : '—' }}</small>
                        </div>
                        <button type="button" class="modal-close" wire:click="cancelEdit" aria-label="Tutup"><i class="fas fa-xmark"></i></button>
                    </div>

                    <div class="modal-body">
                        <p class="font-weight-bold mb-1">{{ $editingPlan->title }}</p>
                        @if ($editingPlan->objective)
                            <p class="small text-muted"><i class="fas fa-link mr-1"></i> Memitigasi KPI {{ $editingPlan->objective->kpi_code }}</p>
                        @endif

                        <div class="text-center my-3">
                            <span class="progres-angka">{{ $editProgress }}%</span>
                        </div>

                        <div class="progress progress-sm mb-3">
                            <div class="progress-bar {{ $editProgress >= 100 ? 'bg-success' : ($editProgress >= 50 ? 'bg-warning' : 'bg-danger') }}"
                                 style="width: {{ max(0, min(100, (int) $editProgress)) }}%"></div>
                        </div>

                        <div class="form-group">
                            <label for="progresGeser" class="small font-weight-bold">Geser untuk mengubah</label>
                            {{-- .live: angkanya ikut bergerak saat digeser, bukan tetap 0% sampai disimpan --}}
                            <input type="range" min="0" max="100" step="5" id="progresGeser"
                                   wire:model.live="editProgress" class="custom-range">
                        </div>

                        <div class="form-row align-items-end">
                            <div class="form-group col-5">
                                <label for="progresAngka" class="small font-weight-bold">atau ketik angkanya</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" min="0" max="100" id="progresAngka"
                                           wire:model.live="editProgress" class="form-control text-right">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                            </div>
                            <div class="form-group col-7 text-right">
                                @foreach ([0, 25, 50, 75, 100] as $cepat)
                                    <button type="button" wire:click="$set('editProgress', {{ $cepat }})"
                                            class="btn btn-xs btn-ghost">{{ $cepat }}%</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="callout callout-info py-2 mb-0 small">
                            Setelah disimpan, statusnya menjadi <strong>{{ $statusBaru }}</strong>.
                            Progres ini ikut menentukan skor Tingkat 4 pada periode
                            {{ $editingPlan->period ? period_label($editingPlan->period) : 'program kerja ini' }}.
                        </div>
                    </div>

                    <div class="modal-ft">
                        <button type="button" wire:click="cancelEdit" class="btn btn-ghost">Batal</button>
                        <button type="button" wire:click="updateProgress" class="btn btn-teal">
                            <i class="fas fa-save mr-1"></i> Simpan progres
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
