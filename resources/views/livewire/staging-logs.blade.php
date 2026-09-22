<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-clipboard-list text-teal mr-2"></i> Staging & Audit Log Integrasi Hop 4
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if(session()->has('message'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle mr-1"></i> {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <!-- TELEMETRY & RECONCILIATION SUMMARY WIDGET (PRD G-04) -->
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-light shadow-sm border">
                        <span class="info-box-icon bg-info"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small">Control Total Match</span>
                            <span class="info-box-number text-dark" style="font-size: 14px;">{{ $reconciliationMetrics['control_total'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-light shadow-sm border">
                        <span class="info-box-icon bg-teal"><i class="fas fa-key"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small">Integritas Hash (Idempotency)</span>
                            <span class="info-box-number text-dark" style="font-size: 14px;">{{ $reconciliationMetrics['hash_integrity'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-light shadow-sm border">
                        <span class="info-box-icon bg-primary"><i class="fas fa-tasks"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small">Kelengkapan Data Periode</span>
                            <span class="info-box-number text-dark" style="font-size: 14px;">{{ $reconciliationMetrics['completeness'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box bg-light shadow-sm border">
                        <span class="info-box-icon bg-success"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted small">Kesegaran Data (26 Jam)</span>
                            <span class="info-box-number text-dark" style="font-size: 13px;">{{ $reconciliationMetrics['freshness'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-paper-plane mr-1"></i> Simulasi Inbound API (Hop 3 &rarr; Hop 4)
                            </h3>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Mengirimkan skor objective baru secara otomatis dari HRIS/Finance ERP ke Hop 4 dengan <code>idempotency_key</code> unik.
                            </p>
                            <div class="form-group">
                                <label>Pilih Departemen Pengirim</label>
                                <select wire:model="simulatedDept" class="form-control form-control-sm">
                                    @foreach($units as $u)
                                        <option value="{{ $u->code }}">{{ $u->code }} - {{ \Illuminate\Support\Str::limit($u->name, 40) }}</option>
                                    @endforeach
                                </select>
                                @error('simulatedDept') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            @can('manage integration')
                            <button wire:click="simulateInbound" class="btn btn-info btn-sm btn-block">
                                <i class="fas fa-satellite-dish mr-1"></i> Kirim Payload Simulasi
                            </button>
                            @else
                            <div class="alert alert-secondary py-2 mb-0 small">
                                <i class="fas fa-lock mr-1"></i> Peran Anda hanya dapat memantau log, tidak mengirim payload.
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-history mr-1"></i> Riwayat Staging & Audit Log
                            </h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-hover table-bordered m-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="width: 10%;">Periode</th>
                                        <th style="width: 10%;">Dept</th>
                                        <th style="width: 30%;">Idempotency Key</th>
                                        <th class="text-center" style="width: 15%;">Status</th>
                                        <th class="text-center" style="width: 10%;">Versi</th>
                                        <th style="width: 25%;">Pesan Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        <tr>
                                            <td><span class="badge badge-light border">{{ $log->period }}</span></td>
                                            <td><span class="badge badge-dark">{{ $log->dept_code }}</span></td>
                                            <td><code>{{ $log->idempotency_key }}</code></td>
                                            <td class="text-center">
                                                @if($log->status === 'SCORED')
                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> SCORED</span>
                                                @elseif($log->status === 'DELIVERED')
                                                    <span class="badge badge-info px-2 py-1"><i class="fas fa-inbox mr-1"></i> DELIVERED</span>
                                                @elseif($log->status === 'SUPERSEDED')
                                                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-history mr-1"></i> SUPERSEDED</span>
                                                @else
                                                    <span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> ERROR</span>
                                                @endif
                                            </td>
                                            <td class="text-center font-weight-bold">v{{ $log->source_version }}</td>
                                            <td><small class="text-muted">{{ $log->message }}</small></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat staging log.</td>
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
