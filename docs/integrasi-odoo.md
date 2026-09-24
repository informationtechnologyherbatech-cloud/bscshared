# Integrasi Odoo → Pos Akun

Angka keuangan tiap entitas dicatat di Odoo, bukan diketik ulang di sini.
Dokumen ini menjelaskan bagaimana angka itu berpindah — dan mengapa bentuknya
seperti sekarang.

## Arahnya menarik, bukan menerima

```
   Odoo entitas                Super Apps BSC entitas              Holding (EMC)
  ┌──────────────┐            ┌─────────────────────────┐        ┌──────────────┐
  │ account.     │  ①  tarik  │ Pemetaan Akun           │        │ Konsolidasi  │
  │ move.line    │ ◀───────── │   4-10001 → PA01        │        │              │
  │ (posted)     │  JSON-RPC  │   5-10001 → PA02        │        │              │
  └──────────────┘            │            ↓            │        │              │
                              │ Pos Akun (PA01–PA16)    │        │              │
                              │            ↓            │        │              │
                              │ 19 rasio → F2           │  ②     │ membaca      │
                              │ Realisasi revenue → F1  │ ─────▶ │ RINGKASAN    │
                              └─────────────────────────┘  API   └──────────────┘
```

① **Aplikasi inilah yang memanggil Odoo**, terjadwal tiap hari pukul 04.30.
Di sisi Odoo tidak ada yang perlu dipasang — cukup satu pengguna yang diberi
hak **membaca** jurnal dan bagan akun. Tidak ada satu pun pemanggilan tulis ke
Odoo.

② Holding tidak pernah menyentuh Odoo maupun database entitas. Ia hanya membaca
ringkasan entitas lewat `GET /api/v1/consolidation` seperti biasa (lihat
[Database per Entitas](database-per-entitas.md)). Jadi jalur integrasi ini
berhenti di batas entitas.

## Menyiapkannya

Menu **Integrasi & Audit → Integrasi & Gateway**, bagian *Hop 1* (butuh izin
`view integration`; menyimpan butuh `manage integration`). Semua urusan integrasi
berada di satu halaman itu: sambungan Odoo, pemetaan akun, pos akun periode
berjalan, payload KPI, dan unggahan berkas.

1. **Sambungan** — alamat Odoo, nama database, pengguna, dan kunci APInya.
   Kunci disimpan terenkripsi dan setelah itu hanya ditampilkan tersamar.
   Tekan **Uji sambungan** untuk memastikan kredensialnya benar.
2. **Ambil daftar akun** — bagan akun Odoo ditarik agar pemetaan tinggal
   memilih, bukan mengetik ulang kodenya.
3. **Pemetaan Akun** — kode akun Odoo → pos akun PA01–PA16. Beberapa kode akun
   boleh menunjuk pos yang sama; nilainya dijumlahkan.
4. **Tarik sekarang** — untuk menguji, atau saat tutup buku tidak mau menunggu
   jadwal. Menarik ulang periode yang sama **menimpa** angkanya, bukan menambah.

Tanpa pemetaan, tarikan tidak menemukan pos apa pun dan ditolak dengan pesan —
bukan diam-diam menulis nol.

## Arti angka yang ditarik

Ini bagian yang paling mudah keliru. Katalog pos akun membedakan dua jenis, dan
keduanya menuntut rentang tanggal yang berbeda:

| Jenis pos | Contoh | Yang ditarik dari Odoo |
|---|---|---|
| **Aliran** | PA01 Penjualan, PA02 HPP, PA03 Beban | Mutasi **1 Januari s.d. akhir periode** (YTD). Rumus rasio menyetahunkannya ×12 ÷ n. |
| **Neraca** | PA08 Kas, PA06 Piutang, PA13 Ekuitas | **Saldo**, bukan mutasi: akumulasi sejak awal buku s.d. akhir periode. Saldo awal tahunnya ditarik terpisah (s.d. 31 Desember tahun sebelumnya) supaya rasio dapat memakai rata-rata saldo awal & akhir. |
| **HRIS** | PA15 Jumlah karyawan, PA16 Jam kerja | Tidak ditarik dari Odoo; diisi di menu **Pos Akun**. |

**Realisasi revenue** ditarik sendiri sebagai mutasi **bulan itu saja** — berbeda
dengan PA01 yang YTD — karena Tingkat 1 menjumlahkannya sendiri dari Januari.
Dapat dimatikan lewat saklar *"Isi juga realisasi revenue bulanan"* bila revenue
diisi manual. **Target** revenue tidak pernah disentuh: itu hasil perencanaan,
bukan hasil pencatatan.

Hanya jurnal berstatus `posted` yang dihitung; draf belum menjadi angka resmi.

### Tanda saldo

