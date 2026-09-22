<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penjualan satu entitas grup ke entitas grup lain dalam satu bulan (mis.
 * Herbatech memasok produk ke Erdigma). Di tingkat holding angka ini
 * dieliminasi agar revenue grup tidak terhitung dua kali.
 *
 * Sengaja tidak memakai pembatas entitas: datanya milik holding dan
 * menyangkut dua entitas sekaligus.
 */
class IntercompanySale extends Model
{
    protected $fillable = ['period', 'seller_entity_id', 'buyer_entity_id', 'planned_amount', 'actual_amount', 'notes'];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'float',
            'actual_amount' => 'float',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'seller_entity_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'buyer_entity_id');
    }
}
