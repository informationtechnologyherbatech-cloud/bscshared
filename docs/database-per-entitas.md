# Database per Entitas & Konsolidasi Holding

Tiap entitas menyimpan datanya di **databasenya sendiri**. Holding (EMC) punya
database sendiri yang **tidak berisi data entitas**: saat halaman Konsolidasi
dibuka, holding meminta *ringkasan* ke masing-masing entitas, menampilkannya,
lalu melupakannya kembali (hanya disimpan sebentar di cache).

Akibatnya: **entitas tidak dapat melihat data entitas lain**, dan yang melihat
gambaran keseluruhan hanya holding.

```
        ENTITAS                                   HOLDING (EMC)
┌────────────────────────┐                   ┌─────────────────────────┐
│ aplikasi + db_bsc_aej  │◀── ringkasan ─────│ aplikasi + db_bsc_emc   │
├────────────────────────┤     (API /        │  • entities             │
│ aplikasi + db_bsc_hbt  │◀──  koneksi db)   │  • intercompany_sales   │
├────────────────────────┤                   │  • users holding        │
│ aplikasi + db_bsc_erd  │◀──────────────────│  (tanpa data entitas)   │
└────────────────────────┘                   └─────────────────────────┘
```

## Yang menyeberang batas entitas

Hanya **ringkasan** (`EntitySummary`):

| Isi | Contoh |
|---|---|
| Identitas entitas | kode, nama, nama PT, bidang usaha |
| Skor | F1, F2, skor puncak |
| Revenue kumulatif | target YTD, realisasi YTD |
| Hitungan | jumlah sasaran mutu, rata-rata capaiannya, jumlah KPI & yang Lolos |
| Telusur terbatas | 19 rasio (nilai & capaiannya) dan ringkasan per unit kerja |

**Tidak pernah ikut:** pos akun, isi sasaran mutu, program kerja, cascade KPI,
pengguna, atau baris transaksi apa pun. Aplikasi entitas memang tidak punya
endpoint untuk itu.

## Mengatur sumber data: lewat layar (disarankan)

Pada pemasangan holding, buka **Sumber Data Entitas** di sidebar (di bawah
Konsolidasi Holding). Untuk tiap entitas, pilih caranya lalu isi keterangannya:

| Pilihan | Yang diisi |
|---|---|
| **API** — entitas di server lain | alamat aplikasi entitas + kunci API-nya |
| **Database terpisah** | nama database entitas |
| **Database aplikasi ini** | tidak ada; datanya memang di sini |

Tombol **Uji** memanggil `/api/v1/ping` entitas itu (atau membuka databasenya)
dan menampilkan hasilnya — tersambung ke entitas mana, versi aplikasinya, atau
sebab kegagalannya. Tidak ada angka kinerja yang ikut terambil saat menguji.

Kunci API **disimpan terenkripsi** di tabel `entity_sources` dan setelah disimpan
tidak pernah ditampilkan utuh lagi (hanya empat huruf terakhirnya). Mengosongkan
kolom kunci saat menyunting berarti "pakai kunci yang lama".

Hanya pengguna level holding yang dapat membuka halaman ini; mengubahnya butuh
izin `manage consolidation` (Super Admin & Admin FAT).

Mengubah sumber otomatis membuang ringkasan yang tersimpan di cache, sehingga
angka dari sumber lama tidak ikut terbawa.

## Alternatif: lewat berkas .env

Berguna untuk penyebaran otomatis atau pemasangan yang belum memakai layar di
atas. **Pengaturan di layar selalu menang**; `.env` menjadi nilai awal yang
otomatis terisi saat pertama kali menyunting entitas itu.

### 1. API — entitas di server lain (paling terisolasi)

```env
BSC_SOURCE_AEJ_URL=https://bsc.aej.co.id
BSC_SOURCE_AEJ_KEY=bsc_live_xxxxxxxxxxxxxxxxxxxxxxxx
```

Kunci API dibuat di aplikasi **entitas**: Setting Sistem → tab API → *Generate
Kunci Baru*, lalu tempel di halaman Sumber Data Entitas (atau `.env`) holding.

Alamatnya **wajib HTTPS** (kecuali `localhost`/`127.0.0.1` atau lingkungan
pengembangan) karena kunci ikut di setiap permintaan. Holding juga tidak
mengikuti pengalihan (redirect) dari server entitas, sehingga kunci tidak
berpindah ke alamat lain.

### 2. Database terpisah — satu server, banyak database

```env
BSC_SOURCE_AEJ_DB=db_bsc_aej
```

Holding membuka koneksi tambahan ke database itu memakai kredensial `DB_*` yang
sama, membaca ringkasannya, lalu menutup koneksinya.

### 3. Lokal — semuanya di satu database

Tidak diisi apa pun. Ini perilaku lama (cocok untuk pengembangan atau pemasangan
tunggal); pemisahan datanya bersandar pada kolom `entity_id`.

Bila URL dan DB sama-sama diisi, **API yang dipakai**.

## Memasang aplikasi entitas

```env
APP_URL=https://bsc.aej.co.id
DB_DATABASE=db_bsc_aej

BSC_DEFAULT_ENTITY=AEJ     # entitas pemilik pemasangan ini
BSC_HOLDING_MODE=false     # tanpa pengalih entitas & tanpa menu Konsolidasi
BSC_SEED_DEMO=false
```

```bash
php artisan migrate --seed      # struktur + unit kerja entitas itu
```

Migrasi `2026_09_23_100000_bind_api_keys_to_entity` menambahkan kolom
`api_keys.entity_code`; kunci yang sudah ada otomatis diikat ke entitas
pemasangan itu.

