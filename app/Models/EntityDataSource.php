<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

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
        'db_host', 'db_port', 'db_username', 'db_password',
        'last_status', 'last_message', 'last_checked_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // Kunci API & kata sandi database entitas tersimpan terenkripsi.
            'api_key' => 'encrypted',
            'db_password' => 'encrypted',
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
        try {
            $kunci = (string) $this->api_key;
        } catch (Throwable) {
            // Kunci aplikasi berganti: nilainya tidak dapat dibuka lagi. Layar
            // tetap harus tampil supaya sumbernya bisa diatur ulang dari sini.
            return 'tidak terbaca';
        }

        return $kunci === '' ? null : str_repeat('•', 8).substr($kunci, -4);
    }
}
