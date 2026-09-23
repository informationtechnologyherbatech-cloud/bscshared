<?php

use App\Models\AccountPostRole;
use App\Models\AppSetting;
use App\Models\Entity;
use App\Models\KpiCascade;
use App\Models\Period;
use App\Models\RevenueTarget;
use App\Support\Bsc\AccountPosts;
use App\Support\Bsc\RatioEngine;
use App\Support\Bsc\RatioLibrary;
use App\Support\Bsc\Sources\EntitySourceFactory;
use App\Support\Bsc\Sources\EntitySourceSettings;
use App\Support\EntityContext;
use App\Support\PasswordPolicy;
use App\Support\Recaptcha;
use App\Support\ScoreStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

if (! function_exists('app_version')) {
    /**
     * Versi aplikasi Super Apps BSC.
     *
     * Sumber nilai: config/app.php -> 'version' (env APP_VERSION),
     * sehingga versi dapat dinaikkan tanpa mengubah kode.
     *
     * @param  bool  $prefixed  Sertakan awalan "v" (default true).
     */
    function app_version(bool $prefixed = true): string
    {
        $version = trim((string) config('app.version', '1.0.0'));

        if ($version === '') {
            $version = '1.0.0';
        }

        $version = ltrim($version, 'vV');

        return $prefixed ? 'v'.$version : $version;
    }
}

if (! function_exists('entity')) {
    /**
     * Ambil satu nilai pengaturan entitas/aplikasi.
     *
     * Contoh: entity('company_name'), entity('company_email').
     * Nilai diambil dari tabel app_settings; bila kosong, jatuh ke
     * default pada config/entity.php.
     */
    function entity(string $key, mixed $default = null): mixed
    {
        $value = AppSetting::getValue($key);

        if ($value === null || $value === '') {
            return $default ?? config('entity.defaults.'.$key);
        }

        return $value;
    }
}

if (! function_exists('entity_name')) {
    /** Nama entitas pengguna aplikasi (mis. "Herbatech Innopharma"). */
    function entity_name(): string
    {
        return (string) entity('entity_name');
    }
}

if (! function_exists('company_name')) {
    /** Nama resmi perusahaan (mis. "PT Herbatech Innopharma Industry"). */
    function company_name(): string
    {
        return (string) entity('company_name');
    }
}

if (! function_exists('app_display_name')) {
    /** Nama aplikasi yang ditampilkan pada judul halaman & brand. */
    function app_display_name(): string
    {
        return (string) entity('app_name');
    }
}

