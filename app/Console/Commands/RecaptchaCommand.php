<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Support\Recaptcha;
use Illuminate\Console\Command;

/**
 * Jalan keluar darurat ketika reCAPTCHA salah dikonfigurasi dan tidak ada
 * seorang pun yang bisa masuk ke aplikasi untuk mematikannya lewat UI.
 *
 * Perintah ini sekaligus membersihkan cache pengaturan — mengubah tabel
 * app_settings langsung lewat SQL saja tidak cukup, karena nilainya di-cache.
 */
class RecaptchaCommand extends Command
{
    protected $signature = 'recaptcha
                            {action=status : status, disable, atau enable}';

    protected $description = 'Lihat, matikan, atau nyalakan reCAPTCHA pada halaman login';

    public function handle(Recaptcha $recaptcha): int
    {
        $action = strtolower((string) $this->argument('action'));

        // Selalu baca dari basis data, bukan dari cache yang mungkin basi.
        AppSetting::flushCache();

        return match ($action) {
            'status' => $this->tampilkanStatus($recaptcha),
            'disable' => $this->ubah(false, $recaptcha),
            'enable' => $this->ubah(true, $recaptcha),
            default => $this->aksiTidakDikenal($action),
        };
    }

    private function tampilkanStatus(Recaptcha $recaptcha): int
    {
        $this->table(['Pengaturan', 'Nilai'], [
            ['Tombol', $recaptcha->toggledOn() ? 'NYALA' : 'MATI'],
            ['Site key', $recaptcha->siteKey() ?? '(belum diisi)'],
            ['Secret key', $recaptcha->secretKey() !== null ? '(tersimpan, terenkripsi)' : '(belum diisi)'],
            ['Berlaku di halaman login', $recaptcha->enabled() ? 'YA' : 'TIDAK'],
        ]);

        if ($recaptcha->toggledOn() && ! $recaptcha->configured()) {
            $this->warn('Tombol menyala tetapi kunci belum lengkap — reCAPTCHA otomatis diabaikan.');
        }

        return self::SUCCESS;
    }

    private function ubah(bool $nyala, Recaptcha $recaptcha): int
    {
        if ($nyala && ! $recaptcha->configured()) {
            $this->error('Site key dan secret key harus terisi lebih dulu (lewat Setting Sistem → Keamanan).');

            return self::FAILURE;
        }

        AppSetting::setValue(Recaptcha::ENABLED_KEY, $nyala ? '1' : '0');
        AppSetting::flushCache();

        $this->info($nyala
            ? 'reCAPTCHA dinyalakan pada halaman login.'
            : 'reCAPTCHA dimatikan. Halaman login kembali dapat diakses tanpa verifikasi.');

        return self::SUCCESS;
    }

    private function aksiTidakDikenal(string $action): int
    {
        $this->error("Aksi '{$action}' tidak dikenal. Gunakan: status, disable, atau enable.");

        return self::FAILURE;
    }
}
