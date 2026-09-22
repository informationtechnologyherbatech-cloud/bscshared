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

Akun awal hasil seeder: `superadmin@herbatech.co.id` / `Bsc#Admin2026`
— **segera ganti** pada menu *Manajemen Pengguna*. Keduanya dapat ditentukan
sendiri lewat `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` di `.env` sebelum
seeder dijalankan. Instalasi yang sudah ada tidak diubah oleh seeder.

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

## Multi-Entitas

Satu aplikasi dipakai empat entitas di bawah holding **Erhanesia Mulia Corpora**:
Herbaemas, Herbatech, AEJ (manufaktur), dan Erdigma (digital marketing). Mesin
penilaiannya sama; yang berbeda per entitas hanya konfigurasinya — unit kerja,
target revenue, dan (menyusul) bobot rasio.

- Seluruh data BSC bertanda `entity_id` dan otomatis dibatasi pada entitas yang
  sedang dibuka lewat trait [`BelongsToEntity`](app/Models/Concerns/BelongsToEntity.php);
  baris baru otomatis ditandai entitas aktif. Komponen tidak perlu menyaring sendiri.
- Entitas aktif ditentukan [`EntityContext`](app/Support/EntityContext.php):
  pengguna yang ditautkan ke satu entitas selalu berada di entitas itu; pengguna
  **level holding** (tanpa entitas) berpindah lewat pengalih di navbar.
- Tautkan pengguna ke entitasnya di **Manage User → Entitas**.
- Kelola struktur organisasi tiap entitas di **Administrasi → Unit Kerja**.

Metodologi lengkap dan tahapan penerapannya:
[docs/penerapan-metodologi-excel.md](docs/penerapan-metodologi-excel.md).

### Skor puncak

Mengikuti workbook `Cascading_Revenue_Rasio_KPI_Erdigma_2026.xlsx`:

```
Skor puncak = 0,45 × F1 (pencapaian revenue kumulatif) + 0,55 × F2 (skor rasio keuangan)
```

F1 diisi lewat menu **Target Revenue**. Bila salah satu belum punya data, bobotnya
dinormalisasi ke yang tersedia. Bobot diatur di `config/bsc.php`.

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

## Keamanan

### reCAPTCHA pada halaman login (opsional)

Google reCAPTCHA v2 (kotak centang *"I'm not a robot"*) dapat dinyalakan atau
dimatikan lewat **Setting Sistem → Keamanan**, tanpa mengubah kode:

1. Buat kunci di `google.com/recaptcha/admin`, pilih tipe
   **reCAPTCHA v2 → "I'm not a robot" Checkbox**, daftarkan domain aplikasi.
2. Isi *site key* & *secret key*, lalu nyalakan tombolnya.

Ketentuannya:

- reCAPTCHA hanya aktif bila tombolnya menyala **dan** kedua kunci terisi, jadi
  salah konfigurasi tidak pernah mengunci semua orang di luar aplikasi.
- *Secret key* disimpan **terenkripsi** (`AppSetting::setSecret`) dan tidak
  pernah dikirim kembali ke peramban.
- Verifikasi dilakukan **di sisi server** sebelum kredensial diperiksa, dan
  *fail closed* — bila server Google tak terjangkau, login ditolak.
- Token bersifat sekali pakai; widget digambar ulang otomatis setiap kegagalan.

Bila terlanjur salah kunci dan tidak seorang pun bisa masuk, matikan dari baris
perintah:

```bash
php artisan recaptcha status    # lihat kondisi saat ini
php artisan recaptcha disable   # matikan reCAPTCHA
php artisan recaptcha enable    # nyalakan lagi (butuh kedua kunci terisi)
```

> Jangan mengubah tabel `app_settings` langsung lewat SQL: pengaturan dibaca
> dari cache, sehingga perubahan tidak akan terlihat sampai cache dibersihkan.
> Perintah di atas sudah membersihkan cache-nya. Bila terpaksa lewat SQL,
> jalankan `php artisan cache:clear` sesudahnya.

### Kata sandi

Setiap kata sandi baru — saat menambah pengguna maupun menggantinya pada
pengguna yang sudah ada — wajib memenuhi syarat berikut:

- minimal **10 karakter** (`PASSWORD_MIN_LENGTH`),
- mengandung **huruf besar** dan **huruf kecil**,
- mengandung **angka**,
- mengandung **karakter khusus** (`!@#$%` dan sejenisnya).

Syaratnya terpusat di [`App\Support\PasswordPolicy`](app/Support/PasswordPolicy.php)
dan diatur lewat `config/security.php`, sehingga aturan yang divalidasi selalu
sama dengan daftar syarat yang ditampilkan di layar. Panjang minimum tidak
pernah bisa turun di bawah 8 karakter, sekalipun konfigurasinya diisi lebih
kecil. Aktifkan `PASSWORD_CHECK_LEAKED=true` bila server punya akses internet —
kata sandi akan diperiksa ke basis data kebocoran publik (haveibeenpwned).

Pada formulir pengguna, syarat tampil sebagai daftar periksa yang tercentang
saat terpenuhi, dan tersedia tombol **tampilkan/sembunyikan** kata sandi — juga
pada halaman login dan halaman ganti kata sandi.

### Pemaksaan ganti kata sandi

Syarat di atas hanya dapat diperiksa ketika kata sandi masih berupa teks biasa,
yaitu **tepat pada saat login**. Karena itu akun lama tidak dibiarkan lolos:

1. Setiap login berhasil, kata sandi yang dipakai diuji terhadap kebijakan.
2. Bila tidak memenuhi syarat, akun ditandai `must_change_password` dan
   langsung diarahkan ke halaman **Ganti Password**.
