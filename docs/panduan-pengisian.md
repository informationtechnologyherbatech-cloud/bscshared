# Panduan Pengisian Super Apps BSC

Panduan langkah demi langkah mengisi data dari nol sampai piramida BSC terisi
penuh. Urutannya mengikuti menu di sidebar: **Tingkat 1 → 2 → 3 → 4**. Di tiap
tingkat, isi dari atas ke bawah: *atur → isi → lihat hasil*.

> Instalasi baru tidak berisi angka contoh. Semua periode, target, dan
> realisasi diisi sendiri lewat menu di bawah ini.

---

## Ringkasan: siapa mengisi apa

| Tingkat | Menu | Diisi oleh | Kapan |
|---|---|---|---|
| Persiapan | Setting › Entitas, Unit Kerja, Manage User | Super Admin / Admin HRIS | Sekali di awal |
| Persiapan | Piramida BSC › **Periode Baru** | Admin FAT / Super Admin | Awal tiap bulan |
| 1 · Revenue | Perencanaan Target | Direksi + Finance | Sekali setahun |
| 1 · Revenue | Target & Realisasi | Finance | Target: awal tahun · Realisasi: tiap bulan |
| 2 · Rasio | Katalog Rasio | Finance | Awal tahun |
| 2 · Rasio | Pos Akun | Finance | Tiap bulan |
| 3 · KPI | Peta Pos Akun | Finance + CFO | Awal tahun |
| 3 · KPI | Cascade KPI | Kepala unit | Awal tahun |
| 3 · KPI | Uji Indikator | Finance | Setelah KPI disusun |
| 3 · KPI | Objective Departemen | Kepala unit / Operator | Tiap bulan |
| 4 · Program Kerja | Program Kerja (Action) | Kepala unit / Operator | Saat sasaran di bawah target |

---

## 0. Persiapan (sekali di awal)

1. **Setting › Entitas**: periksa nama entitas dan nama perusahaan, lalu lengkapi
   alamat dan kontak. Nilai awalnya mengikuti `BSC_DEFAULT_ENTITY` di `.env`.
2. **Unit Kerja**: periksa daftar departemen entitas; tambah atau ubah bila perlu.
   Kode unit dipakai di seluruh aplikasi, jadi tentukan sejak awal.
3. **Manage User**: buat akun pengguna dan pilih perannya (Admin FAT = Finance,
   Kepala Departemen, Operator, Viewer). Pilih unit kerja masing-masing.
4. **Piramida BSC › Periode Baru**: buat periode bulan berjalan, misalnya
   `2026-08`. Semua data bulanan menempel ke periode. Membuat periode baru
   berikutnya otomatis menyalin sasaran mutu periode sebelumnya dengan realisasi 0.

---

## Tingkat 1 · Revenue (F1, bobot 45% skor puncak)

### a. Perencanaan Target *(sekali setahun, opsional tetapi disarankan)*
Menyusun angka target setahun dari beberapa sudut pandang (sheet L1 bagian A–F):

1. Pilih **tahun target**; tahun dasar = tahun sebelumnya.
2. **A.** Revenue tahun dasar YTD dan bulan berjalan. Terisi otomatis bila
   realisasi bulanan tahun dasar sudah ada.
3. **B.** Isi realisasi 3 tahun sebelumnya untuk mendapatkan CAGR dan regresi.
4. **C.** Isi basis per brand × channel dan growth per brand.
5. **D–E.** Isi inisiatif baru (Ansoff) beserta probabilitasnya, serta koreksi SWOT.
6. **Simpan perencanaan**.
7. **F.** Bandingkan enam angka, lalu klik **Sahkan** pada salah satunya (atau
   ketik angka sendiri). Angka yang disahkan inilah yang dipakai bagian G dan H.
8. **G.** Klik **Terapkan ke Target & Realisasi** untuk mengisi 12 target
   bulanan mengikuti pola musiman tahun dasar. Bila tahun dasar belum punya
   realisasi, pembagiannya rata 12 bulan — pesan sesudah menerapkan menyebutkan
   yang mana.
