<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Kode pendaftaran sekali pakai: mengizinkan SATU entitas mendaftarkan dirinya
 * ke holding tanpa ada kunci yang diketik di holding.
 *
 * Seperti kunci API, yang tersimpan hanya sidik jarinya; kodenya sendiri
 * ditampilkan sekali saat dibuat dan hanya berlaku sebentar.
 */
class PairingCode extends Model
{
    /** Berapa lama kode berlaku (menit). */
    public const MASA_BERLAKU = 15;

    protected $fillable = ['entity_id', 'code_hash', 'label', 'expires_at', 'used_at', 'used_ip', 'used_url', 'created_by'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public static function fingerprint(string $code): string
    {
        return hash('sha256', strtoupper(trim($code)));
    }

    /**
     * Terbitkan kode untuk satu entitas. Kode utuh dikembalikan SEKALI.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Entity $entitas): array
    {
        // Bentuk PAIR-XXXX-XXXX: pendek, mudah dibacakan, tanpa huruf yang mirip angka.
        $abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bagian = fn () => collect(range(1, 4))->map(fn () => $abjad[random_int(0, strlen($abjad) - 1)])->implode('');
        $kode = 'PAIR-'.$bagian().'-'.$bagian();

        // Kode lama entitas ini dibatalkan: satu kode berlaku pada satu waktu.
        static::where('entity_id', $entitas->id)->whereNull('used_at')->delete();

        $baris = static::create([
            'entity_id' => $entitas->id,
            'code_hash' => static::fingerprint($kode),
            'label' => Str::limit($kode, 10, '…'),
            'expires_at' => now()->addMinutes(self::MASA_BERLAKU),
            'created_by' => auth()->id(),
        ]);

        return [$baris, $kode];
    }

    /** Kode yang masih dapat dipakai: belum terpakai dan belum lewat waktunya. */
    public static function usable(string $code): ?self
    {
        return static::where('code_hash', static::fingerprint($code))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
