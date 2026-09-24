<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Sambungan Odoo milik entitas ini. Kunci APInya disimpan terenkripsi dan
 * hanya ditampilkan tersamar.
 */
class OdooConnection extends Model
{
    use BelongsToEntity;

    protected $fillable = [
        'entity_id', 'base_url', 'database_name', 'company_id', 'company_name', 'username', 'api_key',
        'is_active', 'fills_revenue', 'last_status', 'last_message', 'last_run_at', 'updated_by',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'fills_revenue' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /** Penanda kunci untuk di layar; tidak pernah utuh. */
    public function maskedKey(): ?string
    {
        try {
            $kunci = (string) $this->api_key;
        } catch (Throwable) {
            // Kunci aplikasi berganti: layar tetap harus tampil agar dapat diatur ulang.
            return 'tidak terbaca';
        }

        return $kunci === '' ? null : str_repeat('•', 8).substr($kunci, -4);
    }
}
