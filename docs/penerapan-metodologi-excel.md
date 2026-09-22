# Penerapan Metodologi Excel ke Super Apps BSC

Sumber metodologi: **`Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx`** (11 sheet).
Dokumen ini memetakan isi workbook ke aplikasi — apa yang sudah diterapkan,
apa yang berikutnya — sehingga pengembangan tetap mengikuti metodologi, bukan
menebak.

---

## Prinsip: input beragam, output seragam

Super Apps BSC dipakai empat entitas di bawah holding **Erhanesia Mulia Corpora**:

| Kode | Entitas | Jenis usaha |
|---|---|---|
| `HERBAEMAS` | PT Herba Emas Wahidatama | Manufaktur |
| `HERBATECH` | PT Herbatech Innopharma Industry | Manufaktur |
| `AEJ` | PT Abithama Emas Juara | Manufaktur |
| `ERDIGMA` | PT Erhanesia Digima Mukitama | Digital marketing |

Mesin penilaiannya **sama** untuk keempatnya (19 rasio, rubrik skor, rumus
puncak), sedangkan yang berbeda per entitas hanyalah **konfigurasinya**: unit
kerja, dimensi revenue, dan bobot rasio. Dengan begitu holding melihat piramida
dengan bentuk dan arti yang sama untuk keempat entitas.

---

## Peta workbook → aplikasi

| Sheet workbook | Isi | Status di aplikasi |
|---|---|---|
| **Asumsi** — A | Bobot skor puncak F1 0,45 · F2 0,55 | ✅ `config/bsc.php` → `apex_weights` |
| **Asumsi** — E | Daftar unit kerja (17 unit Erdigma) | ✅ Menu **Unit Kerja**, per entitas |
| **L1 Target Revenue** — G | Fasing bulanan & pencapaian kumulatif (F1) | ✅ Menu **Target Revenue** |
| **L1 Target Revenue** — A–F | Estimasi run-rate, CAGR, regresi, bottom-up brand × channel, Ansoff, SWOT, rekonsiliasi & pengesahan | ✅ Menu **Perencanaan Target** |
| **Asumsi** — B, C, D | 19 rasio, bobot 5 kelompok, rubrik 5 tingkat | ✅ Menu **Katalog Rasio**, `config/bsc.php` |
| **Asumsi** — F, G | 16 pos akun & data baseline | ✅ Menu **Pos Akun** |
| **L2 Rasio Keuangan** | Rasio dihitung dari pos akun, skor rubrik × bobot | ✅ `App\Support\Bsc\RatioEngine` |
| **Peta Rasio-Akun-Dept** | Pemilik (O) / Kontributor (K) tiap pos akun | ✅ Menu **Peta Pos Akun** |
| **L3 Cascade KPI**, **Form Sasaran Kinerja** | KPI Head → Supervisor → Staff, bobot per jabatan | ✅ Menu **Cascade KPI** |
| **L4 Uji Indikator** | Uji A (logika) & Uji B (simulasi) | ✅ Menu **Uji Indikator** |
| **Asumsi** — A (B7–B9) | Target revenue disahkan, revisi, faktor revisi | ✅ Menu **Target Revenue** |
| *(di luar workbook)* | Konsolidasi holding & eliminasi antarentitas | ✅ Menu **Konsolidasi Holding** |

---

## Tahap 1 — sudah diterapkan

### Multi-entitas
- Tabel `entities`; seluruh data BSC bertanda `entity_id` dan otomatis
  dibatasi pada entitas yang sedang dibuka (`App\Models\Concerns\BelongsToEntity`).
- Pengguna yang ditautkan ke satu entitas hanya melihat entitas itu. Pengguna
  **level holding** (tanpa entitas) memilih entitas lewat pengalih di navbar.
- Periode kini unik **per entitas** — tiap entitas membuka, mengunci, dan
  menilai periodenya sendiri.
- Data lama dipindahkan ke `HERBATECH` (berisi departemen manufaktur).

### Unit kerja
- Menu **Administrasi → Unit Kerja**: tambah, ubah, nonaktifkan, hapus.
- Erdigma berisi 17 unit dari workbook; entitas manufaktur berisi 11
  departemen dari `dokumentasi-integrasi-hris-finance.md`.
- Kode unit yang sudah dipakai data tidak dapat diubah (cukup ganti nama), dan
  unit yang masih dipakai tidak dapat dihapus (nonaktifkan saja).
- Daftar departemen pada Objective Departemen, Program Kerja, dan Manage User
  kini diambil dari master ini.

