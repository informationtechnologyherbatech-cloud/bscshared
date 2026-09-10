<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v2 (kotak centang "I'm not a robot").
 *
 * Tombol nyala/mati dan kedua kunci disimpan pada tabel app_settings; secret
 * key disimpan terenkripsi (lihat AppSetting::setSecret). reCAPTCHA hanya
 * dianggap aktif bila tombolnya dinyalakan DAN kedua kunci terisi, sehingga
 * salah konfigurasi tidak pernah mengunci pengguna keluar dari aplikasi.
 */
class Recaptcha
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public const ENABLED_KEY = 'recaptcha_enabled';

    public const SITE_KEY = 'recaptcha_site_key';

    public const SECRET_KEY = 'recaptcha_secret_key';

    public function enabled(): bool
    {
        return $this->toggledOn() && $this->configured();
    }

    /** Tombol pada menu Setting Sistem, terlepas dari lengkap tidaknya kunci. */
    public function toggledOn(): bool
    {
        return (string) AppSetting::getValue(self::ENABLED_KEY, '0') === '1';
    }

    /** Kedua kunci sudah terisi. */
    public function configured(): bool
    {
        return $this->siteKey() !== null && $this->secretKey() !== null;
    }

    public function siteKey(): ?string
    {
        $key = AppSetting::getValue(self::SITE_KEY);

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function secretKey(): ?string
    {
        return AppSetting::getSecret(self::SECRET_KEY);
    }

    /**
     * Verifikasi token dari peramban ke server Google.
     *
     * Mengembalikan false bila token kosong, permintaan gagal, atau Google
     * menolak token. Kegagalan jaringan sengaja diperlakukan sebagai GAGAL
     * (fail closed) agar perlindungan tidak bisa dilewati dengan memutus akses
     * ke Google.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (\Throwable $e) {
            Log::warning('Verifikasi reCAPTCHA gagal dihubungi.', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('Verifikasi reCAPTCHA menolak permintaan.', ['status' => $response->status()]);

            return false;
        }

        return (bool) $response->json('success', false);
    }
}
