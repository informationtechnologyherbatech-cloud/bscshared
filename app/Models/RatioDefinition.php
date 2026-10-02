<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use App\Support\Bsc\RatioLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rasio yang dipakai satu entitas beserta bobotnya. Nama, kelompok, rumus,
 * satuan, dan polaritas berasal dari RatioLibrary — tabel ini hanya menyimpan
 * pilihan entitas.
 */
class RatioDefinition extends Model
{
    use BelongsToEntity;

    protected $fillable = [
        'entity_id', 'code', 'weight', 'is_active', 'sort',
        'name', 'ratio_group', 'formula', 'expression', 'unit', 'polarity', 'is_builtin',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_active' => 'boolean',
            'is_builtin' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** Rumus yang dipakai mesin disimpan sebentar di memori; sekali berubah, lupakan. */
    protected static function booted(): void
    {
        static::saved(fn () => RatioLibrary::forget());
        static::deleted(fn () => RatioLibrary::forget());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    /**
     * Keterangan rasio ini: nama, kelompok, rumus, satuan, polaritas.
     *
     * Yang tersimpan di baris ini menang atas pustaka bawaan — di situlah
     * penyesuaian entitas tersimpan. Pustaka tetap menjadi cadangan untuk data
     * lama yang kolomnya masih kosong.
     */
    public function meta(): array
    {
        $bawaan = RatioLibrary::all()[$this->code] ?? [
            'name' => $this->code, 'group' => '—', 'formula' => '—',
            'expression' => null, 'unit' => 'x', 'polarity' => RatioLibrary::NAIK, 'weight' => 0,
        ];

        return [
            'name' => $this->name ?: $bawaan['name'],
            'group' => $this->ratio_group ?: $bawaan['group'],
            'formula' => $this->formula ?: $bawaan['formula'],
            'expression' => $this->expression ?: ($bawaan['expression'] ?? null),
            'unit' => $this->unit ?: $bawaan['unit'],
            'polarity' => $this->polarity ?: $bawaan['polarity'],
            'weight' => $bawaan['weight'] ?? 0,
        ];
    }

    /** Rumus yang benar-benar dihitung untuk rasio ini. */
    public function expressionOrDefault(): ?string
    {
        return $this->meta()['expression'];
    }
}