### Tingkat 1 — Revenue & skor puncak
- Menu **Target Revenue**: target & realisasi per bulan, dengan bantuan fasing
  (bagi rata, atau ikuti pola musiman tahun lalu seperti sheet L1 bagian G).
- **F1** = Σ realisasi ÷ Σ target Januari s.d. bulan berjalan, dibatasi 100%.
- **Skor puncak = 0,45 × F1 + 0,55 × F2.** Bila salah satu belum punya data,
  bobotnya dinormalisasi ke yang tersedia.
- Tingkat 1 piramida kini **Revenue**; skor puncak gabungan tampil pada kartu
  Apex di atasnya.

> Tingkat 3 (sasaran mutu) dan Tingkat 4 (program kerja) sengaja tidak masuk
> rumus puncak: menurut metodologinya keduanya menggerakkan rasio lewat pos
> akun, sehingga pengaruhnya sudah tercermin di F2.

---

## Tahap 2 — sudah diterapkan

### Pos akun (sheet Asumsi bagian G)
- Menu **Pos Akun**: Finance mengisi 16 pos akun PA01–PA16 per periode.
  - **Aliran** (PA01–PA04): nilai YTD Januari s.d. bulan berjalan, disetahunkan × 12 ÷ n.
  - **Neraca** (PA05–PA14): saldo awal tahun & saldo akhir; dipakai rata-ratanya.
    Saldo awal otomatis terisi dari bulan lain di tahun yang sama.
  - **HRIS**: jumlah karyawan rata-rata dipakai apa adanya; jam kerja disetahunkan.
- Hasil 19 rasio, skor per kelompok, dan F2 tampil langsung sebagai pratinjau
  saat mengetik; **Simpan** menulis rasionya ke menu Rasio Keuangan.
- Periode yang sudah ditutup tidak dapat diubah.

### Katalog rasio (sheet L2 kolom Bobot & Target)
- Menu **Katalog Rasio**, per entitas: aktif/nonaktif tiap rasio, bobotnya, dan
  target tahunan.
- Menampilkan bobot per kelompok terhadap acuan 30/25/20/15/10 dan **cek
  konsistensi target** seperti di workbook (NPM ≤ GPM, ROE ≥ ROA, DIO = 365 ÷ ITO,
  DSO = 365 ÷ ART, Quick ≤ Current, Cash ≤ Quick, DAR ≤ DER, Σ bobot = 100).
- Setelah disimpan, periode di tahun itu yang sudah punya pos akun langsung
  dihitung ulang (kecuali periode yang sudah ditutup).

### Skor F2
- Tiap rasio: capaian menurut polaritas (Naik / Turun / Rentang, maks 100%) →
  rubrik (≥90%→100, ≥80→80, ≥75→70, ≥65→60, selebihnya 50) → skor tertimbang
  = rubrik × bobot ÷ 100. **F2 = Σ skor tertimbang** (skala 0–100).
- Rasio yang belum punya data atau target tidak ikut diskor dan bobotnya
  dinormalisasi, sehingga F2 tetap berskala 0–100 dan sebanding antar entitas.
- Rasio hasil hitungan tidak dapat diedit langsung di menu Rasio Keuangan
  (tombolnya berganti **Otomatis** → Pos Akun). Rasio lama yang diisi manual
  tetap dapat diedit dan dinilai dengan cara lama sampai periodenya diisi pos akun.
- Kebenaran mesin dijaga `tests/Feature/RatioEngineWorkbookTest.php`: dengan data
  ilustrasi workbook, ke-19 rasio, rubrik, skor per kelompok, dan **F2 = 94,1**
  harus sama persis dengan Excel.

---

## Tahap 3 — sudah diterapkan

### Peta pos akun (sheet Peta Rasio-Akun-Dept)
- **Bagian 1** (tetap): pos akun pembentuk tiap rasio (P = pembilang, Y = penyebut),
  di `RatioLibrary::posts()`.
- **Bagian 2** (isian): menu **Peta Pos Akun**, per entitas — tiap unit kerja
  Pemilik (O) / Kontributor (K) / kosong pada 16 pos akun. Hanya Keuangan
  (`manage ratios`) yang dapat mengubah. Cek "tepat satu Pemilik" per pos akun;
  PA01 Penjualan boleh beberapa Pemilik (tiap unit channel memiliki porsinya).