Lalu buat kunci API di Setting Sistem → tab API.

## Memasang aplikasi holding

```env
APP_URL=https://bsc.emc.co.id
DB_DATABASE=db_bsc_emc

BSC_HOLDING_MODE=true
BSC_DEFAULT_ENTITY=ERDIGMA       # entitas yang ditampilkan lebih dulu

BSC_SOURCE_AEJ_URL=https://bsc.aej.co.id
BSC_SOURCE_AEJ_KEY=bsc_live_...
BSC_SOURCE_HERBATECH_URL=https://bsc.herbatech.co.id
BSC_SOURCE_HERBATECH_KEY=bsc_live_...
BSC_SOURCE_HERBAEMAS_DB=db_bsc_herbaemas   # contoh: satu server
BSC_SOURCE_ERDIGMA_DB=db_bsc_erdigma

BSC_CONSOLIDATION_TTL=300        # detik ringkasan disimpan di cache (0 = selalu langsung)
BSC_API_TIMEOUT=8                # batas waktu satu panggilan API (detik)
BSC_API_MAX_BYTES=2097152        # batas ukuran jawaban API entitas
BSC_REQUIRE_ENTITY_SOURCES=true  # entitas tanpa sumber = galat, bukan dibaca dari db holding
```

```bash
php artisan migrate --seed
```

Database holding hanya berisi daftar entitas, penjualan antarentitas
(eliminasi), pengguna holding, dan pengaturan aplikasi.

## API entitas

| Endpoint | Keterangan |
|---|---|
| `GET /api/v1/consolidation?period=YYYY-MM` | Ringkasan entitas pemasangan itu. Periode kosong = periode aktif. |
| `GET /api/v1/ping` | Uji sambungan: nama entitas, versi aplikasi, waktu server. Tanpa angka kinerja. |

Keduanya wajib membawa header `X-API-KEY` berisi kunci aktif. Tanpa kunci atau
dengan kunci nonaktif: **401**. Kunci milik entitas lain: **403** — tiap kunci
menyebut entitas pemiliknya, jadi kunci entitas A tidak berlaku di pemasangan
entitas B sekalipun keduanya berbagi satu database. Permintaan ke pemasangan
holding: **409** — holding memanggil entitas, bukan sebaliknya.

Batas laju 60 permintaan per menit **diperiksa lebih dulu**, sehingga menebak
kunci juga ikut dibatasi.

Kode entitas pada permintaan **diabaikan**: endpoint selalu menjawab dengan data
entitas pemasangan itu sendiri, sehingga holding tidak bisa menitip pertanyaan
tentang entitas tetangga.

```bash
curl -H "X-API-KEY: bsc_live_..." \
     "https://bsc.aej.co.id/api/v1/consolidation?period=2026-08"
```

```json
{
  "data": {
    "code": "AEJ", "name": "AEJ", "period": "2026-08",
    "f1": 96.43, "f2": 94.1, "apex": 95.15,
    "revenue_target": 560000000000, "revenue_actual": 540000000000,
    "objectives": 8, "objective_score": 88.2, "kpi_total": 8, "kpi_approved": 8,
    "ratios": [{ "code": "P1", "name": "Gross Profit Margin", "unit": "%",
                 "target": 37, "actual": 35, "achievement": 94.6, "status": "Waspada" }],
    "units":  [{ "code": "OPS", "name": "Operasional", "objectives": 5,
                 "score": 88.0, "status": "waspada" }]
  }
}
```

## Di halaman Konsolidasi Holding

- Kolom **Sumber data** menunjukkan asal angka tiap entitas: `API bsc.aej.co.id`,
  `database db_bsc_aej`, atau `database aplikasi ini` — diatur di menu **Sumber
  Data Entitas**.
- Tombol telusur (🔍) pada tiap baris membuka 19 rasio dan ringkasan unit kerja
  entitas itu, sebatas yang dikirim entitasnya.
- Tombol **Segarkan** membuang cache dan menarik ulang dari sumbernya.
- Entitas yang tidak terjangkau ditandai **tidak terjangkau**; skornya dibiarkan
  kosong (bukan 0) dan ada peringatan bahwa angka grup belum lengkap.
- Eliminasi penjualan antarentitas tetap dicatat di holding, karena memang milik
  holding.

## Catatan

- Kegagalan sambungan hanya disimpan 30 detik, sehingga entitas yang sempat mati
  langsung tampil lagi begitu hidup.
- Entitas yang tidak terjangkau **tidak ikut menambah total kotor** grup; angka
  grup saat itu memang belum lengkap, dan halamannya mengatakan demikian.
- Kunci cache memuat identitas pemasangan dan sidik sumbernya, sehingga dua
  pemasangan yang berbagi Redis tidak saling menimpa dan pergantian sumber
  (API ↔ database) tidak dilayani data lama.
- Pesan galat di layar sengaja umum; rincian teknis (host, nama database, SQL)
  hanya masuk ke log.
- `BSC_REQUIRE_ENTITY_SOURCES=true` disarankan begitu database per entitas benar
  dipakai: tanpa itu, salah ketik nama variabel membuat holding diam-diam membaca
  databasenya sendiri dan menampilkan angkanya seolah milik entitas tersebut.
- Holding tidak pernah menulis ke database entitas; seluruh pembacaan bersifat
  baca-saja.
- Pengguna yang terikat satu entitas tetap tidak dapat membuka menu Konsolidasi,
  dan pengalih entitas hanya ada pada pemasangan holding.
