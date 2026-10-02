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

## 5. Untuk pengembang

- `App\Support\Bsc\Formula` — satu-satunya tempat rumus diurai dan dihitung
  (tokeniser → pohon → evaluasi), termasuk penulisan ulang rumus menjadi teks.
- `App\Support\Bsc\AccountPosts` — katalog pos entitas aktif, dengan 16 pos
  bawaan sebagai cadangan bila entitas belum punya katalognya.
- `App\Support\Bsc\RatioLibrary::referenceCompute()` — perhitungan baku workbook
  yang **tidak dipakai aplikasi**; hanya menjadi acuan pengujian yang
  membuktikan mesin rumus menghasilkan angka yang sama persis untuk ke-19 rasio
  bawaan, termasuk saat ada pos kosong dan penyebut nol.
- Pengujiannya: `tests/Feature/KatalogPosDanRumusTest.php`.
