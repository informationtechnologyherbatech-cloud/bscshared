# Pos Akun & Rumus Rasio yang Dapat Disesuaikan

Metodologi BSC datang dengan 16 pos akun dan 19 rasio dari workbook. Susunan itu
tetap menjadi **titik awal setiap entitas**, tetapi kini bukan lagi daftar mati:
tiap entitas dapat menambah pos akun, menambah rasio, dan mengubah rumusnya
sesuai keadaan perusahaannya.

Satu hal yang dijaga di seluruh dokumen ini: **rumus yang tertulis di layar
adalah rumus yang benar-benar dihitung.** Tidak ada perhitungan tersembunyi di
balik keterangan.

---

## 1. Menyesuaikan pos akun

Menu **Pos Akun** → tombol **Sesuaikan pos akun** (hanya untuk yang berhak
`manage ratios`, yaitu Admin FAT dan Super Admin).

| Yang dapat dilakukan | Pos bawaan | Pos tambahan |
|---|---|---|
| Mengubah nama & keterangan | ✅ | ✅ |
| Mengubah sumber data (GL/HRIS) | ✅ | ✅ |
| Mengubah jenis | ❌ dikunci | ✅ |
| Menonaktifkan | ✅ bila tidak dipakai rumus | ✅ |
| Menghapus | ❌ | ✅ bila tidak dipakai rumus |

Pos bawaan tidak dapat berganti kode dan jenis karena rumus bawaan bergantung
padanya — mengubahnya diam-diam akan menggeser angka yang sudah tersimpan.
Bila sebuah pos tidak dipakai entitas ini, **nonaktifkan** saja: angkanya tetap
tersimpan dan pos itu hilang dari layar isian.

### Sumber data: apa arti "GL"?

| Sumber | Artinya |
|---|---|
| **GL** | Buku besar akuntansi (*General Ledger*) — angkanya masuk lewat Odoo atau unggahan CSV |
| **HRIS** | Sistem kepegawaian — jumlah karyawan, jam kerja |
| **GL / HRIS** | Dapat berasal dari keduanya |
| **Manual** | Diketik sendiri di layar Pos Akun, tanpa sistem sumber |

Sumber hanya menjelaskan **dari mana angkanya datang**; ia tidak mengubah
perhitungan. Yang mengubah perhitungan adalah *jenis* pos di bawah ini.

### Jenis pos menentukan cara angkanya dipakai

| Jenis | Diisi apa | Jadi "nilai dipakai" |
|---|---|---|
| Aliran | nilai YTD | × 12 ÷ bulan berjalan (disetahunkan) |
| Neraca | saldo awal tahun & saldo akhir | (awal + akhir) ÷ 2; tanpa saldo awal dipakai saldo akhir |
| Rata-rata periode | rata-rata (mis. jumlah karyawan) | apa adanya |
| Aliran HRIS | total YTD (mis. jam kerja) | × 12 ÷ bulan berjalan |

Kolom **Nilai dipakai** di layar Pos Akun selalu memperlihatkan hitungannya,
jadi pilihan jenis dapat diperiksa langsung tanpa menebak.

---

## 2. Menambah & mengubah rasio

Menu **Katalog Rasio** → **Tambah rasio**, atau ikon pensil pada barisnya untuk
mengubah rasio yang sudah ada.

Satu rasio terdiri atas:

- **Kode** — pengenal singkat (R1, R2, …). Kode inilah yang dipakai bila rasio
  lain ingin menunjuknya.
- **Nama** dan **Kelompok** — kelompok dipilih dari lima kelompok baku
  (Profitabilitas, Aktivitas, Produktivitas, Likuiditas, Solvabilitas), atau
  **+ Kelompok baru…** lalu diberi nama sendiri; kelompok baru muncul sendiri di
  rekap bobot.
- **Satuan** — %, x, hari, atau Rp. Menentukan cara angkanya ditulis.
- **Polaritas** — arah yang dianggap baik: Naik, Turun, atau Rentang.
- **Bobot** — sumbangannya ke skor F2.
- **Rumus** — lihat bagian 3.

Rasio bawaan tidak dapat dihapus (nonaktifkan saja), rasio buatan sendiri dapat
dihapus bila tidak ada rumus lain yang menunjuknya.

