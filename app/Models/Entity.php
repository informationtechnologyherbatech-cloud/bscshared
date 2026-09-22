<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entitas usaha di bawah holding Erhanesia Mulia Corpora.
 *
 * Tiap entitas punya struktur unit kerja, rasio, dan target sendiri, tetapi
 * seluruhnya dinilai dengan mesin skor yang sama — sehingga holding melihat
 * piramida dengan bentuk yang seragam untuk keempatnya.
 */
class Entity extends Model
{
    public const MANUFAKTUR = 'manufaktur';

    public const DIGITAL_MARKETING = 'digital_marketing';

    protected $fillable = ['code', 'name', 'legal_name', 'industry', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function workUnits(): HasMany
    {
        return $this->hasMany(WorkUnit::class)->withoutGlobalScopes();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    public function industryLabel(): string
    {
        return match ($this->industry) {
            self::DIGITAL_MARKETING => 'Digital Marketing',
            default => 'Manufaktur',
        };
    }
}
