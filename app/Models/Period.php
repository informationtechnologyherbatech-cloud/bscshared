<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'status',
        'apex_score',
        'target_revenue',
        'actual_revenue',
        'weight_revenue',
        'weight_ratios',
    ];

    protected $casts = [
        'apex_score' => 'float',
        'target_revenue' => 'float',
        'actual_revenue' => 'float',
        'weight_revenue' => 'float',
        'weight_ratios' => 'float',
    ];

    public function isClosed(): bool
    {
        return $this->status === 'CLOSED';
    }
}
