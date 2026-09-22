<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit kerja (departemen) milik satu entitas.
 *
 * Kodenya menjadi kunci penghubung ke sasaran mutu, program kerja, dan log
 * staging (kolom dept_code / owner_dept). Karena itu kode tidak boleh diubah
 * selama masih dipakai data lain — cukup ganti namanya.
 */
class WorkUnit extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'code', 'name', 'stream', 'reports_to', 'scope', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort')->orderBy('code');
    }

    /** Berapa baris data lain yang merujuk kode unit ini. */
    public function usageCount(): int
    {
        return DepartmentObjective::where('dept_code', $this->code)->count()
            + ActionPlan::where('owner_dept', $this->code)->count();
    }

    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }

    /** "SCM — Supply Chain" untuk daftar pilihan. */
    public function label(): string
    {
        return $this->code.' — '.$this->name;
    }
}
