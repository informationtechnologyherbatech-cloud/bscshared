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
     * holding pasti). Mendukung alamat persis dan awalan CIDR sederhana (10.8.0.0/16).
     */
    public function allowsIp(?string $ip): bool
    {
        $daftar = array_filter(array_map('trim', explode(',', (string) $this->allowed_ips)));

        if ($daftar === [] || $ip === null) {
            return $daftar === [];
        }

        foreach ($daftar as $pola) {
            if ($pola === $ip) {
                return true;
            }

            if (str_contains($pola, '/') && $this->dalamCidr($ip, $pola)) {
                return true;
            }
        }

        return false;
    }

    private function dalamCidr(string $ip, string $cidr): bool
    {
        [$jaringan, $bit] = explode('/', $cidr, 2);
        $alamat = ip2long($ip);
        $dasar = ip2long($jaringan);

        if ($alamat === false || $dasar === false || ! is_numeric($bit)) {
            return false;
        }

        $topeng = -1 << (32 - (int) $bit);

        return ($alamat & $topeng) === ($dasar & $topeng);
    }

    /** Penanda kunci untuk di layar: awalannya saja, tidak pernah utuh. */
    public function label(): string
    {
        return ($this->prefix ?: 'bsc_live_').'…';
    }
}
