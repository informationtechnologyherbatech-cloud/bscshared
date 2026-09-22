<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Target revenue setahun yang disahkan direksi, dan revisinya bila target
 * diubah di tengah tahun (sheet Asumsi B7–B9).
 */
class RevenuePlan extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'year', 'approved_target', 'revised_target'];

    protected function casts(): array
    {
        return [
            'approved_target' => 'float',
            'revised_target' => 'float',
        ];
    }

    /** Faktor revisi = revisi ÷ disahkan; 1 bila belum ada revisi. */
    public function factor(): float
    {
        if ($this->revised_target === null || $this->approved_target <= 0) {
            return 1.0;
        }

        return $this->revised_target / $this->approved_target;
    }

    public static function factorFor(string $year): float
    {
        return static::where('year', $year)->first()?->factor() ?? 1.0;
    }
}
