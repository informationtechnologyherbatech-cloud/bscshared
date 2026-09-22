<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-tasks text-danger mr-2"></i> Program Kerja / Action Plan (Level 4)
                    </h1>
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

            <div class="row">
                <!-- Form Tambah Program Kerja -->
                <div class="col-md-4">
                    <div class="card card-outline card-danger">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-plus-circle mr-1"></i> Tambah Inisiatif Perbaikan
                            </h3>
                        </div>
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
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm btn-block">
                                    <i class="fas fa-save mr-1"></i> Simpan Program Kerja
                                </button>
                            </form>
                        </div>
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
                                            <td>
                                                <div class="font-weight-bold text-dark">{{ $plan->title }}</div>
                                                @if($plan->objective)
                                                    <small class="text-muted"><i class="fas fa-link mr-1"></i> KPI: {{ $plan->objective->kpi_code }}</small>
                                                @endif
                                            </td>

                                            @if($editingPlanId === $plan->id)
                                                <td colspan="2" class="align-middle">
                                                    <div class="d-flex align-items-center">
                                                        <input type="range" min="0" max="100" wire:model="editProgress" class="form-control-range mr-2">
                                                        <span class="font-weight-bold text-teal" style="min-width: 45px;">{{ $editProgress }}%</span>
                                                    </div>
                                                </td>
                                                <td class="text-center align-middle">
                                                    <button wire:click="updateProgress" class="btn btn-xs btn-success mr-1" title="Simpan Progress"><i class="fas fa-check"></i></button>
                                                    <button wire:click="cancelEdit" class="btn btn-xs btn-secondary" title="Batal"><i class="fas fa-times"></i></button>
                                                </td>
                                            @else
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
                                                    <button wire:click="editProgressModal({{ $plan->id }})" class="btn btn-xs btn-outline-danger">
                                                        <i class="fas fa-sliders-h mr-1"></i> Update Progres
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Belum ada program kerja yang terdaftar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>
