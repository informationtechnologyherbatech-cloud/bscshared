<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Di mana data sebuah entitas berada, menurut pengaturan di layar holding.
 *
 * Sengaja TIDAK memakai trait BelongsToEntity: baris ini milik holding (ia
 * menunjuk ke entitas), bukan data milik entitas yang bersangkutan.
 */
class EntityDataSource extends Model
{
    public const LOKAL = 'lokal';

    public const DATABASE = 'database';

    public const API = 'api';

    protected $table = 'entity_sources';

    protected $fillable = [
        'entity_id', 'driver', 'api_url', 'api_key', 'database_name',
        'last_status', 'last_message', 'last_checked_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // Kunci API entitas tersimpan terenkripsi, bukan teks polos.
            'api_key' => 'encrypted',
            'last_checked_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** Pengaturan ini benar-benar menunjuk ke luar database aplikasi ini. */
    public function isRemote(): bool
    {
        return ($this->driver === self::API && $this->api_url)
            || ($this->driver === self::DATABASE && $this->database_name);
    }

    /** Kunci API untuk ditampilkan: hanya ujungnya, isinya tidak pernah tampil utuh. */
    public function maskedKey(): ?string
    {
        $kunci = (string) $this->api_key;

        return $kunci === '' ? null : str_repeat('•', 8).substr($kunci, -4);
    }
}