Setelah disimpan, seluruh periode tahun berjalan yang sudah punya pos akun
**langsung dihitung ulang** — kecuali periode yang sudah ditutup (CLOSED), yang
sengaja dibiarkan apa adanya.

---

## 3. Menulis rumus

Rumus ditulis dengan kode pos akun, angka, dan aritmetika biasa:

```
(PA01 - PA02) / PA01 * 100
365 / A1
PA17 / PA01 * 100
```

### Yang dapat dipakai

| | Contoh | Keterangan |
|---|---|---|
| Kode pos akun | `PA01`, `PA17` | Daftarnya ada di bawah kotak rumus |
| Laba kotor | `LK` | Penjualan − HPP |
| Laba bersih | `LB` | Laba kotor − Beban usaha |
| Kode rasio lain | `365 / A1` | "365 ÷ Inventory Turnover" |
| Angka | `100`, `365`, `0,5` | Koma maupun titik desimal diterima |
| Operator | `+ - * /` atau `+ − × ÷` | Tanda hasil salin dari Excel juga diterima |
| Kurung | `(PA09 - PA05) / PA10` | Urutan hitung biasa: kali/bagi dulu |

### Aturan angka kosong

Bila salah satu pos yang dipakai **belum diisi**, atau penyebutnya **nol**,
hasilnya **kosong — bukan nol**. Rasio yang kosong tidak ikut diskor dan
bobotnya dinormalisasi, sehingga pos yang belum terisi tidak tampil sebagai
capaian buruk.

### Yang ditolak

- Kode yang tidak dikenal → *"Kode tidak dikenal: PA99."*
- Kurung yang tidak berpasangan, operator tanpa angka sesudahnya.
- Rumus yang memakai kodenya sendiri (`P1 = P1 * 2`).
- Rumus yang saling menunjuk sampai berputar — hasilnya kosong, bukan menggantung.

### Tidak perlu menghafal kode

Di bawah kotak rumus tersedia barisan tombol: operator (`+ − × ÷ ( ) 100 365`)
dan seluruh kode beserta namanya — pos akun, turunan LK/LB, serta rasio lain.
Mengkliknya menyisipkan kode itu ke ujung rumus lengkap dengan spasinya, jadi
rumus dapat disusun tanpa mengetik satu kode pun.

Tepat di bawah kotak rumus juga ada baris **Terbaca:** yang menulis ulang rumus
dengan nama pos akun selagi diketik — `PA08 / PA10` terbaca
*"Kas & setara kas ÷ Liabilitas lancar"*. Kalimat itulah yang nanti tampil di
bawah nama rasio.

### Pratinjau sebelum disimpan

Saat rumus diketik, kotak pratinjau langsung **mencobanya dengan angka pos akun
periode terakhir yang terisi** dan menampilkan hitungannya:

```
Dicoba dengan angka pos akun periode Agustus 2026:
120.000.000 ÷ 810.000.000.000 × 100
0,01%
```

Jadi rumus dapat diperiksa tanpa kalkulator dan tanpa menyimpan dulu.

### Keterangan rumus ditulis ulang otomatis

Teks yang tampil di bawah nama rasio (*"Laba kotor ÷ Penjualan × 100"*) dibangun
dari rumusnya sendiri, dengan kode diganti nama pos akun. Karena itu keterangan
di layar tidak mungkin menjelaskan perhitungan yang berbeda dari yang dijalankan.

---

## 4. Akibatnya ke bagian lain

- **Rasio Keuangan & skor F2** — rasio buatan sendiri ikut dihitung, diskor, dan
  masuk rekap kelompoknya.
- **Peta Pos Akun & Cascade KPI** — pos pembentuk sebuah rasio dibaca dari
  rumusnya, sehingga KPI dapat mengklaim rasio buatan sendiri seperti rasio
  bawaan.
- **Cek konsistensi target** di Katalog Rasio tetap memeriksa pasangan rasio
  bawaan (NPM ≤ GPM, DIO = 365 ÷ ITO, dan seterusnya). Rasio buatan sendiri
  tidak punya pasangan baku, jadi tidak ikut diperiksa.
