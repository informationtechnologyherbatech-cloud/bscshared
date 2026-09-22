<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pos akun (PA01–PA16) milik satu entitas pada satu periode.
 *
 * Aliran & HRIS: amount = nilai YTD / rata-rata periode.
 * Neraca: amount = saldo akhir periode, opening = saldo awal tahun.
 */
class AccountBalance extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'period', 'code', 'amount', 'opening'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'opening' => 'float',
        ];
    }
}