if (! function_exists('entity_branding_url')) {
    /**
     * URL berkas branding (logo/favicon) yang tersimpan di disk "public".
     * Mengembalikan null bila belum diunggah atau berkasnya hilang.
     *
     * URL dibentuk dengan asset() — bukan Storage::url() — supaya mengikuti
     * host & port permintaan yang sedang berjalan. Storage::url() memakai
     * APP_URL, sehingga logo gagal dimuat ketika aplikasi diakses lewat
     * host/port lain (mis. APP_URL=http://localhost tetapi dibuka di
     * http://localhost:8000).
     */
    function entity_branding_url(string $key): ?string
    {
        $path = AppSetting::getValue($key);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('public_image_url')) {
    /**
     * URL berkas di folder public/ bila berkasnya ada. Tiap segmen path di-encode
     * karena nama berkas logo memakai spasi (mis. "logo emc - text.webp").
     */
    function public_image_url(?string $path): ?string
    {
        if (! $path || ! is_file(public_path($path))) {
            return null;
        }

        return asset(implode('/', array_map('rawurlencode', explode('/', $path))));
    }
}

if (! function_exists('entity_profile')) {
    /**
     * Profil branding (config/entity.php) untuk entitas yang sedang aktif.
     * Tanpa entitas aktif (mis. halaman login): entitas instalasi, atau profil
     * holding pada instalasi holding.
     *
     * @return array<string, string>|null
     */
    function entity_profile(): ?array
    {
        $kode = app(EntityContext::class)->entity()?->code;

        if ($kode === null) {
            if (config('bsc.holding_mode')) {
                return config('entity.holding');
            }
            $kode = config('bsc.default_entity');
        }

        return config('entity.profiles.'.$kode);
    }
}

if (! function_exists('entity_logo')) {
    /**
     * URL logo untuk halaman admin: logo entitas aktif (public/images), lalu
     * logo yang diunggah di Setting Sistem, atau null (ikon bawaan).
     */
    function entity_logo(): ?string
    {
        return public_image_url(entity_profile()['logo'] ?? null) ?? entity_branding_url('app_logo');
    }
}

if (! function_exists('entity_favicon')) {
    /** URL favicon: milik entitas aktif, lalu unggahan Setting, lalu favicon bawaan. */
    function entity_favicon(): string
    {
        return public_image_url(entity_profile()['favicon'] ?? null)
            ?? entity_branding_url('app_favicon')
            ?? asset('favicon.ico');
    }
}

if (! function_exists('group_logo')) {
    /** Logo holding Erhanesia Mulia Corpora dengan teks — halaman login. */
    function group_logo(): ?string
    {
        return public_image_url(config('entity.holding.logo_text')) ?? public_image_url(config('entity.holding.logo'));
    }
}

if (! function_exists('group_entity_logos')) {
    /**
     * Logo keempat entitas grup yang berkasnya tersedia, urut seperti profil.
     *
     * @return array<int, array{code: string, name: string, legal_name: string, url: string}>
     */
    function group_entity_logos(): array
    {
        $hasil = [];

        foreach (config('entity.profiles', []) as $kode => $p) {
            if ($url = public_image_url($p['logo'] ?? null)) {
                $hasil[] = ['code' => $kode, 'name' => $p['name'], 'legal_name' => $p['legal_name'], 'url' => $url];
            }
        }

        return $hasil;
    }
}

if (! function_exists('rupiah')) {
    /**
     * Format rupiah Indonesia: rupiah(1000000000) → "Rp 1.000.000.000".
     * Null/'' → "—". Negatif ditulis "-Rp 1.000".
     */
    function rupiah(mixed $nilai, int $desimal = 0): string
    {
        if ($nilai === null || $nilai === '' || ! is_numeric($nilai)) {
            return '—';
        }

        $n = (float) $nilai;

        return ($n < 0 ? '-' : '').'Rp '.number_format(abs($n), $desimal, ',', '.');
    }
}

if (! function_exists('recaptcha')) {
    /** Layanan Google reCAPTCHA (lihat App\Support\Recaptcha). */
    function recaptcha(): Recaptcha
    {
        return app(Recaptcha::class);
    }
}

if (! function_exists('entity_copyright')) {
    /** Baris hak cipta, mis. "PT Herbatech Innopharma Industry © 2026". */
    function entity_copyright(): string
    {
        return company_name().' © '.entity('app_year', (string) date('Y'));
    }
}

/*
|--------------------------------------------------------------------------
| Fungsi bantu untuk Blade
|--------------------------------------------------------------------------
|
| View tidak perlu menyebut nama kelas lengkap (mis. \App\Support\Bsc\RatioLibrary).
| Fungsi di sini HANYA MENERUSKAN ke kelas aslinya — jangan menyalin logikanya,
| agar tetap satu sumber kebenaran bila kelas berpindah atau berganti nama.
|
*/

// ───────────────────────────────── Entitas & periode ─────────────────────────────────

if (! function_exists('entity_context')) {
    /** Konteks entitas aktif (dipakai fungsi lain di berkas ini). */
    function entity_context(): EntityContext
    {
        return app(EntityContext::class);
    }
}

if (! function_exists('active_entity')) {
    /** Entitas yang sedang ditampilkan; null bila belum ada. */
    function active_entity(): ?Entity
    {
        return entity_context()->entity();
    }
}

if (! function_exists('can_switch_entity')) {
    /** Pengguna ini boleh berpindah entitas (hanya instalasi holding). */
    function can_switch_entity(): bool
    {
        $user = auth()->user();

        return $user !== null && entity_context()->canSwitch($user);
    }
}

if (! function_exists('switchable_entities')) {
    /**
     * Entitas yang boleh dibuka pengguna ini.
     *
     * @return Collection<int, Entity>
     */
    function switchable_entities(): Collection
    {
        $user = auth()->user();

        return $user ? entity_context()->accessibleFor($user) : collect();
    }
}

if (! function_exists('entity_logo_of')) {
    /** Logo entitas tertentu (berdasarkan kode) dari config/entity.php. */
    function entity_logo_of(?string $code): ?string
    {
        return $code ? public_image_url(config('entity.profiles.'.$code.'.logo')) : null;
    }
}

if (! function_exists('holding_mode')) {
    /** Pemasangan ini adalah holding (punya pengalih entitas & menu Konsolidasi). */
    function holding_mode(): bool
    {
        return entity_context()->isHoldingMode();
    }
}

if (! function_exists('entity_source_label')) {
    /** Asal data entitas aktif: database aplikasi ini · database lain · API entitas. */
    function entity_source_label(): string
    {
        $entitas = active_entity();

        return $entitas ? app(EntitySourceFactory::class)->describe($entitas) : 'database aplikasi ini';
    }
}

if (! function_exists('entity_source_is_remote')) {
    /** Benar bila data entitas aktif berada di luar database aplikasi ini. */
    function entity_source_is_remote(): bool
    {
        $entitas = active_entity();

        if (! $entitas) {
            return false;
        }

        return app(EntitySourceSettings::class)->for($entitas)['origin'] !== 'tidak diatur'
            && app(EntitySourceFactory::class)->describe($entitas) !== 'database aplikasi ini';
    }
}

if (! function_exists('periods_with_status')) {
    /**
     * Periode entitas aktif beserta statusnya, terbaru lebih dulu.
     *
     * @return Collection<int, Period>
     */
    function periods_with_status(): Collection
    {
        return Period::orderByDesc('period')->get(['period', 'status']);
    }
}

if (! function_exists('active_period')) {
    /** Periode aktif (YYYY-MM) yang berlaku di semua halaman. */
    function active_period(): string
    {
        return Period::active();
    }
}

if (! function_exists('period_closed')) {
    /** Periode berstatus CLOSED (datanya tidak dapat diubah). */
    function period_closed(?string $status): bool
    {
        return $status === 'CLOSED';
    }
}

if (! function_exists('month_name')) {
    /** Nama bulan Indonesia dari periode YYYY-MM (atau nomor bulan). */
    function month_name(string $period): string
    {
        $bulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];
        $kode = strlen($period) > 2 ? substr($period, 5, 2) : str_pad($period, 2, '0', STR_PAD_LEFT);

        return $bulan[$kode] ?? $period;
    }
}

