<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use App\Support\Bsc\AccountPosts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu pos akun milik satu entitas — katalog yang dapat diubah lewat menu
 * Pos Akun, bukan lagi daftar tetap di dalam kode.
 *
 * Pos bawaan (is_builtin) berasal dari workbook dan dipakai rumus rasio bawaan,
 * jadi kode dan jenisnya tidak boleh berubah; namanya, keterangannya, dan
 * aktif-tidaknya tetap boleh disesuaikan dengan keadaan entitas.
 */
class AccountPostDefinition extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'code', 'name', 'kind', 'source', 'hint', 'is_active', 'is_builtin', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_builtin' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Katalog disimpan sebentar di memori selama satu permintaan; begitu
     * barisnya berubah, salinan itu harus dilupakan agar layar tidak memakai
     * daftar pos yang sudah usang.
     */
    protected static function booted(): void
    {
        static::saved(fn () => AccountPosts::forget());
        static::deleted(fn () => AccountPosts::forget());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function kindLabel(): string
    {
        return AccountPosts::kindLabel($this->kind);
    }

    public function needsOpening(): bool
    {
        return $this->kind === AccountPosts::NERACA;
    }
}
