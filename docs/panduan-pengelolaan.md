# Panduan Pengelolaan Aplikasi

Panduan ini untuk **mengurus aplikasinya**, bukan mengisi angkanya. Isi angka
per tingkat ada di [Panduan Pengisian](panduan-pengisian.md).

Yang dibahas di sini: masuk pertama kali, memilih entitas & periode, mengatur
pengguna dan unit kerja, memasukkan data dari sistem lain, membaca jejak audit,
dan apa yang harus dilakukan ketika ada yang tidak beres.

---

## 1. Masuk pertama kali

1. Buka alamat aplikasi, masuk dengan email dan kata sandi dari administrator.
2. Bila diminta **mengganti kata sandi**, gantilah — akun dengan kata sandi
   bawaan tidak dapat dipakai sampai diganti.
3. Lupa kata sandi? Tidak ada "lupa kata sandi" otomatis; mintalah Super Admin
   mengatur ulang lewat menu *Manage User*.

Mengganti kata sandi sendiri kapan saja: klik nama Anda di pojok kanan atas →
**Ubah Password**.

---

## 2. Bilah atas: entitas dan periode

Dua hal di pojok kanan atas menentukan **semua angka yang Anda lihat**:

| Yang tampil | Artinya |
|---|---|
| **ENTITAS** (mis. *Erdigma*) | Perusahaan yang sedang dilihat. Seluruh halaman hanya menampilkan data entitas ini. |
| **PERIODE** (mis. *Agustus 2026*) | Bulan yang sedang dilihat. Dipakai bersama oleh semua halaman bulanan. |

- Pengguna yang terikat satu entitas tidak dapat berpindah entitas — itu memang
  pembatasnya, bukan kerusakan.
- Mengganti periode di bilah atas mengubah periode di seluruh halaman sekaligus,
  jadi tidak perlu menggantinya satu per satu.

> Kalau sebuah halaman terasa "kosong padahal datanya ada", periksa dua hal ini
> lebih dulu. Hampir selalu periodenya belum sesuai.

---

### Satu pemasangan melayani satu entitas

Pada pemasangan entitas (`BSC_HOLDING_MODE=false`), **entitas di `.env` yang
berlaku untuk semua orang** — termasuk pengguna yang akunnya terikat entitas
lain. Itu memang disengaja: satu pemasangan dipakai satu entitas, dengan
databasenya sendiri.

Tiga hal yang perlu dijaga:

1. **Satu database untuk satu entitas.** Selama databasenya hanya berisi data
   entitas itu, tidak ada yang bisa terlihat silang. Bila sebuah database yang
   memuat beberapa entitas dipakai pada pemasangan entitas, maka siapa pun yang
   masuk akan melihat entitas yang tertulis di `.env` — bukan entitas akunnya.
2. **Kode entitas harus tepat.** Bila `BSC_DEFAULT_ENTITY` salah ketik atau
   entitasnya dinonaktifkan, aplikasi menampilkan **spanduk merah di setiap
   halaman** yang menyebutkan kode yang salah dan entitas mana yang dipakai
   sebagai gantinya. Jangan diabaikan — artinya pemasangan sedang melayani
   entitas yang bukan dimaksudkan.
3. **Bersihkan cache konfigurasi setelah mengubah `.env`.** Bila
   `bootstrap/cache/config.php` ada, isi `.env` **tidak dibaca lagi** sehingga
   perubahan apa pun tidak berpengaruh. Jalankan `php artisan config:clear`
   (atau `config:cache` ulang) setiap kali `.env` berubah.

Pada pemasangan holding (`BSC_HOLDING_MODE=true`), pengguna yang terikat entitas
tetap melihat entitasnya sendiri, dan hanya pengguna tanpa entitas yang dapat
berpindah antarentitas.

---

## 3. Periode: membuat, menutup, membuka lagi

Semua data bulanan menempel pada periode. Kelolanya di **Piramida BSC**:

