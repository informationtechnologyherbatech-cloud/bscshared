<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark font-weight-bold">
                        <i class="fas fa-tasks text-teal mr-2"></i> Program Kerja & Inisiatif Strategis (Level 4)
                    </h1>
                    <small class="text-muted">Eksekusi inisiatif perbaikan operasional pendukung pencapaian KPI dan Rasio Finansial</small>
                </div>
                <div class="col-sm-6 text-right">
                    @can('manage actionplans')
                    <button wire:click="openCreateModal" class="btn btn-teal btn-sm shadow-sm font-weight-bold">
                        <i class="fas fa-plus-circle mr-1"></i> Tambah Inisiatif Program
                    </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if(session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <!-- 1. SUMMARY STATISTIC CARDS -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white shadow-sm border border-left-teal">
                        <div class="inner p-3">
                            <h3 class="text-teal font-weight-bold mb-1">{{ $totalPlans }}</h3>
                            <p class="text-muted text-uppercase font-weight-bold mb-0" style="font-size: 12px;">Total Program Kerja</p>
                            <small class="text-muted">Rata-rata Progres: <strong>{{ $avgProgress }}%</strong></small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clipboard-list text-muted opacity-25" style="top: 15px; font-size: 55px;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white shadow-sm border border-left-success">
                        <div class="inner p-3">
                            <h3 class="text-success font-weight-bold mb-1">{{ $completedPlans }}</h3>
                            <p class="text-muted text-uppercase font-weight-bold mb-0" style="font-size: 12px;">Selesai (Completed)</p>
                            <small class="text-success font-weight-bold"><i class="fas fa-check-double mr-1"></i>Tuntas 100%</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle text-success opacity-25" style="top: 15px; font-size: 55px;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white shadow-sm border border-left-primary">
                        <div class="inner p-3">
                            <h3 class="text-primary font-weight-bold mb-1">{{ $inProgressPlans }}</h3>
                            <p class="text-muted text-uppercase font-weight-bold mb-0" style="font-size: 12px;">Sedang Berjalan</p>
                            <small class="text-primary font-weight-bold"><i class="fas fa-spinner fa-spin mr-1"></i>Progres Aktif</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-cogs text-primary opacity-25" style="top: 15px; font-size: 55px;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-white shadow-sm border border-left-danger">
                        <div class="inner p-3">
                            <h3 class="text-danger font-weight-bold mb-1">{{ $offTargetPlans }}</h3>
                            <p class="text-muted text-uppercase font-weight-bold mb-0" style="font-size: 12px;">Tertunda / Terhambat</p>
                            <small class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Perlu Intervensi</small>
                        </div>
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle text-danger opacity-25" style="top: 15px; font-size: 55px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. FILTER & SEARCH BAR -->
            <div class="card shadow-sm mb-3">
                <div class="card-body p-3 bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white font-weight-bold"><i class="fas fa-calendar-alt text-teal mr-1"></i> Periode</span>
                                </div>
                                <select wire:model.live="selectedPeriod" class="form-control">
                                    @foreach($periods as $p)
                                        <option value="{{ $p->period }}">{{ $p->period }} {{ $p->is_active ? '(Aktif)' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white font-weight-bold"><i class="fas fa-building text-teal mr-1"></i> Dept</span>
                                </div>
                                <select wire:model.live="filterDept" class="form-control">
                                    <option value="">Semua Departemen</option>
                                    @foreach($departments as $d)
                                        <option value="{{ $d->code }}">{{ $d->code }} - {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-white font-weight-bold"><i class="fas fa-filter text-teal mr-1"></i> Status</span>
                                </div>
                                <select wire:model.live="selectedStatus" class="form-control">
                                    <option value="">Semua Status</option>
                                    <option value="Completed">Selesai (Completed)</option>
                                    <option value="On Progress">On Progress</option>
                                    <option value="Off-Target">Tertunda (Off-Target)</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Cari inisiatif / PIC...">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. MAIN TABLE PROGRAM KERJA -->
            <div class="card card-outline card-teal shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-list-check text-teal mr-1"></i> Matriks Tindak Lanjut Program & Anggaran
                    </h3>
                    <div class="card-tools font-weight-bold text-muted small">
                        Total Anggaran: <span class="text-teal font-weight-bold">Rp {{ number_format($totalBudget, 0, ',', '.') }}</span>
                        <span class="mx-2">|</span>
                        Realisasi Biaya: <span class="text-dark font-weight-bold">Rp {{ number_format($totalActualCost, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 8%;">Dept</th>
                                <th style="width: 28%;">Inisiatif / Judul Program</th>
                                <th style="width: 14%;">PIC & Tenggat</th>
                                <th style="width: 10%;" class="text-center">Prioritas</th>
                                <th style="width: 14%;" class="text-right">Anggaran / Realisasi</th>
                                <th style="width: 14%;">Progres Fisik</th>
                                <th style="width: 12%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($actionPlans as $plan)
                                <tr>
                                    <td>
                                        <span class="badge badge-dark px-2 py-1 font-weight-bold">{{ $plan->owner_dept }}</span>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark" style="font-size: 14px;">{{ $plan->title }}</div>
                                        @if($plan->objective)
                                            <div class="mt-1">
                                                <span class="badge badge-light border text-teal">
                                                    <i class="fas fa-bullseye mr-1"></i> {{ $plan->objective->kpi_code }}: {{ Str::limit($plan->objective->kpi_name, 35) }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($plan->notes)
                                            <small class="text-muted d-block mt-1"><i class="fas fa-sticky-note mr-1 text-warning"></i>{{ Str::limit($plan->notes, 60) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-weight-bold text-dark">
                                            <i class="fas fa-user-circle text-muted mr-1"></i> {{ $plan->pic_name ?: 'Tim ' . $plan->owner_dept }}
                                        </div>
                                        <small class="text-muted">
                                            <i class="far fa-calendar-alt mr-1"></i> {{ $plan->due_date ? date('d M Y', strtotime($plan->due_date)) : '-' }}
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        @if($plan->priority === 'High')
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-arrow-up mr-1"></i>Tinggi</span>
                                        @elseif($plan->priority === 'Low')
                                            <span class="badge badge-secondary px-2 py-1">Rendah</span>
                                        @else
                                            <span class="badge badge-warning px-2 py-1">Sedang</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="font-weight-bold text-teal" style="font-size: 13px;">
                                            {{ $plan->budget_formatted }}
                                        </div>
                                        <small class="text-muted">
                                            Real: {{ $plan->actual_cost_formatted }}
                                        </small>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="font-weight-bold small">{{ $plan->progress_pct }}%</span>
                                            @if($plan->status === 'Completed')
                                                <span class="badge badge-success px-1" style="font-size: 10px;">SELESAI</span>
                                            @elseif($plan->status === 'On Progress')
                                                <span class="badge badge-primary px-1" style="font-size: 10px;">BERJALAN</span>
                                            @else
                                                <span class="badge badge-danger px-1" style="font-size: 10px;">OFF-TARGET</span>
                                            @endif
                                        </div>
                                        <div class="progress progress-xs">
                                            <div class="progress-bar {{ $plan->progress_pct >= 100 ? 'bg-success' : ($plan->progress_pct >= 60 ? 'bg-teal' : 'bg-danger') }}" 
                                                 style="width: {{ $plan->progress_pct }}%"></div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button wire:click="editProgressModal({{ $plan->id }})" class="btn btn-xs btn-outline-teal" title="Update Progres Cepat">
                                                <i class="fas fa-sliders-h"></i>
                                            </button>
                                            @can('manage actionplans')
                                            <button wire:click="openEditModal({{ $plan->id }})" class="btn btn-xs btn-outline-info" title="Ubah Detail">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button wire:confirm="Yakin ingin menghapus program kerja ini?" wire:click="deletePlan({{ $plan->id }})" class="btn btn-xs btn-outline-danger" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-clipboard-check fa-3x text-muted opacity-25 mb-3 d-block"></i>
                                        Tidak ada program kerja yang sesuai filter pada periode <strong>{{ $selectedPeriod }}</strong>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>

    <!-- 4. MODAL CREATE / EDIT DETAIL PROGRAM KERJA -->
    @if($showModal)
    <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-teal text-white">
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas {{ $isEditMode ? 'fa-edit' : 'fa-plus-circle' }} mr-2"></i>
                        {{ $isEditMode ? 'Ubah Program Kerja & Inisiatif' : 'Tambah Program Kerja Baru' }}
                    </h5>
                    <button type="button" wire:click="closeModal" class="close text-white" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form wire:submit.prevent="savePlan">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="font-weight-bold">Judul Inisiatif / Program Kerja <span class="text-danger">*</span></label>
                                <input type="text" wire:model="title" class="form-control" placeholder="Contoh: Optimasi Efisiensi Energi Mesin Blow Molding Line 3">
                                @error('title') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Departemen Pelaksana <span class="text-danger">*</span></label>
                                <select wire:model="ownerDept" class="form-control">
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->code }}">{{ $dept->code }} - {{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                @error('ownerDept') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Terkait Sasaran Mutu / KPI (Opsional)</label>
                                <select wire:model="objectiveId" class="form-control">
                                    <option value="">-- Tanpa Kaitan Langsung --</option>
                                    @foreach($offTargetObjectives as $obj)
                                        <option value="{{ $obj->id }}">[{{ $obj->dept_code }}] {{ $obj->kpi_code }} - {{ Str::limit($obj->kpi_name, 35) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">PIC / Penanggung Jawab</label>
                                <input type="text" wire:model="picName" class="form-control" placeholder="Contoh: Budi Santoso">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Tenggat Waktu (Due Date)</label>
                                <input type="date" wire:model="dueDate" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="font-weight-bold">Tingkat Prioritas</label>
                                <select wire:model="priority" class="form-control">
                                    <option value="High">Tinggi (High Priority)</option>
                                    <option value="Medium">Sedang (Medium Priority)</option>
                                    <option value="Low">Rendah (Low Priority)</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Estimasi Anggaran (Rp)</label>
                                <input type="number" step="1000" wire:model="budgetEstimate" class="form-control" placeholder="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Realisasi Biaya Aktual (Rp)</label>
                                <input type="number" step="1000" wire:model="actualCost" class="form-control" placeholder="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Progres Fisik (%): <span class="text-teal font-weight-bold">{{ $progressPct }}%</span></label>
                                <input type="range" min="0" max="100" wire:model.live="progressPct" class="form-control-range">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="font-weight-bold">Catatan & Rencana Tindak Lanjut</label>
                                <textarea wire:model="notes" rows="2" class="form-control" placeholder="Tuliskan catatan teknis atau kendala..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" wire:click="closeModal" class="btn btn-secondary btn-sm">Batal</button>
                        <button type="submit" class="btn btn-teal btn-sm font-weight-bold px-4">
                            <i class="fas fa-save mr-1"></i> {{ $isEditMode ? 'Simpan Perubahan' : 'Tambahkan Program' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- 5. MODAL UPDATE CEPAT PROGRES FISIK -->
    @if($showProgressModal)
    <div class="modal fade show d-block" style="background: rgba(0,0,0,0.5);" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-teal text-white py-2">
                    <h6 class="modal-title font-weight-bold">
                        <i class="fas fa-sliders-h mr-1"></i> Update Progres Fisik
                    </h6>
                    <button type="button" wire:click="closeProgressModal" class="close text-white" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="display-4 font-weight-bold text-teal mb-3">
                        {{ $editProgress }}%
                    </div>
                    <input type="range" min="0" max="100" wire:model.live="editProgress" class="form-control-range mb-3">
                    <div class="d-flex justify-content-between text-muted small font-weight-bold">
                        <span>0% (Mulai)</span>
                        <span>50%</span>
                        <span>100% (Selesai)</span>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2 justify-content-center">
                    <button type="button" wire:click="closeProgressModal" class="btn btn-secondary btn-xs px-3">Batal</button>
                    <button type="button" wire:click="updateProgress" class="btn btn-teal btn-xs px-3 font-weight-bold">
                        <i class="fas fa-check mr-1"></i> Simpan Progres
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

