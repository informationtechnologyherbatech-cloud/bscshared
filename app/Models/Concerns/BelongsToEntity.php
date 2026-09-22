<?php

namespace App\Models\Concerns;

use App\Models\Entity;
use App\Support\EntityContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data milik satu entitas.
 *
 * Setiap kueri otomatis dibatasi pada entitas yang sedang aktif, dan setiap
 * baris baru otomatis ditandai dengan entitas itu. Dengan begitu komponen yang
 * sudah ada tidak perlu diubah satu per satu untuk menjadi multi-entitas, dan
 * tidak ada jalan untuk lupa menyaring.
 *
 * Tanpa pengguna yang login (konsol, antrean, seeder) pembatas tidak aktif.
 */
trait BelongsToEntity
{
    public static function bootBelongsToEntity(): void
    {
        static::addGlobalScope('entity', function (Builder $query) {
            $entityId = app(EntityContext::class)->id();

            if ($entityId !== null) {
                $query->where($query->getModel()->qualifyColumn('entity_id'), $entityId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->entity_id)) {
                $model->entity_id = app(EntityContext::class)->id();
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
