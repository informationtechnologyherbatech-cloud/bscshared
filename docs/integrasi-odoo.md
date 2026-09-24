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

## Yang perlu disiapkan DI ODOO

Tidak ada modul yang perlu dipasang, tidak ada webhook yang perlu dibuat, dan
tidak ada hak tulis yang perlu diberikan. Yang dibutuhkan hanya empat hal.

**1. Satu pengguna khusus untuk integrasi.**
*Settings → Users & Companies → Users → New*. Beri nama yang jelas, misalnya
`integrasi.bsc@entitas.co.id`, bertipe **Internal User**. Jangan memakai akun
pribadi seseorang: kalau orangnya keluar dan akunnya dinonaktifkan, tarikan ikut
mati.

**2. Hak akses secukupnya — hanya MEMBACA akuntansi.**
Pada tab *Access Rights* pengguna itu, beri akses Akuntansi pada tingkat baca
(di Odoo 16+ namanya *Read-only*; pada versi lain *Billing* adalah pilihan
terendah yang masih bisa membaca jurnal). Aplikasi ini hanya memanggil
`search_read` dan `read_group` — tidak pernah menulis, jadi hak tulis tidak
diperlukan dan sebaiknya tidak diberikan.

**3. Kunci API untuk pengguna itu.**
Masuk ke Odoo **sebagai pengguna tersebut**, lalu *(ikon pengguna) → My Profile
→ Account Security → New API Key*. Odoo akan meminta kata sandi pengguna itu,
lalu menampilkan kuncinya **sekali saja** — salin saat itu juga. Kunci inilah
yang ditempel di layar Integrasi & Gateway, bukan kata sandinya.
Pada Odoo di bawah versi 14 fitur kunci API belum ada; pakai kata sandi pengguna
integrasi tersebut.

**4. Catat tiga keterangan ini:**

| Yang diisi di BSC | Dari mana di Odoo |
|---|---|
| **Alamat Odoo** | URL tempat Odoo dibuka, mis. `https://erp.entitas.co.id`. Untuk Odoo Online: `https://namaperusahaan.odoo.com`. |
| **Nama database** | Terlihat di halaman login Odoo (pemilih database), atau di *Settings → Technical → Database Structure*. Untuk Odoo Online biasanya sama dengan subdomainnya. |
| **Perusahaan** | Bila database itu memuat lebih dari satu perusahaan, pilih yang menjadi entitas ini. BSC akan menolak menarik sampai dipilih — lihat di bawah. |

### Yang perlu dipastikan pada datanya

- **Jurnal sudah diposting.** Hanya entri berstatus *Posted* yang dihitung; draf
  diabaikan. Jadi tarikan sebaiknya dijalankan setelah tutup buku bulanan.
- **Akun punya kode.** Pemetaan bekerja atas kode akun (`code` pada
  *Accounting → Configuration → Chart of Accounts*). Akun tanpa kode dilewati.
- **Saldo awal tahun sudah dibukukan** bila ingin rasio memakai rata-rata saldo
  (pos neraca). Kalau belum, rasio memakai saldo akhir saja — tetap jalan,
  hanya kurang halus.
- **Jaringan.** Server BSC harus dapat menghubungi alamat Odoo itu lewat HTTPS.
  Kalau Odoo berada di jaringan dalam, buka jalannya atau tempatkan BSC di
  jaringan yang sama.

### Satu database, beberapa perusahaan

Pada grup seperti EMC, satu Odoo sering memuat beberapa perusahaan. Kalau
perusahaannya tidak dipilih, saldo **semua** perusahaan akan terjumlah menjadi
satu — angkanya salah tanpa satu pun pesan galat. Karena itu BSC memeriksa
sendiri: begitu database ternyata memuat lebih dari satu perusahaan dan belum
ada yang dipilih, tarikan **ditolak** dengan pesan yang menyebutkan nama-nama
perusahaannya. Pemeriksaan ini juga berlaku pada tarikan terjadwal, yang tidak
ada orang menungguinya.

## Menyiapkannya

Menu **Integrasi & Audit → Integrasi & Gateway**, bagian *Hop 1* (butuh izin
`view integration`; menyimpan butuh `manage integration`). Semua urusan integrasi
berada di satu halaman itu: sambungan Odoo, pemetaan akun, pos akun periode
berjalan, payload KPI, dan unggahan berkas.

