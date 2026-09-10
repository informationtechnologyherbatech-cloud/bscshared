<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan aplikasi & identitas entitas (key-value).
 *
 * Nilai dibaca lewat cache agar satu halaman tidak menghasilkan
 * puluhan query hanya untuk membaca nama perusahaan / logo.
 */
class AppSetting extends Model
{
    /** Kunci cache untuk seluruh peta pengaturan. */
    public const CACHE_KEY = 'app_settings.map';

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    /**
     * Seluruh pengaturan sebagai array key => value.
     *
     * Aman dipanggil sebelum migrasi dijalankan: bila tabel belum ada
     * atau database tidak dapat dihubungi, mengembalikan array kosong
     * sehingga aplikasi jatuh ke default config/entity.php.
     */
    public static function map(): array
    {
        try {
            return Cache::rememberForever(
                static::CACHE_KEY,
                fn () => static::query()->pluck('value', 'key')->all()
            );
        } catch (QueryException) {
            return [];
        }
    }

    public static function getValue(string $key, $default = null)
    {
        return static::map()[$key] ?? $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Simpan banyak pengaturan sekaligus.
     *
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::setValue($key, $value);
        }
    }

    /**
     * Simpan nilai rahasia (mis. secret key) dalam bentuk terenkripsi.
     *
     * Nilai kosong menghapus rahasia, bukan menyimpan string kosong terenkripsi.
     */
    public static function setSecret(string $key, ?string $value): void
    {
        static::setValue($key, $value === null || $value === '' ? '' : Crypt::encryptString($value));
    }

    /**
     * Baca nilai rahasia. Mengembalikan null bila belum diisi atau bila
     * nilainya tidak dapat didekripsi (mis. APP_KEY berganti).
     */
    public static function getSecret(string $key): ?string
    {
        $value = static::getValue($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function flushCache(): void
    {
        try {
            Cache::forget(static::CACHE_KEY);
        } catch (QueryException) {
            // Penyimpanan cache belum siap (mis. sebelum migrasi) — abaikan.
        }
    }
}
