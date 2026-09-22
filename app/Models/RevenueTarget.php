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
        return static::cumulative($period)['score'];
    }

    /** Alasan F1 belum dapat dihitung. */
    public const TANPA_TARGET = 'no_target';

    public const BELUM_DIFASING = 'not_phased';

    public const TANPA_REALISASI = 'no_actual';

    /**
     * Rincian F1 (sheet L1 bagian G): Σ realisasi ÷ Σ target Jan s.d. periode,
     * maks 100. Skor null — dengan alasannya — bila:
     *   - belum ada target bulanan (target setahun mungkin sudah disahkan tetapi
     *     belum difasing ke bulanan), atau
     *   - belum ada realisasi sama sekali (bukan 0%: belum ada datanya).
     *
     * Bulan bertarget yang realisasinya belum diisi tetap dihitung (sebagai 0, sesuai
     * rumus workbook) dan dicantumkan di months_missing agar tampilan bisa memperingatkan.
     *
     * @return array{score: float|null, reason: string|null, target_ytd: float, actual_ytd: float, months_actual: int, months_missing: array<int, string>, annual: float|null}
     */
    public static function cumulative(string $period): array
    {
        $tahun = substr($period, 0, 4);

        $baris = static::query()
            ->where('period', '>=', $tahun.'-01')
            ->where('period', '<=', $period)
            ->orderBy('period')
            ->get(['period', 'target', 'actual']);

        $target = (float) $baris->sum('target');
        $denganRealisasi = $baris->filter(fn ($b) => $b->actual !== null);
        $realisasi = (float) $denganRealisasi->sum(fn ($b) => (float) $b->actual);
        $setahun = RevenuePlan::where('year', $tahun)->first();

        $alasan = match (true) {
            $target <= 0 && $setahun !== null => self::BELUM_DIFASING,
            $target <= 0 => self::TANPA_TARGET,
            $denganRealisasi->isEmpty() => self::TANPA_REALISASI,
            default => null,
        };

        return [
            'score' => $alasan === null ? round(min(100.0, $realisasi / $target * 100), 2) : null,
            'reason' => $alasan,
            'target_ytd' => $target,
            'actual_ytd' => $realisasi,
            'months_actual' => $denganRealisasi->count(),
            'months_missing' => $alasan === null
                ? $baris->filter(fn ($b) => $b->actual === null && (float) $b->target > 0)->pluck('period')->values()->all()
                : [],
            'annual' => $setahun ? ($setahun->revised_target ?? $setahun->approved_target) : null,
        ];
    }

    /** Keterangan singkat alasan F1 kosong, untuk piramida. */
    public static function reasonLabel(?string $alasan): string
    {
        return match ($alasan) {
            self::BELUM_DIFASING => 'target bulanan belum difasing',
            self::TANPA_REALISASI => 'belum ada realisasi',
            default => 'belum ada target',
        };
    }
}