- **Integrasi Odoo & unggahan CSV** mengikuti katalog pos entitas, termasuk pos
  tambahan — pemetaan kode akunnya diatur seperti biasa di Integrasi & Gateway.

## 5. Perkara nyata: akunnya ada di Odoo, tetapi tidak ada rasionya

Pertanyaan yang paling sering muncul: *"Di Integrasi & Gateway ada akun Rebate,
tetapi di Rasio Keuangan tidak ada rasio rebate. Bagaimana memunculkannya?"*

### Mengapa belum muncul

Angka menempuh lima tahap, dan rebate berhenti di tahap ketiga:

```
Akun Odoo  →  Pemetaan  →  Pos akun  →  Rumus  →  Rasio
41000062        PA01        (lebur)      —        tidak ada
```

Rebate dipetakan ke **PA01 Penjualan** dengan *balik tanda*, sehingga ia
**mengurangi** penjualan dan lebur ke dalamnya — penjualan yang tersimpan sudah
neto. Karena rebate tidak pernah berdiri sebagai angka tersendiri, tidak ada
yang bisa dijadikan rasio.

> **Aturan yang menentukan pilihan:** satu kode akun sistem sumber hanya boleh
> menunjuk **satu** pos akun (unik per entitas). Jadi satu akun rebate tidak bisa
> sekaligus mengurangi PA01 *dan* mengisi pos rebate.

### Cara A — rebate dipindahkan ke pos akun sendiri

1. **Pos Akun → Sesuaikan pos akun → Tambah pos akun**
   kode `PA17`, nama `Rebate`, jenis **Aliran**, sumber **GL**.
2. **Integrasi & Gateway → Sambungan Odoo → pemetaan akun**
   ubah `41000062 Rebate` dan `41000065 Rebate Compliance` dari `PA01` ke `PA17`.
   **Balik tanda dimatikan**: akun kontra-pendapatan bersaldo *debit*, jadi
   angkanya sudah positif. (Penjualan sendiri bersaldo kredit, karena itu PA01
   tetap dibalik.)
3. **Tarik ulang**, lalu lihat kolom *Nilai dipakai* di menu Pos Akun. Kalau
   PA17 muncul negatif, nyalakan balik tanda pada kedua pemetaan itu. Ini cara
   paling aman memastikan tandanya: dilihat, bukan ditebak.
4. **Katalog Rasio → Tambah rasio**
   kode `R1`, nama `Rebate terhadap penjualan`, satuan `%`, polaritas **Turun**,
   bobot mis. `4`, rumus:

   ```
   PA17 / PA01 * 100
   ```

5. Isi **target** tahunannya, lalu **Simpan** — periode berjalan langsung
   dihitung ulang.
6. **Tata ulang bobot** agar totalnya kembali 100 (kurangi bobot rasio lain
   sebesar bobot baru).

**Akibat yang harus disadari:** PA01 kini penjualan **bruto**. Semua rasio yang
memakai penjualan — GPM, NPM, Asset Turnover, AR Turnover/DSO, produktivitas per
karyawan/jam/biaya TK — ikut naik sedikit. **Realisasi revenue bulanan di
Tingkat 1 juga ditarik dari akun-akun yang dipetakan ke PA01**, jadi realisasi
revenue pun menjadi bruto. Bila target revenue disusun atas dasar neto,
perbandingannya tidak lagi setara.

### Cara B — penjualan tetap neto, rebate tetap terukur

1. Pemetaan akun rebate **dibiarkan** di `PA01` (dibalik) — tidak ada angka lama
   yang berubah.
2. Buat pos `PA17` `Rebate`, sumber **Manual**.
3. Isi angkanya tiap periode, lewat salah satu dari:
   - layar **Pos Akun** (diketik), atau
   - **unggahan CSV** — kode PA dipakai apa adanya, tanpa pemetaan:

     ```csv
     kode;nilai
     PA17;40000000
     ```

4. Buat rasionya seperti Cara A. Karena PA01 di sini neto, rumus
   `PA17 / PA01 * 100` berarti "rebate terhadap penjualan neto"; bila yang
   diinginkan terhadap penjualan bruto, pakai `PA17 / (PA01 + PA17) * 100`.

**Harganya:** angka rebate tidak ikut tertarik otomatis dari Odoo — harus diisi
tiap periode.

