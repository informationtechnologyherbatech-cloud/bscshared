<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hasil Uji A & Uji B (sheet L4) untuk satu KPI cascade — bukti validasi Keuangan. */
class KpiTest extends Model
{
    use BelongsToEntity;

    protected $fillable = [
        'entity_id', 'kpi_cascade_id', 'q1', 'q2', 'q3', 'q4', 'q5', 'q6', 'q7', 'q8', 'uji_a_result',
        'uji_b_period', 'uji_b_improvement', 'uji_b_coefficients', 'uji_b_notes', 'uji_b_result',
        'notes', 'tested_by', 'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'q1' => 'boolean', 'q2' => 'boolean', 'q3' => 'boolean', 'q4' => 'boolean',
            'q5' => 'boolean', 'q6' => 'boolean', 'q7' => 'boolean', 'q8' => 'boolean',
            'uji_b_improvement' => 'float',
            'uji_b_coefficients' => 'array',
            'uji_b_notes' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(KpiCascade::class, 'kpi_cascade_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
