<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/** Target tahunan satu rasio milik satu entitas (kolom "Target FY" sheet L2). */
class RatioTarget extends Model
{
    use BelongsToEntity;

    protected $fillable = ['entity_id', 'year', 'code', 'target'];

    protected function casts(): array
    {
        return ['target' => 'float'];
    }
}