### Cara C — cukup diketahui

Biarkan apa adanya. Rebate tetap mengurangi penjualan, dan tidak ada rasio
rebate. Ini pilihan yang benar bila rebate tidak dipantau sebagai kinerja.

### Memilih di antara ketiganya

| Pertanyaan | Jawabannya |
|---|---|
| Rebate perlu jadi KPI/rasio yang dipantau? | Tidak → **C** |
| Penjualan di BSC harus tetap neto (sama dengan laporan keuangan & target revenue)? | Ya → **B** |
| Boleh penjualan menjadi bruto, asal semuanya otomatis dari Odoo? | Ya → **A** |

### Perkara lain yang berpola sama

Semua akun di bawah ini kini lebur ke pos lain. Bila salah satunya ingin
dipantau sendiri, langkahnya sama: **buat pos akunnya lebih dulu, baru rumusnya.**

| Perkara | Sekarang lebur di | Pos baru yang masuk akal | Rumus contoh | Polaritas |
|---|---|---|---|---|
| Retur & potongan penjualan | PA01 (dibalik) | `PA18` Retur penjualan | `PA18 / PA01 * 100` | Turun |
| Sales discount | PA01 (dibalik) | `PA19` Diskon penjualan | `PA19 / PA01 * 100` | Turun |
| Beban pemasaran | PA03 | `PA20` Beban pemasaran | `PA20 / PA01 * 100` | Turun |
| Beban logistik/pengiriman | PA03 | `PA21` Beban logistik | `PA21 / PA01 * 100` | Turun |
| Beban bunga | PA03 | `PA22` Beban bunga | `LK / PA22` (*interest coverage*) | Naik |
| Pendapatan lain-lain | PA01 | `PA23` Pendapatan lain-lain | `PA23 / PA01 * 100` | Naik |
| Kas tertahan/jaminan | PA08 | `PA24` Kas dibatasi | `(PA08 - PA24) / PA10` | Naik |

Dua hal yang berlaku untuk semuanya:

- **Mengeluarkan sesuatu dari pos induknya mengubah pos induk itu.** Memindahkan
  beban pemasaran keluar dari PA03 membuat laba bersih (LB) naik, sehingga NPM,
  ROA, dan ROE ikut naik. Bila itu tidak dikehendaki, pakai pola **Cara B**:
  biarkan pemetaannya, isi pos barunya terpisah.
- **Pos yang hanya untuk diukur tidak boleh ikut dijumlahkan dua kali.** Pos
  buatan sendiri tidak dipakai rumus bawaan mana pun, jadi aman; yang perlu
  dijaga hanyalah rumus buatan sendiri — jangan menulis `PA01 + PA17` bila PA17
  sudah termasuk di dalam PA01.

### Setelah menambah rasio

- **Bobot**: total bobot rasio aktif sebaiknya 100. Kotak *Bobot per kelompok* di
  Katalog Rasio menunjukkan selisihnya. Bila bukan 100, F2 tetap dihitung dengan
  normalisasi — jadi tidak rusak, hanya kurang rapi.
- **Target**: rasio tanpa target tetap tampil angkanya, berstatus
  *Belum Ada Target*, dan **tidak ikut diskor**.
- **KPI**: rasio baru langsung dapat diklaim KPI di Cascade KPI, karena pos
  pembentuknya dibaca dari rumusnya.

## 6. Untuk pengembang

- `App\Support\Bsc\Formula` — satu-satunya tempat rumus diurai dan dihitung
  (tokeniser → pohon → evaluasi), termasuk penulisan ulang rumus menjadi teks.
- `App\Support\Bsc\AccountPosts` — katalog pos entitas aktif, dengan 16 pos
  bawaan sebagai cadangan bila entitas belum punya katalognya.
- `App\Support\Bsc\RatioLibrary::referenceCompute()` — perhitungan baku workbook
  yang **tidak dipakai aplikasi**; hanya menjadi acuan pengujian yang
  membuktikan mesin rumus menghasilkan angka yang sama persis untuk ke-19 rasio
  bawaan, termasuk saat ada pos kosong dan penyebut nol.
- Pengujiannya: `tests/Feature/KatalogPosDanRumusTest.php`.
