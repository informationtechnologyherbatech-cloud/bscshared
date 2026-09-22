# Dokumentasi Super Apps BSC

Seluruh dokumen non-teknis dan blueprint sistem dikumpulkan di folder ini.
Panduan untuk pengembang (instalasi, teknologi, pengaturan entitas, keamanan)
tetap berada di [README utama](../README.md).

| Dokumen | Isi |
|---|---|
| [Buku Panduan Lengkap](buku-panduan-lengkap.md) | Arsitektur sistem & operasional: filosofi BSC Kaplan–Norton, topologi 4 sistem (Odoo ERP → Finance → HRIS → BSC), hierarki piramida 4 level, panduan 12 menu modul, pipeline data 4-hop, formulasi matematika & polaritas, keamanan/kriptografi, dan panduan deployment. |
| [Dokumentasi Integrasi HRIS & Finance](dokumentasi-integrasi-hris-finance.md) | Blueprint integrasi enterprise: perubahan yang dibutuhkan di sisi HRIS dan Finance, skema database, katalog master 64 sasaran mutu untuk 11 departemen, kontrak API gateway BSC, SOP bulanan, matriks RACI, dan panduan pemecahan masalah. |
| [Panduan Dokumentasi API & Postman](panduan-dokumentasi-api-postman.md) | Standar penulisan dokumentasi API: struktur folder Postman collection, environment & variabel, header wajib, spesifikasi payload tiap endpoint, skrip otomasi Postman, format kode error, dan prosedur distribusi collection. |
| [Penerapan Metodologi Excel](penerapan-metodologi-excel.md) | Peta sheet workbook Cascading Revenue–Rasio–KPI ke menu aplikasi: apa yang sudah diterapkan (multi-entitas, unit kerja, target revenue, rumus skor puncak) dan tahapan berikutnya. |

---

## ⚠️ Status Penerapan

Ketiga dokumen di atas adalah **rancangan sasaran (blueprint)**, bukan cerminan
kode saat ini. Bagian yang **belum diimplementasikan** pada basis kode:

- **API Gateway 4-hop** (`POST /api/bsc/sync/hris-kpis`, `/finance-coa`,
  `GET /ping`, `/status`) — belum ada `routes/api.php` sama sekali; tidak ada
  satu pun endpoint HTTP di aplikasi ini.
- **Tabel** `kpi_actuals`, `accounts`, dan `audit_logs` — belum ada. Data BSC
  saat ini memakai `periods`, `financial_ratios`, `department_objectives`,
  `action_plans`, dan `staging_logs`.
- **Autentikasi `X-API-KEY`, idempotency key, dan fingerprint SHA-256** — kunci
  API sudah dapat dibuat lewat Setting Sistem, tetapi belum ada konsumen yang
  memverifikasinya.
- Menu **Integrasi Sistem** dan **Staging Log** saat ini adalah **simulasi
  lokal**: payload diisi dari formulir lalu ditulis langsung ke basis data
  aplikasi ini, bukan diterima dari HRIS/Finance.

Lima menu juga masih berupa placeholder (*ComingSoon*): Uji Dampak/What-If,
Simulasi CoA, Konsensus IBP, Sensitivitas, dan Skenario.

Perbarui catatan ini setiap kali salah satu bagian benar-benar dibangun.