9. **H.** *(pilihan lain untuk bagian G)* **Fasing bulanan manual** dipakai bila
   pola musiman tahun dasar tidak mewakili rencana tahun depan — kampanye besar,
   channel baru, atau angka bulanan yang memang sudah disepakati sendiri. Angka
   yang ditulis di sini dipakai **apa adanya**: tidak dibagi ulang dan tidak
   disesuaikan dengan indeks mana pun. Tersedia tombol *Bagi rata*, *Salin dari
   G* sebagai titik awal, dan *Kosongkan*. Drafnya ikut tersimpan, jadi tidak
   hilang saat halaman dimuat ulang.

> Bagian G dan H sama-sama **menimpa** target bulanan di menu Target &
> Realisasi. Realisasi tidak pernah ikut berubah, dan bulan yang periodenya
> sudah **ditutup** tidak disentuh — nama bulannya disebutkan di pesan
> konfirmasi.

### b. Target & Realisasi
1. **Awal tahun**:
   - Isi **Disahkan direksi** (dan **Revisi** bila target berubah di tengah tahun).
   - Klik **Bagi rata 12 bulan** atau **Ikuti pola musiman**.
   - Periksa tabel, lalu **Simpan**.
2. **Setiap bulan**: isi kolom **Realisasi (Rp)** bulan itu, lalu **Simpan**.

> **Bulan yang periodenya sudah ditutup tidak dapat diisi.** Barisnya tampil
> redup dengan ikon gembok dan angkanya menjadi teks biasa, bukan kotak isian —
> supaya tidak ada yang mengetik lalu mengira tersimpan. Untuk mengubahnya,
> buka kembali periodenya lewat Piramida BSC (butuh izin *override*).

**Hasil:** F1 = Σ realisasi ÷ Σ target Januari s.d. bulan periode (maksimal 100%).
Piramida menampilkan alasan bila F1 belum bisa dihitung, misalnya "target bulanan
belum difasing" atau "belum ada realisasi".

---

## Tingkat 2 · Rasio Keuangan (F2, bobot 55% skor puncak)

### a. Katalog Rasio *(awal tahun)*
1. Pilih **tahun target**.
2. Centang rasio yang dipakai entitas. Ke-19 rasio bawaan aktif secara bawaan.
3. Periksa **bobot**. Acuan kelompok: Profitabilitas 30 · Aktivitas 25 ·
   Produktivitas 20 · Likuiditas 15 · Solvabilitas 10; total 100.
4. Isi **target tahunan** tiap rasio. Rasio persen ditulis dalam persen
   (37 berarti 37%).
5. Pastikan **Cek konsistensi target** hijau semua, lalu **Simpan**.

Katalog ini tidak terkunci: lewat tombol **Tambah rasio** dan ikon pensil pada
tiap baris, entitas dapat menambah rasio sendiri atau menyesuaikan nama,
kelompok, satuan, polaritas, dan **rumus** rasio yang ada. Rumus yang tertulis
itulah yang benar-benar dihitung, dan kotak pratinjau langsung mencobanya dengan
angka periode terakhir. Selengkapnya: **[Pos Akun & Rumus](katalog-pos-dan-rumus.md)**.

### b. Pos Akun *(setiap bulan)*
1. Pilih **periode**.
2. Isi pos akun dari GL/HRIS:
   - **Aliran** (Penjualan, HPP, Beban usaha, Beban tenaga kerja): nilai **YTD**
     Januari s.d. bulan itu.
   - **Neraca** (Persediaan, Piutang, Utang, Kas, Aset & Liabilitas, Ekuitas,
     Modal): **saldo awal tahun** dan **saldo akhir** bulan itu.
   - **HRIS**: rata-rata jumlah karyawan dan total jam kerja YTD.
3. Hasil rasio dan F2 tampil langsung sebagai pratinjau. Klik **Simpan &
   hitung rasio**.

