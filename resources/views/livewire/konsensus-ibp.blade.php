<div class="px-2 py-3 px-md-3">
    <!-- HEADER & PERIODE SELECTION -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-handshake text-success mr-2"></i> Konsensus Rapat IBP (Integrated Business Planning)
            </h2>
            <p class="text-muted mb-0">Modul orkestrasi 5 langkah IBP, keselarasan lintas departemen, dan persetujuan Direksi/Kadep.</p>
        </div>
        <div class="d-flex align-items-center mt-3 mt-md-0">
            <span class="mr-2 font-weight-bold text-muted small">Periode IBP:</span>
            <select wire:model.live="selectedPeriod" class="form-control form-control-sm font-weight-bold border-success" style="width: 140px;">
                @foreach($periods as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
            <button wire:click="saveConsensus" class="btn btn-sm btn-success ml-2 px-3 shadow-sm font-weight-bold">
                <i class="fas fa-save mr-1"></i> Simpan Konsensus
            </button>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('message') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- PROGRESS & READINESS BANNER -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);">
                <div class="card-body p-3">
                    <small class="text-white-50 text-uppercase font-weight-bold">Status Kesiapan IBP</small>
                    <h2 class="font-weight-bold my-1 text-white">{{ $readinessPct }}%</h2>
                    <small class="text-white"><i class="fas fa-tasks mr-1"></i> {{ $approvedCount }} dari 5 Langkah Disetujui</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <div class="card-body p-3">
                    <small class="text-white-50 text-uppercase font-weight-bold">Review Berjalan</small>
                    <h2 class="font-weight-bold my-1 text-white">{{ $reviewCount }} Tahap</h2>
                    <small class="text-white-50">Menunggu Verifikasi Stakeholder</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
            <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                <div class="card-body p-3">
                    <small class="text-white-50 text-uppercase font-weight-bold">Jadwal Rapat MBR</small>
                    <h2 class="font-weight-bold my-1 text-white">{{ date('d M Y', strtotime($meeting_date)) }}</h2>
                    <small class="text-white-50">Forum Eksekutif Direksi</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 text-white h-100" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <small class="text-white-50 text-uppercase font-weight-bold">Aksi Cepat Direksi</small>
                        <h6 class="font-weight-bold my-1 text-white">Full Approval</h6>
                    </div>
                    <button wire:click="approveAllSteps" wire:confirm="Setujui seluruh 5 pilar IBP sekaligus untuk periode ini?" class="btn btn-sm btn-light font-weight-bold text-primary mt-2">
                        <i class="fas fa-check-double mr-1"></i> Approve Semua Tahap
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 5 PILAR IBP WORKFLOW CARDS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-stream text-primary mr-2"></i> 5 Pilar Alur Rapat Integrated Business Planning (IBP)
            </h5>
            <span class="badge badge-light border text-muted px-2 py-1">Siklus Bulanan Terpadu</span>
        </div>
        <div class="card-body p-4">
            
            <div class="timeline-steps">
                <!-- STEP 1: PRODUCT REVIEW -->
                <div class="card border mb-3 shadow-none {{ $product_status === 'approved' ? 'border-success' : ($product_status === 'review' ? 'border-warning' : 'border-light') }}">
                    <div class="card-header py-2 {{ $product_status === 'approved' ? 'bg-success text-white' : ($product_status === 'review' ? 'bg-warning text-dark' : 'bg-light text-dark') }} d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <span class="badge badge-light text-dark mr-2">Langkah 1</span>
                            <i class="fas fa-box-open mr-1"></i> Tinjauan Produk Baru & Portfolio (Product Review)
                        </div>
                        <div class="d-flex align-items-center">
                            <select wire:model.live="product_status" class="form-control form-control-sm border-0 font-weight-bold" style="width: 140px;">
                                <option value="draft">Draft / Persiapan</option>
                                <option value="review">Dalam Review</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="small font-weight-bold text-muted">Ringkasan Keputusan R&D, Mold & Spesifikasi Kemasan:</label>
                                <textarea wire:model="product_notes" rows="2" class="form-control form-control-sm" placeholder="Catatan kesiapan mold baru, sertifikasi botol plastik, gramatur resin..."></textarea>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label class="small font-weight-bold text-muted">Penanggung Jawab (PIC):</label>
                                <input type="text" wire:model="product_pic" class="form-control form-control-sm mb-2" placeholder="Nama / Jabatan PIC">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Meliputi peluncuran SKU baru, discontinuations, dan uji kompresi botol.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: DEMAND REVIEW -->
                <div class="card border mb-3 shadow-none {{ $demand_status === 'approved' ? 'border-success' : ($demand_status === 'review' ? 'border-warning' : 'border-light') }}">
                    <div class="card-header py-2 {{ $demand_status === 'approved' ? 'bg-success text-white' : ($demand_status === 'review' ? 'bg-warning text-dark' : 'bg-light text-dark') }} d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <span class="badge badge-light text-dark mr-2">Langkah 2</span>
                            <i class="fas fa-chart-line mr-1"></i> Tinjauan Permintaan Pasar (Demand Review)
                        </div>
                        <div class="d-flex align-items-center">
                            <select wire:model.live="demand_status" class="form-control form-control-sm border-0 font-weight-bold" style="width: 140px;">
                                <option value="draft">Draft / Persiapan</option>
                                <option value="review">Dalam Review</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="small font-weight-bold text-muted">Konsensus Forecast Permintaan (Sales & Commercial):</label>
                                <textarea wire:model="demand_notes" rows="2" class="form-control form-control-sm" placeholder="Estimasi pesanan pelanggan, tren order botol industri herbal, promo sales..."></textarea>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label class="small font-weight-bold text-muted">Penanggung Jawab (PIC):</label>
                                <input type="text" wire:model="demand_pic" class="form-control form-control-sm mb-2" placeholder="Nama / Jabatan PIC">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Memvalidasi gap antara rencana penjualan dan kapasitas serap pasar.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: SUPPLY REVIEW -->
                <div class="card border mb-3 shadow-none {{ $supply_status === 'approved' ? 'border-success' : ($supply_status === 'review' ? 'border-warning' : 'border-light') }}">
                    <div class="card-header py-2 {{ $supply_status === 'approved' ? 'bg-success text-white' : ($supply_status === 'review' ? 'bg-warning text-dark' : 'bg-light text-dark') }} d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <span class="badge badge-light text-dark mr-2">Langkah 3</span>
                            <i class="fas fa-cogs mr-1"></i> Tinjauan Pasokan & Kapasitas Pabrik (Supply Review)
                        </div>
                        <div class="d-flex align-items-center">
                            <select wire:model.live="supply_status" class="form-control form-control-sm border-0 font-weight-bold" style="width: 140px;">
                                <option value="draft">Draft / Persiapan</option>
                                <option value="review">Dalam Review</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="small font-weight-bold text-muted">Kapasitas Produksi, Stok Resin, Masterbatch & Jam Mesin:</label>
                                <textarea wire:model="supply_notes" rows="2" class="form-control form-control-sm" placeholder="Ketersediaan mesin blow-molding, jadwal maintenance, buffer stock bahan baku..."></textarea>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label class="small font-weight-bold text-muted">Penanggung Jawab (PIC):</label>
                                <input type="text" wire:model="supply_pic" class="form-control form-control-sm mb-2" placeholder="Nama / Jabatan PIC">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Menyelaraskan demand forecast dengan kapasitas lini mesin produksi.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: FINANCIAL RECONCILIATION -->
                <div class="card border mb-3 shadow-none {{ $finance_status === 'approved' ? 'border-success' : ($finance_status === 'review' ? 'border-warning' : 'border-light') }}">
                    <div class="card-header py-2 {{ $finance_status === 'approved' ? 'bg-success text-white' : ($finance_status === 'review' ? 'bg-warning text-dark' : 'bg-light text-dark') }} d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <span class="badge badge-light text-dark mr-2">Langkah 4</span>
                            <i class="fas fa-balance-scale mr-1"></i> Rekonsiliasi Finansial Terpadu (Financial Reconciliation)
                        </div>
                        <div class="d-flex align-items-center">
                            <select wire:model.live="finance_status" class="form-control form-control-sm border-0 font-weight-bold" style="width: 140px;">
                                <option value="draft">Draft / Persiapan</option>
                                <option value="review">Dalam Review</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="small font-weight-bold text-muted">Dampak Finansial, COGS, Cash Flow & Margin Profitabilitas:</label>
                                <textarea wire:model="finance_notes" rows="2" class="form-control form-control-sm" placeholder="Perhitungan HPP resin, proyeksi laba kotor, kecukupan working capital..."></textarea>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label class="small font-weight-bold text-muted">Penanggung Jawab (PIC):</label>
                                <input type="text" wire:model="finance_pic" class="form-control form-control-sm mb-2" placeholder="Nama / Jabatan PIC">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Menilai kepatuhan terhadap batasan anggaran belanja dan target rasio keuangan.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: MANAGEMENT BUSINESS REVIEW -->
                <div class="card border mb-3 shadow-none {{ $mbr_status === 'approved' ? 'border-success' : ($mbr_status === 'review' ? 'border-warning' : 'border-light') }}">
                    <div class="card-header py-2 {{ $mbr_status === 'approved' ? 'bg-success text-white' : ($mbr_status === 'review' ? 'bg-warning text-dark' : 'bg-light text-dark') }} d-flex justify-content-between align-items-center">
                        <div class="font-weight-bold">
                            <span class="badge badge-light text-dark mr-2">Langkah 5</span>
                            <i class="fas fa-stamp mr-1"></i> Rapat Bisnis Manajemen Eksekutif (Management Business Review - MBR)
                        </div>
                        <div class="d-flex align-items-center">
                            <select wire:model.live="mbr_status" class="form-control form-control-sm border-0 font-weight-bold" style="width: 140px;">
                                <option value="draft">Draft / Persiapan</option>
                                <option value="review">Dalam Review</option>
                                <option value="approved">Disetujui</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row">
                            <div class="col-md-8">
                                <label class="small font-weight-bold text-muted">Keputusan Final Direksi & Pengesahan Pagu 12 Bulan:</label>
                                <textarea wire:model="mbr_notes" rows="2" class="form-control form-control-sm" placeholder="Catatan pengesahan Direksi, komitmen alokasi capex mold, persetujuan target BSC..."></textarea>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label class="small font-weight-bold text-muted">Penanggung Jawab (PIC):</label>
                                <input type="text" wire:model="mbr_pic" class="form-control form-control-sm mb-2" placeholder="Nama / Jabatan PIC">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Forum penetapan konsensus tertinggi pengikat seluruh unit kerja.</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- LEMBAR PENGESAHAN & APPROVAL DIREKSI / KADEP -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-file-signature text-success mr-2"></i> Form Persetujuan & Penandatanganan Konsensus IBP
            </h5>
            <span class="badge badge-success px-3 py-1 font-weight-bold">Otorisasi Resmi</span>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="font-weight-bold text-dark small">Ringkasan Kesepakatan Lintas Divisi (Consensus Summary):</label>
                    <textarea wire:model="consensus_summary" rows="4" class="form-control" placeholder="Tuliskan kesimpulan rapat konsensus IBP yang disepakati seluruh kepala departemen..."></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="p-3 bg-light rounded border">
                        <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-user-check text-success mr-1"></i> Pejabat Penandatangan / Pengesah:</h6>
                        <div class="form-group mb-2">
                            <label class="small text-muted font-weight-bold mb-1">Nama Pejabat:</label>
                            <input type="text" wire:model="signer_name" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-2">
                            <label class="small text-muted font-weight-bold mb-1">Jabatan Struktural:</label>
                            <input type="text" wire:model="signer_role" class="form-control form-control-sm">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted font-weight-bold mb-1">Tanggal Rapat / Pengesahan:</label>
                            <input type="date" wire:model="meeting_date" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end border-top pt-3">
                <button wire:click="saveConsensus" class="btn btn-success px-4 py-2 font-weight-bold shadow-sm">
                    <i class="fas fa-check-circle mr-1"></i> Simpan & Sahkan Konsensus IBP
                </button>
            </div>
        </div>
    </div>
</div>
