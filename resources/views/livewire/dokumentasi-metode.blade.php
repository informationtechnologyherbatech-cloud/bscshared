<div class="px-2 py-3 px-md-3">
    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-book-open text-info mr-2"></i> Dokumentasi & Panduan Interaktif Metode BSC
            </h2>
            <p class="text-muted mb-0">Rujukan ilmiah rumus skoring polaritas, arsitektur bobot 5 pilar, pohon DuPont, dan kamus rasio botol plastik.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <span class="badge badge-primary px-3 py-2 font-weight-bold">
                <i class="fas fa-shield-alt mr-1"></i> Standar Formula BSC PRD §4
            </span>
        </div>
    </div>

    <!-- NAVIGATION TABS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <button wire:click="setTab('skoring')" class="nav-link font-weight-bold {{ $activeTab === 'skoring' ? 'active bg-info text-white' : 'text-dark' }}">
                        <i class="fas fa-percentage mr-1"></i> 1. Skoring & Polaritas (0-100%)
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('pilar')" class="nav-link font-weight-bold {{ $activeTab === 'pilar' ? 'active bg-info text-white' : 'text-dark' }}">
                        <i class="fas fa-layer-group mr-1"></i> 2. Pembobotan 5 Pilar
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('dupont')" class="nav-link font-weight-bold {{ $activeTab === 'dupont' ? 'active bg-info text-white' : 'text-dark' }}">
                        <i class="fas fa-project-diagram mr-1"></i> 3. Struktur Pohon DuPont
                    </button>
                </li>
                <li class="nav-item">
                    <button wire:click="setTab('kamus')" class="nav-link font-weight-bold {{ $activeTab === 'kamus' ? 'active bg-info text-white' : 'text-dark' }}">
                        <i class="fas fa-wine-bottle mr-1"></i> 4. Kamus 15 Rasio Botol Plastik
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- TAB 1: FORMULA SKORING & POLARITAS -->
    @if($activeTab === 'skoring')
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="font-weight-bold text-dark mb-0">
                            <i class="fas fa-arrow-up text-success mr-2"></i> Polaritas Positif (The Higher The Better)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Digunakan untuk indikator yang semakin tinggi realisasinya, semakin baik kinerja perusahaan (contoh: Revenue, Gross Profit Margin, Net Profit Margin, Asset Turnover).</p>
                        
                        <div class="p-3 bg-light rounded border mb-3">
                            <span class="badge badge-success font-weight-bold mb-2">Rumus Matematika:</span>
                            <div class="h5 font-weight-bold text-dark mb-0 text-monospace">
                                Skor (%) = Min(120%, Max(0%, (Realisasi / Target) × 100%))
                            </div>
                        </div>

                        <h6 class="font-weight-bold text-dark mt-3">Aturan Banding Cap & Floor:</h6>
                        <ul class="text-muted small pl-3">
                            <li><strong>Cap Maksimum 120%:</strong> Mencegah over-achieving satu metrik mendominasi atau mendistorsi skor agregat keseluruhan departemen.</li>
                            <li><strong>Floor Minimum 0%:</strong> Nilai realisasi negatif tetap dibatasi pada 0% untuk menjaga integritas agregasi bobot.</li>
                            <li><strong>Kategori Warna:</strong> Hijau (≥ 100%), Kuning/Waspada (80% - 99.9%), Merah (&lt; 80%).</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="font-weight-bold text-dark mb-0">
                            <i class="fas fa-arrow-down text-danger mr-2"></i> Polaritas Negatif (The Lower The Better)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Digunakan untuk indikator yang semakin rendah realisasinya, semakin efisien kinerja perusahaan (contoh: Biaya COGS, Hari Piutang DSO, Beban Operasional OPEX, Scrap Rate Botol).</p>
                        
                        <div class="p-3 bg-light rounded border mb-3">
                            <span class="badge badge-danger font-weight-bold mb-2">Rumus Matematika:</span>
                            <div class="h5 font-weight-bold text-dark mb-0 text-monospace">
                                Skor (%) = Max(0%, Min(120%, (1 + ((Target - Realisasi) / Target)) × 100%))
                            </div>
                        </div>

                        <h6 class="font-weight-bold text-dark mt-3">Aturan Banding Cap & Floor:</h6>
                        <ul class="text-muted small pl-3">
                            <li>Jika Realisasi = Target, Skor = <strong>100%</strong>.</li>
                            <li>Jika Realisasi 20% lebih hemat daripada Target, Skor = <strong>120% (Cap)</strong>.</li>
                            <li>Jika Realisasi melonjak melampaui 2x Target, Skor menyentuh <strong>0% (Floor)</strong>.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="font-weight-bold text-dark mb-0">
                    <i class="fas fa-compress-arrows-alt text-warning mr-2"></i> Polaritas Range / Nilai Optimal (Target Band)
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Digunakan untuk rasio finansial yang memiliki batas aman ideal atas dan bawah (contoh: <strong>Current Ratio 1.5 - 2.5x</strong>, <strong>DAR 30% - 50%</strong>). Terlalu rendah membahayakan likuiditas, terlalu tinggi menandakan aset menganggur (idle cash).</p>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border border-success">
                            <strong class="text-success d-block"><i class="fas fa-check-circle mr-1"></i> Zona Optimal (100% Skor)</strong>
                            <span class="small text-muted">Realisasi berada di dalam rentang batas min s/d max (Benchmark ideal industri manufaktur).</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border border-warning">
                            <strong class="text-warning d-block"><i class="fas fa-exclamation-circle mr-1"></i> Zona Toleransi (70% - 85% Skor)</strong>
                            <span class="small text-muted">Realisasi meleset &lt; 15% di luar batas batas aman (membutuhkan pemantauan berkala).</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="p-3 bg-white rounded border border-danger">
                            <strong class="text-danger d-block"><i class="fas fa-times-circle mr-1"></i> Zona Kritis (50% Skor)</strong>
                            <span class="small text-muted">Realisasi jauh melampaui toleransi batas atas atau anjlok di bawah ambang likuiditas minimum.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: PEMBOBOTAN 5 PILAR RASIO -->
    @if($activeTab === 'pilar')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="font-weight-bold text-dark mb-0">
                    <i class="fas fa-balance-scale text-primary mr-2"></i> Struktur Bobot 5 Pilar Rasio Keuangan Pabrik Botol Plastik
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">Total kontribusi 5 pilar rasio adalah <strong>55%</strong> dari total Apex BSC Score (bersama <strong>45%</strong> kontribusi pencapaian Omset / Revenue).</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 20%;">Pilar Rasio</th>
                                <th style="width: 15%;" class="text-center">Bobot Standar</th>
                                <th style="width: 35%;">Rasio Pendukung Utama</th>
                                <th style="width: 30%;">Justifikasi Strategis Manufaktur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-weight-bold text-success"><i class="fas fa-coins mr-1"></i> Profitabilitas</td>
                                <td class="text-center font-weight-bold">35.0%</td>
                                <td>GPM, EBIT Margin, NPM, ROA, ROE</td>
                                <td class="small text-muted">Ketahanan margin atas fluktuasi harga biji plastik global dan profitabilitas modal pemilik saham.</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold text-info"><i class="fas fa-tint mr-1"></i> Likuiditas</td>
                                <td class="text-center font-weight-bold">20.0%</td>
                                <td>Current Ratio, Quick Ratio</td>
                                <td class="small text-muted">Kesiapan dana kas tunai untuk melunasi hutang dagang supplier resin dan operasional pabrik.</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold text-warning"><i class="fas fa-sync-alt mr-1"></i> Aktivitas</td>
                                <td class="text-center font-weight-bold">15.0%</td>
                                <td>ITO, AR Turnover, DSO, DIO, DPO, TATO</td>
                                <td class="small text-muted">Kecepatan perputaran inventory botol di gudang dan kelancaran penagihan invoice pembeli.</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold text-primary"><i class="fas fa-industry mr-1"></i> Produktivitas</td>
                                <td class="text-center font-weight-bold">15.0%</td>
                                <td>Yield Ratio, OEE Mesin, Kapasitas Cetak Mold</td>
                                <td class="small text-muted">Efisiensi lini mesin cetak blow & injection moulding dalam menghasilkan botol tanpa cacat (reject).</td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold text-danger"><i class="fas fa-shield-alt mr-1"></i> Solvabilitas</td>
                                <td class="text-center font-weight-bold">15.0%</td>
                                <td>Debt to Equity Ratio (DER), DAR</td>
                                <td class="small text-muted">Pengendalian rasio hutang jangka panjang terhadap ekuitas untuk menjaga risiko solvabilitas pabrik.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td>TOTAL BOBOT PILAR</td>
                                <td class="text-center text-primary h6 mb-0 font-weight-bold">100.0%</td>
                                <td colspan="2" class="text-muted small">Dikonversikan ke bobot komposit Apex Hop 4 secara proporsional.</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: POHON DUPONT -->
    @if($activeTab === 'dupont')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="font-weight-bold text-dark mb-0">
                    <i class="fas fa-sitemap text-teal mr-2"></i> Dekomposisi Pohon DuPont 3-Tahap
                </h5>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">Pohon DuPont menghubungkan kinerja operasional harian di lantai produksi pabrik botol hingga menjadi imbal hasil ekuitas puncak (Return on Equity):</p>
                
                <div class="p-4 bg-light rounded border text-center mb-4">
                    <h3 class="font-weight-bold text-dark mb-3">ROE = Net Profit Margin × Asset Turnover × Financial Leverage</h3>
                    <div class="row justify-content-center">
                        <div class="col-md-3 mb-2">
                            <div class="card p-3 border-info bg-white shadow-sm h-100">
                                <span class="badge badge-info mb-1">Komponen 1</span>
                                <h6 class="font-weight-bold text-dark">Efisiensi Operasional (NPM)</h6>
                                <small class="text-muted">Laba Bersih / Penjualan</small>
                                <span class="small text-primary mt-2">Driver: Efisiensi Resin, Penurunan Reject Botol</span>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-center justify-content-center font-weight-bold h3 text-muted">×</div>
                        <div class="col-md-3 mb-2">
                            <div class="card p-3 border-success bg-white shadow-sm h-100">
                                <span class="badge badge-success mb-1">Komponen 2</span>
                                <h6 class="font-weight-bold text-dark">Efisiensi Penggunaan Aset (TATO)</h6>
                                <small class="text-muted">Penjualan / Total Aset</small>
                                <span class="small text-success mt-2">Driver: Utilisasi Mesin Blow Moulding, Output/Jam</span>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex align-items-center justify-content-center font-weight-bold h3 text-muted">×</div>
                        <div class="col-md-3 mb-2">
                            <div class="card p-3 border-warning bg-white shadow-sm h-100">
                                <span class="badge badge-warning text-dark mb-1">Komponen 3</span>
                                <h6 class="font-weight-bold text-dark">Leverage Keuangan (EM)</h6>
                                <small class="text-muted">Total Aset / Total Ekuitas</small>
                                <span class="small text-warning mt-2">Driver: Struktur Modal, Pengendalian Hutang Capex</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info border-info mb-0">
                    <i class="fas fa-info-circle mr-1"></i> Untuk simulasi interaktif transmisi parameter DuPont secara live, silakan kunjungi menu <a href="{{ route('pohon-dupont') }}" class="font-weight-bold text-info text-underline">Pohon Nilai & Driver DuPont</a>.
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: KAMUS 15 RASIO INDUSTRI BOTOL PLASTIK -->
    @if($activeTab === 'kamus')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center">
                <h5 class="font-weight-bold text-dark mb-2 mb-md-0">
                    <i class="fas fa-book text-success mr-2"></i> Kamus 15 Rasio Keuangan Pabrik Botol Plastik
                </h5>
                <div style="width: 280px;">
                    <input type="text" wire:model.live.debounce.300ms="searchQuery" class="form-control form-control-sm" placeholder="Cari rasio, kategori, formula...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 22%;">Nama Rasio & Kategori</th>
                                <th style="width: 28%;">Formula / Rumus Hitung</th>
                                <th style="width: 15%;" class="text-center">Standar Benchmark</th>
                                <th style="width: 35%;">Konteks Manufaktur Botol Plastik</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kamusRasio as $k)
                                <tr>
                                    <td>
                                        <strong class="text-dark d-block">{{ $k['name'] }}</strong>
                                        <span class="badge {{ $k['category'] === 'Profitabilitas' ? 'badge-success' : ($k['category'] === 'Likuiditas' ? 'badge-info' : ($k['category'] === 'Aktivitas' ? 'badge-warning text-dark' : 'badge-danger')) }}">
                                            {{ $k['category'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <code class="text-primary font-weight-bold">{{ $k['formula'] }}</code>
                                        <div class="small text-muted mt-1">Polaritas: {{ $k['polarity'] }}</div>
                                    </td>
                                    <td class="text-center font-weight-bold text-dark">
                                        {{ $k['benchmark'] }}
                                    </td>
                                    <td class="small text-muted">
                                        {{ $k['industry_context'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="fas fa-search mr-1"></i> Tidak ditemukan rasio yang cocok dengan kata kunci pencarian.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