| Tombol | Gunanya | Siapa |
|---|---|---|
| **Periode Baru** | Membuat bulan berikutnya. Bulannya **dipilih**, bukan diketik — tombol *Bulan berikutnya* mengisinya sekali klik. Sasaran mutu periode sebelumnya ikut tersalin dengan realisasi 0, dan program kerja yang belum selesai ikut terbawa bertanda *Lanjutan*. | Admin FAT · Super Admin |
| **Kunci Periode** | Menutup periode (status CLOSED) setelah angkanya final. Tombolnya **baru muncul setelah bulannya berakhir** — bulan berjalan dan bulan mendatang bertanda *Belum berakhir*, karena menutup buku atas angka yang belum selesai dikumpulkan tidak masuk akal. Periode yang sudah terkunci selalu dapat dibuka kembali. | Admin FAT · Super Admin |

**Apa yang berubah setelah periode ditutup:**

- Target & realisasi revenue bulan itu **tidak lagi punya kotak isian** — barisnya
  redup dengan ikon gembok.
- Pos akun bulan itu hanya dapat dilihat; tombol *Simpan & hitung rasio* hilang.
- Penerapan fasing dari Perencanaan Target **melewati** bulan yang sudah ditutup,
  dan menyebutkan bulan mana yang dilewati.
- Unggahan CSV maupun kiriman API untuk periode itu **ditolak** dan tercatat di
  Staging & Audit Log.

Membuka kembali periode yang sudah ditutup butuh izin *override* (Admin FAT atau
Super Admin), lewat Piramida BSC.

---

## 4. Peran & hak akses

Enam peran, dari yang paling luas ke yang paling sempit:

| Peran | Boleh melakukan |
|---|---|
| **Super Admin** | Semuanya, termasuk Setting Sistem, Manage User, dan kunci API. |
| **Admin FAT** (Finance) | Pos akun, katalog rasio & rumus, target revenue, cascade KPI, uji indikator, program kerja, integrasi & kunci API, konsolidasi, kelola periode. Tidak boleh mengubah identitas aplikasi atau pengguna. |
| **Admin HRIS** | Sasaran mutu, program kerja, **unit kerja**, integrasi. Tidak menyentuh angka keuangan. |
| **Kepala Departemen** | Melihat skor, mengisi realisasi KPI unitnya, program kerja, uji dampak. |
| **Operator** | Mengisi realisasi KPI dan memperbarui progres program kerja. |
| **Viewer** | Baca-saja: dashboard, rasio, sasaran, wiring, jejak audit. |

Dua hal yang sering ditanyakan:

- **Kenapa menu saya lebih sedikit?** Menu yang tidak boleh diakses memang tidak
  ditampilkan, bukan disembunyikan setengah-setengah.
- **Kenapa tombol Simpan tidak ada?** Halaman yang hanya boleh Anda baca tidak
  menampilkan kontrol tulisnya, dan memberi keterangan izin apa yang dibutuhkan.

---

## 5. Unit Kerja (master data departemen)

Menu **Unit Kerja** adalah tempat departemen ditambah, diubah, dan
dinonaktifkan. Kodenya dipakai di seluruh aplikasi — sasaran mutu, cascade KPI,
program kerja, dan unit pengguna — jadi tentukan sejak awal.

**Siapa yang boleh:** Admin HRIS dan Super Admin (izin `manage units`).
**Admin FAT tidak**, meskipun ia berwenang atas angka keuangan. Jalan pintas ke
menu ini juga tersedia lewat tombol *Kelola unit kerja* di menu Objective
Departemen.

### Menambah departemen
**Tambah Unit** → isi **kode** (singkat, dipakai di seluruh aplikasi) dan
**nama**, lalu lengkapi *stream*, *reports to*, dan *scope* bila perlu →
**Simpan**. Departemen langsung muncul di semua pilihan departemen.

### Mengubah departemen
Ikon pensil pada barisnya. Namanya bebas diubah kapan saja. **Kodenya hanya
dapat diubah selama belum dipakai data lain** — begitu ada sasaran mutu, KPI,
atau program kerja yang memakainya, kode dikunci supaya rujukan lama tidak
putus.

