<?php

use App\Models\AppSetting;
use App\Support\Recaptcha;
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
        $kode = app(\App\Support\EntityContext::class)->entity()?->code;

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
