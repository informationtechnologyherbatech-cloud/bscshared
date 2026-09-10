# Super Apps Balanced Scorecard (BSC)

Platform manajemen kinerja strategis berbasis metodologi Kaplan–Norton: piramida
4 level (Apex korporat → 4 perspektif → sasaran mutu departemen → action plan),
analisis rasio keuangan, wiring sebab-akibat, serta gerbang penerimaan data dari
sistem HRIS/Finance.

Aplikasi bersifat **multi-entitas**: satu basis kode dapat dipakai perusahaan mana
pun. Identitas entitas diatur lewat menu *Setting Sistem*, bukan lewat kode.

---

## Teknologi

| Komponen | Versi |
|---|---|
| PHP | 8.4 |
| Laravel | 13.x |
| Livewire | 4.x |
| Database | MySQL 8.0+ / MariaDB 10.5+ |
| UI | Bootstrap 4.6 + AdminLTE 3.2 + jQuery |
| RBAC | spatie/laravel-permission 8.x |
| Build asset | Vite 7 |

Berkas Bootstrap, AdminLTE, jQuery dan Font Awesome dilayani **offline** dari
`public/vendor/` (dengan fallback CDN otomatis bila berkasnya tidak ada), sehingga
aplikasi tetap tampil normal tanpa koneksi internet. Vite hanya membundel CSS/JS
kustom di `resources/`.

---

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
# sesuaikan DB_DATABASE / DB_USERNAME / DB_PASSWORD di .env
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Akun awal hasil seeder: `superadmin@herbatech.co.id` / `password`
(segera ganti pada menu *Manajemen Pengguna*).

---

## Identitas Entitas

Seluruh identitas perusahaan disimpan di tabel `app_settings` dan dikelola pada
menu **Setting Sistem → Identitas Aplikasi**:

| Pengaturan | Kunci | Dipakai di |
|---|---|---|
| Nama entitas | `entity_name` | sidebar, atribut gambar |
| Nama perusahaan | `company_name` | judul halaman, footer, halaman login |
| Alamat perusahaan | `company_address` | footer |
| Nomor kontak | `company_phone` | footer |
| Email kontak | `company_email` | footer, halaman login |
| Situs web | `company_website` | footer |
| Logo | `app_logo` | sidebar, halaman login |
| Favicon | `app_favicon` | tab peramban |
| Nama & tagline aplikasi, tahun, warna primary | `app_name`, `app_tagline`, `app_year`, `app_primary_color` | seluruh antarmuka |

Nilai cadangan (dipakai bila pengaturan kosong) berada di `config/entity.php`.
Pembacaannya lewat helper, bukan query langsung:

```php
entity_name();                 // Herbatech Innopharma
company_name();                // PT Herbatech Innopharma Industry
entity('company_email');       // email kontak, fallback ke config
entity_logo();                 // URL logo, null bila belum diunggah
entity_favicon();              // URL favicon, fallback favicon.ico
entity_copyright();            // "PT ... © 2026"
```

Pengaturan dibaca lewat cache (`AppSetting::map()`) sehingga satu halaman hanya
menghasilkan satu query; cache otomatis dibersihkan setiap pengaturan disimpan.

---

## Versi Aplikasi

Versi aplikasi diambil dari helper `app_version()` pada
[`app/Helpers/helper.php`](app/Helpers/helper.php) — sesuai pola yang dipakai
proyek *official-website*:

```php
app_version();       // "v1.0.0"
app_version(false);  // "1.0.0"
```

Sumber nilainya `config('app.version')`, yang membaca `APP_VERSION` pada `.env`,
sehingga versi dapat dinaikkan tanpa mengubah kode. Versi ditampilkan pada footer,
halaman login, dan menu *Setting Sistem → Informasi Sistem*.

Helper dimuat otomatis lewat `autoload.files` di `composer.json`.

---

## Hak Akses

Enam peran (`Super Admin`, `Admin FAT`, `Admin HRIS`, `Kepala Departemen`,
`Operator`, `Viewer`) didefinisikan di
[`database/seeders/RolesAndPermissionsSeeder.php`](database/seeders/RolesAndPermissionsSeeder.php).
Setiap rute dijaga middleware `permission:` dan menu sidebar hanya muncul sesuai
izin peran. Pengguna non-aktif otomatis dikeluarkan oleh middleware `active`.

---

## Pengujian

```bash
php artisan test
```

Mencakup smoke test seluruh menu, middleware akun non-aktif, helper versi, dan
pengaturan identitas entitas.

---

## Dokumen Terkait

- `BUKU_PANDUAN_LENGKAP_SUPER_APPS_BSC_DAN_EKOSISTEM.md` — arsitektur & 12 modul
- `DOKUMENTASI_INTEGRASI_HRIS_FINANCE_BSC.md` — blueprint integrasi HRIS/Finance
- `PANDUAN_DOKUMENTASI_API_POSTMAN.md` — standar dokumentasi API

> Catatan: ketiga dokumen di atas menjelaskan API Gateway 4-hop
> (`/api/bsc/sync/*`) yang **belum diimplementasikan** pada basis kode ini.
> Menu *Integrasi Sistem* dan *Staging Log* saat ini masih berupa simulasi lokal.