- **Bagian 3** (otomatis): rasio yang boleh diklaim tiap unit.
- Erdigma terisi dari workbook (usulan awal, perlu disahkan CFO). Entitas
  manufaktur **sengaja kosong** — kepemilikan pos akun menentukan siapa dibebani
  target rupiah, jadi harus ditetapkan Keuangan masing-masing entitas.
- Kebenaran dijaga `tests/Feature/PostMapWorkbookTest.php`: 47 sambungan
  bagian 1 dan jumlah rasio yang boleh diklaim ke-17 unit Erdigma harus sama
  dengan workbook.

### Cascade KPI (sheet L3 Cascade KPI & Form Sasaran Kinerja)
- Menu **Cascade KPI**, per tahun: KPI Head (lag) → Supervisor (lead) → Staff
  (output, rutin/milestone), dengan kode KPI induk, jabatan/PIC, brand (BU
  berbasis brand), target & satuan, metode, key initiative, program kerja,
  record, bobot, jenis Driver/Guardrail, elastisitas, rasio (atau REV) & pos
  akun yang digerakkan, arah pengaruh.
- Kode otomatis mengikuti workbook (`SCM-H01`, `SCM-S01`, `SCM-T01`). KPI turunan
  mewarisi unit, brand, rasio, dan pos akun induknya.
- Kolom "auto" workbook dihitung langsung: Σ bobot per jabatan (harus 100%),
  peran unit di pos akun (dari Peta), nama rasio & pos akun, serta ringkasan per
  unit (Σ bobot Head per brand, jumlah KPI Supervisor/Staff, baris ≠100%).
- Pemeriksaan logis Uji A yang bisa dihitung otomatis: jenis ukuran sesuai level
  (Q7), bobot 100% (Q8), pos akun ada di rumus rasio yang diklaim (Q3), unit
  Pemilik/Kontributor pos itu (Q4), dan KPI induk sah.
- **Status validasi** (Belum diuji / Lolos / Revisi) hanya ditetapkan Keuangan.
  Bila unit mengubah isi KPI yang sudah Lolos, statusnya kembali "Belum diuji".
- **Masukkan ke monitoring**: KPI berstatus Lolos dimasukkan ke Objective
  Departemen untuk periode yang dipilih. Realisasi yang sudah diisi tidak
  berubah; hanya definisi & target yang diperbarui.
- Objective Departemen kini menghitung capaian menurut polaritas Naik / Turun /
  **Rentang** (sebelumnya Rentang dihitung seperti Naik), dan kolom target/
  realisasinya diperlebar sehingga target ratusan miliar rupiah dan pecahan
  seperti 0,97 tidak terpotong.
- Contoh Terisi (SCM, TTC Eyebost, CMP) dipakai di
  `tests/Feature/KpiCascadeTest.php`: semua baris harus lolos cek, dan peran
  unit serta ringkasan per unit harus sama dengan workbook.

---

## Penyusunan target revenue (L1 bagian A–G) — sudah diterapkan

- Menu **Perencanaan Target**, per entitas per tahun target (tahun dasar = tahun
  sebelumnya):
  - **A** estimasi akhir tahun dasar = YTD × 12 ÷ n, otomatis dari realisasi di
    menu Target Revenue (dapat ditimpa manual bila data bulanan belum ada);
  - **B** realisasi 3 tahun sebelumnya → YoY, CAGR, dan regresi linear (setara
    `FORECAST` Excel);
  - **C** bottom-up brand × channel dengan growth per brand → target per brand
    dan per channel. Channel dapat diatur per entitas (Erdigma bawaan SOC, TTC,
    ECO, OFD, PTN; entitas manufaktur mengisi channelnya sendiri); cek Σ basis
    vs estimasi A (toleransi 2%);
  - **D** inisiatif Ansoff × probabilitas = expected value; **E** SWOT + koreksi %;
  - **F** rekonsiliasi enam angka terhadap target disahkan, tombol **Sahkan**
    per metode atau angka ketikan sendiri (revisi yang sudah ada tidak berubah);
  - **G** indeks musiman dari realisasi tahun dasar → **Terapkan ke Target
    Revenue** mengisi 12 target bulanan (realisasi yang sudah ada tidak berubah).
- Fasing "Ikuti pola musiman" di menu Target Revenue kini memakai metode yang
  sama (bagian G), sehingga cukup realisasi sebagian tahun — sebelumnya wajib
  12 bulan lengkap.