Bila susunan akun entitas berbeda, panel **Sesuaikan pos akun** di halaman yang
sama dipakai untuk menambah pos (mis. `PA17 Rebate`), mengubah nama/keterangan
pos bawaan, atau menonaktifkan pos yang tidak dipakai. Kolom **Sumber**
menerangkan asal angkanya: **GL** = buku besar akuntansi (lewat Odoo/CSV),
**HRIS** = sistem kepegawaian, **Manual** = diketik sendiri.

**Hasil:** menu **Rasio Keuangan** berisi rasio bertanda **Otomatis** (tidak
diedit manual), dan Tingkat 2 piramida terisi.

> Alternatif: data CoA dari ERP dapat dikirim lewat **Integrasi & Gateway ›
> Wadah Penerimaan Data Finance**; angkanya masuk ke Pos Akun yang sama.

---

## Tingkat 3 · KPI & Sasaran Mutu

### a. Peta Pos Akun *(awal tahun, Finance + CFO)*
Tandai tiap unit kerja sebagai **Pemilik (O)** atau **Kontributor (K)** pada 16
pos akun. Tiap pos akun harus punya tepat satu Pemilik, kecuali Penjualan (PA01):
tiap unit channel memiliki porsinya sendiri. Tabel bawah menunjukkan rasio yang
boleh diklaim tiap unit.

### b. Cascade KPI *(awal tahun, kepala unit)*
1. Klik **KPI Head** dan isi sasaran kerja (lag), target, satuan, bobot, serta
   rasio dan pos akun yang digerakkan. KPI kepatuhan boleh dijadikan
   **Guardrail**.
2. Pada baris Head, klik ↳ untuk menurunkan **KPI Supervisor** (lead); lalu dari
   Supervisor turunkan **KPI Staff** (output: rutin atau milestone).
3. Pastikan kolom **Cek** hijau: bobot per jabatan 100%, jenis ukuran sesuai
   level, dan rasio/pos akun sesuai Peta.

### c. Uji Indikator *(Finance)*
1. Buka KPI, lalu jawab **Uji A** Q1, Q2, Q5, Q6 (Q3, Q4, Q7, Q8 terisi otomatis).
2. Untuk KPI Driver, isi **Uji B**: persen perbaikan KPI dan koefisien
   transmisi per pos akun.
3. **Simpan hasil uji**, lalu **Tetapkan Lolos** atau **Tetapkan Revisi**.

> **Tetapkan Lolos terkunci?** Tombol itu hanya terbuka bila hasil uji yang
> **sudah tersimpan** menganjurkan Lolos — Guardrail cukup lolos Uji A, Driver
> harus lolos Uji A *dan* Uji B. Keterangan di bawah tombol menyebutkan apa yang
> kurang. Dua sebab tersering: hasil ujinya belum ditekan **Simpan hasil uji**
> (isian di layar belum menjadi hasil tersimpan), atau Uji B memang menyimpulkan
> Revisi karena baseline pos akunnya masih kosong. **Tetapkan Revisi** selalu
> dapat ditekan.

### d. Masukkan ke monitoring
Di **Cascade KPI**, kartu *Masukkan ke monitoring*: pilih periode, lalu klik
**Masukkan**. Hanya KPI berstatus **Lolos** yang masuk ke Objective Departemen.

### e. Objective Departemen *(setiap bulan)*
Klik ✏️ pada tiap sasaran dan isi **Actual** bulan itu. Capaian dihitung sesuai
polaritas (Naik / Turun / Rentang).

**Hasil:** Tingkat 3 piramida = rata-rata capaian sasaran mutu periode itu.

---

## Tingkat 4 · Program Kerja (Action)

1. Untuk sasaran yang **Waspada** atau **Di Bawah Target**, buat program kerja
   perbaikan: judul, unit pemilik, dan sasaran yang dimitigasi.
2. Perbarui **progres (%)** secara berkala.

**Hasil:** Tingkat 4 piramida = rata-rata progres program kerja yang tertaut ke
sasaran periode itu.

---

## Membaca hasil

