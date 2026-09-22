<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Target & realisasi revenue satu bulan milik satu entitas — Tingkat 1 (L1).
 *
 * Mengikuti sheet "L1 Target Revenue" bagian G: target tahunan difasing per
 * bulan, lalu pencapaian dihitung kumulatif (Jan s.d. bulan berjalan).
 */
class RevenueTarget extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'period', 'target', 'actual'];

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'actual' => 'decimal:2',
        ];
    }

    /**
     * Pencapaian revenue kumulatif sejak Januari hingga periode tersebut, dalam
     * persen dan dibatasi 100 (konvensi BSC: melebihi target tidak menambah
     * nilai). Null bila belum ada target sama sekali — belum ada data, bukan nol.
     */
    public static function cumulativeAchievement(string $period): ?float
    {
        $tahun = substr($period, 0, 4);

        $baris = static::query()
            ->where('period', '>=', $tahun.'-01')
            ->where('period', '<=', $period)
            ->get(['target', 'actual']);

        $target = (float) $baris->sum('target');

        if ($target <= 0) {
            return null;
        }

        $realisasi = (float) $baris->sum(fn ($b) => (float) ($b->actual ?? 0));

        return round(min(100.0, $realisasi / $target * 100), 2);
    }
}
