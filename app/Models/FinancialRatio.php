<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialRatio extends Model
{
    use BelongsToEntity, HasFactory;

    protected $fillable = [
        'entity_id',
        'period',
        'category',
        'ratio_name',
        'target',
        'actual',
        'achievement_pct',
        'status',
    ];
}
