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
| **L1 Target Revenue** — B–F | CAGR, regresi, bottom-up brand × channel, Ansoff, SWOT, rekonsiliasi | ⏳ Menyusul (alat bantu penyusunan target) |
| **Asumsi** — B, C, D | 19 rasio, bobot 5 kelompok, rubrik 5 tingkat | ✅ Menu **Katalog Rasio**, `config/bsc.php` |
| **Asumsi** — F, G | 16 pos akun & data baseline | ✅ Menu **Pos Akun** |
| **L2 Rasio Keuangan** | Rasio dihitung dari pos akun, skor rubrik × bobot | ✅ `App\Support\Bsc\RatioEngine` |
| **Peta Rasio-Akun-Dept** | Pemilik (O) / Kontributor (K) tiap pos akun | ⏳ Tahap 3 |
| **L3 Cascade KPI**, **Form Sasaran Kinerja** | KPI Head → Supervisor → Staff, bobot per jabatan | ⏳ Tahap 3 |
| **L4 Uji Indikator** | Uji A (logika) & Uji B (simulasi) | ⏳ Tahap 4 |

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

## Tahap berikutnya

**Tahap 3 — Cascade KPI (L3).**
KPI berjenjang Head (lag) → Supervisor (lead) → Staff (output) dengan kode KPI
induk, bobot per jabatan (Σ 100%), jenis Driver/Guardrail, elastisitas, serta
rasio & pos akun yang digerakkannya; peta Pemilik/Kontributor pos akun.

**Tahap 4 — Uji indikator (L4) & konsolidasi holding.**
Uji A/B sebelum KPI masuk monitoring. Tampilan holding yang menggabungkan
keempat entitas — termasuk **eliminasi penjualan antarentitas** (mis. Herbatech
sebagai pemasok HPP Erdigma), agar revenue grup tidak terhitung dua kali.

---

## Catatan & keputusan terbuka

- Bobot 19 rasio di workbook masih berstatus **usulan**; bobot kelompok
  30/25/20/15/10 sudah disepakati.
- Angka target & baseline di workbook adalah **ilustrasi** — wajib diganti data
  korporat (GL, HRIS).
- Untuk tiga entitas manufaktur belum ada workbook tersendiri; formatnya
  mengikuti workbook Erdigma, dengan unit kerja yang dapat diubah lewat menu
  Unit Kerja.
