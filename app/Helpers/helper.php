<?php

use App\Models\AppSetting;
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

if (! function_exists('entity_logo')) {
    /** URL logo entitas, atau null bila belum diatur. */
    function entity_logo(): ?string
    {
        return entity_branding_url('app_logo');
    }
}

if (! function_exists('entity_favicon')) {
    /** URL favicon entitas, dengan fallback ke favicon bawaan. */
    function entity_favicon(): string
    {
        return entity_branding_url('app_favicon') ?? asset('favicon.ico');
    }
}

if (! function_exists('recaptcha')) {
    /** Layanan Google reCAPTCHA (lihat App\Support\Recaptcha). */
    function recaptcha(): \App\Support\Recaptcha
    {
        return app(\App\Support\Recaptcha::class);
    }
}

if (! function_exists('entity_copyright')) {
    /** Baris hak cipta, mis. "PT Herbatech Innopharma Industry © 2026". */
    function entity_copyright(): string
    {
        return company_name().' © '.entity('app_year', (string) date('Y'));
    }
}