1. **Sambungan** — alamat Odoo, nama database, pengguna, dan kunci APInya.
   Kunci disimpan terenkripsi dan setelah itu hanya ditampilkan tersamar.
   Tekan **Uji sambungan** untuk memastikan kredensialnya benar.
2. **Ambil daftar akun** — bagan akun Odoo ditarik agar pemetaan tinggal
   memilih, bukan mengetik ulang kodenya. Daftarnya dapat dicari.
3. **Petakan otomatis** — bagan akun sungguhan berisi ratusan baris, jadi
   pemetaannya diusulkan dari **jenis akun** Odoo (`account_type`):

   | Jenis akun Odoo | Pos akun BSC |
   |---|---|
   | `income`, `income_other` | PA01 Penjualan (tanda dibalik) |
   | `expense_direct_cost` | PA02 HPP |
   | `expense`, `expense_depreciation` | PA03 Beban usaha |
   | `asset_receivable` | PA06 Piutang usaha |
   | `liability_payable` | PA07 Utang usaha (dibalik) |
   | `asset_cash` | PA08 Kas & setara kas |
   | `liability_current` | PA10 Liabilitas lancar (dibalik) |
   | `equity`, `equity_unaffected` | PA13 Ekuitas (dibalik) |

   Usulannya tetap dapat diubah atau dihapus satu per satu. Yang **tidak**
   diusulkan karena artinya mendua: PA05 Persediaan (di Odoo berjenis
   `asset_current`, tidak terbedakan dari uang muka), serta PA09 Aset lancar,
   PA11 Total aset, dan PA12 Total liabilitas — ketiganya JUMLAH yang memuat
   akun yang sama dengan pos lain, sedangkan satu kode akun hanya boleh menunjuk
   satu pos. Semuanya diisi di menu **Pos Akun**.
4. **Pemetaan Akun** — kode akun Odoo → pos akun PA01–PA16, bila ingin mengatur
   sendiri. Beberapa kode akun boleh menunjuk pos yang sama; nilainya
   dijumlahkan.
5. **Tarik sekarang** — untuk menguji, atau saat tutup buku tidak mau menunggu
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

### Versi Odoo

Odoo 18 memperkenalkan `formatted_read_group` dan membuang `read_group`, dengan
urutan argumen serta nama kolom hasil yang berbeda. Aplikasi ini mencoba yang
baru lebih dulu lalu jatuh ke yang lama, sehingga satu kode melayani Odoo 14
sampai 19 tanpa perlu menanyakan versinya. Cara masuknya pun demikian:
`common.authenticate` (yang didokumentasikan) dengan cadangan `common.login`.

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

## Sejauh mana ini sudah teruji

Alur penuhnya sudah dijalankan dari peramban melawan server Odoo tiruan yang
bicara JSON-RPC sungguhan lewat HTTP: masuk → daftar perusahaan → bagan akun →
tarik tiga rentang tanggal → pos akun terisi → rasio terhitung. Yang terbukti
benar di situ: nilai YTD untuk pos aliran, saldo akhir + saldo awal tahun untuk
pos neraca, realisasi revenue bulan berjalan saja, jurnal draf tidak ikut,
perusahaan lain tidak ikut, dan kode akun diambil dari bagan akun (bukan
dipotong dari nama tampilan, yang susunannya berbeda antarversi Odoo).

Yang **belum** dapat dibuktikan di sini: perilaku terhadap **server Odoo
sungguhan**. Kontrak JSON-RPC yang dipakai adalah yang didokumentasikan Odoo
(`common.authenticate`, lalu `object.execute_kw`), dengan cadangan ke
`common.login` untuk versi lama. Uji pertama pada Odoo asli sebaiknya dilakukan
pada database salinan, lalu bandingkan angka PA01–PA03 dengan laporan Laba Rugi
Odoo untuk periode yang sama.

## Yang belum dibangun

- Menarik **realisasi KPI** dari Odoo. KPI operasional umumnya tidak tersimpan di
  Odoo; jalurnya saat ini unggahan CSV, payload manual, atau pengisian layar.
- Endpoint **API masuk** (Odoo mendorong ke sini). Arah yang dipakai sekarang
  adalah menarik; bila kelak dibutuhkan, kelas pemasukan yang ada tinggal
  dipasangkan pada sebuah rute ber-`api.key`.
- **PA15/PA16 dari HRIS** — masih diisi lewat menu Pos Akun.
