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

    protected $fillable = ['entity_id', 'code', 'weight', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    /** Metadata rumus dari pustaka: name, group, formula, unit, polarity. */
    public function meta(): array
    {
        return RatioLibrary::all()[$this->code] ?? [
            'name' => $this->code, 'group' => '—', 'formula' => '—', 'unit' => 'x', 'polarity' => RatioLibrary::NAIK, 'weight' => 0,
        ];
    }
}