if (! function_exists('period_label')) {
    /** Periode siap tampil, mis. "September 2026". */
    function period_label(string $period): string
    {
        $bulan = month_name($period);

        return $bulan === $period ? $period : $bulan.' '.substr($period, 0, 4);
    }
}

if (! function_exists('month_abbr')) {
    /** Singkatan bulan, mis. "Agu" — untuk daftar periode. */
    function month_abbr(string $period): string
    {
        return mb_substr(month_name($period), 0, 3);
    }
}

if (! function_exists('month_short')) {
    /** Singkatan bulan huruf besar untuk ikon kalender, mis. "SEP". */
    function month_short(string $period): string
    {
        return mb_strtoupper(mb_substr(month_name($period), 0, 3));
    }
}

// ─────────────────────────────── Status & warna capaian ───────────────────────────────

if (! function_exists('score_status')) {
    /** Status capaian (Tercapai · Waspada · Di Bawah Target · Belum Lengkap) dari sebuah skor. */
    function score_status(?float $score, bool $adaData = true): string
    {
        return ScoreStatus::for($score, $adaData);
    }
}

if (! function_exists('score_color')) {
    /** Warna status capaian. */
    function score_color(string $status): string
    {
        return ScoreStatus::color($status);
    }
}

if (! function_exists('score_label')) {
    /** Label status capaian. */
    function score_label(string $status): string
    {
        return ScoreStatus::label($status);
    }
}

if (! function_exists('status_tanpa_target')) {
    /** Status rasio yang nilainya sudah ada tetapi targetnya belum diisi. */
    function status_tanpa_target(): string
    {
        return RatioEngine::TANPA_TARGET;
    }
}