Saldo Odoo adalah **debit − kredit**, jadi akun bersaldo kredit (penjualan,
utang, ekuitas) bernilai negatif. Pos akun BSC memakai angka positif apa adanya,
karena itu tiap pemetaan punya saklar **balik tanda**. Nilai awalnya mengikuti
kebiasaan pos yang dipilih (PA01, PA07, PA10, PA12, PA13, PA14 dibalik), tetapi
tetap dapat diubah — tiap bagan akun punya kebiasaannya sendiri.

## Unggahan berkas

Menu **Integrasi & Gateway** menerima berkas CSV yang **benar-benar dibaca dan
diterapkan**, lewat jalur pemasukan yang sama dengan tarikan Odoo. Dua bentuk
dilayani, dikenali sendiri dari judul kolomnya:

**Saldo akun** — masuk ke Pos Akun, rasio dihitung ulang:

```csv
kode,nilai,saldo_awal
4-10001,-812000000,
1-10002,95000000,70000000
```

**Realisasi KPI** — sesuai format baku panduan adaptasi:

```csv
periode,dept_code,kpi_code,target,actual,idempotency_key
2026-08,QC,KPI-01,100,90,IDEMP-QC-20260831
```

Nama kolom boleh Indonesia maupun Inggris, huruf besar/kecil bebas. Pemisah titik
koma (kebiasaan Excel berbahasa Indonesia), angka bergaya `1.234.567,89`, dan BOM
UTF-8 dari Excel semuanya dikenali. Kolom `periode` boleh dikosongkan bila
periodenya sudah dipilih di layar.

## Aturan yang berlaku untuk semua jalur

Tarikan Odoo, unggahan CSV, dan payload manual bermuara di kelas pemasukan yang
sama, sehingga diperlakukan seragam:

- **Periode tertutup ditolak.** Periode berstatus CLOSED memang sengaja
  dibekukan; datanya tidak diubah.
- **Kiriman kembar tidak diterapkan dua kali.** Tiap kiriman membawa penanda
  idempotensi; penanda yang sudah pernah dipakai dijawab "sudah pernah diproses"
  tanpa mengubah apa pun. Penandanya unik **per entitas**.
- **Kode akun yang belum dipetakan dilaporkan**, tidak dibuang diam-diam —
  jumlah dan contohnya masuk ke pesan hasil dan ke jejak audit.
- **Setiap kiriman meninggalkan satu baris** di *Staging & Audit Log*, memuat
  pos mana saja yang berubah.
- **Rasio keuangan dihitung ulang** oleh mesin yang sama dengan menu Pos Akun,
  sehingga angkanya tidak mungkin berbeda dengan piramida.

## Rantai 4-Hop di layar menunjukkan keadaan sebenarnya

Ketiga kotak di bagian atas halaman dulu selalu bertuliskan *Connected* — kata
yang ditulis di berkas tampilan, bukan disimpulkan dari apa pun. Sekarang tiap
kotak membaca keadaan nyata:

| Kotak | Yang diperiksa |
|---|---|
| **Hop 1 · Odoo ERP** | Ada tidaknya sambungan, aktif/nonaktif, hasil tarikan terakhir, dan jumlah akun yang sudah dipetakan. |
| **Hop 2 · Finance Monitoring** | Berapa dari 16 pos akun terisi pada periode itu, dan berapa rasio yang berhasil dihitung. |
| **Hop 4 · Super Apps BSC** | Ada tidaknya kunci API aktif, dan kapan terakhir holding benar-benar membaca. |

Begitu pula kotak ringkasan Penjualan/HPP/Opex/Laba: isinya dibaca dari **pos
akun yang tersimpan**, bukan dari isian formulir, dan berkata *"belum diisi"*
bila memang kosong.

## Menjalankan tarikan dari konsol

```bash
php artisan bsc:tarik-odoo                      # semua entitas, periode berjalan
php artisan bsc:tarik-odoo --period=2026-08     # periode tertentu
php artisan bsc:tarik-odoo --entity=AEJ         # satu entitas saja
```

Penjadwal (`php artisan schedule:work` atau cron) menjalankannya tiap hari pukul
04.30 dengan `withoutOverlapping()`. Pada konsol tidak ada pengguna yang login,
jadi perintah ini memasang sendiri konteks entitas pemilik tiap sambungan —
tanpa itu barisnya akan tertulis tanpa tuan.

## Yang belum dibangun

- Menarik **realisasi KPI** dari Odoo. KPI operasional umumnya tidak tersimpan di
  Odoo; jalurnya saat ini unggahan CSV, payload manual, atau pengisian layar.
- Endpoint **API masuk** (Odoo mendorong ke sini). Arah yang dipakai sekarang
  adalah menarik; bila kelak dibutuhkan, kelas pemasukan yang ada tinggal
  dipasangkan pada sebuah rute ber-`api.key`.
- **PA15/PA16 dari HRIS** — masih diisi lewat menu Pos Akun.