- **Piramida BSC**: klik tingkat mana pun untuk langsung melihat telusur
  detailnya. Titik warna: hijau Tercapai (≥100%), kuning Waspada (80–99%),
  merah Di Bawah Target (<80%), abu-abu belum lengkap.
- **Skor puncak** = 45% × F1 + 55% × F2. Tingkat tanpa data dikeluarkan dan
  bobotnya dibagi ke tingkat yang tersedia.
- **Wiring / Peta Hubungan**: jalur target revenue → perspektif rasio → unit
  kerja, ditarik dari Cascade KPI.
- **Konsolidasi Holding** (hanya instalasi holding): keempat entitas
  berdampingan, dengan revenue grup setelah eliminasi penjualan antarentitas.

---

## Pertanyaan umum

**Tingkat 1 "target bulanan belum difasing".** Target setahun sudah disahkan,
tetapi target bulanan kosong. Klik *Bagi rata* atau *Pola musiman*, lalu
*Simpan*.

**Tingkat 1 "belum ada realisasi".** Isi kolom Realisasi di Target & Realisasi
Revenue.

**Tingkat 2 "target rasio belum diisi".** Pos akun sudah ada, tetapi target di
Katalog Rasio kosong.

**Rasio tidak bisa diedit.** Rasio bertanda *Otomatis* berasal dari Pos Akun.
Ubah angkanya lewat Pos Akun atau Katalog Rasio.

**Akunnya ada di Integrasi & Gateway, tetapi tidak ada rasionya.** Misalnya
*Rebate*: akunnya dipetakan ke PA01 Penjualan, jadi ia mengurangi penjualan dan
lebur di dalamnya — tidak ada angka tersendiri yang bisa dijadikan rasio.
Langkah dan pilihannya ada di
**[Pos Akun & Rumus › Akunnya ada di Odoo, tetapi tidak ada rasionya](katalog-pos-dan-rumus.md)**,
juga tersedia di layar lewat tombol *Panduan Odoo* di Integrasi & Gateway.

**Kotak konfirmasi.** Tindakan yang menimpa atau menghapus data selalu
menanyakan dulu lewat kotak konfirmasi aplikasi (bukan kotak bawaan peramban
yang menampilkan nama domain). Tombol merah berarti tindakannya menghapus.

**Sasaran bertanda "belum di cascade".** Sasaran lama yang belum tertaut ke KPI.
Buka Cascade KPI, lalu klik *Ambil dari Objective Departemen*.

**Periode ditutup.** Periode berstatus CLOSED tidak dapat diubah. Buka kembali
lewat Piramida BSC (peran dengan izin *override*).

**Butuh data contoh untuk latihan.** Set `BSC_SEED_DEMO=true` di `.env`, lalu
jalankan `php artisan migrate:fresh --seed`. Keempat tingkat piramida terisi
angka ilustrasi workbook:

| Tingkat | Isi contoh | Di mana melihatnya |
|---|---|---|
| 1 | Target 2026 Rp 840 M difasing 70 M/bulan, realisasi Jan–Agu (F1 96,43%); Perencanaan Target 2027 lengkap dengan target Rp 900 M disahkan | Target & Realisasi · Perencanaan Target (tahun 2027) |
| 2 | Pos akun periode 2026-08 dan target rasio tahun 2026 (F2 94,1) | Pos Akun · Katalog Rasio · Rasio Keuangan |
| 3 | 8 KPI Head berstatus Lolos beserta hasil Uji A/B, dan sasaran bulanan berisi realisasi | Cascade KPI · Uji Indikator · Objective Departemen |
| 4 | 3 program kerja untuk sasaran yang Waspada | Program Kerja (Action) |

Hati-hati: `migrate:fresh` menghapus semua data. Untuk menambahkan data contoh
ke database yang sudah berisi, jalankan `php artisan db:seed --class=BscDataSeeder`;
perintah ini **menimpa** data periode 2026-08 dan revenue 2026 entitas itu.
Kembalikan `BSC_SEED_DEMO=false` sebelum dipakai untuk data sungguhan.