3. Selama penanda itu menyala, middleware
   [`RequirePasswordChange`](app/Http/Middleware/RequirePasswordChange.php)
   mengembalikan setiap permintaan ke halaman tersebut — tidak ada satu menu pun
   yang dapat dibuka. Yang tetap bisa dilakukan hanyalah mengganti kata sandi
   atau keluar.
4. Penanda hilang begitu kata sandi baru yang memenuhi syarat disimpan.

Halaman ganti kata sandi meminta kata sandi saat ini, kata sandi baru, dan
konfirmasinya; kata sandi baru harus berbeda dari yang sekarang, dan ID sesi
diperbarui setelah penggantian.

Super Admin juga dapat menyalakan penanda ini secara manual lewat sakelar
**"Wajib ganti password saat login berikutnya"** pada formulir pengguna —
menyala secara bawaan ketika membuat pengguna baru, sehingga kata sandi awal
yang ditentukan admin hanya berlaku sekali.

### Perlindungan bawaan

| Lapisan | Penerapan |
|---|---|
| Header keamanan | `App\Http\Middleware\SecurityHeaders` memasang `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag`, dan HSTS (hanya pada HTTPS) |
| Content-Security-Policy | Daftar sumber di `config/security.php`; membatasi skrip/gaya/font hanya ke aset sendiri, Google Fonts, cdnjs, jsdelivr dan reCAPTCHA. Dapat dimatikan (`CSP_ENABLED=false`) atau dijadikan mode laporan (`CSP_REPORT_ONLY=true`) |
| XSS | Seluruh keluaran Blade memakai `{{ }}` yang otomatis di-escape; tidak ada `{!! !!}` di seluruh tampilan |
| Unggahan berkas | Hanya PNG/JPG/WEBP (logo) dan PNG/WEBP/ICO (favicon), divalidasi ekstensi **dan** MIME. **SVG ditolak** karena dapat memuat `<script>` dan menjadi stored XSS saat dibuka dari `/storage` |
| URL dari pengguna | `company_website` divalidasi `url:http,https`, menutup `javascript:` yang lolos validasi URL biasa |
| Kata sandi | Minimal 10 karakter dengan huruf besar, huruf kecil, angka, dan karakter khusus; di-hash bcrypt (12 putaran) |
| Kata sandi lemah | Terdeteksi saat login, akun dikunci pada halaman ganti kata sandi sampai diperbarui |
| Brute force | 5 percobaan gagal per kombinasi email + IP, jeda 60 detik (`RateLimiter`) |
| Open redirect | Tujuan setelah login ditolak bila host-nya bukan host aplikasi |
| Sesi | ID sesi diperbarui setiap login, cookie `HttpOnly`, `SameSite=lax`, serialisasi JSON; akun non-aktif langsung dikeluarkan |
| CSRF | Bawaan Laravel pada seluruh form dan permintaan Livewire |
| SQL injection | Seluruh akses data lewat Eloquent; tidak ada query mentah |

Sebelum ke produksi: setel `APP_DEBUG=false`, `APP_ENV=production`,
`SESSION_SECURE_COOKIE=true`, dan pertimbangkan `SESSION_ENCRYPT=true`.

---

## Hak Akses

Enam peran (`Super Admin`, `Admin FAT`, `Admin HRIS`, `Kepala Departemen`,
`Operator`, `Viewer`) didefinisikan di
[`database/seeders/RolesAndPermissionsSeeder.php`](database/seeders/RolesAndPermissionsSeeder.php).
Pengguna non-aktif otomatis dikeluarkan oleh middleware `active`.

Pemeriksaan izin berlapis tiga:

1. **Rute** — middleware `permission:` menentukan siapa boleh membuka halaman.
2. **Aksi** — middleware rute hanya menjaga akses halaman, sedangkan aksi
   Livewire dapat dipanggil siapa pun yang berhasil membuka halamannya. Karena
   itu setiap aksi yang menulis data memeriksa izinnya sendiri lewat trait
   [`AuthorizesWrites`](app/Livewire/Concerns/AuthorizesWrites.php) — mis.
   `manage settings` untuk identitas & keamanan, `manage apikey` untuk kunci
   API, `manage integration` untuk gerbang integrasi, dan `can_override` untuk
   membuka/mengunci periode.
3. **Tampilan** — tombol dan tab yang tidak berwenang disembunyikan dengan
   `@can`, sehingga pengguna tidak menemukan tombol yang selalu ditolak.

---

## Pengujian

```bash
php artisan test
```

Mencakup smoke test seluruh menu, middleware akun non-aktif, helper versi, dan
pengaturan identitas entitas.

---

## Dokumen Terkait

Seluruh dokumen blueprint dikumpulkan di folder [`docs/`](docs/README.md):

- [Buku Panduan Lengkap](docs/buku-panduan-lengkap.md) — arsitektur & 12 modul
- [Dokumentasi Integrasi HRIS & Finance](docs/dokumentasi-integrasi-hris-finance.md) — blueprint integrasi
- [Panduan Dokumentasi API & Postman](docs/panduan-dokumentasi-api-postman.md) — standar dokumentasi API
- [Penerapan Metodologi Excel](docs/penerapan-metodologi-excel.md) — peta workbook → aplikasi & tahapannya

> Catatan: ketiga dokumen di atas menjelaskan API Gateway 4-hop
> (`/api/bsc/sync/*`) yang **belum diimplementasikan** pada basis kode ini.
> Menu *Integrasi Sistem* dan *Staging Log* saat ini masih berupa simulasi lokal.
> Rincian selisih rancangan vs kode ada di [docs/README.md](docs/README.md).
