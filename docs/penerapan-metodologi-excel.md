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
| **L1 Target Revenue** — B–F | CAGR, regresi, bottom-up brand × channel, Ansoff, SWOT, rekonsiliasi | ⏳ Tahap 2 |
| **Asumsi** — B, C, D | 19 rasio, bobot 5 kelompok, rubrik 5 tingkat | ⏳ Tahap 2 |
| **Asumsi** — F, G | 16 pos akun & data baseline | ⏳ Tahap 2 |
| **L2 Rasio Keuangan** | Rasio dihitung dari pos akun, skor rubrik × bobot | ⏳ Tahap 2 |
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

## Tahap berikutnya

**Tahap 2 — Rasio dari pos akun (L2).**
Input 16 pos akun bulanan (diketik tim Finance lebih dulu; tarik otomatis dari
sistem akuntansi menyusul) → 19 rasio dihitung otomatis dengan konvensi ×12/n
dan rata-rata neraca → rubrik 5 tingkat (≥90%→100, ≥80→80, ≥75→70, ≥65→60,
<65→50) × bobot per kelompok → **F2**. Katalog 19 rasio dan bobotnya dapat
berbeda per entitas.

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