### Menonaktifkan departemen yang tidak dipakai lagi
Tombol **Nonaktifkan** pada barisnya. Sesudah itu:

- departemen **hilang dari semua pilihan** — Objective Departemen, Cascade KPI,
  Program Kerja, dan unit pengguna;
- **data lamanya tetap utuh dan tetap terbaca**; penyaring Objective Departemen
  masih menampilkan kodenya selama masih ada sasaran mutu yang memakainya,
  sehingga riwayatnya tidak hilang dari layar;
- barisnya menghilang dari daftar Unit Kerja. Untuk melihat atau
  **mengaktifkannya kembali**, centang **Tampilkan nonaktif** di atas daftar.

### Menghapus
Hanya untuk departemen yang **belum pernah dipakai**. Bila sudah dipakai,
aplikasi menolak menghapusnya — nonaktifkan saja. Ini disengaja: menghapus
departemen yang masih dirujuk akan membuat data lama kehilangan pemiliknya.

> Ringkasnya: **belum pernah dipakai → boleh dihapus; sudah dipakai → nonaktifkan.**

---

## 6. Manage User

Menu **Manage User** (Super Admin). Satu pengguna terdiri atas:

| Kolom | Catatan |
|---|---|
| Nama, Email | Email menjadi identitas masuk. |
| Password | Wajib diisi saat membuat. Centang *harus ganti password* agar pengguna menggantinya sendiri saat pertama masuk. |
| Peran | Lihat tabel di bagian 4. |
| Entitas | Kosongkan hanya untuk pengguna **holding**. Diisi = pengguna terikat satu entitas. |
| Unit kerja | Dipakai menyaring sasaran mutu & program kerja miliknya. |
| Foto | Opsional. JPG/PNG/WEBP maksimal 1 MB. Fotonya tampil sebagai avatar di bilah atas dan di daftar pengguna; bila kosong dipakai inisial nama. Centang *Hapus foto* untuk kembali ke inisial. |
| Aktif | Menonaktifkan akun tanpa menghapusnya — cara yang benar untuk karyawan yang keluar. |

---

## 7. Setting Sistem

Menu **Setting** (Super Admin; Admin FAT hanya bagian kunci API). Isinya:
identitas aplikasi dan entitas (nama, logo, alamat, kontak), pengaturan
keamanan, dan **kunci API** untuk sistem lain yang mengirim data ke aplikasi ini.

Kunci API hanya tampil **sekali** saat dibuat — simpan segera. Bila hilang,
buat kunci baru dan hapus yang lama.

---

## 8. Memasukkan data dari sistem lain

Menu **Integrasi & Gateway**. Tiga jalur, semuanya bermuara ke tempat yang sama
(Pos Akun / Sasaran Mutu) dengan aturan yang sama:

### a. Tarik dari Odoo
Sambungan JSON-RPC yang **menarik** saldo akun, bukan menunggu dikirimi. Apa
yang harus disiapkan di sisi Odoo ada di tombol **Panduan Odoo** pada halaman
itu, dan di [Integrasi Odoo](integrasi-odoo.md).

### b. Unggah CSV
Satu pengunggah melayani dua bentuk berkas; bentuknya dikenali dari judul kolom.

**Saldo akun** (untuk Pos Akun & rasio):

```csv
kode;nilai;saldo_awal
4-10001;812000000;
PA08;77000000000;60000000000
```

- `kode` boleh **kode akun buku besar** (diterjemahkan lewat pemetaan di halaman
  yang sama) atau langsung **kode pos akun** seperti `PA08` — kode PA dipakai apa
  adanya tanpa pemetaan.
- `saldo_awal` hanya untuk pos neraca; kosongkan untuk pos aliran.
- Kolom `periode` boleh ditambahkan; bila tidak ada, dipakai periode di layar.

**Realisasi KPI**:

```csv
periode;dept_code;kpi_code;target;actual
2026-08;PROD;PRD-01;95;92
```