- Rumus di `App\Support\Bsc\RevenueForecast`; kebenaran dijaga
  `tests/Feature/RevenueForecastWorkbookTest.php` (estimasi 810 M, CAGR 13,0921%,
  916.046.140.972, regresi 895 M, bottom-up 922.020.000.000 & target per channel,
  EV Ansoff 58,5 M, metode ambisius 980.520.000.000, rata-rata 911.022.046.991,
  fasing Jan 70 M … Des 75 M) dan `RevenuePlanningTest` (alur lengkap dari data
  tersimpan).

---

## Tahap 4 — sudah diterapkan

### Uji indikator (sheet L4)
- Menu **Uji Indikator**, dikerjakan Keuangan (`manage ratios`) per KPI cascade.
- **Uji A** — 8 pertanyaan Ya/Tidak. Q3 (pos akun ada di rumus rasio), Q4 (unit
  Pemilik/Kontributor), Q7 (jenis ukuran sesuai level), dan Q8 (bobot 100%)
  **dihitung dari data** dan tidak bisa diisi manual; Q1, Q2, Q5, Q6 dijawab
  Keuangan. Driver: 8 Ya = LOLOS, 7 Ya = REVISI MINOR; Guardrail cukup Q5, Q7, Q8.
- **Uji B** — baseline dari pos akun periode terpilih, % perbaikan KPI, dan
  koefisien transmisi per pos akun (plus keterangan asumsinya). 19 rasio & F2
  dinilai ulang dengan **mesin yang sama** seperti Tingkat 2. Kesimpulan: rasio
  yang diklaim bergerak & ke arah baik → LOLOS; klaim REV dinilai dari Penjualan.
- Hasil uji tersimpan sebagai bukti (penguji & waktunya). **Tetapkan Lolos**
  hanya bisa setelah hasil uji disimpan; status yang dianjurkan: Guardrail cukup
  Uji A, Driver harus lolos Uji A dan Uji B.
- Kebenaran dijaga `tests/Feature/IndicatorTestWorkbookTest.php`: contoh Uji B
  workbook (TTC-H02, klaim P2, perbaikan 5%, koefisien PA01 0,4 · PA02 0,4 ·
  PA03 −0,3) harus menghasilkan skenario rasio yang sama, Δ NPM 0,008578, 12
  rasio lain ikut bergerak, dan kesimpulan LOLOS.

### Target disesuaikan
- Menu **Target Revenue** kini mencatat target setahun **disahkan** dan **revisi**;
  faktor revisi = revisi ÷ disahkan.
- Cascade KPI menampilkan **Target disesuaikan** = target × (1 + e × (faktor − 1)),
  dan monitoring memakai target itu. Guardrail (e = 0) tidak berubah.

### Konsolidasi holding
- Menu **Konsolidasi Holding**, hanya untuk pengguna **level holding** (tanpa
  entitas) dengan izin `view consolidation` — pengguna yang terikat satu entitas
  ditolak walaupun perannya punya izin itu.
- Keempat entitas berdampingan dengan skala yang sama: revenue YTD, F1, F2, skor
  puncak, sasaran mutu, KPI Lolos. Skor tiap entitas dihitung dengan fungsi yang
  sama dengan Piramida BSC entitas itu (`App\Support\Bsc\Scorecard`).
- **Eliminasi penjualan antarentitas** (`manage consolidation`): per bulan,
  penjual → pembeli, rencana & realisasi. Rencana dikurangkan dari target grup,
  realisasi dari revenue grup.
- F1 grup = realisasi bersih ÷ target bersih (kumulatif, maks 100).
  F2 grup = F2 entitas dibobot target revenue YTD-nya.
  Skor puncak grup = 0,45 × F1 grup + 0,55 × F2 grup.

---

## Catatan & keputusan terbuka

- Bobot 19 rasio di workbook masih berstatus **usulan**; bobot kelompok
  30/25/20/15/10 sudah disepakati.
- Angka target & baseline di workbook adalah **ilustrasi** — wajib diganti data
  korporat (GL, HRIS).
- Untuk tiga entitas manufaktur belum ada workbook tersendiri; formatnya
  mengikuti workbook Erdigma, dengan unit kerja yang dapat diubah lewat menu
  Unit Kerja.
- **F2 grup** memakai rata-rata F2 entitas yang dibobot target revenue — bukan
  rasio dari laporan keuangan konsolidasi. Rasio konsolidasi penuh butuh pos
  akun konsolidasi (termasuk eliminasi piutang/utang & persediaan antarentitas)
  yang belum dicatat. Bobot ini keputusan yang dapat diubah bila holding
  menghendaki cara lain (mis. rata-rata sederhana).
