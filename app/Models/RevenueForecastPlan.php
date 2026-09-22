<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;

/**
 * Bahan penyusunan target revenue setahun (sheet L1 bagian A–F) milik satu
 * entitas. Rumusnya ada di App\Support\Bsc\RevenueForecast.
 */
class RevenueForecastPlan extends Model
{
    use BelongsToEntity;

    protected $table = 'revenue_forecasts';

    protected $fillable = [
        'entity_id', 'year', 'history', 'base_ytd', 'base_months', 'channels', 'brands',
        'ansoff', 'swot', 'swot_adjustment', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'history' => 'array',
            'base_ytd' => 'float',
            'base_months' => 'integer',
            'channels' => 'array',
            'brands' => 'array',
            'ansoff' => 'array',
            'swot' => 'array',
            'swot_adjustment' => 'float',
        ];
    }
}