Pemisah titik koma maupun koma sama-sama diterima — berkas dari Excel berbahasa
Indonesia biasanya memakai titik koma.

### c. Kiriman API
Sistem lain mengirim dengan header `X-API-KEY`. Tanpa kunci, jawabannya 401
dengan keterangan jelas. Setiap kiriman membawa *penanda* (idempotency key)
sehingga kiriman yang sama dua kali tidak menimpa data dua kali.

> **Yang masih simulasi:** tombol *Uji Coba Kirim API Payload Inbound* dan
> *Kirim Payload Simulasi* hanya menulis baris jejak untuk latihan — keduanya
> tidak mengubah angka.

---

## 9. Staging & Audit Log

Menu **Staging & Audit Log** adalah **catatan, bukan tempat mengisi**. Halaman
ini tidak pernah menulis data; ia hanya memperlihatkan apa yang masuk, kapan,
dari mana, dan apakah diterima.

| Status | Artinya | Yang perlu dilakukan |
|---|---|---|
| **SCORED** | Diterima dan sudah dihitung. | Tidak ada. |
| **OPEN** | Realisasi masuk, tetapi belum ada target pembandingnya. | Isi targetnya di menu terkait. |
| **ERROR** | Ditolak. Alasannya ada di kolom pesan. | Perbaiki penyebabnya, lalu kirim ulang. |

Alasan penolakan yang paling sering:

- **Periode sudah ditutup** → buka kembali periodenya, atau kirim ke periode yang benar.
- **Kode akun belum dipetakan** → petakan di Integrasi & Gateway, lalu ulangi.
- **Nilainya bukan angka** → periksa pemisah ribuan/desimal di berkasnya.
- **Penanda sudah pernah dipakai** → kiriman itu memang sudah diproses; tidak ada
  yang perlu diulang.

Saringan periode, unit, status, dan pencarian bebas tersedia di atas tabel, dan
pilihannya ikut tersimpan di alamat halaman — jadi tautannya bisa dibagikan.

---

## 10. Khusus instalasi holding

Dua menu ini hanya muncul pada instalasi holding (EMC) dan hanya untuk pengguna
yang tidak terikat satu entitas:

- **Konsolidasi Holding** — skor keempat entitas berdampingan, beserta revenue
  grup setelah **eliminasi penjualan antarentitas**. Eliminasi diisi di halaman
  ini: entitas penjual, entitas pembeli, dan nilainya.
- **Sumber Data Entitas** — status sambungan ke tiap entitas (API atau koneksi
  database), termasuk kapan terakhir berhasil dibaca. Bila sebuah entitas tidak
  terjangkau, angkanya ditandai, bukan dianggap nol.

Rinciannya di [Database per Entitas & Konsolidasi Holding](database-per-entitas.md).

---

## 11. Kalau ada yang tidak beres

| Gejala | Kemungkinan terbesar |
|---|---|
| Halaman kosong padahal data sudah diisi | Periode atau entitas di bilah atas belum sesuai. |
| Angka tidak berubah setelah menyimpan | Periodenya sudah ditutup — cek peringatan di bagian atas halaman. |
| Rasio kosong (—) padahal pos akun terisi | Ada pos pembentuk yang belum diisi, atau penyebutnya nol. Buka telusur rasionya di Piramida BSC. |
| Skor F2 turun tiba-tiba | Bobot rasio aktif tidak lagi berjumlah 100, atau ada rasio baru tanpa target. Cek *Bobot per kelompok* di Katalog Rasio. |
| Unggahan ditolak | Buka Staging & Audit Log, baca pesannya — alasannya selalu dicatat. |
| Menu yang dicari tidak ada | Peran Anda tidak memilikinya. Lihat tabel peran di bagian 4. |

Masih tidak terjawab? Menu **Dokumentasi Metode** memuat seluruh panduan ini
beserta **Metode Skoring** (rumus yang dibaca langsung dari kode) dan **Uji
Mandiri** (12 pemeriksaan mesin terhadap angka acuan) — tombol *Jalankan* di situ
memastikan perhitungannya masih sesuai metodologi.
