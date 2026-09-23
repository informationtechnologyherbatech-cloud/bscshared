<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kunci API entitas untuk dibaca holding.
 *
 * Yang tersimpan hanya SIDIK JARI (sha256) kunci dan 16 huruf awalnya sebagai
 * penanda — cukup untuk memeriksa, tidak cukup untuk memakai. Kunci utuh hanya
 * ada di tangan pembuatnya (sekali tampil) dan di holding (terenkripsi).
 */
class ApiKey extends Model
{
    protected $fillable = [
        'name', 'entity_code', 'key_hash', 'prefix', 'is_active',
        'allowed_ips', 'expires_at', 'created_by', 'last_used_at',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiAccessLog::class);
    }

    /** Sidik jari kunci; satu-satunya bentuk yang disimpan entitas. */
    public static function fingerprint(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Terbitkan kunci baru. Nilai utuhnya dikembalikan SEKALI — setelah ini
     * tidak ada lagi cara membacanya dari aplikasi maupun database.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name, ?string $entityCode = null, ?string $allowedIps = null, ?string $expiresAt = null): array
    {
        $plain = 'bsc_live_'.Str::random(40);

        $kunci = static::create([
            'name' => $name,
            'entity_code' => $entityCode ? strtoupper($entityCode) : null,
            'key_hash' => static::fingerprint($plain),
            'prefix' => substr($plain, 0, 16),
            'is_active' => true,
            'allowed_ips' => $allowedIps ?: null,
            'expires_at' => $expiresAt ?: null,
            'created_by' => auth()->id(),
        ]);

        return [$kunci, $plain];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * IP pemanggil diizinkan? Daftar kosong = dari mana saja (mis. sebelum alamat
     * holding pasti). Mendukung alamat persis dan awalan CIDR (10.8.0.0/16 atau
     * 2001:db8::/32), IPv4 maupun IPv6.
     *
     * Pola yang salah tulis hanya TIDAK COCOK; tidak boleh sampai menggagalkan
     * permintaan, karena daftar ini diisi manusia dari layar Setting.
     */
    public function allowsIp(?string $ip): bool
    {
        $daftar = array_filter(array_map('trim', explode(',', (string) $this->allowed_ips)));

        if ($daftar === [] || $ip === null) {
            return $daftar === [];
        }

        $alamat = @inet_pton($ip);

        foreach ($daftar as $pola) {
            if ($pola === $ip) {
                return true;
            }

            if ($alamat === false) {
                continue;
            }

            if (! str_contains($pola, '/')) {
                // Alamat persis; dibandingkan dalam bentuk biner supaya ::1 dan
                // 0:0:0:0:0:0:0:1 dianggap sama.
                if (@inet_pton($pola) === $alamat) {
                    return true;
                }

                continue;
            }

            if ($this->dalamCidr($alamat, $pola)) {
                return true;
            }
        }

        return false;
    }

    /** @param  string  $alamat  alamat pemanggil dalam bentuk biner (inet_pton) */
    private function dalamCidr(string $alamat, string $cidr): bool
    {
        [$jaringan, $bit] = explode('/', $cidr, 2);
        $dasar = @inet_pton(trim($jaringan));
        $bit = trim($bit);

        // Keluarga alamat harus sama: 4 bita untuk IPv4, 16 bita untuk IPv6.
        if ($dasar === false || ! ctype_digit($bit) || strlen($dasar) !== strlen($alamat)) {
            return false;
        }

        $panjang = (int) $bit;

        if ($panjang > strlen($dasar) * 8) {
            return false;
        }

        $bitaPenuh = intdiv($panjang, 8);
        $sisaBit = $panjang % 8;

        if ($bitaPenuh > 0 && substr($alamat, 0, $bitaPenuh) !== substr($dasar, 0, $bitaPenuh)) {
            return false;
        }

        if ($sisaBit === 0) {
            return true;
        }

        $topeng = chr((0xFF << (8 - $sisaBit)) & 0xFF);

        return ($alamat[$bitaPenuh] & $topeng) === ($dasar[$bitaPenuh] & $topeng);
    }

    /** Penanda kunci untuk di layar: awalannya saja, tidak pernah utuh. */
    public function label(): string
    {
        return ($this->prefix ?: 'bsc_live_').'…';
    }
}
