<div>
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-8">
                    <h1 class="m-0 text-dark">
                        <i class="fas fa-clipboard-list text-teal mr-2"></i> Staging &amp; Audit Log
                    </h1>
                    <small class="text-muted">
                        Jejak setiap data yang <strong>masuk</strong> ke entitas ini — tarikan Odoo, unggahan berkas,
                        payload KPI, dan pengisian pos akun dari layar Integrasi. Halaman ini hanya membaca.
                    </small>
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

            {{-- Ringkasan: hanya angka yang benar-benar dapat berubah. --}}
            <div class="row mb-3">
                @php
                    $kartu = [
                        'masuk' => ['Kiriman masuk', 'fa-inbox'],
                        'gagal' => ['Ditolak', 'fa-circle-exclamation'],
                        'kelengkapan' => ['Kelengkapan KPI periode', 'fa-list-check'],
                        'terakhir' => ['Kiriman terakhir', 'fa-clock'],
                    ];
                @endphp
                @foreach($kartu as $kunci => [$judul, $ikon])
                    <div class="col-md-3 col-sm-6">
                        <div class="info-box bg-light shadow-sm border h-100">
                            <span class="info-box-icon bg-{{ $ringkasan[$kunci]['nada'] }}"><i class="fas {{ $ikon }}"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text text-muted small">{{ $judul }}</span>
                                <span class="info-box-number text-dark" style="font-size: 15px;">{{ $ringkasan[$kunci]['nilai'] }}</span>
                                <small class="text-muted">{{ $ringkasan[$kunci]['keterangan'] }}</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">
                        <i class="fas fa-history mr-1"></i> Riwayat kiriman
                    </h3>
                </div>

                {{-- Penyaring: log tumbuh terus, jadi harus dapat dipersempit. --}}
                <div class="card-body pb-2">
                    <div class="form-row align-items-end">
                        <div class="col-md-4 mb-2">
                            <label for="filterCari" class="small font-weight-bold mb-1">Cari pesan atau penanda</label>
                            <input type="search" id="filterCari" wire:model.live.debounce.400ms="cari" class="form-control form-control-sm"
                                   placeholder="mis. Odoo, ditolak, IDEMP-…">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="filterPeriode" class="small font-weight-bold mb-1">Periode</label>
                            <select id="filterPeriode" wire:model.live="periode" class="form-control form-control-sm">
                                <option value="">Semua periode</option>
                                @foreach($daftarPeriode as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="filterUnit" class="small font-weight-bold mb-1">Unit kerja</label>
                            <select id="filterUnit" wire:model.live="unit" class="form-control form-control-sm">
                                <option value="">Semua unit</option>
                                <option value="FIN">FIN - Keuangan (pos akun)</option>
                                <option value="BATCH">BATCH - Unggahan massal</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->code }}">{{ $u->code }} - {{ \Illuminate\Support\Str::limit($u->name, 30) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label for="filterStatus" class="small font-weight-bold mb-1">Status</label>
                            <select id="filterStatus" wire:model.live="status" class="form-control form-control-sm">
                                <option value="">Semua status</option>
                                @foreach($daftarStatus as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 mb-2">
                            @if($adaSaringan)
                                <button type="button" wire:click="bersihkanSaringan" class="btn btn-ghost btn-sm btn-block"
                                        title="Tampilkan semua lagi">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-bordered m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 15%;">Waktu</th>
                                <th style="width: 9%;">Periode</th>
                                <th style="width: 9%;">Unit</th>
                                <th class="text-center" style="width: 12%;">Status</th>
                                <th style="width: 20%;">Penanda (idempotency)</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>
                                        {{-- Waktu adalah kolom terpenting sebuah jejak audit. --}}
                                        <span class="d-block">{{ $log->created_at?->translatedFormat('d M Y, H:i') }}</span>
                                        <small class="text-muted">{{ $log->created_at?->diffForHumans() }}</small>
                                    </td>
                                    <td><span class="badge badge-light border">{{ $log->period }}</span></td>
                                    <td><span class="badge badge-dark">{{ $log->dept_code }}</span></td>
                                    <td class="text-center">
                                        @if($log->status === 'SCORED')
                                            <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Diterima</span>
                                        @elseif($log->status === 'DELIVERED')
                                            <span class="badge badge-info px-2 py-1"><i class="fas fa-inbox mr-1"></i> Masuk antrean</span>
                                        @elseif($log->status === 'SUPERSEDED')
                                            <span class="badge badge-secondary px-2 py-1"><i class="fas fa-history mr-1"></i> Digantikan</span>
                                        @else
                                            <span class="badge badge-danger px-2 py-1"><i class="fas fa-exclamation-triangle mr-1"></i> Ditolak</span>
                                        @endif
                                    </td>
                                    <td><code class="small">{{ $log->idempotency_key }}</code></td>
                                    <td><small class="text-muted">{{ $log->message }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        @if($adaSaringan)
                                            Tidak ada kiriman yang cocok dengan saringan ini.
                                            <button type="button" wire:click="bersihkanSaringan" class="btn btn-ghost btn-xs ml-1">
                                                Tampilkan semua
                                            </button>
                                        @else
                                            Belum ada kiriman yang tercatat. Data masuk lewat menu
                                            <strong>Integrasi &amp; Gateway</strong>: tarikan Odoo, unggahan berkas, atau pengisian pos akun.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                    <div class="d-flex justify-content-between align-items-center flex-wrap px-3 pt-3">
                        <small class="text-muted mb-2">Menampilkan {{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari {{ $logs->total() }}</small>
                        {{ $logs->links() }}
                    </div>
                @endif
                <div class="card-footer small text-muted py-2">
                    <i class="fas fa-circle-info mr-1"></i>
                    <strong>Diterima</strong> = datanya masuk dan rasio/KPI dihitung ulang ·
                    <strong>Ditolak</strong> = tidak ada yang diubah, alasannya tertulis di keterangan ·
                    <strong>Penanda</strong> membuat kiriman yang sama tidak diproses dua kali.
                </div>
            </div>

        </div>
    </section>
</div>
