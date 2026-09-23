<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jejak akses API entitas. Dicatat baik saat diterima maupun ditolak,
 * sehingga percobaan memakai kunci yang salah ikut terlihat. Tidak pernah memuat
 * kunci — hanya awalannya.
 */
class ApiAccessLog extends Model
{
    public const UPDATED_AT = null;

    public const DITERIMA = 'diterima';

    public const KUNCI_SALAH = 'kunci salah';

    public const KADALUWARSA = 'kadaluwarsa';

    public const IP_DITOLAK = 'ip ditolak';

    public const ENTITAS_LAIN = 'entitas lain';

    public const TANPA_KUNCI = 'tanpa kunci';

    protected $fillable = ['api_key_id', 'prefix', 'ip', 'path', 'status', 'result', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function diterima(): bool
    {
        return $this->result === self::DITERIMA;
    }
}