// ─────────────────────────────────── Rasio & pos akun ───────────────────────────────────

if (! function_exists('ratio_format')) {
    /** Nilai rasio sesuai satuannya (%, kali, hari); null → "—". */
    function ratio_format(?float $value, string $unit): string
    {
        return RatioLibrary::format($value, $unit);
    }
}

if (! function_exists('ratio_posts')) {
    /**
     * Pos akun yang membentuk sebuah rasio.
     *
     * @return array<int, string>
     */
    function ratio_posts(string $ratioCode): array
    {
        return RatioLibrary::postsOf($ratioCode);
    }
}

if (! function_exists('post_kind_label')) {
    /** Label jenis pos akun (Aliran · Neraca · HRIS). */
    function post_kind_label(string $kind): string
    {
        return AccountPosts::kindLabel($kind);
    }
}

if (! function_exists('post_is_neraca')) {
    /** Pos akun neraca — butuh saldo awal tahun & saldo akhir. */
    function post_is_neraca(string $kind): bool
    {
        return $kind === AccountPosts::NERACA;
    }
}

if (! function_exists('post_is_aliran')) {
    /** Pos akun aliran — nilai YTD Januari s.d. bulan periode. */
    function post_is_aliran(string $kind): bool
    {
        return $kind === AccountPosts::ALIRAN;
    }
}

if (! function_exists('post_is_hris')) {
    /** Pos akun dari HRIS (jumlah karyawan / jam kerja). */
    function post_is_hris(string $kind): bool
    {
        return in_array($kind, [AccountPosts::HRIS_RATA, AccountPosts::HRIS_ALIRAN], true);
    }
}

if (! function_exists('post_is_hris_rata')) {
    /** Pos akun HRIS yang berupa rata-rata, bukan akumulasi. */
    function post_is_hris_rata(string $kind): bool
    {
        return $kind === AccountPosts::HRIS_RATA;
    }
}

if (! function_exists('post_kind')) {
    /** Nilai jenis pos akun (aliran · neraca · hris_rata · hris_aliran) dari namanya. */
    function post_kind(string $nama): string
    {
        return match ($nama) {
            'aliran' => AccountPosts::ALIRAN,
            'neraca' => AccountPosts::NERACA,
            'hris_rata' => AccountPosts::HRIS_RATA,
            'hris_aliran' => AccountPosts::HRIS_ALIRAN,
            default => throw new InvalidArgumentException('Jenis pos akun tidak dikenal: '.$nama),
        };
    }
}

if (! function_exists('post_role_label')) {
    /** Label peran unit atas pos akun: Pemilik (O) / Kontributor (K). */
    function post_role_label(?string $role): string
    {
        return AccountPostRole::label($role);
    }
}

// ───────────────────────────────────── KPI cascade ─────────────────────────────────────

if (! function_exists('kpi_levels')) {
    /**
     * Jenjang KPI cascade (Head · Supervisor · Staff).
     *
     * @return array<int, string>
     */
    function kpi_levels(): array
    {
        return KpiCascade::LEVELS;
    }
}

if (! function_exists('kpi_statuses')) {
    /**
     * Status validasi keuangan untuk KPI cascade.
     *
     * @return array<int, string>
     */
    function kpi_statuses(): array
    {
        return KpiCascade::STATUSES;
    }
}

// ──────────────────────────────────── Revenue (F1) ────────────────────────────────────

if (! function_exists('f1_belum_difasing')) {
    /** Alasan F1 kosong: target setahun sudah ada, target bulanan belum diisi. */
    function f1_belum_difasing(): string
    {
        return RevenueTarget::BELUM_DIFASING;
    }
}

if (! function_exists('f1_tanpa_realisasi')) {
    /** Alasan F1 kosong: belum ada realisasi sama sekali. */
    function f1_tanpa_realisasi(): string
    {
        return RevenueTarget::TANPA_REALISASI;
    }
}

// ───────────────────────────────────────── Lainnya ─────────────────────────────────────

if (! function_exists('password_hint')) {
    /** Keterangan singkat aturan kata sandi. */
    function password_hint(): string
    {
        return PasswordPolicy::hint();
    }
}
