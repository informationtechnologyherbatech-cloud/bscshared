<div>
    <style>
        .doc-markdown h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: .75rem; }
        .doc-markdown h2 { font-size: 1.25rem; font-weight: 700; margin-top: 1.75rem; padding-top: .75rem; border-top: 1px solid #e5e7eb; }
        .doc-markdown h3 { font-size: 1.05rem; font-weight: 700; margin-top: 1.1rem; }
        .doc-markdown table { width: 100%; margin: .75rem 0; border-collapse: collapse; font-size: .9rem; }
        .doc-markdown th, .doc-markdown td { border: 1px solid #dee2e6; padding: .4rem .6rem; vertical-align: top; }
        .doc-markdown th { background: #f4f6f9; }
        .doc-markdown blockquote { border-left: 4px solid #17a2b8; background: #f0f9fb; padding: .6rem .9rem; margin: .75rem 0; }
        .doc-markdown blockquote p { margin: 0; }
        .doc-markdown code { background: #f4f6f9; padding: 0 .25rem; border-radius: 3px; }
        .doc-markdown hr { display: none; }
    </style>

    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-book-open mr-2 text-teal"></i>Dokumentasi Metode</h1>
            <small class="text-muted">Panduan pengisian, rumus skoring tiap tingkat piramida, dan uji mandiri mesin perhitungan.</small>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary card-outline card-tabs">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a wire:click="switchTab('panduan')" class="nav-link {{ $tab === 'panduan' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-route mr-1"></i> Panduan Pengisian
                            </a>
                        </li>
                        <li class="nav-item">
                            <a wire:click="switchTab('metode')" class="nav-link {{ $tab === 'metode' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-square-root-alt mr-1"></i> Metode Skoring
                            </a>
                        </li>
                        <li class="nav-item">
                            <a wire:click="switchTab('uji')" class="nav-link {{ $tab === 'uji' ? 'active' : '' }}" href="#" role="tab">
                                <i class="fas fa-vial mr-1"></i> Uji Mandiri
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    @if ($tab === 'panduan')
                        @if ($panduan)
                            <div class="doc-markdown">{!! $panduan !!}</div>
                        @else
                            <div class="text-muted">Berkas <code>docs/panduan-pengisian.md</code> tidak ditemukan.</div>
                        @endif

                    @elseif ($tab === 'metode')
                        <div class="doc-markdown">
                            <p class="text-muted">Tabel di halaman ini dibaca langsung dari kode aplikasi, sehingga selalu sama dengan perhitungan yang berjalan.</p>

                            <h2>Skor puncak</h2>
                            <p><strong>Skor puncak = {{ $apex['revenue'] * 100 }}% × F1 + {{ $apex['ratios'] * 100 }}% × F2.</strong>
                                Tingkat yang belum punya data dikeluarkan, dan bobotnya dibagi ke tingkat yang tersedia, sehingga skor tidak jatuh semu.
                                Tingkat 3 dan 4 tidak masuk rumus ini: keduanya menggerakkan rasio lewat pos akun, jadi pengaruhnya sudah tercermin di F2.</p>

                            <h2>Tingkat 1 — Revenue (F1)</h2>
                            <p>F1 = Σ realisasi ÷ Σ target, Januari s.d. bulan periode (kumulatif), dibatasi 100%.
                                F1 kosong — bukan 0% — bila belum ada target bulanan atau belum ada realisasi sama sekali.</p>
                            <p>Penyusunan target setahun (Perencanaan Target): estimasi run-rate YTD × 12 ÷ n · CAGR · regresi linear ·
                                bottom-up brand × channel × (1 + growth) · Ansoff (revenue × probabilitas) · koreksi SWOT.
                                Fasing bulanan memakai indeks musiman = realisasi bulan tahun dasar ÷ estimasi akhir tahun; bulan tanpa realisasi berbagi rata sisa indeks.</p>

                            <h2>Tingkat 2 — Rasio Keuangan (F2)</h2>
                            <h3>1. Pos akun → nilai dipakai</h3>
                            <table>
                                <thead><tr><th>Kode</th><th>Pos akun</th><th>Jenis</th><th>Nilai dipakai</th></tr></thead>
                                <tbody>
                                    @foreach ($posts as $kode => $p)
                                        <tr>
                                            <td><code>{{ $kode }}</code></td>
                                            <td>{{ $p['name'] }}</td>
                                            <td>{{ \App\Support\Bsc\AccountPosts::kindLabel($p['kind']) }}</td>
                                            <td>
                                                @switch($p['kind'])
                                                    @case(\App\Support\Bsc\AccountPosts::ALIRAN) YTD × 12 ÷ bulan berjalan @break
                                                    @case(\App\Support\Bsc\AccountPosts::NERACA) (saldo awal tahun + saldo akhir) ÷ 2 @break
                                                    @case(\App\Support\Bsc\AccountPosts::HRIS_ALIRAN) YTD × 12 ÷ bulan berjalan @break
                                                    @default rata-rata periode, apa adanya
                                                @endswitch
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr><td><code>LK</code></td><td>Laba kotor</td><td>Turunan</td><td>PA01 − PA02</td></tr>
                                    <tr><td><code>LB</code></td><td>Laba bersih</td><td>Turunan</td><td>LK − PA03</td></tr>
                                </tbody>
                            </table>

                            <h3>2. Sembilan belas rasio</h3>
                            <table>
                                <thead><tr><th>Kode</th><th>Rasio</th><th>Kelompok</th><th>Rumus</th><th>Satuan</th><th>Polaritas</th><th>Bobot</th></tr></thead>
                                <tbody>
                                    @foreach ($ratios as $kode => $r)
                                        <tr>
                                            <td><code>{{ $kode }}</code></td><td>{{ $r['name'] }}</td><td>{{ $r['group'] }}</td>
                                            <td>{{ $r['formula'] }}</td><td>{{ $r['unit'] }}</td><td>{{ $r['polarity'] }}</td><td>{{ $r['weight'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p>Bobot kelompok acuan:
                                @foreach ($groups as $g => $b){{ $g }} {{ $b }}@if (! $loop->last) · @endif @endforeach
                                (total 100). Bobot tiap rasio dapat diubah per entitas di Katalog Rasio.</p>

                            <h3>3. Pencapaian (dibatasi 0–100%)</h3>
                            <table>
                                <thead><tr><th>Polaritas</th><th>Rumus pencapaian</th><th>Contoh</th></tr></thead>
                                <tbody>
                                    <tr><td>Naik</td><td>aktual ÷ target</td><td>GPM, ROE, Current Ratio</td></tr>
                                    <tr><td>Turun</td><td>target ÷ aktual</td><td>DIO, DSO, Debt to Equity</td></tr>
                                    <tr><td>Rentang</td><td>1 − |aktual − target| ÷ target</td><td>DPO</td></tr>
                                </tbody>
                            </table>

                            <h3>4. Rubrik → skor tertimbang → F2</h3>
                            <table>
                                <thead><tr><th>Pencapaian minimal</th><th>Skor rubrik</th></tr></thead>
                                <tbody>
                                    @foreach ($rubric as $min => $skor)
                                        <tr><td>{{ $min > 0 ? '≥ '.$min.'%' : '< '.collect($rubric)->keys()->filter()->min().'%' }}</td><td>{{ $skor }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p>Skor tertimbang = rubrik × bobot ÷ 100. <strong>F2 = Σ skor tertimbang ÷ Σ bobot rasio terskor × 100</strong>.
                                Rasio tanpa data atau target dikeluarkan, sehingga F2 tetap berskala 0–100 dan sebanding antar entitas.</p>

                            <h2>Tingkat 3 — KPI & Sasaran Mutu</h2>
                            <p>Skor Tingkat 3 = rata-rata pencapaian sasaran mutu periode itu (Objective Departemen). Pencapaian tiap sasaran mengikuti polaritasnya,
                                sama dengan rasio (Naik / Turun / Rentang, maks 100%).</p>
                            <p>Sasaran berasal dari KPI Cascade yang lolos validasi: Head (lag) → Supervisor (lead) → Staff (output), Σ bobot tiap jabatan 100%.
                                Target disesuaikan = target × (1 + elastisitas × (faktor revisi revenue − 1)); KPI guardrail (elastisitas 0) tidak berubah.</p>
                            <p>Uji A: Driver lolos bila 8 jawaban Ya (7 Ya = revisi minor); Guardrail cukup Q5, Q7, Q8.
                                Uji B: pos akun digeser sebesar % perbaikan × koefisien transmisi, lalu 19 rasio dinilai ulang; KPI lolos bila rasio yang diklaim bergerak ke arah baik.</p>

                            <h2>Tingkat 4 — Program Kerja</h2>
                            <p>Skor Tingkat 4 = rata-rata progres (%) program kerja yang tertaut ke sasaran mutu periode itu.
                                Program kerja tanpa sasaran tidak dapat diatribusikan ke periode mana pun, sehingga tidak ikut diskor.</p>

                            <h2>Status warna</h2>
                            <table>
                                <thead><tr><th>Status</th><th>Arti</th></tr></thead>
                                <tbody>
                                    <tr><td><x-status-badge status="Tercapai" /></td><td>pencapaian ≥ 100%</td></tr>
                                    <tr><td><x-status-badge status="Waspada" /></td><td>pencapaian 80–99%</td></tr>
                                    <tr><td><x-status-badge status="Di Bawah Target" /></td><td>pencapaian &lt; 80%</td></tr>
                                    <tr><td><x-status-badge status="Belum Ada Target" /></td><td>nilai ada, target belum diisi</td></tr>
                                </tbody>
                            </table>
                            <p class="small text-muted">Sumber metodologi: workbook <code>Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx</code>. Penerapannya per sheet dirinci di
                                <code>docs/penerapan-metodologi-excel.md</code>.</p>
                        </div>

                    @else
                        <p>Uji mandiri menjalankan <strong>mesin perhitungan yang sama</strong> dengan yang dipakai aplikasi terhadap angka ilustrasi workbook,
                            lalu membandingkannya dengan hasil Excel. Tidak membaca maupun mengubah data perusahaan.</p>
                        <button wire:click="runSelfTest" class="btn btn-primary mb-3"><i class="fas fa-play mr-1"></i> Jalankan uji mandiri</button>

                        @if ($selfTest)
                            @php($lolos = collect($selfTest)->where('ok', true)->count())
                            <div class="alert {{ $lolos === count($selfTest) ? 'alert-success' : 'alert-danger' }}">
                                <i class="fas {{ $lolos === count($selfTest) ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i>
                                <strong>{{ $lolos }} dari {{ count($selfTest) }}</strong> pemeriksaan sesuai workbook.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="bg-light"><tr><th>#</th><th>Pemeriksaan</th><th>Aturan</th><th class="text-right">Excel</th><th class="text-right">Aplikasi</th><th class="text-center">Hasil</th></tr></thead>
                                    <tbody>
                                        @foreach ($selfTest as $t)
                                            <tr class="{{ $t['ok'] ? '' : 'table-danger' }}">
                                                <td>{{ $t['no'] }}</td>
                                                <td class="font-weight-bold">{{ $t['name'] }}</td>
                                                <td class="small text-muted">{{ $t['rule'] }}</td>
                                                <td class="text-right">{{ $t['expected'] }}</td>
                                                <td class="text-right">{{ $t['actual'] }}</td>
                                                <td class="text-center">{!! $t['ok'] ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>' !!}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
